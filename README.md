# Bank Sampah Digital - Backend (Laravel 13)

API untuk sistem bank sampah: nasabah ber-kartu QR, toko mitra, dan admin.
Mengikuti Dokumen Perancangan Sistem v1.2, bagian 14 (Rancangan API).

**Isi:** autentikasi PIN, kelola nasabah/mitra/kartu/jenis sampah, setoran dan ledger saldo,
belanja di mitra (scan kartu + PIN), pembatalan, pencairan mitra, penarikan + pemeriksaan status payout,
laporan, audit log, pengaturan sistem.

**Frontend (Vue 3 + Tailwind, bisa dipasang sebagai PWA):**
- **Toko mitra (HP):** login, scan kartu, profil dan saldo nasabah, nominal, PIN, hasil, riwayat, pembatalan, pencairan.
- **Admin (komputer atau HP):** input setoran, riwayat setoran, nasabah + kartu (daftar, cetak kartu ber-QR, blokir, ganti, reset PIN, koreksi saldo), jenis sampah dan harga, toko mitra, transaksi mitra, pemantauan pencairan Midtrans, penarikan untuk nasabah tanpa HP, laporan, pengaturan, audit dan cek saldo.
- **Nasabah (HP):** saldo dan setoran bulan ini, riwayat saldo, setoran saya, toko mitra, tarik saldo, riwayat penarikan, ubah PIN.

**Payment gateway:** tersedia driver payout bank Midtrans Iris dan simulasi `FakeGateway`.
Penerimaan saldo nasabah dari setoran sampah dicatat admin di aplikasi; bukan pembayaran masuk Midtrans.
Produksi tetap memerlukan kredensial/aktivasi Iris dan hosting HTTPS.

## Kebutuhan
PHP 8.3+ (ekstensi `bcmath`, `pdo_pgsql`, `mbstring`), Composer, PostgreSQL 14+.

## Pasang
```bash
composer install
cp .env.example .env
php artisan key:generate
# edit .env: DB_PASSWORD, SEED_ADMIN_PIN
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

Untuk mencoba cepat: jalankan `scripts/siapkan-data.ps1` (membuat jenis sampah, nasabah, dan toko mitra, lalu menampilkan nomor HP, PIN, dan kode kartu). Atau lewat browser: login sebagai admin utama (`SEED_ADMIN_HP` / `SEED_ADMIN_PIN`), lalu daftarkan jenis sampah, nasabah, dan toko mitra dari menu admin.

**Alur harian:** nasabah datang membawa kartu -> admin scan kartu di *Input Setoran* -> nilai setoran masuk ke saldo aplikasi. Di warung, kasir scan kartu -> nasabah mengetik PIN di HP kasir -> saldo nasabah terpotong dan saldo aplikasi toko langsung bertambah. Pencairan nasabah atau mitra membuat payout Midtrans Iris; approver menyetujui payout dengan OTP di Iris, lalu aplikasi menyinkronkan hasilnya.

**Cetak kartu:** menu *Nasabah* -> pilih nasabah -> *Cetak*. Ukuran kartu 85,6 x 54 mm (standar kartu ATM). Pilih "Save as PDF" atau printer kartu. QR berisi token acak, bukan saldo atau data pribadi.

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

## Mengaktifkan Midtrans Iris
1. Pastikan akun Midtrans Anda telah mengaktifkan Iris dan memiliki Iris API key untuk membuat payout.
2. Isi `.env` dengan `GATEWAY_DRIVER=midtrans`, `MIDTRANS_IRIS_API_KEY`, dan `MIDTRANS_ENVIRONMENT=sandbox` atau `production`.
3. Daftar bank dan validasi rekening diambil dari Iris. Nasabah dan mitra hanya dapat mencairkan ke rekening bank yang didukung.
4. Pengajuan nasabah dan mitra langsung dibuat sebagai payout di Iris. **Payout Iris berstatus antre sampai approver menyetujuinya dengan OTP di Midtrans Iris**; permintaan dari aplikasi tidak langsung mengirim uang.
5. Saldo nasabah dikreditkan admin ketika setoran sampah disimpan. Belanja nasabah tetap memindahkan saldo di ledger internal; keduanya tidak memanggil payment gateway.
6. Sebelum mengaktifkan scheduler, rekonsiliasi pengajuan pending lama yang dibuat sebelum integrasi karena data rekeningnya tidak tersimpan sebagai snapshot. Pengajuan lama tanpa snapshot tidak dikirim otomatis. Jalankan scheduler tiap menit (`* * * * * php artisan schedule:run`). Perintah `penarikan:cek-pending` mengirim ulang permintaan baru yang belum mendapat referensi dan memeriksa status payout setiap 5 menit. Saldo tagihan mitra baru berkurang setelah Iris mengonfirmasi payout berhasil.

`FakeGateway` hanya untuk pengembangan dan tes. Jangan gunakan di produksi. Kredensial Iris jangan disimpan di source code atau dikirim ke klien.

## Struktur
```
app/Services/         logika bisnis (Saldo, Pin, Setoran, MitraTransaksi, Pencairan, Penarikan, Kartu, Akun, Audit)
app/Services/Gateway/ kontrak dan driver payment gateway
app/Http/Controllers/ Admin/, Mitra/, Nasabah/, Auth, Webhook
app/Http/Middleware/  RoleMiddleware, Idempotency
database/sql/         skema inti (PostgreSQL) yang dijalankan migration
routes/api.php        semua endpoint (/api/v1/...)
tests/Feature/        UangTest (inti uang), AlurTest (alur HTTP)
resources/js/         aplikasi Vue (views/admin, views/mitra, views/nasabah)
scripts/              skrip PowerShell: siapkan-data.ps1, uji-alur.ps1
```
