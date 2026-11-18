<?php

namespace App\Console\Commands;

use App\Services\Gateway\GatewayException;
use App\Services\Gateway\GatewayInterface;
use App\Services\PenarikanService;
use App\Services\PencairanService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CekPenarikanPending extends Command
{
    protected $signature = 'penarikan:cek-pending';

    protected $description = 'Kirim ulang dan cek status payout bank yang masih pending';

    public function handle(GatewayInterface $gateway, PenarikanService $penarikan, PencairanService $pencairan): int
    {
        $batasWaktu = now()->subMinutes(5);
        $penarikanPending = DB::table('penarikan')
            ->where('status', 'pending')->where('created_at', '<', $batasWaktu)->limit(100)->get();

        foreach ($penarikanPending as $p) {
            try {
                if (! $p->ref_gateway) {
                    if (! $p->tujuan_bank) {
                        $this->warn("Penarikan nasabah #{$p->id}: data rekening lama tidak memiliki kode bank; perlu rekonsiliasi admin.");

                        continue;
                    }
                    $penarikan->kirimPending($p->id);
                } else {
                    $status = $gateway->cekStatus($p->ref_gateway);
                    if ($status !== 'pending') {
                        $penarikan->selesaikan($p->ref_gateway, $status);
                    }
                }
            } catch (GatewayException $e) {
                $this->warn("Penarikan nasabah #{$p->id}: {$e->getMessage()}");
            }
        }

        $mitraPending = DB::table('pencairan_mitra')
            ->where('status', 'pending')->where('created_at', '<', $batasWaktu)->limit(100)->get();

        foreach ($mitraPending as $p) {
            try {
                if (! $p->ref_gateway) {
                    if (! $p->bank_tujuan || ! $p->no_rekening || ! $p->atas_nama) {
                        $this->warn("Pencairan mitra #{$p->id}: snapshot rekening lama tidak lengkap; perlu rekonsiliasi admin.");

                        continue;
                    }
                    $pencairan->kirimPending($p->id);
                } else {
                    $status = $gateway->cekStatus($p->ref_gateway);
                    if ($status !== 'pending') {
                        $pencairan->selesaikan($p->ref_gateway, $status);
                    }
                }
            } catch (GatewayException $e) {
                $this->warn("Pencairan mitra #{$p->id}: {$e->getMessage()}");
            }
        }

        $this->info($penarikanPending->count().' penarikan nasabah dan '.$mitraPending->count().' pencairan mitra diperiksa.');

        return self::SUCCESS;
    }
}
