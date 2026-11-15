<?php

namespace App\Http\Controllers\Mitra;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Services\MitraTransaksiService;
use App\Services\PencairanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Semua endpoint untuk peran 'mitra' (kasir toko). */
class MitraAppController extends Controller
{
    public function __construct(private MitraTransaksiService $transaksi, private PencairanService $pencairan)
    {
    }

    private function toko(Request $request): object
    {
        $toko = DB::table('toko_mitra')->where('user_id', $request->user()->id)->first();
        if (!$toko) {
            throw new ApiException('TIDAK_BERWENANG', 'Akun ini bukan toko mitra.', 403);
        }

        return $toko;
    }

    public function beranda(Request $request): JsonResponse
    {
        $toko = $this->toko($request);
        $dikunci = DB::table('pencairan_mitra')->where('toko_mitra_id', $toko->id)->whereIn('status', ['pending', 'disetujui'])->sum('nominal');
        $hariIni = DB::table('transaksi_mitra')->where('toko_mitra_id', $toko->id)->where('status', 'berhasil')
            ->where('created_at', '>=', now()->startOfDay());

        return response()->json([
            'nama_toko' => $toko->nama_toko,
            'saldo_tagihan' => $toko->saldo_tagihan,
            'sedang_diajukan' => bcadd((string) $dikunci, '0', 2),
            'hari_ini' => ['jumlah' => $hariIni->count(), 'total' => bcadd((string) $hariIni->sum('nominal'), '0', 2)],
        ]);
    }

    public function mulai(Request $request): JsonResponse
    {
        $data = $request->validate(['qr_token' => ['required', 'string', 'max:64']]);

        return response()->json($this->transaksi->mulai($request->user(), $data['qr_token']));
    }

    public function konfirmasi(Request $request, string $sesiId): JsonResponse
    {
        $data = $request->validate([
            'nominal' => ['required', 'regex:' . self::REGEX_NOMINAL],
            'pin' => ['required', 'digits:6'],
        ]);

        return response()->json($this->transaksi->konfirmasi($request->user(), $sesiId, $data['nominal'], $data['pin']), 201);
    }

    public function riwayat(Request $request): JsonResponse
    {
        $toko = $this->toko($request);

        return response()->json(
            DB::table('transaksi_mitra')->join('nasabah', 'nasabah.id', '=', 'transaksi_mitra.nasabah_id')
                ->join('users', 'users.id', '=', 'nasabah.user_id')
                ->where('transaksi_mitra.toko_mitra_id', $toko->id)
                ->select('transaksi_mitra.id', 'transaksi_mitra.nominal', 'transaksi_mitra.status', 'transaksi_mitra.alasan_batal',
                    'transaksi_mitra.pencairan_id', 'transaksi_mitra.created_at', 'users.nama')
                ->orderByDesc('transaksi_mitra.id')->paginate($this->perPage($request))
        );
    }

    public function batal(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['alasan' => ['required', 'string', 'min:3', 'max:500']]);

        return response()->json($this->transaksi->batal($request->user(), $id, $data['alasan']));
    }

    public function pencairan(Request $request): JsonResponse
    {
        $toko = $this->toko($request);

        return response()->json(
            DB::table('pencairan_mitra')->where('toko_mitra_id', $toko->id)
                ->select('id', 'nominal', 'status', 'created_at', 'updated_at')->orderByDesc('id')->paginate($this->perPage($request))
        );
    }

    public function ajukanPencairan(Request $request): JsonResponse
    {
        return response()->json($this->pencairan->ajukan($request->user()), 201);
    }
}
