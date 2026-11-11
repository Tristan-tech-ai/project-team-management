#!/bin/sh
# Menyiapkan database uji ptm_test dengan urutan yang sama seperti Petunjuk Teknis 2.3:
# migrate tabel bawaan Laravel dari admin-app, lalu impor SQL modul satu kali. Tes memakai transaksi yang dibatalkan.
set -e
cd "$(dirname "$0")/../.."
cd admin-app
DB_DATABASE=ptm_test php artisan migrate --force --no-interaction >/dev/null
ada=$(docker exec ptm_mysql sh -c 'mysql -uptm -p"$MYSQL_PASSWORD" -N -e "select count(*) from information_schema.tables where table_schema=\"ptm_test\" and table_name=\"roles\"" 2>/dev/null')
if [ "$ada" = "0" ]; then
  docker exec -i ptm_mysql sh -c 'mysql -uptm -p"$MYSQL_PASSWORD" ptm_test 2>/dev/null' < ../docs/project_team_management_laravel.sql
  echo "ptm_test: SQL modul diimpor"
else
  echo "ptm_test: SQL modul sudah ada"
fi
docker exec ptm_mysql sh -c 'mysql -uptm -p"$MYSQL_PASSWORD" ptm_test -N -e "select count(*) tabel from information_schema.tables where table_schema=database(); select (select count(*) from roles), (select count(*) from permissions), (select count(*) from menu_items)" 2>/dev/null'
