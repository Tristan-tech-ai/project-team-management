<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\User;
use App\Support\MenuIcons;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * Dynamic menu from the menu_items table (Technical Guide 4.2): navigation only, never a replacement for backend security.
 * A submenu shows when it is active, its route_name is registered in Laravel, and the user has its permission;
 * a parent menu shows when at least one of its submenus shows.
 */
class MenuService
{
    /** @return array<int, array{slug:string,title:string,icon:?string,children:array<int,array{slug:string,title:string,route:string,url:string}>}> */
    public function for(User $user): array
    {
        $all = MenuItem::query()->with('permission:id,slug')->where('is_active', 1)
            ->orderBy('sort_order')->orderBy('id')->get();
        $gate = Gate::forUser($user);
        $result = [];
        foreach ($all->whereNull('parent_id') as $parent) {
            $children = [];
            foreach ($all->where('parent_id', $parent->id) as $m) {
                if (! $m->route_name || ! Route::has($m->route_name)) {
                    continue;
                }
                if ($m->permission && ! $gate->allows($m->permission->slug)) {
                    continue;
                }
                $children[] = ['slug' => $m->slug, 'title' => $m->title, 'route' => $m->route_name, 'url' => route($m->route_name),
                    'icon' => $m->icon ?: MenuIcons::forRoute($m->route_name, $parent->icon)];
            }
            if ($children !== []) {
                $result[] = ['slug' => $parent->slug, 'title' => $parent->title, 'icon' => $parent->icon, 'children' => $children];
            }
        }

        return $result;
    }

    /**
     * The submenu route to highlight for the current page: the exact route, otherwise the first submenu with the same route prefix
     * (projects.show -> projects.index, templates.projects.edit -> templates.projects.index, settings.audit -> settings.general).
     */
    public static function activeRoute(array $menu, string $current): ?string
    {
        $routes = collect($menu)->pluck('children')->flatten(1)->pluck('route');
        if ($routes->contains($current)) {
            return $current;
        }
        $prefix = fn (string $r) => str_contains($r, '.') ? substr($r, 0, strrpos($r, '.')) : $r;

        return $routes->first(fn ($r) => $prefix($r) === $prefix($current));
    }
}
