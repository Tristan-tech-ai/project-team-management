#!/usr/bin/env bash
# One-time installation on Linux/macOS: MySQL in Docker, both apps, initial data, and demo accounts.
# Usage (from this folder):  ./setup.sh            Without Docker (own MySQL):  ./setup.sh --no-docker
set -euo pipefail
cd "$(dirname "$0")"
docker_enabled=1; sample_flag=""
for arg in "$@"; do
  case "$arg" in
    --no-docker) docker_enabled=0 ;;
    --no-sample) sample_flag="--no-sample" ;;
  esac
done
for tool in php composer npm; do
  command -v "$tool" >/dev/null || { echo "$tool belum terpasang. Lihat bagian Persyaratan di README.md."; exit 1; }
done
if [ "$docker_enabled" = 1 ]; then
  command -v docker >/dev/null || { echo "Docker belum terpasang. Pasang Docker, atau jalankan ./setup.sh --no-docker dengan MySQL sendiri."; exit 1; }
  echo "== Menyalakan MySQL (Docker)"
  docker compose up -d --wait
fi
for app in admin-app team-app; do
  echo "== Memasang $app"
  (
    cd "$app"
    composer install --no-interaction --no-progress
    npm install --no-audit --no-fund
    npm run build
    if [ "$app" = admin-app ]; then php artisan ptm:setup $sample_flag; else php artisan ptm:setup; fi
  )
done
echo "== Selesai. Jalankan kedua app dengan: ./start.sh"
