<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Admin' }} · PTM Admin</title>
    @include('shared.head')
    @fonts
    @vite(['resources/css/layout.css', 'resources/js/app.js'])
</head>
<body data-table-page-length="{{ \App\Support\UiSettings::tablePageLength() }}">
<a href="#content" class="skip-link">Lewati ke isi</a>
@php
    $user = auth()->user();
    $menu = app(\App\Services\MenuService::class)->for($user);
    $activeRoute = \App\Services\MenuService::activeRoute($menu, request()->route()?->getName() ?? '');
    $roleNames = collect($user->roleSlugs())->map(fn ($r) => ['super_admin' => 'Super Admin', 'manager' => 'Manager', 'member' => 'Member'][$r] ?? $r)->implode(', ');
    $create = array_values(array_filter([
        $user->can('projects.create') ? [route('projects.create'), 'Project', 'folder-plus', 'Dari template atau kosong'] : null,
        $user->can('tasks.create') ? [route('tasks.create'), 'Task', 'list-plus', 'Masuk ke Antrian'] : null,
        $user->can('members.manage') ? [route('members.create'), 'Anggota', 'user-plus', 'Akun dan divisi'] : null,
        $user->can('clients.manage') ? [route('clients.create'), 'Client', 'contact', 'Pemberi project'] : null,
    ]));
    // Top bar trail from the menu: "Group › Page" for pages in a group, the page alone otherwise, the page title as a fallback.
    $trail = [];
    foreach ($menu as $parent) {
        foreach ($parent['children'] as $child) {
            if ($child['route'] === $activeRoute) {
                $trail = count($parent['children']) === 1 ? [$parent['title']] : [$parent['title'], $child['title']];
            }
        }
    }
    if (! $trail) {
        $trail = [$title ?? 'Admin'];
    }
@endphp
<aside class="admin-sidebar desktop d-none d-lg-flex" aria-label="Menu utama">
    <a class="brand" href="{{ route('dashboard.index') }}"><span class="brand-mark"><x-icon name="folder-kanban" :size="16"/></span>
        <span class="brand-name">Project &amp; Team<small>Admin</small></span></a>
    <button type="button" class="side-search" data-open-palette><x-icon name="search" :size="16"/><span>Cari atau pindah...</span><kbd class="kbd">Ctrl K</kbd></button>
    <div class="side-scroll" data-side-scroll="admin">@include('layouts.sidebar-nav', ['label' => 'Menu utama', 'idPrefix' => 'nav'])</div>
    <div class="side-foot dropup">
        <button class="side-user" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="avatar" aria-hidden="true">{{ $user->initials() }}</span>
            <span class="user-text truncate"><span class="d-block fw-medium truncate">{{ $user->name }}</span><span class="d-block small truncate">{{ $roleNames }}</span></span>
            <x-icon name="chevrons-up-down" :size="16" class="ms-auto muted"/>
        </button>
        @include('layouts.account-menu', ['menuClass' => 'dropdown-menu w-100'])
    </div>
</aside>
<div class="offcanvas offcanvas-start admin-sidebar" tabindex="-1" id="mobile-menu" aria-labelledby="mobile-menu-title">
    <div class="offcanvas-header"><span id="mobile-menu-title" class="brand-name">Project &amp; Team<small>Admin</small></span>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup menu"></button></div>
    <div class="offcanvas-body p-0 side-scroll" data-side-scroll="admin-phone">@include('layouts.sidebar-nav', ['label' => 'Menu utama (ponsel)', 'idPrefix' => 'mnav'])</div>
</div>

<div class="admin-main">
    <header class="admin-header">
        <div class="content">
            <button class="btn btn-icon d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobile-menu" aria-label="Buka menu"><x-icon name="menu"/></button>
            <nav class="header-title truncate" aria-label="Lokasi">
                @foreach($trail as $i => $part)
                    @if($i > 0)<span class="sep" aria-hidden="true">/</span>@endif
                    <span class="truncate @if($i < count($trail) - 1) muted @endif">{{ $part }}</span>
                @endforeach
            </nav>
            <form class="global-search d-none d-md-block" role="search" action="{{ route('search') }}" method="get">
                <x-icon name="search"/>
                <label for="global-search" class="visually-hidden">Cari project, task, atau anggota</label>
                <input id="global-search" type="search" name="keyword" value="{{ request('keyword') }}" class="form-control" placeholder="Cari project, task, anggota" maxlength="100" data-shortcut="/">
                <kbd class="kbd d-none d-lg-inline-flex" aria-hidden="true">/</kbd>
            </form>
            <div class="header-actions ms-auto ms-md-0">
                <a class="btn btn-icon d-md-none" href="{{ route('search') }}" aria-label="Cari"><x-icon name="search"/></a>
                <button class="btn btn-icon theme-toggle" type="button" data-theme-toggle aria-label="Ganti tema terang atau gelap" title="Ganti tema terang atau gelap"><x-icon name="moon" class="theme-icon-moon"/><x-icon name="sun" class="theme-icon-sun"/></button>
                <div class="dropdown" data-todo-bell data-url="{{ route('todo') }}">
                    <button class="btn btn-icon bell-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Perlu kamu kerjakan">
                        <x-icon name="bell"/><span class="bell-count numeric" data-todo-count hidden></span></button>
                    <div class="dropdown-menu dropdown-menu-end todo-menu">
                        <div class="todo-menu-head">Perlu kamu kerjakan</div>
                        <div data-todo-items><p class="small muted px-2 py-1 mb-0">Memuat...</p></div>
                        @if(request()->route()?->getName() !== 'dashboard.index')<a class="dropdown-item small justify-content-center" href="{{ route('dashboard.index') }}">Buka Dashboard</a>@endif
                    </div>
                </div>
                @if($create)
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false"><x-icon name="plus" :size="16"/><span class="d-none d-sm-inline">Buat</span><span class="visually-hidden d-sm-none">Buat baru</span></button>
                        <ul class="dropdown-menu dropdown-menu-end create-menu">
                            @foreach($create as [$url, $label, $icon, $hint])
                                <li><a class="dropdown-item" href="{{ $url }}"><span class="create-icon"><x-icon :name="$icon"/></span><span><span class="d-block fw-medium">{{ $label }}</span><span class="d-block small muted">{{ $hint }}</span></span></a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <div class="dropdown d-lg-none">
                    <button class="btn user-button" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="avatar" aria-hidden="true">{{ $user->initials() }}</span><span class="visually-hidden">Akun</span>
                    </button>
                    @include('layouts.account-menu', ['menuClass' => 'dropdown-menu dropdown-menu-end'])
                </div>
            </div>
        </div>
    </header>
    <main id="content" class="content-area" tabindex="-1">
        @if($errors->any() && ! ($hideGenericErrors ?? false))
            <div class="alert alert-danger" role="alert">{{ $errors->first() }}@if($errors->count() > 1) <span class="d-block small mt-1">Ada {{ $errors->count() }} isian yang perlu diperbaiki; lihat tanda merah di formulir.</span>@endif</div>
        @endif
        {{ $slot }}
    </main>
</div>
<x-confirm/>
<x-quick-add-modal/>
<x-task-panel/>
<x-command-palette :menu="$menu" :create="$create"/>
@stack('script')
</body>
</html>
