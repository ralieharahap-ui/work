<#
    ============================================================
    PT GEOSYS ENERGI PRIMA - ERP
    Perakit Paket Deploy cPanel (siap unggah)
    ------------------------------------------------------------
    Menghasilkan folder  deploy\paket-upload\  berisi:

        gep-erp\       -> inti aplikasi (diunggah ke /home/USERNAME/)
        public_html\   -> berkas publik (diunggah ke /home/USERNAME/public_html/)
        database\      -> gep_erp_master.sql (diimpor lewat phpMyAdmin)
        PANDUAN-DEPLOY-CPANEL.md

    Jalankan dari akar proyek:
        powershell -ExecutionPolicy Bypass -File deploy\build-paket-deploy.ps1
    ============================================================
#>

$ErrorActionPreference = 'Stop'

$Root   = Split-Path -Parent $PSScriptRoot
$Out    = Join-Path $PSScriptRoot 'paket-upload'
$AppDir = Join-Path $Out 'gep-erp'
$PubDir = Join-Path $Out 'public_html'
$DbDir  = Join-Path $Out 'database'

# PHP & Composer dari Laragon (sesuaikan bila lokasi berubah).
$Php      = 'C:\Working _Space_Ralie\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
$Composer = 'C:\Working _Space_Ralie\laragon\bin\composer\composer.phar'

Write-Host ''
Write-Host '=== Merakit paket deploy cPanel ===' -ForegroundColor Cyan
Write-Host ''

# --- 1. Bersihkan hasil rakitan sebelumnya -------------------
if (Test-Path $Out) {
    Write-Host '[1/7] Membersihkan paket lama...' -ForegroundColor Yellow
    Remove-Item -Recurse -Force $Out
}
New-Item -ItemType Directory -Force -Path $AppDir, $PubDir, $DbDir | Out-Null

# --- 2. Build aset frontend ----------------------------------
Write-Host '[2/7] Build aset frontend (npm run build)...' -ForegroundColor Yellow
Push-Location $Root
npm run build
if ($LASTEXITCODE -ne 0) { Pop-Location; throw 'npm run build gagal.' }
Pop-Location

# --- 3. Bersihkan cache agar tidak terbawa path lokal --------
Write-Host '[3/7] Membersihkan cache aplikasi...' -ForegroundColor Yellow
Push-Location $Root
if (Test-Path $Php) {
    & $Php artisan config:clear
    & $Php artisan route:clear
    & $Php artisan view:clear
    & $Php artisan cache:clear
} else {
    Write-Host '      PHP tidak ditemukan - lewati (hapus manual isi bootstrap\cache).' -ForegroundColor DarkYellow
}
Pop-Location

# --- 4. Salin inti aplikasi (TANPA vendor) -------------------
# vendor lokal memuat paket pengembangan (phpunit/psalm/pint) yang berukuran
# ratusan MB dan tidak dipakai di produksi - dibangun ulang di langkah 5.
Write-Host '[4/7] Menyalin inti aplikasi...' -ForegroundColor Yellow
$AppItems = @('app', 'bootstrap', 'config', 'database', 'resources', 'routes', 'storage', 'artisan', 'composer.json', 'composer.lock')
foreach ($item in $AppItems) {
    $src = Join-Path $Root $item
    if (Test-Path $src) {
        Copy-Item -Recurse -Force $src (Join-Path $AppDir $item)
    }
}

# Kosongkan cache & log yang ikut tersalin.
foreach ($pattern in @('bootstrap\cache\*.php', 'storage\logs\*.log', 'storage\framework\cache\data\*', 'storage\framework\sessions\*', 'storage\framework\views\*.php')) {
    $path = Join-Path $AppDir $pattern
    Get-ChildItem $path -ErrorAction SilentlyContinue | Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
}

# .env produksi (template - wajib diisi sebelum diunggah).
Copy-Item (Join-Path $PSScriptRoot 'template\env-hosting.txt') (Join-Path $AppDir '.env.CONTOH-ISI-DULU') -Force

