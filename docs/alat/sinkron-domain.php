<?php

/*
 * Menyalin kode domain bersama dari admin-app ke team-app (Petunjuk Teknis 9.1: logika status, progress,
 * permission, dan perubahan deadline harus identik di kedua aplikasi, dengan tes yang sama).
 * Pakai:  php docs/alat/sinkron-domain.php          (salin)
 *         php docs/alat/sinkron-domain.php --cek    (hanya memeriksa; keluar 1 bila ada yang berbeda)
 * Tes DomainSamaTest di kedua app memanggil daftar yang sama dan gagal bila salinan berbeda.
 */

const BERSAMA = [
    'app/Models', 'app/Policies', 'app/Services', 'app/Support', 'app/Http/Requests/Kerja', 'app/Http/Middleware',
    'app/Http/Controllers/Bersama', 'app/Http/Controllers/Auth', 'app/Http/Controllers/AkunController.php', 'app/Http/Controllers/Controller.php',
    'app/Providers/AppServiceProvider.php', 'lang/id', 'resources/ikon', 'resources/views/bersama', 'resources/views/errors',
    'resources/views/components/ikon.blade.php', 'resources/views/components/lencana.blade.php', 'resources/views/components/isian.blade.php',
    'resources/views/components/konfirmasi.blade.php', 'resources/views/components/layouts/tamu.blade.php', 'resources/views/auth', 'resources/views/akun/isi.blade.php',
    'resources/js/inti.js', 'resources/js/tabel.js', 'resources/js/kanban.js', 'resources/js/kalender.js', 'resources/css/app.css', 'tests/Domain',
];

function berkas(string $akar, string $jalur): array
{
    $penuh = $akar.'/'.$jalur;
    if (is_file($penuh)) {
        return [$jalur];
    }
    if (! is_dir($penuh)) {
        return [];
    }
    $hasil = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($penuh, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $hasil[] = $jalur.'/'.str_replace('\\', '/', substr($f->getPathname(), strlen($penuh) + 1));
    }
    sort($hasil);

    return $hasil;
}

/** @return array<string, string> jalur relatif => sha256 */
function sidik(string $akar): array
{
    $h = [];
    foreach (BERSAMA as $j) {
        foreach (berkas($akar, $j) as $f) {
            $h[$f] = hash_file('sha256', $akar.'/'.$f);
        }
    }

    return $h;
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    $repo = dirname(__DIR__, 2);
    $sumber = $repo.'/admin-app';
    $tujuan = $repo.'/team-app';
    $cek = in_array('--cek', $argv, true);
    $a = sidik($sumber);
    $b = sidik($tujuan);
    $beda = array_keys(array_filter($a, fn ($v, $k) => ($b[$k] ?? null) !== $v, ARRAY_FILTER_USE_BOTH));
    $lebih = array_diff(array_keys($b), array_keys($a));
    if ($cek) {
        foreach ($beda as $f) {
            echo "BEDA  {$f}\n";
        }
        foreach ($lebih as $f) {
            echo "LEBIH {$f} (ada di team-app saja)\n";
        }
        echo ($beda || $lebih) ? "domain TIDAK sama\n" : 'domain sama: '.count($a)." berkas\n";
        exit(($beda || $lebih) ? 1 : 0);
    }
    foreach ($beda as $f) {
        @mkdir(dirname($tujuan.'/'.$f), 0777, true);
        copy($sumber.'/'.$f, $tujuan.'/'.$f);
        echo "salin {$f}\n";
    }
    foreach ($lebih as $f) {
        // Berkas bersama yang sudah tidak ada di admin-app ikut dihapus dari team-app supaya salinan identik.
        unlink($tujuan.'/'.$f);
        echo "hapus {$f}\n";
    }
    echo count($beda).' disalin, '.count($lebih)." dihapus, total bersama ".count($a)."\n";
}
