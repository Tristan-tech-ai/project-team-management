<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\CompanyRequest;
use App\Models\Company;
use App\Services\Scope;
use App\Support\CodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class CompanyController extends Controller
{
    public function index(): View
    {
        return view('admin.master.index', [
            'title' => 'Company', 'description' => 'Perusahaan internal yang menaungi divisi, jabatan, anggota, dan project.',
            'data' => route('companies.data'), 'add' => auth()->user()->can('companies.manage') ? route('companies.create') : null,
            'columns' => [['data' => 'code', 'title' => 'Kode'], ['data' => 'name', 'title' => 'Nama'], ['data' => 'email', 'title' => 'Email'],
                ['data' => 'divisions', 'title' => 'Divisi', 'searchable' => false, 'orderable' => false, 'cssClass' => 'numeric'],
                ['data' => 'status', 'title' => 'Status', 'render' => 'badge', 'searchable' => false, 'name' => 'is_active'],
                ['data' => 'actions', 'title' => '', 'render' => 'actions', 'searchable' => false, 'orderable' => false]],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $canEdit = $request->user()->can('companies.manage');
        // Scope (Technical Guide 3.2): apart from the Super Admin, only companies where the user is an active member.
        $query = Scope::limitToCompanies(Company::query()->select('companies.*')->withCount('divisions'), $request->user(), 'companies.id');

        return DataTables::eloquent($query)
            ->addColumn('divisions', fn (Company $c) => $c->divisions_count)
            ->addColumn('status', fn (Company $c) => $c->is_active ? 'Aktif' : 'Nonaktif')
            ->addColumn('status_class', fn (Company $c) => $c->is_active ? 'ok' : 'neutral')
            ->addColumn('actions', fn (Company $c) => $canEdit ? [
                ['label' => 'Ubah', 'url' => route('companies.edit', $c)],
                ['label' => 'Hapus', 'url' => route('companies.destroy', $c), 'method' => 'DELETE', 'confirmation' => "Hapus {$c->name}? Data disimpan sebagai terhapus (bisa dipulihkan dari database)."],
            ] : [])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('companies.manage');

        return view('admin.master.company', ['company' => new Company(['is_active' => true])]);
    }

    public function store(CompanyRequest $request): RedirectResponse|JsonResponse
    {
        $company = Company::create($this->withCode($request->validated()) + ['is_active' => $request->boolean('is_active', true)]);
        if ($request->expectsJson()) {
            // Quick add from another form (U5): the new record is selected there right away.
            return response()->json(['id' => $company->id, 'name' => $company->name, 'code' => $company->code, 'message' => "Company {$company->name} ditambahkan."], 201);
        }

        return redirect()->route('companies.index')->with('ok', 'Company tersimpan.');
    }

    public function edit(Company $company): View
    {
        $this->authorize('companies.manage');
        abort_unless(Scope::canAccessCompany(request()->user(), $company->id), 403);

        return view('admin.master.company', ['company' => $company]);
    }

    public function update(CompanyRequest $request, Company $company): RedirectResponse
    {
        abort_unless(Scope::canAccessCompany(request()->user(), $company->id), 403);
        $company->update($this->withCode($request->validated(), $company->id) + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('companies.index')->with('ok', 'Perubahan company tersimpan.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        $this->authorize('companies.manage');
        abort_unless(Scope::canAccessCompany(request()->user(), $company->id), 403);
        if ($company->projects()->exists()) {
            return back()->with('error', "{$company->name} masih punya project. Nonaktifkan saja, atau pindahkan project-nya dulu.");
        }
        $company->delete();

        return redirect()->route('companies.index')->with('ok', "{$company->name} dihapus.");
    }

    /** U4: an empty code is generated from the name (unique, soft-deleted rows included). */
    private function withCode(array $data, ?int $ignoreId = null): array
    {
        if (blank($data['code'] ?? null)) {
            $data['code'] = CodeGenerator::unique($data['name'], fn (string $code) => Company::withTrashed()->where('code', $code)
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists());
        }

        return $data;
    }
}
