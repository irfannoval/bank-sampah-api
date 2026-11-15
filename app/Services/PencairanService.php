<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Pencairan = seluruh transaksi berhasil yang belum dicairkan (pencairan_id kosong).
 * saldo_tagihan baru berkurang saat pencairan DIBAYAR.
 */
class PencairanService
{
    public function __construct(private AuditService $audit)
    {
    }

    public function ajukan(User $mitraUser): array
    {
        return DB::transaction(function () use ($mitraUser) {
            $toko = DB::table('toko_mitra')->where('user_id', $mitraUser->id)->lockForUpdate()->first();
            if (!$toko) {
                throw new ApiException('TIDAK_BERWENANG', 'Akun ini bukan toko mitra.', 403);
            }
            if (!$toko->bank_tujuan || !$toko->no_rekening) {
                throw new ApiException('REKENING_BELUM_DIISI', 'Rekening tujuan toko belum diisi. Hubungi admin.');
            }

            $trx = DB::table('transaksi_mitra')
                ->where('toko_mitra_id', $toko->id)->where('status', 'berhasil')->whereNull('pencairan_id')
                ->lockForUpdate()->get(['id', 'nominal']);

            $total = '0.00';
            foreach ($trx as $t) {
                $total = bcadd($total, $t->nominal, 2);
            }
            if (bccomp($total, '0', 2) <= 0) {
                throw new ApiException('TIDAK_ADA_TAGIHAN', 'Tidak ada tagihan yang bisa dicairkan.');
            }

            $id = DB::table('pencairan_mitra')->insertGetId(['toko_mitra_id' => $toko->id, 'nominal' => $total]);
            DB::table('transaksi_mitra')->whereIn('id', $trx->pluck('id'))->update(['pencairan_id' => $id]);
            $this->audit->catat($mitraUser->id, 'pencairan.ajukan', 'pencairan_mitra', null, ['pencairan_id' => $id, 'nominal' => $total]);

            return ['pencairan_id' => $id, 'nominal' => $total, 'jumlah_transaksi' => $trx->count()];
        });
    }

    public function setujui(int $id, int $adminId): void
    {
        DB::transaction(function () use ($id, $adminId) {
            $p = $this->kunci($id);
            if ($p->status !== 'pending') {
                throw new ApiException('STATUS_TIDAK_VALID', 'Hanya pengajuan berstatus pending yang bisa disetujui.');
            }
            DB::table('pencairan_mitra')->where('id', $id)->update(['status' => 'disetujui', 'diproses_oleh' => $adminId, 'updated_at' => now()]);
            $this->audit->catat($adminId, 'pencairan.setujui', 'pencairan_mitra', ['status' => 'pending'], ['pencairan_id' => $id]);
        });
    }

    /** Menolak melepas kunci: transaksi kembali bisa diajukan ulang. */
    public function tolak(int $id, int $adminId, string $alasan): void
    {
        DB::transaction(function () use ($id, $adminId, $alasan) {
            $p = $this->kunci($id);
            if (!in_array($p->status, ['pending', 'disetujui'], true)) {
                throw new ApiException('STATUS_TIDAK_VALID', 'Pengajuan ini tidak bisa ditolak.');
            }
            DB::table('transaksi_mitra')->where('pencairan_id', $id)->update(['pencairan_id' => null]);
            DB::table('pencairan_mitra')->where('id', $id)->update(['status' => 'ditolak', 'diproses_oleh' => $adminId, 'updated_at' => now()]);
            $this->audit->catat($adminId, 'pencairan.tolak', 'pencairan_mitra', ['status' => $p->status], ['pencairan_id' => $id, 'alasan' => $alasan]);
        });
    }

    public function bayar(int $id, int $adminId, UploadedFile $bukti): void
    {
        DB::transaction(function () use ($id, $adminId, $bukti) {
            $p = $this->kunci($id);
            if ($p->status !== 'disetujui') {
                throw new ApiException('STATUS_TIDAK_VALID', 'Pencairan harus disetujui dulu sebelum dibayar.');
            }

            $toko = DB::table('toko_mitra')->where('id', $p->toko_mitra_id)->lockForUpdate()->first();
            if (bccomp($toko->saldo_tagihan, $p->nominal, 2) < 0) {
                throw new ApiException('SALDO_TAGIHAN_KURANG', 'Saldo tagihan toko lebih kecil dari nominal pencairan.');
            }

            $path = $bukti->store('bukti_transfer');
            DB::table('toko_mitra')->where('id', $toko->id)->decrement('saldo_tagihan', $p->nominal);
            DB::table('pencairan_mitra')->where('id', $id)->update([
                'status' => 'dibayar', 'diproses_oleh' => $adminId, 'bukti_transfer' => $path, 'updated_at' => now(),
            ]);
            $this->audit->catat($adminId, 'pencairan.bayar', 'pencairan_mitra', ['status' => 'disetujui'], ['pencairan_id' => $id, 'nominal' => $p->nominal]);
        });
    }

    private function kunci(int $id): object
    {
        $p = DB::table('pencairan_mitra')->where('id', $id)->lockForUpdate()->first();
        if (!$p) {
            throw new ApiException('PENCAIRAN_TIDAK_DITEMUKAN', 'Pengajuan pencairan tidak ditemukan.', 404);
        }

        return $p;
    }
}
