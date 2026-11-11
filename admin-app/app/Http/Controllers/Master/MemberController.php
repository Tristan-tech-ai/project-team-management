<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\MemberRequest;
use App\Models\Company;
use App\Models\Division;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

/** Anggota tim: akun (users), profil, role, keanggotaan company dan divisi + jabatan. Anggota tidak dihapus, dinonaktifkan. */
class MemberController extends Controller
{
    public function index(): View
    {
        return view('admin.master.index', [
            'judul' => 'Anggota Tim', 'ket' => 'Akun yang bisa masuk ke aplikasi Admin dan Tim, dengan role, company, dan divisi.',
            'data' => route('members.data'), 'tambah' => auth()->user()->can('members.manage') ? route('members.create') : null,
            'kolom' => [['data' => 'name', 'judul' => 'Nama'], ['data' => 'email', 'judul' => 'Email'],
                ['data' => 'role', 'judul' => 'Role', 'searchable' => false, 'orderable' => false],
                ['data' => 'divisi', 'judul' => 'Divisi', 'searchable' => false, 'orderable' => false],
                ['data' => 'status', 'judul' => 'Status', 'render' => 'lencana', 'searchable' => false, 'orderable' => false],
                ['data' => 'aksi', 'judul' => '', 'render' => 'aksi', 'searchable' => false, 'orderable' => false]],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $boleh = $request->user()->can('members.manage');
        $q = User::query()->with(['roles:id,name', 'profile:id,user_id,is_active', 'divisionMemberships' => fn ($m) => $m->where('is_active', 1)->with('division:id,name')])
            ->select('users.id', 'users.name', 'users.email');

        return DataTables::eloquent($q)
            ->addColumn('role', fn (User $u) => $u->roles->pluck('name')->implode(', ') ?: '-')
            ->addColumn('divisi', fn (User $u) => $u->divisionMemberships->pluck('division.name')->filter()->implode(', ') ?: '-')
            ->addColumn('status', fn (User $u) => $u->profile?->is_active ? 'Aktif' : 'Nonaktif')
            ->addColumn('status_kelas', fn (User $u) => $u->profile?->is_active ? 'ok' : 'netral')
            ->addColumn('aksi', fn (User $u) => $boleh ? [['label' => 'Ubah', 'url' => route('members.edit', $u)]] : [])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('members.manage');

        return $this->form(new User);
    }

    public function store(MemberRequest $request): RedirectResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create(['name' => $request->input('name'), 'email' => $request->input('email'), 'password' => $request->input('password')]);
            $this->simpanKeanggotaan($user, $request);

            return $user;
        });

        return redirect()->route('members.index')->with('ok', "{$user->name} ditambahkan sebagai anggota tim.");
    }

    public function edit(User $member): View
    {
        $this->authorize('members.manage');

        return $this->form($member);
    }

    public function update(MemberRequest $request, User $member): RedirectResponse
    {
        DB::transaction(function () use ($request, $member) {
            $isi = ['name' => $request->input('name'), 'email' => $request->input('email')];
            if ($request->filled('password')) {
                $isi['password'] = $request->input('password');
            }
            $member->update($isi);
            $this->simpanKeanggotaan($member, $request);
        });

        return redirect()->route('members.index')->with('ok', "Perubahan {$member->name} tersimpan.");
    }

    private function simpanKeanggotaan(User $user, MemberRequest $request): void
    {
        $sekarang = now();
        DB::table('user_profiles')->updateOrInsert(['user_id' => $user->id],
            ['phone' => $request->input('phone'), 'is_active' => $request->boolean('is_active') ? 1 : 0, 'updated_at' => $sekarang, 'created_at' => $sekarang]);

        // Satu role per anggota di versi pertama; pemetaan role -> permission tetap dari SQL sumber.
        $roleId = Role::query()->where('slug', $request->input('role'))->value('id');
        DB::table('user_roles')->where('user_id', $user->id)->where('role_id', '!=', $roleId)->delete();
        DB::table('user_roles')->updateOrInsert(['user_id' => $user->id, 'role_id' => $roleId], ['updated_at' => $sekarang, 'created_at' => $sekarang]);

        // Keanggotaan company: yang tidak dipilih dinonaktifkan, bukan dihapus (riwayat project tetap terbaca).
        $companies = array_map('intval', (array) $request->input('companies', []));
        DB::table('company_members')->where('user_id', $user->id)->whereNotIn('company_id', $companies ?: [0])->update(['is_active' => 0, 'updated_at' => $sekarang]);
        foreach ($companies as $cid) {
            $ada = DB::table('company_members')->where('user_id', $user->id)->where('company_id', $cid)->exists();
            DB::table('company_members')->updateOrInsert(['company_id' => $cid, 'user_id' => $user->id],
                ['is_active' => 1, 'updated_at' => $sekarang] + ($ada ? [] : ['joined_on' => $sekarang->toDateString(), 'created_at' => $sekarang]));
        }

        $dipilih = [];
        foreach ((array) $request->input('divisi', []) as $divisionId => $isi) {
            if (! empty($isi['ikut'])) {
                $dipilih[(int) $divisionId] = ! empty($isi['position_id']) ? (int) $isi['position_id'] : null;
            }
        }
        DB::table('division_members')->where('user_id', $user->id)->whereNotIn('division_id', array_keys($dipilih) ?: [0])->update(['is_active' => 0, 'updated_at' => $sekarang]);
        foreach ($dipilih as $divisionId => $positionId) {
            $ada = DB::table('division_members')->where('user_id', $user->id)->where('division_id', $divisionId)->exists();
            DB::table('division_members')->updateOrInsert(['division_id' => $divisionId, 'user_id' => $user->id],
                ['position_id' => $positionId, 'is_active' => 1, 'updated_at' => $sekarang] + ($ada ? [] : ['joined_on' => $sekarang->toDateString(), 'created_at' => $sekarang]));
        }
        $user->lupakanIzin();
    }

    private function form(User $member): View
    {
        $member->loadMissing(['profile', 'roles']);

        return view('admin.master.member', [
            'member' => $member,
            'roles' => Role::query()->orderBy('id')->pluck('name', 'slug'),
            'companies' => Company::query()->where('is_active', 1)->orderBy('name')->get(['id', 'name']),
            'divisions' => Division::query()->with('company:id,name')->where('is_active', 1)->orderBy('company_id')->orderBy('name')->get(),
            'positions' => Position::query()->where('is_active', 1)->orderBy('name')->get(['id', 'name', 'company_id']),
            'companyIni' => $member->exists ? DB::table('company_members')->where('user_id', $member->id)->where('is_active', 1)->pluck('company_id')->all() : [],
            'divisiIni' => $member->exists ? DB::table('division_members')->where('user_id', $member->id)->where('is_active', 1)->pluck('position_id', 'division_id')->all() : [],
        ]);
    }
}
