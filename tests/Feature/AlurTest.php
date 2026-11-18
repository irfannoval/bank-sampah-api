<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Tes alur HTTP end-to-end. Wajib PostgreSQL (lihat README). */
class AlurTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $hp, string $pin = '123456'): User
    {
        $id = DB::table('users')->insertGetId([
            'nama' => ucfirst($role), 'no_hp' => $hp, 'pin_hash' => Hash::make($pin), 'role' => $role,
        ]);

        return User::findOrFail($id);
    }

    /** @return array{0: User, 1: int, 2: string} user nasabah, nasabah_id, qr_token */
    private function nasabah(string $saldo): array
    {
        $u = $this->user('nasabah', '0822' . random_int(1000000, 9999999));
        $nid = DB::table('nasabah')->insertGetId(['user_id' => $u->id, 'kode_nasabah' => 'NSB-' . $u->id, 'saldo' => $saldo]);
        $qr = bin2hex(random_bytes(8));
        DB::table('kartu')->insert(['nasabah_id' => $nid, 'qr_token' => $qr]);
        if (bccomp($saldo, '0', 2) > 0) { // ledger harus cocok dengan saldo awal
            DB::table('mutasi_saldo')->insert([
                'nasabah_id' => $nid, 'tipe' => 'setoran', 'arah' => 'kredit', 'nominal' => $saldo,
                'saldo_sebelum' => '0', 'saldo_sesudah' => $saldo,
            ]);
        }

        return [$u, $nid, $qr];
    }

    private function mitra(): User
    {
        $u = $this->user('mitra', '0833' . random_int(1000000, 9999999));
        DB::table('toko_mitra')->insert([
            'user_id' => $u->id, 'nama_toko' => 'Warung Tes', 'bank_tujuan' => 'BCA', 'no_rekening' => '123456', 'atas_nama' => 'Tes',
        ]);

        return $u;
    }

    public function test_login_dan_penguncian(): void
    {
        $u = $this->user('admin', '0811000001');

        $this->postJson('/api/v1/auth/login', ['no_hp' => '0811000001', 'pin' => '123456'])
            ->assertOk()->assertJsonStructure(['token', 'user']);

        for ($i = 0; $i < 3; $i++) {
            $r = $this->postJson('/api/v1/auth/login', ['no_hp' => '0811000001', 'pin' => '000000']);
        }
        $r->assertStatus(423)->assertJsonPath('error.kode', 'AKUN_TERKUNCI');
    }

    public function test_belanja_di_mitra_dan_pembatalan(): void
    {
        [, $nid, $qr] = $this->nasabah('50000.00');
        $mitra = $this->mitra();
        Sanctum::actingAs($mitra);

        $sesi = $this->postJson('/api/v1/mitra/transaksi/mulai', ['qr_token' => $qr])
            ->assertOk()->assertJsonPath('nasabah.saldo', '50000.00')->json('sesi_id');

        // PIN salah tidak memproses transaksi
        $this->postJson("/api/v1/mitra/transaksi/{$sesi}/konfirmasi", ['nominal' => '20000', 'pin' => '999999'], ['Idempotency-Key' => 'a1'])
            ->assertStatus(422)->assertJsonPath('error.kode', 'PIN_SALAH');

        $r = $this->postJson("/api/v1/mitra/transaksi/{$sesi}/konfirmasi", ['nominal' => '20000', 'pin' => '123456'], ['Idempotency-Key' => 'a2'])
            ->assertStatus(201)
            ->assertJsonPath('saldo_toko_sesudah', '20000.00');
        $trxId = $r->json('transaksi_id');

        $this->assertSame('30000.00', (string) DB::table('nasabah')->where('id', $nid)->value('saldo'));
        $this->assertSame('20000.00', (string) DB::table('toko_mitra')->where('user_id', $mitra->id)->value('saldo_tagihan'));
        $this->getJson('/api/v1/mitra/beranda')->assertOk()->assertJsonPath('saldo_toko', '20000.00');

        // Kirim ulang dengan kunci sama: hasil sama, tidak diproses dua kali
        $this->postJson("/api/v1/mitra/transaksi/{$sesi}/konfirmasi", ['nominal' => '20000', 'pin' => '123456'], ['Idempotency-Key' => 'a2'])
            ->assertStatus(201)->assertHeader('Idempotent-Replayed', 'true');
        $this->assertSame('30000.00', (string) DB::table('nasabah')->where('id', $nid)->value('saldo'));

        // Batalkan: refund otomatis
        $this->postJson("/api/v1/mitra/transaksi/{$trxId}/batal", ['alasan' => 'salah input'])->assertOk();
        $this->assertSame('50000.00', (string) DB::table('nasabah')->where('id', $nid)->value('saldo'));
        $this->assertSame('0.00', (string) DB::table('toko_mitra')->where('user_id', $mitra->id)->value('saldo_tagihan'));
        $this->assertSame(0, DB::table('v_selisih_saldo')->count());
    }

    public function test_saldo_kurang_saat_belanja(): void
    {
        [, , $qr] = $this->nasabah('10000.00');
        Sanctum::actingAs($this->mitra());

        $sesi = $this->postJson('/api/v1/mitra/transaksi/mulai', ['qr_token' => $qr])->json('sesi_id');
        $this->postJson("/api/v1/mitra/transaksi/{$sesi}/konfirmasi", ['nominal' => '10000.01', 'pin' => '123456'], ['Idempotency-Key' => 'b1'])
            ->assertStatus(422)->assertJsonPath('error.kode', 'SALDO_KURANG');
    }

    public function test_pencairan_penuh_alurnya(): void
    {
        [, , $qr] = $this->nasabah('100000.00');
        $mitra = $this->mitra();
        $admin = $this->user('admin', '0811000002');

        Sanctum::actingAs($mitra);
        $sesi = $this->postJson('/api/v1/mitra/transaksi/mulai', ['qr_token' => $qr])->json('sesi_id');
        $this->postJson("/api/v1/mitra/transaksi/{$sesi}/konfirmasi", ['nominal' => '40000', 'pin' => '123456'], ['Idempotency-Key' => 'c1'])->assertStatus(201);

        $id = $this->postJson('/api/v1/mitra/pencairan', [], ['Idempotency-Key' => 'c2'])
            ->assertStatus(201)->assertJsonPath('nominal', '40000.00')->json('pencairan_id');

        // Transaksi yang sudah masuk pencairan tidak bisa dibatalkan mitra
        $trx = DB::table('transaksi_mitra')->value('id');
        $this->postJson("/api/v1/mitra/transaksi/{$trx}/batal", ['alasan' => 'coba batal'])
            ->assertStatus(422)->assertJsonPath('error.kode', 'SUDAH_DICAIRKAN');

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/pencairan/{$id}/setujui")->assertOk();
        $this->assertSame('disetujui', DB::table('pencairan_mitra')->where('id', $id)->value('status'));
    }

    public function test_penarikan_aturan_dan_webhook_refund(): void
    {
        [$user, $nid] = $this->nasabah('100000.00');
        Sanctum::actingAs($user);

        // di bawah minimal
        $this->postJson('/api/v1/nasabah/penarikan', ['nominal' => '1000', 'tujuan_tipe' => 'e-wallet', 'tujuan_nomor' => '0812345678', 'pin' => '123456'], ['Idempotency-Key' => 'd1'])
            ->assertStatus(422)->assertJsonPath('error.kode', 'BATAS_PENARIKAN');

        // saldo tersisa kurang dari 10.000
        $this->postJson('/api/v1/nasabah/penarikan', ['nominal' => '95000', 'tujuan_tipe' => 'e-wallet', 'tujuan_nomor' => '0812345678', 'pin' => '123456'], ['Idempotency-Key' => 'd2'])
            ->assertStatus(422)->assertJsonPath('error.kode', 'SALDO_MINIM_TERSISA');

        $r = $this->postJson('/api/v1/nasabah/penarikan', ['nominal' => '20000', 'tujuan_tipe' => 'e-wallet', 'tujuan_nomor' => '0812345678', 'pin' => '123456'], ['Idempotency-Key' => 'd3'])
            ->assertStatus(201)->assertJsonPath('status', 'pending');
        $this->assertSame('80000.00', (string) DB::table('nasabah')->where('id', $nid)->value('saldo'));
        $ref = $r->json('ref_gateway');

        // webhook dengan tanda tangan salah ditolak
        $isi = json_encode(['ref' => $ref, 'status' => 'gagal']);
        $this->call('POST', '/api/v1/webhook/gateway/disbursement', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => 'salah', 'HTTP_ACCEPT' => 'application/json'], $isi)
            ->assertStatus(401);

        // webhook sah: gagal -> refund
        $ttd = hash_hmac('sha256', $isi, 'rahasia-tes');
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => $ttd, 'HTTP_ACCEPT' => 'application/json'];
        $this->call('POST', '/api/v1/webhook/gateway/disbursement', [], [], [], $server, $isi)->assertOk();
        $this->assertSame('100000.00', (string) DB::table('nasabah')->where('id', $nid)->value('saldo'));

        // webhook yang sama datang lagi: tidak refund dua kali
        $this->call('POST', '/api/v1/webhook/gateway/disbursement', [], [], [], $server, $isi)->assertOk();
        $this->assertSame('100000.00', (string) DB::table('nasabah')->where('id', $nid)->value('saldo'));
        $this->assertSame(0, DB::table('v_selisih_saldo')->count());
    }

    public function test_hak_akses_per_peran(): void
    {
        Sanctum::actingAs($this->user('admin', '0811000003'));
        $this->getJson('/api/v1/admin/pengaturan')->assertStatus(403)->assertJsonPath('error.kode', 'TIDAK_BERWENANG');
        $this->postJson('/api/v1/admin/nasabah/1/koreksi', [], ['Idempotency-Key' => 'e1'])->assertStatus(403);

        Sanctum::actingAs($this->user('nasabah', '0811000004'));
        $this->getJson('/api/v1/admin/jenis-sampah')->assertStatus(403);
    }
}
