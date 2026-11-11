<?php

namespace Tests\Domain;

use PHPUnit\Framework\TestCase;

/** Petunjuk Teknis 9.1: logika status, progress, permission, dan deadline identik di kedua aplikasi. */
class DomainSamaTest extends TestCase
{
    public function test_kode_domain_admin_dan_tim_identik(): void
    {
        require_once dirname(__DIR__, 3).'/docs/alat/sinkron-domain.php';
        $repo = dirname(__DIR__, 3);
        $admin = sidik($repo.'/admin-app');
        $tim = sidik($repo.'/team-app');
        $this->assertGreaterThan(100, count($admin), 'daftar berkas bersama terlalu sedikit');
        $beda = array_keys(array_filter($admin, fn ($h, $f) => ($tim[$f] ?? null) !== $h, ARRAY_FILTER_USE_BOTH));
        $this->assertSame([], $beda, 'Berkas domain berbeda; jalankan php docs/alat/sinkron-domain.php');
        $this->assertSame([], array_values(array_diff(array_keys($tim), array_keys($admin))));
    }
}
