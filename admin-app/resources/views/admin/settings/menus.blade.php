<x-layouts.admin title="Menu Management">
    <div class="page-header"><div><div class="crumb">System Settings</div><h1>Menu Management</h1>
        <p>Atur nama, ikon, urutan, aktif, dan izin menu. Alamat halaman (route) tidak bisa diubah dari sini.</p></div></div>
    <form method="post" action="{{ route('settings.menus.update') }}">
        @csrf @method('PUT')
        @foreach($parent as $m)
            <div class="panel mb-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4"><label class="form-label" for="m{{ $m->id }}t">Menu induk</label>
                        <input class="form-control" id="m{{ $m->id }}t" name="menu[{{ $m->id }}][title]" value="{{ old("menu.$m->id.title", $m->title) }}" required></div>
                    <div class="col-md-3"><label class="form-label" for="m{{ $m->id }}i">Ikon</label>
                        <select class="form-select" id="m{{ $m->id }}i" name="menu[{{ $m->id }}][icon]">@foreach($icon as $i)<option value="{{ $i }}" @selected($m->icon === $i)>{{ $i }}</option>@endforeach</select></div>
                    <div class="col-md-2"><label class="form-label" for="m{{ $m->id }}s">Urutan</label>
                        <input class="form-control numeric" type="number" min="0" id="m{{ $m->id }}s" name="menu[{{ $m->id }}][sort_order]" value="{{ $m->sort_order }}"></div>
                    <div class="col-md-3"><input type="hidden" name="menu[{{ $m->id }}][is_active]" value="0">
                        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="m{{ $m->id }}a" name="menu[{{ $m->id }}][is_active]" value="1" @checked($m->is_active)>
                            <label class="form-check-label" for="m{{ $m->id }}a">Aktif</label></div></div>
                </div>
                <div class="table-responsive mt-3"><table class="table table-sm align-middle">
                    <thead><tr><th scope="col">Submenu</th><th scope="col">Route</th><th scope="col">Izin</th><th scope="col">Urutan</th><th scope="col">Aktif</th></tr></thead>
                    <tbody>
                    @foreach($children[$m->id] ?? [] as $a)
                        <tr>
                            <td><label class="visually-hidden" for="m{{ $a->id }}t">Nama submenu</label><input class="form-control form-control-sm" id="m{{ $a->id }}t" name="menu[{{ $a->id }}][title]" value="{{ $a->title }}" required></td>
                            <td><code>{{ $a->route_name }}</code>@unless(\Illuminate\Support\Facades\Route::has($a->route_name))<span class="pill pill-late ms-1">route belum ada</span>@endunless</td>
                            <td><label class="visually-hidden" for="m{{ $a->id }}p">Izin {{ $a->title }}</label>
                                <select class="form-select form-select-sm" id="m{{ $a->id }}p" name="menu[{{ $a->id }}][permission_id]"><option value="">Tanpa izin khusus</option>
                                    @foreach($permissions as $p)<option value="{{ $p->id }}" @selected($a->permission_id === $p->id)>{{ $p->slug }}</option>@endforeach</select></td>
                            <td style="width:110px"><label class="visually-hidden" for="m{{ $a->id }}s">Urutan {{ $a->title }}</label><input class="form-control form-control-sm numeric" type="number" min="0" id="m{{ $a->id }}s" name="menu[{{ $a->id }}][sort_order]" value="{{ $a->sort_order }}"></td>
                            <td><input type="hidden" name="menu[{{ $a->id }}][is_active]" value="0"><label class="check-hit"><input class="form-check-input" type="checkbox" name="menu[{{ $a->id }}][is_active]" value="1" @checked($a->is_active) aria-label="{{ $a->title }} aktif"></label></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            </div>
        @endforeach
        <button class="btn btn-primary" type="submit">Simpan menu</button>
    </form>
</x-layouts.admin>
