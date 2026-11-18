<?php

namespace App\Services\Gateway;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class MidtransIrisGateway
{
    public function daftarBank(): array
    {
        $data = $this->get('/api/v1/beneficiary_banks', [], 'mengambil daftar bank')['beneficiary_banks'] ?? null;
        if (! is_array($data)) {
            throw new GatewayException('Midtrans Iris mengembalikan daftar bank dengan format tidak valid.');
        }

        $banks = [];
        foreach ($data as $bank) {
            if (is_array($bank) && isset($bank['code'], $bank['name']) && is_string($bank['code']) && is_string($bank['name'])) {
                $banks[] = ['code' => $bank['code'], 'name' => $bank['name']];
            }
        }

        return $banks;
    }

    public function validasiRekening(string $bank, string $nomor): array
    {
        $data = $this->get('/api/v1/account_validation', ['bank' => strtolower(trim($bank)), 'account' => $nomor], 'validasi rekening');
        if (! isset($data['account_name']) || ! is_string($data['account_name']) || $data['account_name'] === '') {
            throw new GatewayAccountException('Rekening bank tidak valid.');
        }

        return ['nama' => $data['account_name']];
    }

    public function kirim(object $payout, string $idempotencyKey): array
    {
        $data = $this->post('/api/v1/payouts', [
            'payouts' => [[
                'beneficiary_name' => $payout->nama_pemilik,
                'beneficiary_account' => $payout->tujuan_nomor,
                'beneficiary_bank' => strtolower(trim($payout->tujuan_bank)),
                'amount' => $payout->nominal_dikirim,
                'notes' => $payout->catatan,
            ]],
        ], $idempotencyKey, 'membuat payout');
        $payouts = $data['payouts'] ?? null;
        $detail = is_array($payouts) ? ($payouts[0] ?? null) : null;
        if (! is_array($detail) || ! isset($detail['reference_no'], $detail['status'])) {
            throw new GatewayException('Midtrans Iris tidak mengembalikan referensi payout.');
        }

        return [
            'ref' => (string) $detail['reference_no'],
            'status' => $this->status((string) $detail['status']),
        ];
    }

    public function cekStatus(string $ref): string
    {
        $data = $this->get('/api/v1/payouts/'.rawurlencode($ref), [], 'memeriksa status payout');
        if (! isset($data['status']) || ! is_string($data['status'])) {
            throw new GatewayException('Midtrans Iris tidak mengembalikan status payout.');
        }

        return $this->status($data['status']);
    }

    private function request(): PendingRequest
    {
        $apiKey = (string) config('gateway.midtrans.iris_api_key');
        if ($apiKey === '') {
            throw new GatewayException('MIDTRANS_IRIS_API_KEY belum diatur.');
        }

        $environment = (string) config('gateway.midtrans.environment', 'sandbox');
        $baseUrl = match ($environment) {
            'sandbox' => 'https://app.sandbox.midtrans.com/iris',
            'production' => 'https://app.midtrans.com/iris',
            default => throw new GatewayException('MIDTRANS_ENVIRONMENT harus sandbox atau production.'),
        };

        return Http::baseUrl($baseUrl)
            ->withBasicAuth($apiKey, '')
            ->acceptJson()
            ->asJson()
            ->connectTimeout(3)
            ->timeout(max(1, (int) config('gateway.midtrans.timeout', 15)));
    }

    /**
     * @param  array<string, string>  $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query, string $operation): array
    {
        try {
            $response = $this->request()->get($path, $query);
        } catch (ConnectionException $e) {
            throw new GatewayException('Midtrans Iris tidak dapat dihubungi saat '.$operation.'.', previous: $e);
        }

        if (in_array($response->status(), [400, 422], true) && $operation === 'validasi rekening') {
            throw new GatewayAccountException('Rekening bank tidak valid.');
        }

        return $this->data($response, $operation);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload, string $idempotencyKey, string $operation): array
    {
        try {
            $response = $this->request()
                ->withHeaders(['X-Idempotency-Key' => $idempotencyKey])
                ->post($path, $payload);
        } catch (ConnectionException $e) {
            throw new GatewayException('Midtrans Iris tidak dapat dihubungi saat '.$operation.'.', previous: $e);
        }

        return $this->data($response, $operation);
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Response $response, string $operation): array
    {
        if (! $response->successful()) {
            throw new GatewayException('Midtrans Iris gagal '.$operation.' (HTTP '.$response->status().').');
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new GatewayException('Midtrans Iris mengembalikan respons tidak valid saat '.$operation.'.');
        }

        return $data;
    }

    private function status(string $status): string
    {
        return match (strtolower($status)) {
            'processed', 'completed', 'success', 'successful' => 'berhasil',
            'failed', 'rejected', 'cancelled' => 'gagal',
            'queued', 'pending', 'approved', 'processing' => 'pending',
            default => throw new GatewayException('Status payout Midtrans Iris tidak dikenal: '.$status),
        };
    }
}
