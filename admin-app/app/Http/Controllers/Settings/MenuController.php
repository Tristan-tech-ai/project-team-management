<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Menu Management: urutan, ikon, nama, aktif/nonaktif, permission (Petunjuk Teknis 5.1).
 * route_name sengaja tidak bisa diubah dari layar (dokumen 01: editor menu tidak mengubah route PHP; 4.2: route harus terdaftar).
 */
class MenuController extends Controller
{
    public function index(): View
    {
        $semua = MenuItem::query()->with('permission:id,slug,name')->orderBy('sort_order')->orderBy('id')->get();

        return view('admin.settings.menus', [
            'induk' => $semua->whereNull('parent_id')->values(),
            'anak' => $semua->whereNotNull('parent_id')->groupBy('parent_id'),
            'permissions' => Permission::query()->orderBy('module')->orderBy('id')->get(['id', 'slug', 'name']),
            'ikon' => self::ikonTersedia(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'menu' => ['required', 'array'],
            'menu.*.title' => ['required', 'string', 'max:150'],
            'menu.*.icon' => ['nullable', Rule::in(self::ikonTersedia())],
            'menu.*.sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'menu.*.is_active' => ['boolean'],
            'menu.*.permission_id' => ['nullable', 'integer', 'exists:permissions,id'],
        ]);
        DB::transaction(function () use ($data) {
            foreach ($data['menu'] as $id => $isi) {
                $m = MenuItem::query()->find((int) $id);
                if (! $m) {
                    continue;
                }
                $ubah = ['title' => $isi['title'], 'sort_order' => (int) $isi['sort_order'], 'is_active' => (bool) ($isi['is_active'] ?? false)];
                if ($m->parent_id === null) {
                    $ubah['icon'] = $isi['icon'] ?? $m->icon;
                } else {
                    $ubah['permission_id'] = $isi['permission_id'] ?? null;
                }
                $m->update($ubah);
            }
        });

        return back()->with('ok', 'Menu tersimpan. Menu tetap hanya navigasi; hak akses diperiksa di server.');
    }

    /** Ikon yang tersedia sebagai berkas SVG lokal (Lucide, ISC). */
    public static function ikonTersedia(): array
    {
        return collect(glob(resource_path('ikon/*.svg')) ?: [])->map(fn ($f) => basename($f, '.svg'))->sort()->values()->all();
    }
}
