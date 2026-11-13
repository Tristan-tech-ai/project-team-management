<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ProjectTemplate;
use App\Models\SystemSetting;
use App\Support\UiSettings;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** System Settings: display time zone, default template, UI parameters (system_settings). */
class GeneralSettingController extends Controller
{
    public const KEYS = [
        'timezone' => 'Zona waktu tampilan tanggal dan jam',
        'default_project_template_id' => 'Template project yang terpilih lebih dulu saat membuat project',
        'table_page_length' => 'Jumlah baris per halaman tabel',
    ];

    public function edit(): View
    {
        return view('admin.settings.general', [
            'values' => SystemSetting::query()->whereIn('setting_key', array_keys(self::KEYS))->pluck('setting_value', 'setting_key'),
            'zone' => ['Asia/Jakarta' => 'WIB (Asia/Jakarta)', 'Asia/Makassar' => 'WITA (Asia/Makassar)', 'Asia/Jayapura' => 'WIT (Asia/Jayapura)', 'UTC' => 'UTC'],
            'templates' => ProjectTemplate::query()->where('is_active', 1)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'timezone' => ['required', Rule::in(['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura', 'UTC'])],
            'default_project_template_id' => ['nullable', 'integer', Rule::exists('project_templates', 'id')->whereNull('deleted_at')],
            'table_page_length' => ['required', Rule::in(['10', '25', '50', '100'])],
        ]);
        foreach (self::KEYS as $key => $description) {
            SystemSetting::query()->updateOrCreate(['setting_key' => $key],
                ['setting_value' => isset($data[$key]) ? (string) $data[$key] : null, 'description' => $description, 'updated_by' => $request->user()->id]);
        }
        Dates::forget();
        UiSettings::forget();

        return back()->with('ok', 'Pengaturan sistem tersimpan.');
    }
}
