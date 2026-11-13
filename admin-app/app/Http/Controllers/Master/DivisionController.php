<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\DivisionRequest;
use App\Models\Company;
use App\Models\Division;
use App\Models\User;
use App\Services\Scope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

/** Divisi tidak punya deleted_at di SQL, jadi tidak dihapus: dinonaktifkan supaya riwayat project tetap utuh. */
class DivisionController extends Controller
{
    public function index(): View
    {
        return view('admin.master.index', [
            'judul' => 'Divisi', 'ket' => 'Divisi per company beserta kepala divisinya. Satu orang boleh di beberapa divisi.',
            'data' => route('divisions.data'), 'tambah' => auth()->user()->can('divisions.manage') ? route('divisions.create') : null,
            'kolom' => [['data' => 'name', 'judul' => 'Divisi'], ['data' => 'company', 'judul' => 'Company', 'name' => 'company.name'],
                ['data' => 'lead', 'judul' => 'Kepala divisi', 'name' => 'lead.name'],
                ['data' => 'anggota', 'judul' => 'Anggota', 'searchable' => false, 'orderable' => false, 'kelas' => 'angka'],
                ['data' => 'status', 'judul' => 'Status', 'render' => 'lencana', 'searchable' => false, 'name' => 'is_active'],
                ['data' => 'aksi', 'judul' => '', 'render' => 'aksi', 'searchable' => false, 'orderable' => false]],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $boleh = $request->user()->can('divisions.manage');
        $q = Scope::batasiCompany(Division::query(), $request->user(), 'divisions.company_id')->with(['company:id,name', 'lead:id,name'])
            ->select('divisions.*')->withCount(['memberships as anggota_aktif' => fn ($m) => $m->where('is_active', 1)]);

        return DataTables::eloquent($q)
            ->addColumn('company', fn (Division $d) => $d->company?->name)
            ->addColumn('lead', fn (Division $d) => $d->lead?->name ?? '-')
            ->addColumn('anggota', fn (Division $d) => $d->anggota_aktif)
            ->addColumn('status', fn (Division $d) => $d->is_active ? 'Aktif' : 'Nonaktif')
            ->addColumn('status_kelas', fn (Division $d) => $d->is_active ? 'ok' : 'netral')
            ->addColumn('aksi', fn (Division $d) => $boleh ? [
                ['label' => 'Ubah', 'url' => route('divisions.edit', $d)],
                ['label' => $d->is_active ? 'Nonaktifkan' : 'Aktifkan', 'url' => route('divisions.toggle', $d), 'metode' => 'PATCH',
                    'konfirmasi' => $d->is_active ? "Nonaktifkan divisi {$d->name}? Riwayat project tetap tersimpan." : null],
            ] : [])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('divisions.manage');

        return $this->form(new Division(['is_active' => true]));
    }

    public function store(DivisionRequest $request): RedirectResponse
    {
        Division::create($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('divisions.index')->with('ok', 'Divisi tersimpan.');
    }

    public function edit(Division $division): View
    {
        $this->authorize('divisions.manage');
        abort_unless(Scope::bolehCompany(request()->user(), $division->company_id), 403);

        return $this->form($division);
    }

    public function update(DivisionRequest $request, Division $division): RedirectResponse
    {
        abort_unless(Scope::bolehCompany(request()->user(), $division->company_id), 403);
        $division->update($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('divisions.index')->with('ok', 'Perubahan divisi tersimpan.');
    }

    public function toggle(Division $division): RedirectResponse
    {
        $this->authorize('divisions.manage');
        abort_unless(Scope::bolehCompany(request()->user(), $division->company_id), 403);
        $division->update(['is_active' => ! $division->is_active]);

        return back()->with('ok', $division->is_active ? "Divisi {$division->name} aktif lagi." : "Divisi {$division->name} dinonaktifkan.");
    }

    private function form(Division $division): View
    {
        return view('admin.master.division', [
            'division' => $division,
            'companies' => Scope::batasiCompany(Company::query(), request()->user(), 'companies.id')->where('is_active', 1)->orderBy('name')->pluck('name', 'id'),
            'users' => Scope::anggotaMaster(request()->user())->orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
