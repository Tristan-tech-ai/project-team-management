<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\MemberRequest;
use App\Models\Company;
use App\Models\Division;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use App\Services\Scope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

/** Team members: account (users), profile, role, company and division memberships + position. Members are deactivated, never deleted. */
class MemberController extends Controller
{
    public function index(): View
    {
        return view('admin.master.index', [
            'title' => 'Anggota Tim', 'description' => 'Akun yang bisa masuk ke aplikasi Admin dan Tim, dengan role, company, dan divisi.',
            'data' => route('members.data'), 'add' => auth()->user()->can('members.manage') ? route('members.create') : null,
            'columns' => [['data' => 'name', 'title' => 'Nama'], ['data' => 'email', 'title' => 'Email'],
                ['data' => 'role', 'title' => 'Role', 'searchable' => false, 'orderable' => false],
                ['data' => 'divisions', 'title' => 'Divisi', 'searchable' => false, 'orderable' => false],
                ['data' => 'status', 'title' => 'Status', 'render' => 'badge', 'searchable' => false, 'orderable' => false],
                ['data' => 'actions', 'title' => '', 'render' => 'actions', 'searchable' => false, 'orderable' => false]],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $can = $request->user()->can('members.manage');
        $q = Scope::masterMembers($request->user())->with(['roles:id,name', 'profile:id,user_id,is_active', 'divisionMemberships' => fn ($m) => $m->where('is_active', 1)->with('division:id,name')])
            ->select('users.id', 'users.name', 'users.email');

        return DataTables::eloquent($q)
            ->addColumn('role', fn (User $u) => $u->roles->pluck('name')->implode(', ') ?: '-')
            ->addColumn('divisions', fn (User $u) => $u->divisionMemberships->pluck('division.name')->filter()->implode(', ') ?: '-')
            ->addColumn('status', fn (User $u) => $u->profile?->is_active ? 'Aktif' : 'Nonaktif')
            ->addColumn('status_class', fn (User $u) => $u->profile?->is_active ? 'ok' : 'neutral')
            ->addColumn('actions', fn (User $u) => $can ? [['label' => 'Ubah', 'url' => route('members.edit', $u)]] : [])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('members.manage');

        return $this->form(new User);
    }

    public function store(MemberRequest $request): RedirectResponse
    {
        // I12: an empty password field gets a generated first password, shown once to the admin to pass on.
        $generated = $request->filled('password') ? null : self::firstPassword();
        $user = DB::transaction(function () use ($request, $generated) {
            $user = User::create(['name' => $request->input('name'), 'email' => $request->input('email'), 'password' => $generated ?? $request->input('password')]);
            $this->saveMemberships($user, $request);

            return $user;
        });
        $redirect = redirect()->route('members.index')->with('ok', "{$user->name} ditambahkan sebagai anggota tim.");

        return $generated ? $redirect->with('first_password', ['name' => $user->name, 'email' => $user->email, 'password' => $generated]) : $redirect;
    }

    public function edit(User $member): View
    {
        $this->authorize('members.manage');
        abort_unless(Scope::masterMembers(request()->user())->whereKey($member->id)->exists(), 403);

        return $this->form($member);
    }

    public function update(MemberRequest $request, User $member): RedirectResponse
    {
        abort_unless(Scope::masterMembers(request()->user())->whereKey($member->id)->exists(), 403);
        DB::transaction(function () use ($request, $member) {
            $attributes = ['name' => $request->input('name'), 'email' => $request->input('email')];
            if ($request->filled('password')) {
                $attributes['password'] = $request->input('password');
            }
            $member->update($attributes);
            $this->saveMemberships($member, $request);
        });

        return redirect()->route('members.index')->with('ok', "Perubahan {$member->name} tersimpan.");
    }

    private function saveMemberships(User $user, MemberRequest $request): void
    {
        $now = now();
        DB::table('user_profiles')->updateOrInsert(['user_id' => $user->id],
            ['phone' => $request->input('phone'), 'is_active' => $request->boolean('is_active') ? 1 : 0, 'updated_at' => $now, 'created_at' => $now]);

        // One role per member in the first version; the role -> permission mapping still comes from the source SQL.
        $roleId = Role::query()->where('slug', $request->input('role'))->value('id');
        DB::table('user_roles')->where('user_id', $user->id)->where('role_id', '!=', $roleId)->delete();
        DB::table('user_roles')->updateOrInsert(['user_id' => $user->id, 'role_id' => $roleId], ['updated_at' => $now, 'created_at' => $now]);

        // Company memberships that are not selected are deactivated, not deleted (project history stays readable).
        $companies = array_map('intval', (array) $request->input('companies', []));
        DB::table('company_members')->where('user_id', $user->id)->whereNotIn('company_id', $companies ?: [0])->update(['is_active' => 0, 'updated_at' => $now]);
        foreach ($companies as $cid) {
            $exists = DB::table('company_members')->where('user_id', $user->id)->where('company_id', $cid)->exists();
            DB::table('company_members')->updateOrInsert(['company_id' => $cid, 'user_id' => $user->id],
                ['is_active' => 1, 'updated_at' => $now] + ($exists ? [] : ['joined_on' => $now->toDateString(), 'created_at' => $now]));
        }

        $selected = [];
        foreach ((array) $request->input('divisions', []) as $divisionId => $row) {
            if (! empty($row['included'])) {
                $selected[(int) $divisionId] = ! empty($row['position_id']) ? (int) $row['position_id'] : null;
            }
        }
        DB::table('division_members')->where('user_id', $user->id)->whereNotIn('division_id', array_keys($selected) ?: [0])->update(['is_active' => 0, 'updated_at' => $now]);
        foreach ($selected as $divisionId => $positionId) {
            $exists = DB::table('division_members')->where('user_id', $user->id)->where('division_id', $divisionId)->exists();
            DB::table('division_members')->updateOrInsert(['division_id' => $divisionId, 'user_id' => $user->id],
                ['position_id' => $positionId, 'is_active' => 1, 'updated_at' => $now] + ($exists ? [] : ['joined_on' => $now->toDateString(), 'created_at' => $now]));
        }
        $user->forgetPermissions();
    }

    private function form(User $member): View
    {
        $member->loadMissing(['profile', 'roles']);

        return view('admin.master.member', [
            'member' => $member,
            // Apart from the Super Admin: the Super Admin role cannot be granted, and company/division/position options stay within scope.
            'roles' => Role::query()->when(! request()->user()->isSuperAdmin(), fn ($q) => $q->where('slug', '!=', 'super_admin'))->orderBy('id')->pluck('name', 'slug'),
            'companies' => Scope::limitToCompanies(Company::query(), request()->user(), 'companies.id')->where('is_active', 1)->orderBy('name')->get(['id', 'name']),
            'divisions' => Scope::limitToCompanies(Division::query(), request()->user())->with('company:id,name')->where('is_active', 1)->orderBy('company_id')->orderBy('name')->get(),
            'positions' => Scope::limitToCompanies(Position::query(), request()->user())->where('is_active', 1)->orderBy('name')->get(['id', 'name', 'company_id']),
            'currentCompanies' => $member->exists ? DB::table('company_members')->where('user_id', $member->id)->where('is_active', 1)->pluck('company_id')->all() : [],
            'currentDivisions' => $member->exists ? DB::table('division_members')->where('user_id', $member->id)->where('is_active', 1)->pluck('position_id', 'division_id')->all() : [],
        ]);
    }

    /** Readable first password: letters and digits only, no look-alike characters, 12 characters (MemberRequest minimum). */
    public static function firstPassword(): string
    {
        $letters = 'abcdefghjkmnpqrstuvwxyz';
        $digits = '23456789';
        $pick = fn (string $set, int $n) => implode('', array_map(fn () => $set[random_int(0, strlen($set) - 1)], range(1, $n)));

        return $pick($letters, 4).'-'.$pick($digits, 3).'-'.$pick($letters, 4);
    }
}
