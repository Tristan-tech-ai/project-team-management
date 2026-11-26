<?php

namespace Tests\Feature;

use App\Models\Client;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Domain\BuildsTestData;
use Tests\TestCase;

/**
 * Clients per company (table client_company, PKL supervisor 26 Nov 2026): a client can be linked to the companies that serve it;
 * the project form then lists only the chosen company's clients plus the general ones (no company), and the server refuses a
 * client of another company.
 */
class ClientCompanyTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    public function test_super_admin_links_a_client_to_companies(): void
    {
        $a = $this->company('Kreasi Bali');
        $b = $this->company('Kreasi Jakarta');
        $admin = $this->user('super_admin', $a);
        $this->actingAs($admin)->get('/master/clients/create')->assertOk()->assertSee('Company yang melayani client ini')
            ->assertSee('name="companies[]"', false)->assertSee('Kreasi Jakarta');

        $this->actingAs($admin)->post('/master/clients', ['name' => 'Toko Roti Nusa', 'companies' => [$a->id]])->assertRedirect()->assertSessionHasNoErrors();
        $client = Client::query()->where('name', 'Toko Roti Nusa')->firstOrFail();
        $this->assertSame([$a->id], $client->companies()->pluck('companies.id')->map(fn ($id) => (int) $id)->all());
        $this->assertTrue($client->isAvailableFor($a->id));
        $this->assertFalse($client->isAvailableFor($b->id));

        $this->actingAs($admin)->put("/master/clients/{$client->id}", ['name' => 'Toko Roti Nusa', 'code' => $client->code, 'companies' => [$a->id, $b->id], 'is_active' => 1])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, $client->companies()->count());

        // Unticking every company makes it general again: available to every company.
        $this->actingAs($admin)->put("/master/clients/{$client->id}", ['name' => 'Toko Roti Nusa', 'code' => $client->code, 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertSame(0, $client->companies()->count());
        $this->assertTrue($client->fresh()->isAvailableFor($b->id));

        $rows = $this->actingAs($admin)->getJson('/master/clients/data?draw=1&start=0&length=50')->assertOk()->json('data');
        $this->assertSame('Umum (semua company)', collect($rows)->firstWhere('name', 'Toko Roti Nusa')['company']);
    }

    public function test_project_form_lists_clients_per_company_and_server_refuses_another_companys_client(): void
    {
        $a = $this->company('Kreasi Bali');
        $b = $this->company('Kreasi Jakarta');
        $admin = $this->user('super_admin', $a);
        $onlyA = Client::query()->create(['code' => 'ROTI-A', 'name' => 'Roti Bali', 'is_active' => 1]);
        $onlyA->companies()->sync([$a->id]);
        $general = Client::query()->create(['code' => 'UMUM-X', 'name' => 'Klien Umum', 'is_active' => 1]);

        $html = $this->actingAs($admin)->get('/projects/create')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<option value="'.$onlyA->id.'" data-companies="'.$a->id.'"/', $html, 'the option says which company it belongs to');
        $this->assertMatchesRegularExpression('/<option value="'.$general->id.'" data-companies=""/', $html, 'a general client fits every company');
        $this->assertStringContainsString('data-link-company="#f-company_id"', $html, 'a quick-added client belongs to the chosen company');

        $base = ['name' => 'Website Baru', 'project_type' => 'one_time', 'pic_user_id' => $admin->id, 'status' => 'active', 'priority' => 'normal'];
        $this->actingAs($admin)->post('/projects', $base + ['company_id' => $b->id, 'client_id' => $onlyA->id])->assertSessionHasErrors('client_id');
        $this->actingAs($admin)->post('/projects', $base + ['company_id' => $a->id, 'client_id' => $onlyA->id])->assertSessionDoesntHaveErrors('client_id');
        $this->actingAs($admin)->post('/projects', $base + ['company_id' => $b->id, 'client_id' => $general->id])->assertSessionDoesntHaveErrors('client_id');
    }

    public function test_quick_add_from_the_project_form_links_the_chosen_company(): void
    {
        $a = $this->company('Kreasi Bali');
        $admin = $this->user('super_admin', $a);
        $id = $this->actingAs($admin)->postJson('/master/clients', ['name' => 'Kopi Pagi', 'is_active' => 1, 'companies' => [$a->id]])->assertCreated()->json('id');
        $this->assertSame([$a->id], Client::query()->findOrFail($id)->companies()->pluck('companies.id')->map(fn ($v) => (int) $v)->all());
    }
}
