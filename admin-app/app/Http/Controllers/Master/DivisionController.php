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

/** Divisions have no deleted_at in the SQL, so they are not deleted: they are deactivated so project history stays intact. */
class DivisionController extends Controller
{
    public function index(): View
    {
        return view('admin.master.index', [
            'title' => 'Divisi', 'description' => 'Divisi per company beserta kepala divisinya. Satu orang boleh di beberapa divisi.',
            'data' => route('divisions.data'), 'add' => auth()->user()->can('divisions.manage') ? route('divisions.create') : null,
            'columns' => [['data' => 'name', 'title' => 'Divisi'], ['data' => 'company', 'title' => 'Company', 'name' => 'company.name'],
                ['data' => 'lead', 'title' => 'Kepala divisi', 'name' => 'lead.name'],
                ['data' => 'members', 'title' => 'Anggota', 'searchable' => false, 'orderable' => false, 'cssClass' => 'numeric'],
                ['data' => 'status', 'title' => 'Status', 'render' => 'badge', 'searchable' => false, 'name' => 'is_active'],
                ['data' => 'actions', 'title' => '', 'render' => 'actions', 'searchable' => false, 'orderable' => false]],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $can = $request->user()->can('divisions.manage');
        $q = Scope::limitToCompanies(Division::query(), $request->user(), 'divisions.company_id')->with(['company:id,name', 'lead:id,name'])
            ->select('divisions.*')->withCount(['memberships as active_members' => fn ($m) => $m->where('is_active', 1)]);

        return DataTables::eloquent($q)
            ->addColumn('company', fn (Division $d) => $d->company?->name)
            ->addColumn('lead', fn (Division $d) => $d->lead?->name ?? '-')
            ->addColumn('members', fn (Division $d) => $d->active_members)
            ->addColumn('status', fn (Division $d) => $d->is_active ? 'Aktif' : 'Nonaktif')
            ->addColumn('status_class', fn (Division $d) => $d->is_active ? 'ok' : 'neutral')
            ->addColumn('actions', fn (Division $d) => $can ? [
                ['label' => 'Ubah', 'url' => route('divisions.edit', $d)],
                ['label' => $d->is_active ? 'Nonaktifkan' : 'Aktifkan', 'url' => route('divisions.toggle', $d), 'method' => 'PATCH',
                    'confirmation' => $d->is_active ? "Nonaktifkan divisi {$d->name}? Riwayat project tetap tersimpan." : null],
            ] : [])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('divisions.manage');

        return $this->form(new Division(['is_active' => true]));
    }

    public function store(DivisionRequest $request): RedirectResponse|JsonResponse
    {
        $division = Division::create($request->validated() + ['is_active' => $request->boolean('is_active', true)]);
        if ($request->expectsJson()) {
            // U5.1 quick add from the member form: the new division appears there, ticked, right away.
            return response()->json(['id' => $division->id, 'name' => $division->name, 'company_id' => $division->company_id,
                'company' => $division->company?->name, 'message' => "Divisi {$division->name} ditambahkan."], 201);
        }

        return redirect()->route('divisions.index')->with('ok', 'Divisi tersimpan.');
    }

    public function edit(Division $division): View
    {
        $this->authorize('divisions.manage');
        abort_unless(Scope::canAccessCompany(request()->user(), $division->company_id), 403);

        return $this->form($division);
    }

    public function update(DivisionRequest $request, Division $division): RedirectResponse
    {
        abort_unless(Scope::canAccessCompany(request()->user(), $division->company_id), 403);
        $division->update($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('divisions.index')->with('ok', 'Perubahan divisi tersimpan.');
    }

    public function toggle(Division $division): RedirectResponse
    {
        $this->authorize('divisions.manage');
        abort_unless(Scope::canAccessCompany(request()->user(), $division->company_id), 403);
        $division->update(['is_active' => ! $division->is_active]);

        return back()->with('ok', $division->is_active ? "Divisi {$division->name} aktif lagi." : "Divisi {$division->name} dinonaktifkan.");
    }

    private function form(Division $division): View
    {
        return view('admin.master.division', [
            'division' => $division,
            'companies' => Scope::limitToCompanies(Company::query(), request()->user(), 'companies.id')->where('is_active', 1)->orderBy('name')->pluck('name', 'id'),
            'users' => Scope::masterMembers(request()->user())->orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
