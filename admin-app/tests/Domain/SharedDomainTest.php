<?php

namespace Tests\Domain;

use PHPUnit\Framework\TestCase;

/** Technical Guide 9.1: status, progress, permission, and deadline logic is identical in both apps. */
class SharedDomainTest extends TestCase
{
    public function test_admin_and_team_domain_code_is_identical(): void
    {
        require_once dirname(__DIR__, 3).'/docs/tools/sync-domain.php';
        $repo = dirname(__DIR__, 3);
        $admin = fingerprint($repo.'/admin-app');
        $team = fingerprint($repo.'/team-app');
        $this->assertGreaterThan(100, count($admin), 'daftar berkas bersama terlalu sedikit');
        $different = array_keys(array_filter($admin, fn ($h, $f) => ($team[$f] ?? null) !== $h, ARRAY_FILTER_USE_BOTH));
        $this->assertSame([], $different, 'Berkas domain berbeda; jalankan php docs/tools/sync-domain.php');
        $this->assertSame([], array_values(array_diff(array_keys($team), array_keys($admin))));
    }
}
