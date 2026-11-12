<?php
// Impor docs/project_team_management_laravel.sql SATU KALI ke database MySQL/TiDB mana pun tanpa phpMyAdmin.
// Koneksi dari env: DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, dan MYSQL_ATTR_SSL_CA (berkas CA untuk TLS,
// wajib untuk TiDB Cloud; kosongkan untuk MySQL lokal tanpa TLS). Berhenti tanpa mengubah apa pun bila tabel modul sudah ada.
// Pakai (setelah php artisan migrate --force di admin-app): php docs/alat/impor-sql.php docs/project_team_management_laravel.sql

$opsi = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
if (getenv('MYSQL_ATTR_SSL_CA')) {
    $opsi[PHP_VERSION_ID >= 80500 ? Pdo\Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA] = getenv('MYSQL_ATTR_SSL_CA');
}
$db = new PDO(
    'mysql:host='.getenv('DB_HOST').';port='.(getenv('DB_PORT') ?: '3306').';dbname='.getenv('DB_DATABASE'),
    getenv('DB_USERNAME'),
    getenv('DB_PASSWORD'),
    $opsi
);
if ($db->query("SHOW TABLES LIKE 'companies'")->fetch()) {
    echo "Tabel modul sudah ada, impor dilewati.\n";
    exit(0);
}
$n = 0;
foreach (preg_split('/;\s*\n/', file_get_contents($argv[1] ?? 'docs/project_team_management_laravel.sql')) as $pernyataan) {
    $bersih = trim(preg_replace('/^\s*--.*$/m', '', $pernyataan));
    if ($bersih === '') {
        continue;
    }
    try {
        $db->exec($bersih);
        $n++;
    } catch (Throwable $e) {
        echo 'Gagal di pernyataan ke-'.($n + 1).': '.substr($e->getMessage(), 0, 300)."\n";
        exit(1);
    }
}
echo "Impor selesai: $n pernyataan, ".count($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN))." tabel.\n";
