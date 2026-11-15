<?php

namespace Tests\Feature;

use App\Http\Controllers\Master\MemberController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Domain\BuildsTestData;
use Tests\TestCase;

/** I12: adding a member without typing a password gives a readable first password, shown once, that works for signing in. */
class MemberFirstPasswordTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    public function test_first_password_is_generated_shown_once_and_works(): void
    {
        $c = $this->company();
        $dev = $this->division($c, 'Development');
        $admin = $this->user('super_admin', $c);
        $response = $this->actingAs($admin)->post('/master/members', [
            'name' => 'Rani Putri', 'email' => 'rani.uji@example.test', 'password' => '', 'role' => 'member', 'is_active' => 1,
            'companies' => [$c->id], 'divisions' => [$dev->id => ['included' => 1]],
        ])->assertRedirect('/master/members');
        $first = $response->getSession()->get('first_password');
        $this->assertSame('rani.uji@example.test', $first['email']);
        $this->assertMatchesRegularExpression('/^[a-z]{4}-\d{3}-[a-z]{4}$/', $first['password']);

        $page = $this->actingAs($admin)->withSession(['first_password' => $first])->get('/master/members')->assertOk();
        $page->assertSee($first['password'])->assertSee('hanya tampil sekali');
        $this->actingAs($admin)->get('/master/members')->assertOk()->assertDontSee($first['password']);

        $this->post('/logout');
        $this->post('/login', ['email' => 'rani.uji@example.test', 'password' => $first['password']])->assertRedirect(route('home'));
    }

    public function test_generated_passwords_differ_and_avoid_look_alike_characters(): void
    {
        $passwords = array_map(fn () => MemberController::firstPassword(), range(1, 20));
        $this->assertCount(20, array_unique($passwords));
        foreach ($passwords as $p) {
            $this->assertDoesNotMatchRegularExpression('/[ilo01]/', $p);
        }
    }
}
