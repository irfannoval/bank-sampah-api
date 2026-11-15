# Bank Sampah Digital - Backend (Laravel 13)

API untuk sistem bank sampah: nasabah ber-kartu QR, toko mitra, dan admin.
Mengikuti Dokumen Perancangan Sistem v1.2, bagian 14 (Rancangan API).

**Isi:** autentikasi PIN, kelola nasabah/mitra/kartu/jenis sampah, setoran dan ledger saldo,
belanja di mitra (scan kartu + PIN), pembatalan, pencairan mitra, penarikan + webhook gateway,
laporan, audit log, pengaturan sistem.

**Frontend (Vue 3 + Tailwind, PWA):** layar kasir toko mitra sudah ada (login, scan kartu, profil, nominal, PIN, hasil, riwayat, pembatalan, pencairan). Layar admin dan nasabah belum.

**Belum ada:** layar admin, layar nasabah, dan driver payment gateway asli.

## Kebutuhan
PHP 8.3+ (ekstensi `bcmath`, `pdo_pgsql`, `mbstring`), Composer, PostgreSQL 14+.

## Pasang
```bash
composer install
cp .env.example .env
php artisan key:generate
# edit .env: DB_PASSWORD, SEED_ADMIN_PIN, GATEWAY_WEBHOOK_SECRET
createdb bank_sampah
php artisan migrate --seed
php artisan serve
```
Akun awal: admin utama (`SEED_ADMIN_HP` / `SEED_ADMIN_PIN` di `.env`). **Ganti PIN-nya** sebelum produksi.

Jika Composer menolak versi `laravel/sanctum`, jalankan `composer require laravel/sanctum` agar memilih versi yang cocok dengan Laravel Anda.

## Frontend
Butuh Node.js (LTS).
```bash
npm install
npm run build      # hasil di public/build
php artisan serve  # buka http://127.0.0.1:8000
```
Saat mengubah tampilan, jalankan `npm run dev` di terminal kedua (sambil `php artisan serve`).

**Kamera untuk scan QR hanya bisa dipakai lewat HTTPS** (atau `localhost`). Untuk uji coba di HP, aplikasi harus di-hosting dengan HTTPS. Tanpa kamera, kasir bisa mengetik/menempel kode kartu.

Untuk mencoba layar kasir: daftarkan toko mitra dan nasabah lewat API (lihat `uji-alur.ps1`), lalu login di browser memakai nomor HP dan PIN awal toko mitra. Kode kartu nasabah = `qr_token` dari balasan pendaftaran nasabah.

## Tes
```bash
createdb bank_sampah_test
php artisan test
```
Tes memakai PostgreSQL (`phpunit.xml`), karena skema memakai trigger dan view khusus PostgreSQL.
Host/user/password mengikuti `.env`.

## Alur cepat (curl)
```bash
H='-H Accept:application/json -H Content-Type:application/json'
# 1. login admin utama
curl -X POST localhost:8000/api/v1/auth/login $H -d '{"no_hp":"081200000000","pin":"123456"}'
# 2. tambah jenis sampah
curl -X POST localhost:8000/api/v1/admin/jenis-sampah $H -H "Authorization: Bearer TOKEN" \
  -d '{"nama":"Plastik","harga_per_kg":"2500"}'
# 3. daftarkan nasabah (balasan berisi pin_awal dan qr_token kartu)
curl -X POST localhost:8000/api/v1/admin/nasabah $H -H "Authorization: Bearer TOKEN" \
  -d '{"nama":"Siti","no_hp":"081234500001"}'
# 4. setoran (wajib Idempotency-Key)
curl -X POST localhost:8000/api/v1/admin/setoran $H -H "Authorization: Bearer TOKEN" -H "Idempotency-Key: s-001" \
  -d '{"nasabah_id":1,"items":[{"jenis_sampah_id":1,"berat":"2.5"}]}'
```
Alur belanja: `POST /mitra/transaksi/mulai` (kirim `qr_token`) lalu
`POST /mitra/transaksi/{sesi_id}/konfirmasi` (kirim `nominal` dan `pin`).

## Aturan yang tidak boleh dilanggar
1. Semua perubahan saldo nasabah **hanya** lewat `SaldoService::catat()` dalam `DB::transaction`.
2. Harga dan total dihitung server, tidak pernah diterima dari klien.
3. PIN tidak pernah masuk log, audit log, atau respons.
4. Error PIN dilempar setelah transaksi selesai (lihat `PinService`), agar hitungan salah PIN tidak di-rollback.
5. `mutasi_saldo` dan `audit_log` tidak boleh di-update/hapus (dijaga trigger database).
6. Cek `GET /admin/selisih-saldo` secara berkala. Hasilnya harus selalu kosong.

## Keputusan desain yang perlu Anda konfirmasi
- **Pencairan = seluruh tagihan yang belum dicairkan.** Mitra tidak mengetik nominal. `saldo_tagihan` baru berkurang saat pencairan dibayar.
- **Biaya admin penarikan (`penarikan_biaya_admin`) = Rp2.500 adalah nilai CONTOH.** Ganti lewat `PATCH /admin/pengaturan` sesuai fee gateway yang dipilih.
- **Lama kunci akun** setelah 3x salah PIN = 30 menit (`pin_kunci_menit`), usulan.
- **Masa berlaku token** = 480 menit (`SANCTUM_EXPIRATION`), usulan.
- Pembatalan transaksi yang pencairannya sudah **dibayar** tidak bisa lewat API; diselesaikan manual.

## Memasang payment gateway asli
1. Buat kelas yang mengimplementasi `App\Services\Gateway\GatewayInterface` (`validasiRekening`, `kirim`, `cekStatus`).
2. Daftarkan di `AppServiceProvider` (`match` pada `config('gateway.driver')`) dan set `GATEWAY_DRIVER` di `.env`.
3. Sesuaikan `WebhookController` dengan format tanda tangan dan isi webhook provider.
4. `FakeGateway` hanya untuk pengembangan. Jangan dipakai di produksi.
Perintah `penarikan:cek-pending` (dijadwalkan tiap 5 menit di `routes/console.php`) menjaga penarikan yang webhook-nya tidak datang. Pastikan scheduler berjalan: `* * * * * php artisan schedule:run`.

## Struktur
```
app/Services/         logika bisnis (Saldo, Pin, Setoran, MitraTransaksi, Pencairan, Penarikan, Kartu, Akun, Audit)
app/Services/Gateway/ kontrak dan driver payment gateway
app/Http/Controllers/ Admin/, Mitra/, Nasabah/, Auth, Webhook
app/Http/Middleware/  RoleMiddleware, Idempotency
database/sql/         skema inti (PostgreSQL) yang dijalankan migration
routes/api.php        semua endpoint (/api/v1/...)
tests/Feature/        UangTest (inti uang), AlurTest (alur HTTP)
```
