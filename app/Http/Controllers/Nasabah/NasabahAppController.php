<?php

namespace App\Http\Controllers\Nasabah;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Services\PenarikanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Semua endpoint untuk peran 'nasabah' (aplikasi pelengkap kartu). */
class NasabahAppController extends Controller
{
    public function __construct(private PenarikanService $penarikan)
    {
    }

    private function nasabah(Request $request): object
    {
        $n = DB::table('nasabah')->where('user_id', $request->user()->id)->first();
        if (!$n) {
            throw new ApiException('TIDAK_BERWENANG', 'Akun ini bukan nasabah.', 403);
        }

        return $n;
    }

    public function beranda(Request $request): JsonResponse
    {
        $n = $this->nasabah($request);
        $bulanIni = DB::table('setoran')->where('nasabah_id', $n->id)->where('status', 'berhasil')
            ->where('created_at', '>=', now()->startOfMonth());

        return response()->json([
            'nama' => $request->user()->nama,
            'kode_nasabah' => $n->kode_nasabah,
            'saldo' => $n->saldo,
            'setoran_bulan_ini' => [
                'jumlah' => $bulanIni->count(),
                'total_berat_kg' => bcadd((string) $bulanIni->sum('total_berat'), '0', 3),
            ],
        ]);
    }

    public function mutasi(Request $request): JsonResponse
    {
        $n = $this->nasabah($request);

        return response()->json(
            DB::table('mutasi_saldo')->where('nasabah_id', $n->id)
                ->select('id', 'tipe', 'arah', 'nominal', 'saldo_sesudah', 'keterangan', 'created_at')
                ->orderByDesc('id')->paginate($this->perPage($request))
        );
    }

    public function setoran(Request $request): JsonResponse
    {
        $n = $this->nasabah($request);

        return response()->json(
            DB::table('setoran')->where('nasabah_id', $n->id)
                ->select('id', 'total_berat', 'total_nilai', 'status', 'created_at')
                ->orderByDesc('id')->paginate($this->perPage($request))
        );
    }

    /** Rincian satu setoran, untuk memeriksa saldo sudah masuk. */
    public function setoranDetail(Request $request, int $id): JsonResponse
    {
        $n = $this->nasabah($request);
        $s = DB::table('setoran')->where('id', $id)->where('nasabah_id', $n->id)->first(['id', 'total_berat', 'total_nilai', 'status', 'created_at']);
        if (!$s) {
            throw new ApiException('SETORAN_TIDAK_DITEMUKAN', 'Setoran tidak ditemukan.', 404);
        }
        $rincian = DB::table('detail_setoran')->join('jenis_sampah', 'jenis_sampah.id', '=', 'detail_setoran.jenis_sampah_id')
            ->where('detail_setoran.setoran_id', $id)
            ->get(['jenis_sampah.nama', 'detail_setoran.berat', 'detail_setoran.harga_saat_itu', 'detail_setoran.subtotal']);

        return response()->json(['setoran' => $s, 'rincian' => $rincian]);
    }

    public function tokoMitra(): JsonResponse
    {
        return response()->json(
            DB::table('toko_mitra')->join('users', 'users.id', '=', 'toko_mitra.user_id')->where('users.status', 'aktif')
                ->orderBy('nama_toko')->get(['toko_mitra.id', 'toko_mitra.nama_toko', 'toko_mitra.alamat'])
        );
    }

    public function biayaPenarikan(Request $request): JsonResponse
    {
        $data = $request->validate(['nominal' => ['required', 'regex:' . self::REGEX_NOMINAL]]);

        return response()->json($this->penarikan->biaya($data['nominal']));
    }

    public function validasiRekening(Request $request): JsonResponse
    {
        $data = $request->validate(['tujuan_tipe' => ['required', 'in:bank,e-wallet'], 'tujuan_nomor' => ['required', 'string', 'max:40']]);

        return response()->json($this->penarikan->validasiRekening($data['tujuan_tipe'], $data['tujuan_nomor']));
    }

    public function ajukanPenarikan(Request $request): JsonResponse
    {
        $n = $this->nasabah($request);
        $data = $request->validate([
            'nominal' => ['required', 'regex:' . self::REGEX_NOMINAL],
            'tujuan_tipe' => ['required', 'in:bank,e-wallet'],
            'tujuan_nomor' => ['required', 'string', 'max:40'],
            'pin' => ['required', 'digits:6'],
        ]);

        return response()->json(
            $this->penarikan->ajukan($request->user(), $n->id, $data['nominal'], $data['tujuan_tipe'], $data['tujuan_nomor'], $data['pin']),
            201
        );
    }

    public function daftarPenarikan(Request $request): JsonResponse
    {
        $n = $this->nasabah($request);

        return response()->json(
            DB::table('penarikan')->where('nasabah_id', $n->id)
                ->select('id', 'nominal', 'biaya_admin', 'nominal_diterima', 'tujuan_tipe', 'tujuan_nomor', 'nama_pemilik', 'status', 'created_at')
                ->orderByDesc('id')->paginate($this->perPage($request))
        );
    }

    public function penarikan(Request $request, int $id): JsonResponse
    {
        $n = $this->nasabah($request);
        $p = DB::table('penarikan')->where('id', $id)->where('nasabah_id', $n->id)
            ->first(['id', 'nominal', 'biaya_admin', 'nominal_diterima', 'tujuan_tipe', 'tujuan_nomor', 'nama_pemilik', 'status', 'created_at']);
        if (!$p) {
            throw new ApiException('PENARIKAN_TIDAK_DITEMUKAN', 'Penarikan tidak ditemukan.', 404);
        }

        return response()->json($p);
    }
}
