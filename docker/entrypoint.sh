#!/usr/bin/env bash
set -e
cd /var/www/html

echo "==> Aplikasi Sumber Biomassa PT GEP — starting"

# 0) Sesuaikan port Nginx dengan platform (Railway/Render set $PORT; VPS default 80)
LISTEN_PORT="${PORT:-80}"
sed -i "s/listen 80 default_server;/listen ${LISTEN_PORT} default_server;/; s/listen \[::\]:80 default_server;/listen [::]:${LISTEN_PORT} default_server;/" /etc/nginx/sites-available/default
echo "==> Nginx listen di port ${LISTEN_PORT}"

# 0b) Folder session persisten (agar user tidak logout tiap deploy)
if [ -n "${SESSION_FILES}" ]; then
  mkdir -p "${SESSION_FILES}"
  chown -R www-data:www-data "${SESSION_FILES}"
  echo "==> Session disimpan di ${SESSION_FILES}"
fi

DB_CONN="${DB_CONNECTION:-mysql}"

if [ "${DB_CONN}" = "sqlite" ]; then
  # ---- Mode SQLite (paling sederhana, tanpa server DB terpisah) ----
  DB_FILE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
  mkdir -p "$(dirname "$DB_FILE")"
  [ -f "$DB_FILE" ] || touch "$DB_FILE"
  chown -R www-data:www-data "$(dirname "$DB_FILE")"
  echo "==> Database SQLite: ${DB_FILE}"
else
  # ---- Mode MySQL: tunggu server DB siap ----
  echo "==> Menunggu database ${DB_HOST}:${DB_PORT:-3306} ..."
  TRIES=0
  until php -r '
    try {
      new PDO("mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT")?:"3306"), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));
      exit(0);
    } catch (Throwable $e) { exit(1); }
  ' 2>/dev/null; do
    TRIES=$((TRIES+1))
    if [ "$TRIES" -gt 40 ]; then echo "!! Database tidak merespons, berhenti."; exit 1; fi
    sleep 3
  done
  echo "==> Database siap."
fi

# 1) APP_KEY otomatis bila kosong (disarankan set permanen via env)
if [ -z "${APP_KEY}" ]; then
  echo "==> APP_KEY kosong, generate sementara (set APP_KEY di env agar permanen)."
  php artisan key:generate --force
fi

# 2) Migrasi
echo "==> Menjalankan migrasi..."
php artisan migrate --force

# 3) Seed hanya jika database masih kosong (organisasi = 0)
if [ "${SEED_ON_DEPLOY:-true}" = "true" ]; then
  ORG=$(php -r '
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    try { echo \App\Models\Organization::count(); } catch (Throwable $e) { echo 0; }
  ' 2>/dev/null || echo 0)
  if [ "${ORG:-0}" = "0" ]; then
    echo "==> Database kosong, seeding data awal..."
    php artisan db:seed --force || true
  else
    echo "==> Data sudah ada (organisasi=${ORG}), lewati seeding."
  fi
fi

# 3b) Set / reset password admin bila variabel ADMIN_PASSWORD diisi
if [ -n "${ADMIN_PASSWORD}" ]; then
  echo "==> Menerapkan ADMIN_PASSWORD untuk akun admin..."
  php -r '
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $email = getenv("ADMIN_EMAIL") ?: "admin@pt-gep.com";
    $u = \App\Models\User::where("email", $email)->first();
    if ($u) {
      $u->password = getenv("ADMIN_PASSWORD");   // cast "hashed" otomatis meng-hash
      $u->save();
      echo "==> Password admin ({$email}) berhasil diperbarui\n";
    } else {
      echo "==> Akun {$email} tidak ditemukan, dilewati\n";
    }
  ' || echo "==> Gagal menerapkan ADMIN_PASSWORD (dilewati)"
fi

# 4) Cache produksi
php artisan config:cache
php artisan route:cache
php artisan view:cache || true
php artisan storage:link 2>/dev/null || true

echo "==> Siap. Menjalankan web server."
exec "$@"
