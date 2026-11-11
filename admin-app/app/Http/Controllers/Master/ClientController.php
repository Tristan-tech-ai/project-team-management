<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\ClientRequest;
use App\Models\Client;
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
            'judul' => 'Client', 'ket' => 'Klien project. Project internal boleh tanpa client. PIC project selalu anggota tim internal.',
            'data' => route('clients.data'), 'tambah' => auth()->user()->can('clients.manage') ? route('clients.create') : null,
            'kolom' => [['data' => 'code', 'judul' => 'Kode'], ['data' => 'name', 'judul' => 'Nama'], ['data' => 'email', 'judul' => 'Email'],
                ['data' => 'phone', 'judul' => 'Telepon'],
                ['data' => 'project', 'judul' => 'Project', 'searchable' => false, 'orderable' => false, 'kelas' => 'angka'],
                ['data' => 'status', 'judul' => 'Status', 'render' => 'lencana', 'searchable' => false, 'name' => 'is_active'],
                ['data' => 'aksi', 'judul' => '', 'render' => 'aksi', 'searchable' => false, 'orderable' => false]],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $bolehUbah = $request->user()->can('clients.manage');

        return DataTables::eloquent(Client::query()->select('clients.*')->withCount('projects'))
            ->addColumn('project', fn (Client $c) => $c->projects_count)
            ->addColumn('status', fn (Client $c) => $c->is_active ? 'Aktif' : 'Nonaktif')
            ->addColumn('status_kelas', fn (Client $c) => $c->is_active ? 'ok' : 'netral')
            ->addColumn('aksi', fn (Client $c) => $bolehUbah ? [
                ['label' => 'Ubah', 'url' => route('clients.edit', $c)],
                ['label' => 'Hapus', 'url' => route('clients.destroy', $c), 'metode' => 'DELETE', 'konfirmasi' => "Hapus {$c->name}? Project lama tetap ada tanpa client."],
            ] : [])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('clients.manage');

        return view('admin.master.client', ['client' => new Client(['is_active' => true])]);
    }

    public function store(ClientRequest $request): RedirectResponse
    {
        Client::create($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('clients.index')->with('ok', 'Client tersimpan.');
    }

    public function edit(Client $client): View
    {
        $this->authorize('clients.manage');

        return view('admin.master.client', ['client' => $client]);
    }

    public function update(ClientRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('clients.index')->with('ok', 'Perubahan client tersimpan.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $this->authorize('clients.manage');
        $client->delete();

        return redirect()->route('clients.index')->with('ok', "{$client->name} dihapus.");
    }
}
