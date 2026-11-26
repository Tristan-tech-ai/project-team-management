<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\ClientRequest;
use App\Models\Client;
use App\Services\Scope;
use App\Support\CodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class ClientController extends Controller
{
    public function index(): View
    {
        return view('admin.master.index', [
            'title' => 'Client', 'description' => 'Klien project. Project internal boleh tanpa client. PIC project selalu anggota tim internal.',
            'data' => route('clients.data'), 'add' => auth()->user()->can('clients.manage') ? route('clients.create') : null,
            'columns' => [['data' => 'code', 'title' => 'Kode'], ['data' => 'name', 'title' => 'Nama'], ['data' => 'email', 'title' => 'Email'],
                ['data' => 'phone', 'title' => 'Telepon'],
                ['data' => 'company', 'title' => 'Company', 'searchable' => false, 'orderable' => false, 'cssClass' => 'col-wide'],
                ['data' => 'project', 'title' => 'Project', 'searchable' => false, 'orderable' => false, 'cssClass' => 'numeric'],
                ['data' => 'status', 'title' => 'Status', 'render' => 'badge', 'searchable' => false, 'name' => 'is_active'],
                ['data' => 'actions', 'title' => '', 'render' => 'actions', 'searchable' => false, 'orderable' => false]],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $canEdit = $request->user()->can('clients.manage');

        // Scope: apart from the Super Admin, only clients of projects the user can see.
        return DataTables::eloquent(Scope::clients($request->user())->select('clients.*')->withCount('projects')->with('companies:id,name'))
            ->addColumn('company', fn (Client $c) => $c->companies->isEmpty() ? 'Umum (semua company)' : $c->companies->pluck('name')->sort()->implode(', '))
            ->addColumn('project', fn (Client $c) => $c->projects_count)
            ->addColumn('status', fn (Client $c) => $c->is_active ? 'Aktif' : 'Nonaktif')
            ->addColumn('status_class', fn (Client $c) => $c->is_active ? 'ok' : 'neutral')
            ->addColumn('actions', fn (Client $c) => $canEdit ? [
                ['label' => 'Ubah', 'url' => route('clients.edit', $c)],
                ['label' => 'Hapus', 'url' => route('clients.destroy', $c), 'method' => 'DELETE', 'confirmation' => "Hapus {$c->name}? Project lama tetap ada tanpa client."],
            ] : [])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('clients.manage');

        return view('admin.master.client', ['client' => new Client(['is_active' => true]), 'companies' => Scope::filterCompanies(request()->user()), 'linked' => []]);
    }

    public function store(ClientRequest $request): RedirectResponse|JsonResponse
    {
        $client = Client::create($this->withCode($this->fields($request)) + ['is_active' => $request->boolean('is_active', true)]);
        $client->companies()->sync($request->validated('companies') ?? []);
        if ($request->expectsJson()) {
            // Quick add from another form (U5): the new record is selected there right away.
            return response()->json(['id' => $client->id, 'name' => $client->name, 'code' => $client->code, 'message' => "Client {$client->name} ditambahkan."], 201);
        }

        return redirect()->route('clients.index')->with('ok', 'Client tersimpan.');
    }

    public function edit(Client $client): View
    {
        $this->authorize('clients.manage');
        abort_unless(Scope::clients(request()->user())->whereKey($client->id)->exists(), 403);

        return view('admin.master.client', ['client' => $client, 'companies' => Scope::filterCompanies(request()->user()),
            'linked' => $client->companies()->pluck('companies.id')->map(fn ($id) => (int) $id)->all()]);
    }

    public function update(ClientRequest $request, Client $client): RedirectResponse
    {
        abort_unless(Scope::clients(request()->user())->whereKey($client->id)->exists(), 403);
        $client->update($this->withCode($this->fields($request), $client->id) + ['is_active' => $request->boolean('is_active')]);
        // Links to companies outside the editor's access are kept; only the ones the editor can see follow the form.
        $visible = Scope::filterCompanies($request->user())->keys()->map(fn ($id) => (int) $id);
        $kept = $client->companies()->pluck('companies.id')->map(fn ($id) => (int) $id)->diff($visible);
        $client->companies()->sync($kept->merge($request->validated('companies') ?? [])->unique()->values()->all());

        return redirect()->route('clients.index')->with('ok', 'Perubahan client tersimpan.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $this->authorize('clients.manage');
        abort_unless(Scope::clients(request()->user())->whereKey($client->id)->exists(), 403);
        $client->delete();

        return redirect()->route('clients.index')->with('ok', "{$client->name} dihapus.");
    }

    /** Columns of the clients table only; the company links go to client_company. */
    private function fields(ClientRequest $request): array
    {
        return collect($request->validated())->except('companies')->all();
    }

    /** U4: an empty code is generated from the name (unique, soft-deleted rows included). */
    private function withCode(array $data, ?int $ignoreId = null): array
    {
        if (blank($data['code'] ?? null)) {
            $data['code'] = CodeGenerator::unique($data['name'], fn (string $code) => Client::withTrashed()->where('code', $code)
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists());
        }

        return $data;
    }
}
