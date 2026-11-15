<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Kartu;
use App\Services\KartuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KartuController extends Controller
{
    public function __construct(private KartuService $kartu)
    {
    }

    /** Admin memindai QR di kartu: dapat data nasabah untuk halaman setoran/penarikan. */
    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate(['qr_token' => ['required', 'string', 'max:64']]);

        $kartu = Kartu::with('nasabah.user')->where('qr_token', $data['qr_token'])->first();

        if (!$kartu) {
            throw new ApiException('KARTU_TIDAK_DITEMUKAN', 'Kartu tidak dikenal.', 404);
        }
        if ($kartu->status !== 'aktif') {
            throw new ApiException('KARTU_TIDAK_AKTIF', 'Kartu diblokir atau dilaporkan hilang.');
        }
        if ($kartu->nasabah->user->status !== 'aktif') {
            throw new ApiException('AKUN_NONAKTIF', 'Akun nasabah tidak aktif.');
        }

        return response()->json([
            'kartu_id' => $kartu->id,
            'nasabah' => [
                'id' => $kartu->nasabah->id,
                'kode_nasabah' => $kartu->nasabah->kode_nasabah,
                'nama' => $kartu->nasabah->user->nama,
                'saldo' => $kartu->nasabah->saldo,
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $q = DB::table('kartu')->join('nasabah', 'nasabah.id', '=', 'kartu.nasabah_id')
            ->join('users', 'users.id', '=', 'nasabah.user_id')
            ->select('kartu.id', 'kartu.status', 'kartu.qr_token', 'kartu.dicetak_pada', 'nasabah.kode_nasabah', 'users.nama')
            ->orderByDesc('kartu.id');
        if ($s = $request->query('status')) {
            $q->where('kartu.status', $s);
        }

        return response()->json($q->paginate($this->perPage($request)));
    }

    public function cetak(Request $request, int $nasabahId): JsonResponse
    {
        return response()->json($this->kartu->cetak($nasabahId, $request->user()->id), 201);
    }

    public function status(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:aktif,diblokir,hilang']]);
        $this->kartu->ubahStatus($id, $data['status'], $request->user()->id);

        return response()->json(['ok' => true]);
    }

    public function ganti(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['status_lama' => ['sometimes', 'in:diblokir,hilang']]);

        return response()->json($this->kartu->ganti($id, $data['status_lama'] ?? 'hilang', $request->user()->id), 201);
    }
}
