<?php

namespace Tests\Feature;

use App\Services\Gateway\GatewayAccountException;
use App\Services\Gateway\MidtransIrisGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MidtransIrisGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'gateway.midtrans.iris_api_key' => 'IRIS-test-key',
            'gateway.midtrans.environment' => 'sandbox',
            'gateway.midtrans.timeout' => 10,
        ]);
    }

    public function test_lists_supported_banks_and_validates_account(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://app.sandbox.midtrans.com/iris/api/v1/beneficiary_banks' => Http::response([
                'beneficiary_banks' => [['code' => 'bca', 'name' => 'Bank Central Asia']],
            ]),
            'https://app.sandbox.midtrans.com/iris/api/v1/account_validation*' => Http::response([
                'account_name' => 'SITI',
                'account_no' => '123456',
                'bank_name' => 'BCA',
            ]),
        ]);

        $gateway = new MidtransIrisGateway;

        $this->assertSame([['code' => 'bca', 'name' => 'Bank Central Asia']], $gateway->daftarBank());
        $this->assertSame(['nama' => 'SITI'], $gateway->validasiRekening('bca', '123456'));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/account_validation')
            && $request['bank'] === 'bca'
            && $request['account'] === '123456');
    }

    public function test_rejects_invalid_bank_account(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://app.sandbox.midtrans.com/iris/api/v1/account_validation*' => Http::response([
                'error_message' => 'Invalid bank account',
            ], 422),
        ]);

        $this->expectException(GatewayAccountException::class);
        (new MidtransIrisGateway)->validasiRekening('bca', '000000');
    }

    public function test_creates_idempotent_payout_and_maps_midtrans_status(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://app.sandbox.midtrans.com/iris/api/v1/payouts' => Http::response([
                'payouts' => [['status' => 'queued', 'reference_no' => 'IRIS-123']],
            ]),
            'https://app.sandbox.midtrans.com/iris/api/v1/payouts/IRIS-123' => Http::response([
                'status' => 'processed',
            ]),
        ]);

        $gateway = new MidtransIrisGateway;
        $payout = (object) [
            'tujuan_bank' => 'bca',
            'tujuan_nomor' => '123456',
            'nama_pemilik' => 'SITI',
            'nominal_dikirim' => '25000.00',
            'catatan' => 'Penarikan nasabah #1',
        ];

        $this->assertSame(
            ['ref' => 'IRIS-123', 'status' => 'pending'],
            $gateway->kirim($payout, 'bank-sampah-penarikan-1')
        );
        $this->assertSame('berhasil', $gateway->cekStatus('IRIS-123'));
        Http::assertSent(fn (Request $request) => $request->url() === 'https://app.sandbox.midtrans.com/iris/api/v1/payouts'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('IRIS-test-key:'))
            && $request->hasHeader('X-Idempotency-Key', 'bank-sampah-penarikan-1')
            && $request['payouts'][0]['amount'] === '25000.00');
    }
}
