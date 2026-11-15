<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaporanController extends Controller
{
    public function show(Request $request, string $jenis): JsonResponse
    {
        $request->validate(['dari' => ['nullable', 'date'], 'sampai' => ['nullable', 'date']]);
        $dari = $request->query('dari');
        $sampai = $request->query('sampai') ? date('Y-m-d', strtotime($request->query('sampai') . ' +1 day')) : null;

        return match ($jenis) {
            'setoran' => response()->json($this->setoran($dari, $sampai)),
            'saldo-beredar' => response()->json($this->saldoBeredar()),
            'transaksi-mitra' => response()->json($this->transaksiMitra($dari, $sampai)),
            default => throw new ApiException('LAPORAN_TIDAK_DITEMUKAN', 'Jenis laporan tidak dikenal.', 404),
        };
    }

    /** Total berat dan nilai per jenis sampah (hanya setoran yang tidak dibatalkan). */
    private function setoran(?string $dari, ?string $sampai): array
    {
        $q = DB::table('detail_setoran')->join('setoran', 'setoran.id', '=', 'detail_setoran.setoran_id')
            ->join('jenis_sampah', 'jenis_sampah.id', '=', 'detail_setoran.jenis_sampah_id')
            ->where('setoran.status', 'berhasil')
            ->selectRaw('jenis_sampah.nama, sum(detail_setoran.berat) as total_berat, sum(detail_setoran.subtotal) as total_nilai')
            ->groupBy('jenis_sampah.nama')->orderBy('jenis_sampah.nama');
        $dari && $q->where('setoran.created_at', '>=', $dari);
        $sampai && $q->where('setoran.created_at', '<', $sampai);

        return ['per_jenis' => $q->get()];
    }

    /** Kewajiban bank sampah: saldo nasabah + tagihan mitra yang belum dibayar. */
    private function saldoBeredar(): array
    {
        $nasabah = (string) DB::table('nasabah')->sum('saldo');
        $tagihan = (string) DB::table('toko_mitra')->sum('saldo_tagihan');
        $penarikanPending = (string) DB::table('penarikan')->where('status', 'pending')->sum('nominal');

        return [
            'saldo_nasabah' => bcadd($nasabah, '0', 2),
            'tagihan_mitra_belum_dibayar' => bcadd($tagihan, '0', 2),
            'penarikan_pending' => bcadd($penarikanPending, '0', 2),
            'total_kewajiban' => bcadd(bcadd($nasabah, $tagihan, 2), '0', 2),
            'catatan' => 'Bandingkan total_kewajiban dengan kas bank sampah dan saldo payment gateway.',
        ];
    }

    private function transaksiMitra(?string $dari, ?string $sampai): array
    {
        $q = DB::table('transaksi_mitra')->join('toko_mitra', 'toko_mitra.id', '=', 'transaksi_mitra.toko_mitra_id')
            ->where('transaksi_mitra.status', 'berhasil')
            ->selectRaw('toko_mitra.nama_toko, count(*) as jumlah, sum(transaksi_mitra.nominal) as total')
            ->groupBy('toko_mitra.nama_toko')->orderBy('toko_mitra.nama_toko');
        $dari && $q->where('transaksi_mitra.created_at', '>=', $dari);
        $sampai && $q->where('transaksi_mitra.created_at', '<', $sampai);

        return ['per_toko' => $q->get()];
    }
}
