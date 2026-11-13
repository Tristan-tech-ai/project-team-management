<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\PositionRequest;
use App\Models\Company;
use App\Models\Position;
use App\Services\Scope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

/** Jabatan tidak punya deleted_at di SQL: dinonaktifkan, tidak dihapus. */
class PositionController extends Controller
{
    public function index(): View
    {
        return view('admin.master.index', [
            'judul' => 'Jabatan', 'ket' => 'Jabatan per company. Jabatan terpisah dari role hak akses.',
            'data' => route('positions.data'), 'tambah' => auth()->user()->can('positions.manage') ? route('positions.create') : null,
            'kolom' => [['data' => 'name', 'judul' => 'Jabatan'], ['data' => 'company', 'judul' => 'Company', 'name' => 'company.name'],
                ['data' => 'description', 'judul' => 'Keterangan'],
                ['data' => 'status', 'judul' => 'Status', 'render' => 'lencana', 'searchable' => false, 'name' => 'is_active'],
                ['data' => 'aksi', 'judul' => '', 'render' => 'aksi', 'searchable' => false, 'orderable' => false]],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $boleh = $request->user()->can('positions.manage');

        return DataTables::eloquent(Scope::batasiCompany(Position::query(), $request->user(), 'positions.company_id')->with('company:id,name')->select('positions.*'))
            ->addColumn('company', fn (Position $p) => $p->company?->name)
            ->editColumn('description', fn (Position $p) => $p->description ?? '-')
            ->addColumn('status', fn (Position $p) => $p->is_active ? 'Aktif' : 'Nonaktif')
            ->addColumn('status_kelas', fn (Position $p) => $p->is_active ? 'ok' : 'netral')
            ->addColumn('aksi', fn (Position $p) => $boleh ? [
                ['label' => 'Ubah', 'url' => route('positions.edit', $p)],
                ['label' => $p->is_active ? 'Nonaktifkan' : 'Aktifkan', 'url' => route('positions.toggle', $p), 'metode' => 'PATCH'],
            ] : [])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('positions.manage');

        return $this->form(new Position(['is_active' => true]));
    }

    public function store(PositionRequest $request): RedirectResponse
    {
        Position::create($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('positions.index')->with('ok', 'Jabatan tersimpan.');
    }

    public function edit(Position $position): View
    {
        $this->authorize('positions.manage');
        abort_unless(Scope::bolehCompany(request()->user(), $position->company_id), 403);

        return $this->form($position);
    }

    public function update(PositionRequest $request, Position $position): RedirectResponse
    {
        abort_unless(Scope::bolehCompany(request()->user(), $position->company_id), 403);
        $position->update($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('positions.index')->with('ok', 'Perubahan jabatan tersimpan.');
    }

    public function toggle(Position $position): RedirectResponse
    {
        $this->authorize('positions.manage');
        abort_unless(Scope::bolehCompany(request()->user(), $position->company_id), 403);
        $position->update(['is_active' => ! $position->is_active]);

        return back()->with('ok', $position->is_active ? "Jabatan {$position->name} aktif lagi." : "Jabatan {$position->name} dinonaktifkan.");
    }

    private function form(Position $position): View
    {
        return view('admin.master.position', ['position' => $position, 'companies' => Scope::batasiCompany(Company::query(), request()->user(), 'companies.id')->where('is_active', 1)->orderBy('name')->pluck('name', 'id')]);
    }
}
