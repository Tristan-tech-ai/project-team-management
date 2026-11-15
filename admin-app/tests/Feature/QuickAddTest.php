<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Support\CodeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Domain\BuildsTestData;
use Tests\TestCase;

/** U4 automatic codes and active-by-default, U5 quick add of a client or company from the project form. */
class QuickAddTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    public function test_code_is_readable_and_unique(): void
    {
        $this->assertSame('KREASI-ENERGI-KITA', CodeGenerator::base('PT Kreasi Energi Kita'));
        $this->assertSame('KOPI-SENJA', CodeGenerator::base('Kopi Senja'));
        $this->assertSame('SMA-HARAPAN-BANGSA', CodeGenerator::base('SMA Harapan Bangsa'));
        $this->assertSame('KAFE-ESPRESSO', CodeGenerator::base('Kafé Espresso!'));
        $this->assertSame('DATA', CodeGenerator::base('PT'));
        $this->assertLessThanOrEqual(24, strlen(CodeGenerator::base('Yayasan Pendidikan Nusantara Raya Sejahtera Bersama')));
        $used = ['KOPI-SENJA', 'KOPI-SENJA-2'];
        $this->assertSame('KOPI-SENJA-3', CodeGenerator::unique('Kopi Senja', fn ($c) => in_array($c, $used, true)));
    }

    public function test_empty_code_is_generated_and_new_record_is_active(): void
    {
        $admin = $this->user('super_admin', $this->company());
        $this->actingAs($admin)->post('/master/clients', ['name' => 'Toko Roti Melati', 'code' => ''])->assertRedirect();
        $client = Client::query()->where('name', 'Toko Roti Melati')->firstOrFail();
        $this->assertSame('TOKO-ROTI-MELATI', $client->code);
        $this->assertTrue((bool) $client->is_active, 'a new record is active unless switched off');

        $this->actingAs($admin)->post('/master/clients', ['name' => 'Toko Roti Melati'])->assertRedirect();
        $this->assertSame(['TOKO-ROTI-MELATI', 'TOKO-ROTI-MELATI-2'], Client::query()->where('name', 'Toko Roti Melati')->orderBy('id')->pluck('code')->all());

        $this->actingAs($admin)->post('/master/companies', ['name' => 'CV Sinar Abadi', 'is_active' => '0'])->assertRedirect();
        $company = Company::query()->where('name', 'CV Sinar Abadi')->firstOrFail();
        $this->assertSame('SINAR-ABADI', $company->code);
        $this->assertFalse((bool) $company->is_active, 'switching it off is still respected');
    }

    public function test_super_admin_quick_adds_a_client_as_json(): void
    {
        $admin = $this->user('super_admin', $this->company());
        $this->actingAs($admin)->get('/projects/create')->assertOk()->assertSee('Client baru')->assertSee('Company baru')->assertSee('id="quick-add-modal"', false);

        $this->actingAs($admin)->postJson('/master/clients', ['name' => 'Klinik Gigi Ceria', 'email' => 'halo@klinik.test', 'is_active' => 1])
            ->assertCreated()->assertJson(['name' => 'Klinik Gigi Ceria', 'code' => 'KLINIK-GIGI-CERIA']);
        $this->actingAs($admin)->postJson('/master/clients', ['name' => ''])->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_manager_cannot_quick_add_master_data(): void
    {
        $company = $this->company();
        $manager = $this->user('manager', $company);
        $page = $this->actingAs($manager)->get('/projects/create')->assertOk();
        $page->assertDontSee('Client baru')->assertDontSee('Company baru');
        $this->actingAs($manager)->postJson('/master/clients', ['name' => 'Klien Liar'])->assertForbidden();
        $this->actingAs($manager)->postJson('/master/companies', ['name' => 'Company Liar'])->assertForbidden();
        $this->assertDatabaseMissing('clients', ['name' => 'Klien Liar']);
    }
}
