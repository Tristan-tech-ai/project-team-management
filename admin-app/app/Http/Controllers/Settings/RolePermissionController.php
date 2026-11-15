<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionMatrix;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Permissions per role (roles -> role_permissions -> permissions). The Super Admin always has every permission. */
class RolePermissionController extends Controller
{
    public function index(Request $request): View
    {
        $roles = Role::query()->with('permissions:id')->orderBy('id')->get();
        $selected = $roles->firstWhere('slug', $request->query('role')) ?? $roles->firstWhere('slug', 'manager') ?? $roles->first();

        return view('admin.settings.roles', [
            'roles' => $roles,
            'role' => $selected,
            'matrix' => PermissionMatrix::build(Permission::query()->orderBy('id')->get(['id', 'slug', 'name'])),
            'granted' => $selected->slug === 'super_admin' ? Permission::query()->pluck('id')->all() : $selected->permissions->pluck('id')->all(),
            'canEdit' => auth()->user()->can('settings.roles.manage'),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('settings.roles.manage');
        if ($role->slug === 'super_admin') {
            return back()->with('error', 'Super Admin selalu memegang semua izin dan tidak bisa diubah.');
        }
        $data = $request->validate(['permissions' => ['array'], 'permissions.*' => ['integer', Rule::exists('permissions', 'id')]]);
        $ids = array_values(array_unique(array_map('intval', $data['permissions'] ?? [])));
        DB::transaction(function () use ($role, $ids) {
            DB::table('role_permissions')->where('role_id', $role->id)->whereNotIn('permission_id', $ids ?: [0])->delete();
            $exists = DB::table('role_permissions')->where('role_id', $role->id)->pluck('permission_id')->all();
            $new = array_diff($ids, $exists);
            DB::table('role_permissions')->insert(array_map(fn ($p) => ['role_id' => $role->id, 'permission_id' => $p, 'created_at' => now(), 'updated_at' => now()], array_values($new)));
        });

        return redirect()->route('settings.roles.index', ['role' => $role->slug])->with('ok', "Hak akses {$role->name} tersimpan: ".count($ids).' izin.');
    }
}