# --- 4b. Bangun vendor khusus produksi -----------------------
Write-Host '      Membangun vendor produksi (composer install --no-dev)...' -ForegroundColor Yellow
$vendorSiap = $false
if ((Test-Path $Composer) -and (Test-Path $Php)) {
    Push-Location $AppDir
    & $Php $Composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --no-progress
    $ok = ($LASTEXITCODE -eq 0)
    Pop-Location
    if ($ok) {
        $vendorSiap = $true
    } else {
        Write-Host '      composer install gagal - memakai salinan vendor lokal sebagai cadangan.' -ForegroundColor DarkYellow
    }
} else {
    Write-Host '      composer.phar/PHP tidak ditemukan - memakai salinan vendor lokal.' -ForegroundColor DarkYellow
}

if (-not $vendorSiap) {
    # Cadangan: salin vendor lokal, lalu buang folder "tools" (phar psalm/phpdocumentor)
    # dan paket pengembangan agar ukurannya wajar.
    Copy-Item -Recurse -Force (Join-Path $Root 'vendor') (Join-Path $AppDir 'vendor')
    $devPkg = @('phpunit', 'sebastian', 'mockery', 'fakerphp', 'phar-io', 'theseer', 'myclabs', 'nunomaduro')
    foreach ($p in $devPkg) {
        $d = Join-Path $AppDir "vendor\$p"
        if (Test-Path $d) { Remove-Item -Recurse -Force $d -ErrorAction SilentlyContinue }
    }
    Get-ChildItem (Join-Path $AppDir 'vendor') -Recurse -Directory -Filter 'tools' -ErrorAction SilentlyContinue |
        Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
}

# --- 5. Salin berkas publik ----------------------------------
Write-Host '[5/7] Menyalin berkas publik...' -ForegroundColor Yellow
$PublicSrc = Join-Path $Root 'public'
# Kecualikan: hot (penanda dev server Vite) & storage (symlink lokal).
Get-ChildItem $PublicSrc -Force | Where-Object { $_.Name -notin @('hot', 'storage') } | ForEach-Object {
    Copy-Item -Recurse -Force $_.FullName (Join-Path $PubDir $_.Name)
}
# index.php versi cPanel menggantikan bawaan.
Copy-Item (Join-Path $PSScriptRoot 'template\index-cpanel.php') (Join-Path $PubDir 'index.php') -Force
Copy-Item (Join-Path $PSScriptRoot 'template\link-storage.php') (Join-Path $PubDir 'link-storage.php') -Force

# --- 6. Database & panduan -----------------------------------
Write-Host '[6/7] Menyalin database master & panduan...' -ForegroundColor Yellow
Copy-Item (Join-Path $PSScriptRoot 'database\gep_erp_master.sql') $DbDir -Force
Copy-Item (Join-Path $PSScriptRoot 'PANDUAN-DEPLOY-CPANEL.md') $Out -Force

# --- 7. Ringkasan --------------------------------------------
Write-Host '[7/7] Selesai.' -ForegroundColor Yellow
$size = (Get-ChildItem $Out -Recurse -File | Measure-Object -Property Length -Sum).Sum / 1MB
Write-Host ''
Write-Host '=== PAKET SIAP ===' -ForegroundColor Green
Write-Host ("Lokasi : {0}" -f $Out)
Write-Host ("Ukuran : {0:N1} MB" -f $size)
Write-Host ''
Write-Host 'Langkah berikutnya:' -ForegroundColor Cyan
Write-Host '  1. Buka PANDUAN-DEPLOY-CPANEL.md di dalam folder paket.'
Write-Host '  2. Isi .env.CONTOH-ISI-DULU lalu ganti namanya menjadi .env'
Write-Host '  3. Kompres gep-erp dan public_html menjadi .zip, unggah lewat cPanel File Manager.'
Write-Host ''
