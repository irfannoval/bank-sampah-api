<?php

namespace Tests\Feature;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\PinService;
use App\Services\SaldoService;
use App\Services\SetoranService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Wajib memakai PostgreSQL (lihat README: .env.testing), karena skema memakai
 * trigger dan view khusus PostgreSQL.
 */
class UangTest extends TestCase
{
    use RefreshDatabase;

    private function buatNasabah(): int
    {
        $uid = DB::table('users')->insertGetId([
            'nama' => 'Tes',
            'no_hp' => '08' . random_int(100000000, 999999999),
            'pin_hash' => Hash::make('123456'),
            'role' => 'nasabah',
        ]);

        return DB::table('nasabah')->insertGetId([
            'user_id' => $uid, 'kode_nasabah' => 'NSB-' . $uid, 'saldo' => 0,
        ]);
    }

    private function saldo(int $id): string
    {
        return (string) DB::table('nasabah')->where('id', $id)->value('saldo');
    }

    public function test_kredit_dan_debit_mengubah_saldo_dan_mencatat_mutasi(): void
    {
        $id = $this->buatNasabah();
        $svc = app(SaldoService::class);

        DB::transaction(function () use ($svc, $id) {
            $svc->catat($id, 'setoran', 'kredit', '150000.00');
            $svc->catat($id, 'belanja', 'debit', '40000.00');
        });

        $this->assertSame('110000.00', $this->saldo($id));
        $this->assertSame(2, DB::table('mutasi_saldo')->where('nasabah_id', $id)->count());
        $this->assertSame(0, DB::table('v_selisih_saldo')->count());
    }

    public function test_saldo_tidak_bisa_minus(): void
    {
        $id = $this->buatNasabah();
        $svc = app(SaldoService::class);

        DB::transaction(fn () => $svc->catat($id, 'setoran', 'kredit', '10000.00'));

        try {
            DB::transaction(fn () => $svc->catat($id, 'belanja', 'debit', '10000.01'));
            $this->fail('Seharusnya melempar SALDO_KURANG');
        } catch (ApiException $e) {
            $this->assertSame('SALDO_KURANG', $e->kode);
        }

        $this->assertSame('10000.00', $this->saldo($id));
        $this->assertSame(0, DB::table('v_selisih_saldo')->count());
    }

    public function test_pin_salah_tiga_kali_mengunci_dan_hitungan_tersimpan(): void
    {
        $id = $this->buatNasabah();
        $user = User::findOrFail(DB::table('nasabah')->where('id', $id)->value('user_id'));
        $pin = app(PinService::class);

        $kode = [];
        for ($i = 0; $i < 3; $i++) {
            try {
                $pin->verifikasi($user, '000000');
            } catch (ApiException $e) {
                $kode[] = $e->kode;
            }
        }

        $this->assertSame(['PIN_SALAH', 'PIN_SALAH', 'AKUN_TERKUNCI'], $kode);
        $this->assertNotNull(User::find($user->id)->terkunci_sampai);

        // Saat terkunci, PIN yang benar pun ditolak.
        $this->expectException(ApiException::class);
        $pin->verifikasi($user, '123456');
    }

    public function test_setoran_menghitung_di_server_dan_menambah_saldo(): void
    {
        $id = $this->buatNasabah();
        $adminId = DB::table('users')->insertGetId([
            'nama' => 'Admin', 'no_hp' => '0811111111', 'pin_hash' => Hash::make('123456'), 'role' => 'admin',
        ]);
        $plastik = DB::table('jenis_sampah')->insertGetId(['nama' => 'Plastik', 'harga_per_kg' => '2500.00']);

        $hasil = app(SetoranService::class)->simpan($adminId, $id, null, [
            ['jenis_sampah_id' => $plastik, 'berat' => '2.345'],
        ]);

        $this->assertSame('5862.50', $hasil['total_nilai']); // 2.345 x 2500
        $this->assertSame('5862.50', $this->saldo($id));
        $this->assertSame(0, DB::table('v_selisih_saldo')->count());
    }

    public function test_mutasi_saldo_tidak_bisa_diubah(): void
    {
        $id = $this->buatNasabah();
        DB::transaction(fn () => app(SaldoService::class)->catat($id, 'setoran', 'kredit', '1000.00'));

        // Harus terakhir: error PostgreSQL membatalkan transaksi tes.
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('mutasi_saldo')->where('nasabah_id', $id)->update(['nominal' => '9999.00']);
    }
}
