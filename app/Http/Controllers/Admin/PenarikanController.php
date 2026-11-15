<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PenarikanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PenarikanController extends Controller
{
    public function __construct(private PenarikanService $penarikan)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $q = DB::table('penarikan')->join('nasabah', 'nasabah.id', '=', 'penarikan.nasabah_id')
            ->join('users', 'users.id', '=', 'nasabah.user_id')
            ->select('penarikan.*', 'nasabah.kode_nasabah', 'users.nama')->orderByDesc('penarikan.id');
        if ($s = $request->query('status')) {
            $q->where('penarikan.status', $s);
        }

        return response()->json($q->paginate($this->perPage($request)));
    }

    /** Penarikan untuk nasabah tanpa HP: PIN diketik nasabah di layar admin. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nasabah_id' => ['required', 'integer'],
            'nominal' => ['required', 'regex:' . self::REGEX_NOMINAL],
            'tujuan_tipe' => ['required', 'in:bank,e-wallet'],
            'tujuan_nomor' => ['required', 'string', 'max:40'],
            'pin' => ['required', 'digits:6'],
        ]);

        $nasabah = DB::table('nasabah')->where('id', $data['nasabah_id'])->first();
        if (!$nasabah) {
            throw new ApiException('NASABAH_TIDAK_DITEMUKAN', 'Nasabah tidak ditemukan.', 404);
        }
        $user = User::findOrFail($nasabah->user_id);
        if ($user->status !== 'aktif') {
            throw new ApiException('AKUN_NONAKTIF', 'Akun nasabah tidak aktif.');
        }

        $hasil = $this->penarikan->ajukan(
            $user, $nasabah->id, $data['nominal'], $data['tujuan_tipe'], $data['tujuan_nomor'], $data['pin'], $request->user()->id
        );

        return response()->json($hasil, 201);
    }
}
