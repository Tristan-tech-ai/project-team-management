<?php
// Imports docs/project_team_management_laravel.sql ONCE into any MySQL/TiDB database without phpMyAdmin.
// Connection from env: DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, and MYSQL_ATTR_SSL_CA (CA file for TLS,
// required for TiDB Cloud; leave empty for local MySQL without TLS). Stops without changing anything when the module tables exist.
// Usage (after php artisan migrate --force in admin-app): php docs/tools/import-sql.php docs/project_team_management_laravel.sql

$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
if (getenv('MYSQL_ATTR_SSL_CA')) {
    $options[PHP_VERSION_ID >= 80500 ? Pdo\Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA] = getenv('MYSQL_ATTR_SSL_CA');
}
$db = new PDO(
    'mysql:host='.getenv('DB_HOST').';port='.(getenv('DB_PORT') ?: '3306').';dbname='.getenv('DB_DATABASE'),
    getenv('DB_USERNAME'),
    getenv('DB_PASSWORD'),
    $options
);
if ($db->query("SHOW TABLES LIKE 'companies'")->fetch()) {
    echo "Tabel modul sudah ada, impor dilewati.\n";
    exit(0);
}
$n = 0;
foreach (preg_split('/;\s*\n/', file_get_contents($argv[1] ?? 'docs/project_team_management_laravel.sql')) as $statement) {
    $clean = trim(preg_replace('/^\s*--.*$/m', '', $statement));
    if ($clean === '') {
        continue;
    }
    try {
        $db->exec($clean);
        $n++;
    } catch (Throwable $e) {
        echo 'Gagal di pernyataan ke-'.($n + 1).': '.substr($e->getMessage(), 0, 300)."\n";
        exit(1);
    }
}
echo "Impor selesai: $n pernyataan, ".count($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN))." tabel.\n";
