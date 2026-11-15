<?php

namespace App\Console\Commands;

use App\Services\Gateway\GatewayException;
use App\Services\Gateway\GatewayInterface;
use App\Services\PenarikanService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CekPenarikanPending extends Command
{
    protected $signature = 'penarikan:cek-pending';
    protected $description = 'Cek status penarikan yang masih pending lebih dari 5 menit ke payment gateway';

    public function handle(GatewayInterface $gateway, PenarikanService $service): int
    {
        $pending = DB::table('penarikan')
            ->where('status', 'pending')->whereNotNull('ref_gateway')
            ->where('created_at', '<', now()->subMinutes(5))->limit(100)->get();

        foreach ($pending as $p) {
            try {
                $status = $gateway->cekStatus($p->ref_gateway);
                if ($status !== 'pending') {
                    $service->selesaikan($p->ref_gateway, $status);
                }
            } catch (GatewayException $e) {
                $this->warn("Penarikan #{$p->id}: {$e->getMessage()}");
            }
        }

        $this->info($pending->count() . ' penarikan diperiksa.');

        return self::SUCCESS;
    }
}
