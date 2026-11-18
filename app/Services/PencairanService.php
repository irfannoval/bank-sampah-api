<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\Gateway\GatewayAccountException;
use App\Services\Gateway\GatewayException;
use App\Services\Gateway\GatewayInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pencairan = seluruh transaksi berhasil yang belum dicairkan.
 * Saldo tagihan toko baru berkurang setelah Midtrans melaporkan payout berhasil.
 */
class PencairanService
{
    public function __construct(
        private AuditService $audit,
        private GatewayInterface $gateway,
    ) {}

    public function ajukan(User $mitraUser): array
    {
        $tokoAwal = DB::table('toko_mitra')->where('user_id', $mitraUser->id)->first();
        if (! $tokoAwal) {
            throw new ApiException('TIDAK_BERWENANG', 'Akun ini bukan toko mitra.', 403);
        }
        if (! $tokoAwal->bank_tujuan || ! $tokoAwal->no_rekening) {
            throw new ApiException('REKENING_BELUM_DIISI', 'Rekening tujuan toko belum diisi. Hubungi admin.');
        }

        $bank = strtolower(trim($tokoAwal->bank_tujuan));
        try {
            $info = $this->gateway->validasiRekening($bank, $tokoAwal->no_rekening);
        } catch (GatewayAccountException $e) {
            throw new ApiException('REKENING_TIDAK_VALID', 'Rekening bank toko tidak valid.', 422);
        } catch (GatewayException $e) {
            throw new ApiException('GATEWAY_TIDAK_TERSEDIA', 'Layanan validasi rekening sedang tidak tersedia.', 503);
        }

        $pencairan = DB::transaction(function () use ($mitraUser, $tokoAwal, $bank, $info) {
            $toko = DB::table('toko_mitra')->where('id', $tokoAwal->id)->lockForUpdate()->first();
            if (! $toko->bank_tujuan || ! $toko->no_rekening
                || strtolower(trim($toko->bank_tujuan)) !== $bank
                || $toko->no_rekening !== $tokoAwal->no_rekening) {
                throw new ApiException('REKENING_BERUBAH', 'Rekening toko berubah saat pencairan diproses. Silakan coba lagi.', 409);
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

            $id = DB::table('pencairan_mitra')->insertGetId([
                'toko_mitra_id' => $toko->id,
                'nominal' => $total,
                'bank_tujuan' => $bank,
                'no_rekening' => $toko->no_rekening,
                'atas_nama' => $info['nama'],
            ]);
            DB::table('transaksi_mitra')->whereIn('id', $trx->pluck('id'))->update(['pencairan_id' => $id]);
            $this->audit->catat($mitraUser->id, 'pencairan.ajukan', 'pencairan_mitra', null, ['pencairan_id' => $id, 'nominal' => $total]);

            return DB::table('pencairan_mitra')->where('id', $id)->first();
        });

        $this->kirimKeGateway($pencairan);

        $hasil = DB::table('pencairan_mitra')->where('id', $pencairan->id)->first();

        return [
            'pencairan_id' => $hasil->id,
            'nominal' => $hasil->nominal,
            'status' => $hasil->status,
            'ref_gateway' => $hasil->ref_gateway,
        ];
    }

    public function kirimPending(int $id): void
    {
        $pencairan = DB::table('pencairan_mitra')->where('id', $id)->where('status', 'pending')->first();
        if (! $pencairan) {
            return;
        }

        if (! $pencairan->bank_tujuan || ! $pencairan->no_rekening || ! $pencairan->atas_nama) {
            Log::warning('Pencairan lama #'.$id.' tidak dikirim karena snapshot rekening tujuan tidak lengkap.');

            return;
        }

        $this->kirimKeGateway($pencairan);
    }

    /** Dipanggil pengecekan berkala. Aman dipanggil berulang (idempoten). */
    public function selesaikan(string $ref, string $status): bool
    {
        return DB::transaction(function () use ($ref, $status) {
            $pencairan = DB::table('pencairan_mitra')->where('ref_gateway', $ref)->lockForUpdate()->first();
            if (! $pencairan) {
                return false;
            }
            if ($pencairan->status !== 'pending') {
                return true;
            }

            if ($status === 'berhasil') {
                $toko = DB::table('toko_mitra')->where('id', $pencairan->toko_mitra_id)->lockForUpdate()->first();
                if (bccomp($toko->saldo_tagihan, $pencairan->nominal, 2) < 0) {
                    throw new ApiException('SALDO_TAGIHAN_KURANG', 'Saldo tagihan toko lebih kecil dari nominal pencairan.');
                }

                DB::table('toko_mitra')->where('id', $toko->id)->decrement('saldo_tagihan', $pencairan->nominal);
                DB::table('pencairan_mitra')->where('id', $pencairan->id)->update(['status' => 'dibayar', 'updated_at' => now()]);
                $this->audit->catat(null, 'pencairan.berhasil', 'pencairan_mitra', ['status' => 'pending'], [
                    'pencairan_id' => $pencairan->id, 'nominal' => $pencairan->nominal,
                ]);
            } elseif ($status === 'gagal') {
                DB::table('transaksi_mitra')->where('pencairan_id', $pencairan->id)->update(['pencairan_id' => null]);
                DB::table('pencairan_mitra')->where('id', $pencairan->id)->update(['status' => 'ditolak', 'updated_at' => now()]);
                $this->audit->catat(null, 'pencairan.gagal', 'pencairan_mitra', ['status' => 'pending'], [
                    'pencairan_id' => $pencairan->id, 'nominal' => $pencairan->nominal,
                ]);
            }

            return true;
        });
    }

    private function kirimKeGateway(object $pencairan): void
    {
        try {
            $hasil = $this->gateway->kirim((object) [
                'tujuan_bank' => $pencairan->bank_tujuan,
                'tujuan_nomor' => $pencairan->no_rekening,
                'nama_pemilik' => $pencairan->atas_nama,
                'nominal_dikirim' => $pencairan->nominal,
                'catatan' => 'Pencairan mitra #'.$pencairan->id,
            ], 'bank-sampah-pencairan-'.$pencairan->id);

            DB::table('pencairan_mitra')->where('id', $pencairan->id)->where('status', 'pending')->update([
                'ref_gateway' => $hasil['ref'], 'updated_at' => now(),
            ]);
            if ($hasil['status'] !== 'pending') {
                $this->selesaikan($hasil['ref'], $hasil['status']);
            }
        } catch (GatewayException $e) {
            Log::warning('Gateway belum bisa dihubungi untuk pencairan #'.$pencairan->id.': '.$e->getMessage());
        }
    }
}
