<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Hak akses per role (roles -> role_permissions -> permissions). Super Admin selalu punya semua izin. */
class RolePermissionController extends Controller
{
    public function index(): View
    {
        return view('admin.settings.roles', [
            'roles' => Role::query()->with('permissions:id')->orderBy('id')->get(),
            'modul' => Permission::query()->orderBy('id')->get()->groupBy('module'),
            'bolehUbah' => auth()->user()->can('settings.roles.manage'),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('settings.roles.manage');
        if ($role->slug === 'super_admin') {
            return back()->with('galat', 'Super Admin selalu memegang semua izin dan tidak bisa diubah.');
        }
        $data = $request->validate(['permissions' => ['array'], 'permissions.*' => ['integer', Rule::exists('permissions', 'id')]]);
        $ids = array_values(array_unique(array_map('intval', $data['permissions'] ?? [])));
        DB::transaction(function () use ($role, $ids) {
            DB::table('role_permissions')->where('role_id', $role->id)->whereNotIn('permission_id', $ids ?: [0])->delete();
            $ada = DB::table('role_permissions')->where('role_id', $role->id)->pluck('permission_id')->all();
            $baru = array_diff($ids, $ada);
            DB::table('role_permissions')->insert(array_map(fn ($p) => ['role_id' => $role->id, 'permission_id' => $p, 'created_at' => now(), 'updated_at' => now()], array_values($baru)));
        });

        return back()->with('ok', "Hak akses {$role->name} tersimpan: ".count($ids).' izin.');
    }
}
