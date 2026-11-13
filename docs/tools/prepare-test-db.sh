#!/bin/sh
# Prepares the ptm_test database in the same order as Technical Guide 2.3:
# migrate the default Laravel tables from admin-app, then import the module SQL once. Tests use rolled-back transactions.
set -e
cd "$(dirname "$0")/../.."
# Create the test database and grant the application user access (uses the root password inside the container, never printed).
docker exec ptm_mysql sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS ptm_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON ptm_test.* TO \"ptm\"@\"%\"; FLUSH PRIVILEGES;" 2>/dev/null'
cd admin-app
DB_DATABASE=ptm_test php artisan migrate --force --no-interaction >/dev/null
exists=$(docker exec ptm_mysql sh -c 'mysql -uptm -p"$MYSQL_PASSWORD" -N -e "select count(*) from information_schema.tables where table_schema=\"ptm_test\" and table_name=\"roles\"" 2>/dev/null')
if [ "$exists" = "0" ]; then
  docker exec -i ptm_mysql sh -c 'mysql -uptm -p"$MYSQL_PASSWORD" ptm_test 2>/dev/null' < ../docs/project_team_management_laravel.sql
  echo "ptm_test: SQL modul diimpor"
else
  echo "ptm_test: SQL modul sudah ada"
fi
docker exec ptm_mysql sh -c 'mysql -uptm -p"$MYSQL_PASSWORD" ptm_test -N -e "select count(*) tabel from information_schema.tables where table_schema=database(); select (select count(*) from roles), (select count(*) from permissions), (select count(*) from menu_items)" 2>/dev/null'
