<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ProjectTemplate;
use App\Models\SystemSetting;
use App\Support\Pengaturan;
use App\Support\Waktu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Pengaturan Sistem: zona waktu tampilan, template bawaan, parameter UI (system_settings). */
class GeneralSettingController extends Controller
{
    public const KUNCI = [
        'timezone' => 'Zona waktu tampilan tanggal dan jam',
        'default_project_template_id' => 'Template project yang terpilih lebih dulu saat membuat project',
        'table_page_length' => 'Jumlah baris per halaman tabel',
    ];

    public function edit(): View
    {
        return view('admin.settings.general', [
            'nilai' => SystemSetting::query()->whereIn('setting_key', array_keys(self::KUNCI))->pluck('setting_value', 'setting_key'),
            'zona' => ['Asia/Jakarta' => 'WIB (Asia/Jakarta)', 'Asia/Makassar' => 'WITA (Asia/Makassar)', 'Asia/Jayapura' => 'WIT (Asia/Jayapura)', 'UTC' => 'UTC'],
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
        foreach (self::KUNCI as $kunci => $ket) {
            SystemSetting::query()->updateOrCreate(['setting_key' => $kunci],
                ['setting_value' => isset($data[$kunci]) ? (string) $data[$kunci] : null, 'description' => $ket, 'updated_by' => $request->user()->id]);
        }
        Waktu::lupakan();
        Pengaturan::lupakan();

        return back()->with('ok', 'Pengaturan sistem tersimpan.');
    }
}
