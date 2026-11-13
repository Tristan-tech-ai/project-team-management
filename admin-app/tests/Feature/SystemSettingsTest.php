<?php

namespace Tests\Feature;

use App\Support\UiSettings;
use App\Support\Dates;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Domain\BuildsTestData;
use Tests\TestCase;

/** System Settings: stored in the JSON system_settings column and actually applied (display time zone, table rows per page). */
class SystemSettingsTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    protected function tearDown(): void
    {
        Dates::forget();
        UiSettings::forget();
        parent::tearDown();
    }

    public function test_settings_are_saved_and_applied(): void
    {
        $admin = $this->user('super_admin', $this->company());
        $this->actingAs($admin)->put('/settings/general', ['timezone' => 'Asia/Jakarta', 'table_page_length' => '50'])
            ->assertRedirect()->assertSessionHas('ok');

        $this->assertSame('"Asia/Jakarta"', DB::table('system_settings')->where('setting_key', 'timezone')->value('setting_value'));
        $this->assertSame('Asia/Jakarta', Dates::zone());
        $this->assertSame(50, UiSettings::tablePageLength());
        $this->actingAs($admin)->get('/projects')->assertOk()->assertSee('data-table-page-length="50"', false);
        $this->assertMatchesRegularExpression('#value="Asia/Jakarta"\s+selected#', $this->actingAs($admin)->get('/settings/general')->assertOk()->getContent());

        $this->actingAs($admin)->put('/settings/general', ['timezone' => 'Asia/Makassar', 'table_page_length' => '25'])->assertRedirect();
        $this->assertSame('Asia/Makassar', Dates::zone());
        $this->assertSame(25, UiSettings::tablePageLength());
    }
}
