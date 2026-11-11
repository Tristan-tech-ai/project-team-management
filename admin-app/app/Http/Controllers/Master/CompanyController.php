<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\CompanyRequest;
use App\Models\Company;
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
            'judul' => 'Company', 'ket' => 'Perusahaan internal yang menaungi divisi, jabatan, anggota, dan project.',
            'data' => route('companies.data'), 'tambah' => auth()->user()->can('companies.manage') ? route('companies.create') : null,
            'kolom' => [['data' => 'code', 'judul' => 'Kode'], ['data' => 'name', 'judul' => 'Nama'], ['data' => 'email', 'judul' => 'Email'],
                ['data' => 'divisi', 'judul' => 'Divisi', 'searchable' => false, 'orderable' => false, 'kelas' => 'angka'],
                ['data' => 'status', 'judul' => 'Status', 'render' => 'lencana', 'searchable' => false, 'name' => 'is_active'],
                ['data' => 'aksi', 'judul' => '', 'render' => 'aksi', 'searchable' => false, 'orderable' => false]],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $bolehUbah = $request->user()->can('companies.manage');
        $query = Company::query()->select('companies.*')->withCount('divisions');

        return DataTables::eloquent($query)
            ->addColumn('divisi', fn (Company $c) => $c->divisions_count)
            ->addColumn('status', fn (Company $c) => $c->is_active ? 'Aktif' : 'Nonaktif')
            ->addColumn('status_kelas', fn (Company $c) => $c->is_active ? 'ok' : 'netral')
            ->addColumn('aksi', fn (Company $c) => $bolehUbah ? [
                ['label' => 'Ubah', 'url' => route('companies.edit', $c)],
                ['label' => 'Hapus', 'url' => route('companies.destroy', $c), 'metode' => 'DELETE', 'konfirmasi' => "Hapus {$c->name}? Data disimpan sebagai terhapus (bisa dipulihkan dari database)."],
            ] : [])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('companies.manage');

        return view('admin.master.company', ['company' => new Company(['is_active' => true])]);
    }

    public function store(CompanyRequest $request): RedirectResponse
    {
        Company::create($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('companies.index')->with('ok', 'Company tersimpan.');
    }

    public function edit(Company $company): View
    {
        $this->authorize('companies.manage');

        return view('admin.master.company', ['company' => $company]);
    }

    public function update(CompanyRequest $request, Company $company): RedirectResponse
    {
        $company->update($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('companies.index')->with('ok', 'Perubahan company tersimpan.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        $this->authorize('companies.manage');
        if ($company->projects()->exists()) {
            return back()->with('galat', "{$company->name} masih punya project. Nonaktifkan saja, atau pindahkan project-nya dulu.");
        }
        $company->delete();

        return redirect()->route('companies.index')->with('ok', "{$company->name} dihapus.");
    }
}
