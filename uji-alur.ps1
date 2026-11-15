# Uji alur Bank Sampah Digital tanpa Postman (PowerShell bawaan Windows).
# Pastikan "php artisan serve" sedang berjalan di jendela lain.
#
# Cara pakai (PowerShell, di folder tempat file ini berada):
#   powershell -ExecutionPolicy Bypass -File .\uji-alur.ps1 -AdminPin 123456
# Ganti 123456 dengan nilai SEED_ADMIN_PIN di file .env Anda.

param(
    [string]$Base = "http://127.0.0.1:8000/api/v1",
    [string]$AdminHp = "081200000000",
    [string]$AdminPin = "123456"
)

$ErrorActionPreference = "Stop"
$script:gagal = 0

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
        Write-Host ""
        Write-Host "GAGAL: $Method $Path" -ForegroundColor Red
        Write-Host $pesan -ForegroundColor Red
        Write-Host "Kirim tulisan merah ini ke Claude." -ForegroundColor Yellow
        exit 1
    }
}

function Cek($nama, $kondisi) {
    if ($kondisi) {
        Write-Host "  [OK]    $nama" -ForegroundColor Green
    } else {
        Write-Host "  [SALAH] $nama" -ForegroundColor Red
        $script:gagal++
    }
}

function Langkah($teks) { Write-Host ""; Write-Host $teks -ForegroundColor Cyan }

$stamp = (Get-Date).ToString("HHmmss")
$hpNasabah = "08" + (Get-Random -Minimum 1000000000 -Maximum 1999999999)
$hpMitra = "08" + (Get-Random -Minimum 2000000000 -Maximum 2999999999)

Langkah "1. Login admin utama"
$adm = Panggil POST "/auth/login" @{ no_hp = $AdminHp; pin = $AdminPin }
$tA = $adm.token
Cek "token admin diterima" ([bool]$tA)

Langkah "2. Tambah jenis sampah (Rp2.500/kg)"
$j = Panggil POST "/admin/jenis-sampah" @{ nama = "Plastik $stamp"; kategori = "Plastik"; harga_per_kg = "2500" } -Token $tA
Cek "jenis sampah dibuat" ($j.id -gt 0)

Langkah "3. Daftarkan nasabah (otomatis dapat kartu)"
$n = Panggil POST "/admin/nasabah" @{ nama = "Siti Contoh"; no_hp = $hpNasabah; alamat = "Cirebon" } -Token $tA
Cek "nasabah dibuat, kode: $($n.kode_nasabah)" ($n.nasabah_id -gt 0)
Cek "kartu QR dibuat" ([bool]$n.kartu.qr_token)

Langkah "4. Setoran 5 kg plastik"
$s = Panggil POST "/admin/setoran" @{ nasabah_id = $n.nasabah_id; items = @( @{ jenis_sampah_id = $j.id; berat = "5" } ) } -Token $tA -Idem
Cek "total nilai Rp12.500 (hasil: $($s.total_nilai))" ($s.total_nilai -eq "12500.00")
Cek "saldo nasabah Rp12.500" ($s.saldo_sesudah -eq "12500.00")

Langkah "5. Daftarkan toko mitra dan login"
$m = Panggil POST "/admin/mitra" @{ nama = "Bu Warung"; no_hp = $hpMitra; nama_toko = "Warung Contoh"; alamat = "Cirebon"; bank_tujuan = "BCA"; no_rekening = "1234567890"; atas_nama = "Bu Warung" } -Token $tA
$lm = Panggil POST "/auth/login" @{ no_hp = $hpMitra; pin = $m.pin_awal }
$tM = $lm.token
Cek "login mitra berhasil" ([bool]$tM)

Langkah "6. Kasir scan kartu lalu belanja Rp10.000 (PIN nasabah)"
$sesi = Panggil POST "/mitra/transaksi/mulai" @{ qr_token = $n.kartu.qr_token } -Token $tM
Cek "kasir melihat saldo nasabah Rp12.500" ($sesi.nasabah.saldo -eq "12500.00")
$statusSalah = 0
$salah = ""
try {
    Invoke-RestMethod -Method POST -Uri "$Base/mitra/transaksi/$($sesi.sesi_id)/konfirmasi" -ContentType "application/json" `
        -Headers @{ Accept = "application/json"; Authorization = "Bearer $tM"; "Idempotency-Key" = [guid]::NewGuid().ToString() } `
        -Body (@{ nominal = "10000"; pin = "000000" } | ConvertTo-Json) | Out-Null
    $salah = "(tidak ada error: permintaan diterima)"
} catch {
    try { $statusSalah = [int]$_.Exception.Response.StatusCode } catch { $statusSalah = 0 }
    $salah = $_.ErrorDetails.Message
    if (-not $salah) { $salah = $_.Exception.Message }
}
$okSalah = ($statusSalah -eq 422) -or ($salah -like "*PIN_SALAH*")
Cek "PIN salah ditolak (status: $statusSalah)" $okSalah
if (-not $okSalah) { Write-Host "         balasan server: $salah" -ForegroundColor Yellow }
$t = Panggil POST "/mitra/transaksi/$($sesi.sesi_id)/konfirmasi" @{ nominal = "10000"; pin = $n.pin_awal } -Token $tM -Idem
Cek "transaksi berhasil (id: $($t.transaksi_id))" ($t.transaksi_id -gt 0)

Langkah "7. Cek dari sisi nasabah dan warung"
$ln = Panggil POST "/auth/login" @{ no_hp = $hpNasabah; pin = $n.pin_awal }
$tN = $ln.token
$bn = Panggil GET "/nasabah/beranda" -Token $tN
Cek "saldo nasabah Rp2.500 (hasil: $($bn.saldo))" ($bn.saldo -eq "2500.00")
Cek "setoran bulan ini 5 kg (hasil: $($bn.setoran_bulan_ini.total_berat_kg))" ($bn.setoran_bulan_ini.total_berat_kg -eq "5.000")
$bm = Panggil GET "/mitra/beranda" -Token $tM
Cek "saldo tagihan warung Rp10.000 (hasil: $($bm.saldo_tagihan))" ($bm.saldo_tagihan -eq "10000.00")

Langkah "8. Pencairan mitra"
$p = Panggil POST "/mitra/pencairan" @{} -Token $tM -Idem
Cek "pencairan diajukan Rp10.000 (hasil: $($p.nominal))" ($p.nominal -eq "10000.00")
Panggil POST "/admin/pencairan/$($p.pencairan_id)/setujui" @{} -Token $tA | Out-Null
Cek "pencairan disetujui admin" $true

Langkah "9. Cek kesehatan: saldo dan ledger harus cocok"
$sel = Panggil GET "/admin/selisih-saldo" -Token $tA
Cek "tidak ada selisih saldo" (@($sel).Count -eq 0)

Write-Host ""
if ($script:gagal -eq 0) {
    Write-Host "SEMUA LANGKAH LULUS. Backend berjalan sesuai rancangan." -ForegroundColor Green
} else {
    Write-Host "$($script:gagal) pemeriksaan SALAH. Kirim layar ini ke Claude." -ForegroundColor Red
}