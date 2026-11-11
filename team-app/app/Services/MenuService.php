<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * Menu dinamis dari tabel menu_items (Petunjuk Teknis 4.2): hanya navigasi, bukan pengganti keamanan backend.
 * Submenu tampil bila aktif, route_name terdaftar di Laravel, dan pengguna punya permission-nya;
 * menu induk tampil bila setidaknya satu submenunya tampil.
 */
class MenuService
{
    /** @return array<int, array{slug:string,title:string,icon:?string,children:array<int,array{slug:string,title:string,route:string,url:string}>}> */
    public function untuk(User $user): array
    {
        $semua = MenuItem::query()->with('permission:id,slug')->where('is_active', 1)
            ->orderBy('sort_order')->orderBy('id')->get();
        $gate = Gate::forUser($user);
        $hasil = [];
        foreach ($semua->whereNull('parent_id') as $induk) {
            $anak = [];
            foreach ($semua->where('parent_id', $induk->id) as $m) {
                if (! $m->route_name || ! Route::has($m->route_name)) {
                    continue;
                }
                if ($m->permission && ! $gate->allows($m->permission->slug)) {
                    continue;
                }
                $anak[] = ['slug' => $m->slug, 'title' => $m->title, 'route' => $m->route_name, 'url' => route($m->route_name)];
            }
            if ($anak !== []) {
                $hasil[] = ['slug' => $induk->slug, 'title' => $induk->title, 'icon' => $induk->icon, 'children' => $anak];
            }
        }

        return $hasil;
    }
}
