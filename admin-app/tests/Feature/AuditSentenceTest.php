<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Support\AuditSentence;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Domain\BuildsTestData;
use Tests\TestCase;

/** I10: the activity log reads as sentences, with the technical record only as detail. */
class AuditSentenceTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    private function row(string $route, array $input = [], int $status = 302, string $method = 'POST'): AuditLog
    {
        return new AuditLog(['route' => $route, 'method' => $method, 'path' => '/x', 'input' => $input, 'status' => $status, 'app' => 'admin']);
    }

    public function test_actions_read_as_sentences(): void
    {
        $this->assertSame('menambah task Desain feed', AuditSentence::describe($this->row('projects.tasks.store', ['title' => 'Desain feed'])));
        $this->assertSame('memindah task ke Dikerjakan', AuditSentence::describe($this->row('tasks.status', ['status' => 'in_progress'], 200, 'PATCH')));
        $this->assertSame('menerima hasil task', AuditSentence::describe($this->row('submissions.review', ['decision' => 'approved'])));
        $this->assertSame('meminta revisi hasil task', AuditSentence::describe($this->row('submissions.review', ['decision' => 'revision'])));
        $this->assertSame('mencatat waktu kerja 30 menit', AuditSentence::describe($this->row('tasks.time.store', ['minutes_spent' => 30])));
        $this->assertSame('masuk', AuditSentence::describe($this->row('login')));
        // Regression: the real sign-in request has no route name and was logged as "menyimpan data di login".
        $this->assertSame('masuk', AuditSentence::describe(new AuditLog(['route' => null, 'method' => 'POST', 'path' => '/login', 'input' => [], 'status' => 302])));
        $this->assertSame('menyimpan data di something/new', AuditSentence::describe(new AuditLog(['route' => null, 'method' => 'POST', 'path' => '/something/new', 'input' => [], 'status' => 302])));
        $this->assertSame(['Ditolak: tanpa izin', 'late'], AuditSentence::outcome(403));
        $this->assertSame(['Berhasil', 'ok'], AuditSentence::outcome(302));
    }

    public function test_log_page_shows_sentences(): void
    {
        $admin = $this->user('super_admin', $this->company());
        AuditLog::create(['user_id' => $admin->id, 'role' => 'super_admin', 'app' => 'admin', 'method' => 'POST', 'route' => 'clients.store', 'path' => '/master/clients',
            'input' => ['name' => 'Klinik Gigi Ceria'], 'status' => 302, 'ip' => '127.0.0.1']);
        $this->actingAs($admin)->get('/settings/audit')->assertOk()->assertSee('menambah client Klinik Gigi Ceria')->assertSee('Berhasil')->assertSee('Yang terjadi');
    }
}
