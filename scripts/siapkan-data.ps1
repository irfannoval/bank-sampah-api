# Menyiapkan data contoh untuk mencoba layar kasir, lalu MENAMPILKAN nomor HP, PIN, dan kode kartu.
# Pastikan "php artisan serve" sedang berjalan di jendela lain.
#
# Cara pakai (PowerShell, di folder tempat file ini berada):
#   powershell -ExecutionPolicy Bypass -File .\siapkan-data.ps1 -AdminPin 123456
# Ganti 123456 dengan nilai SEED_ADMIN_PIN di file .env Anda.

param(
    [string]$Base = "http://127.0.0.1:8000/api/v1",
    [string]$AdminHp = "081200000000",
    [string]$AdminPin = "123456"
)

$ErrorActionPreference = "Stop"

function Panggil($Method, $Path, $Body = $null, $Token = $null, [switch]$Idem) {
    $headers = @{ "Accept" = "application/json" }
    if ($Token) { $headers["Authorization"] = "Bearer $Token" }
    if ($Idem) { $headers["Idempotency-Key"] = [guid]::NewGuid().ToString() }
    $params = @{ Method = $Method; Uri = "$Base$Path"; Headers = $headers; ContentType = "application/json" }
    if ($Body -ne $null) { $params["Body"] = ($Body | ConvertTo-Json -Depth 6) }
    try {
        return Invoke-RestMethod @params
    } catch {
        $pesan = $_.ErrorDetails.Message
        if (-not $pesan) { $pesan = $_.Exception.Message }
        Write-Host "GAGAL: $Method $Path" -ForegroundColor Red
        Write-Host $pesan -ForegroundColor Red
        Write-Host "Kirim tulisan merah ini ke Claude." -ForegroundColor Yellow
        exit 1
    }
}

$stamp = (Get-Date).ToString("HHmmss")
$hpNasabah = "08" + (Get-Random -Minimum 1000000000 -Maximum 1999999999)
$hpMitra = "08" + (Get-Random -Minimum 2000000000 -Maximum 2999999999)

$adm = Panggil POST "/auth/login" @{ no_hp = $AdminHp; pin = $AdminPin }
$tA = $adm.token

$j = Panggil POST "/admin/jenis-sampah" @{ nama = "Plastik $stamp"; kategori = "Plastik"; harga_per_kg = "2500" } -Token $tA
$n = Panggil POST "/admin/nasabah" @{ nama = "Siti Contoh"; no_hp = $hpNasabah; alamat = "Cirebon" } -Token $tA
$s = Panggil POST "/admin/setoran" @{ nasabah_id = $n.nasabah_id; items = @( @{ jenis_sampah_id = $j.id; berat = "20" } ) } -Token $tA -Idem
$m = Panggil POST "/admin/mitra" @{ nama = "Bu Warung"; no_hp = $hpMitra; nama_toko = "Warung Contoh"; alamat = "Cirebon"; bank_tujuan = "BCA"; no_rekening = "1234567890"; atas_nama = "Bu Warung" } -Token $tA

Write-Host ""
Write-Host "=== DATA SIAP. CATAT DAN SIMPAN ===" -ForegroundColor Green
Write-Host ""
Write-Host "LOGIN KASIR TOKO MITRA (untuk dicoba di browser):" -ForegroundColor Cyan
Write-Host "  Nomor HP : $hpMitra"
Write-Host "  PIN      : $($m.pin_awal)"
Write-Host ""
Write-Host "NASABAH (pemilik kartu):" -ForegroundColor Cyan
Write-Host "  Nama       : Siti Contoh ($($n.kode_nasabah))"
Write-Host "  Saldo awal : Rp 50.000"
Write-Host "  PIN        : $($n.pin_awal)   <- diketik nasabah saat bayar"
Write-Host "  KODE KARTU : $($n.kartu.qr_token)"
Write-Host "               (tempel di kolom 'kode kartu' pada layar Scan)"
Write-Host ""
Write-Host "ADMIN UTAMA:" -ForegroundColor Cyan
Write-Host "  Nomor HP : $AdminHp"
Write-Host "  PIN      : yang Anda isi di SEED_ADMIN_PIN"
Write-Host "  (layar admin belum dibangun, jadi belum dipakai di browser)"
