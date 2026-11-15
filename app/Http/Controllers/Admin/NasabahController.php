<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Services\AkunService;
use App\Services\AuditService;
use App\Services\SaldoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NasabahController extends Controller
{
    public function __construct(private AkunService $akun, private SaldoService $saldo, private AuditService $audit)
    {
    }

    private function query()
    {
        return DB::table('nasabah')->join('users', 'users.id', '=', 'nasabah.user_id')
            ->select('nasabah.id', 'nasabah.kode_nasabah', 'nasabah.alamat', 'nasabah.saldo', 'users.id as user_id',
                'users.nama', 'users.no_hp', 'users.status', 'users.terkunci_sampai');
    }

    public function index(Request $request): JsonResponse
    {
        $q = $this->query()->orderBy('users.nama');
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('users.nama', 'ilike', "%{$s}%")->orWhere('nasabah.kode_nasabah', 'ilike', "%{$s}%")->orWhere('users.no_hp', 'like', "%{$s}%"));
        }

        return response()->json($q->paginate($this->perPage($request)));
    }

    public function show(int $id): JsonResponse
    {
        $n = $this->query()->where('nasabah.id', $id)->first();
        if (!$n) {
            throw new ApiException('NASABAH_TIDAK_DITEMUKAN', 'Nasabah tidak ditemukan.', 404);
        }
        $kartu = DB::table('kartu')->where('nasabah_id', $id)->orderByDesc('id')->get(['id', 'qr_token', 'status', 'dicetak_pada']);

        return response()->json(['nasabah' => $n, 'kartu' => $kartu]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:120'],
            'no_hp' => ['required', 'string', 'max:20', 'unique:users,no_hp'],
            'alamat' => ['nullable', 'string'],
            'pin' => ['nullable', 'digits:6'],
        ]);

        return response()->json($this->akun->daftarNasabah($data, $request->user()->id), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $n = DB::table('nasabah')->where('id', $id)->first();
        if (!$n) {
            throw new ApiException('NASABAH_TIDAK_DITEMUKAN', 'Nasabah tidak ditemukan.', 404);
        }

        $data = $request->validate([
            'nama' => ['sometimes', 'string', 'max:120'],
            'no_hp' => ['sometimes', 'string', 'max:20', 'unique:users,no_hp,' . $n->user_id],
            'status' => ['sometimes', 'in:aktif,nonaktif'],
            'alamat' => ['sometimes', 'nullable', 'string'],
        ]);

        DB::transaction(function () use ($data, $n, $request) {
            $user = array_intersect_key($data, array_flip(['nama', 'no_hp', 'status']));
            if ($user) {
                DB::table('users')->where('id', $n->user_id)->update($user);
            }
            if (array_key_exists('alamat', $data)) {
                DB::table('nasabah')->where('id', $n->id)->update(['alamat' => $data['alamat']]);
            }
            $this->audit->catat($request->user()->id, 'nasabah.ubah', 'nasabah', null, ['nasabah_id' => $n->id] + $data);
        });

        return response()->json(['ok' => true]);
    }

    public function resetPin(Request $request, int $id): JsonResponse
    {
        $n = DB::table('nasabah')->where('id', $id)->first();
        if (!$n) {
            throw new ApiException('NASABAH_TIDAK_DITEMUKAN', 'Nasabah tidak ditemukan.', 404);
        }

        return response()->json($this->akun->resetPin($n->user_id, $request->user()->id));
    }

    /** Hanya admin_utama (dibatasi di rute). */
    public function koreksi(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'arah' => ['required', 'in:kredit,debit'],
            'nominal' => ['required', 'regex:' . self::REGEX_NOMINAL],
            'alasan' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $hasil = DB::transaction(function () use ($data, $id, $request) {
            $m = $this->saldo->catat($id, 'koreksi', $data['arah'], $data['nominal'], null, null, $request->user()->id, $data['alasan']);
            $this->audit->catat($request->user()->id, 'saldo.koreksi', 'nasabah', ['saldo' => $m->saldo_sebelum], ['saldo' => $m->saldo_sesudah, 'alasan' => $data['alasan']]);

            return $m;
        });

        return response()->json(['saldo_sebelum' => $hasil->saldo_sebelum, 'saldo_sesudah' => $hasil->saldo_sesudah], 201);
    }

    public function mutasi(Request $request, int $id): JsonResponse
    {
        return response()->json(
            DB::table('mutasi_saldo')->where('nasabah_id', $id)->orderByDesc('id')->paginate($this->perPage($request))
        );
    }
}
