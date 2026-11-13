<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Admin' }} · PTM Admin</title>
    @fonts
    @vite(['resources/css/layout.css', 'resources/js/app.js'])
</head>
<body data-table-page-length="{{ \App\Support\UiSettings::tablePageLength() }}">
<a href="#content" class="skip-link">Lewati ke isi</a>
@php
    $menu = app(\App\Services\MenuService::class)->for(auth()->user());
    $currentRoute = request()->route()?->getName() ?? '';
    $currentSection = explode('.', $currentRoute)[0];
@endphp
@php
    $navigation = function () use ($menu, $currentRoute, $currentSection) {
        $html = '';
        foreach ($menu as $parent) {
            $activeChildren = false;
            foreach ($parent['children'] as $a) {
                if ($a['route'] === $currentRoute || explode('.', $a['route'])[0] === $currentSection) { $activeChildren = true; }
            }
            $html .= view('layouts.menu-group', ['parent' => $parent, 'currentRoute' => $currentRoute, 'active' => $activeChildren])->render();
        }
        return $html;
    };
@endphp
<aside class="admin-sidebar desktop d-none d-lg-block" aria-label="Menu utama">
    <a class="brand" href="{{ route('dashboard.index') }}"><span class="brand-mark"><x-icon name="folder-kanban" :size="20"/></span>
        <span class="brand-name">PROJECT &amp; TEAM<small>MANAGEMENT</small></span></a>
    <nav><ul class="nav-group">{!! $navigation() !!}</ul></nav>
</aside>
<div class="offcanvas offcanvas-start admin-sidebar" tabindex="-1" id="mobile-menu" aria-labelledby="mobile-menu-title">
    <div class="offcanvas-header"><span id="mobile-menu-title" class="brand-name text-white">PROJECT &amp; TEAM<small>MANAGEMENT</small></span>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Tutup menu"></button></div>
    <div class="offcanvas-body p-0"><nav aria-label="Menu utama"><ul class="nav-group">{!! $navigation() !!}</ul></nav></div>
</div>

<div class="admin-main">
    <header class="admin-header">
        <div class="content">
            <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobile-menu" aria-label="Buka menu"><x-icon name="menu"/></button>
            <form class="global-search" role="search" action="{{ route('search') }}" method="get">
                <x-icon name="search"/>
                <label for="global-search" class="visually-hidden">Cari project, task, atau anggota</label>
                <input id="global-search" type="search" name="keyword" value="{{ request('keyword') }}" class="form-control" placeholder="Cari project, task, atau anggota" maxlength="100">
            </form>
            <div class="dropdown ms-auto">
                <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="d-none d-sm-inline">{{ auth()->user()->name }}</span><span class="d-sm-none">{{ auth()->user()->initials() }}</span>
                    <span class="pill pill-info ms-1">{{ collect(auth()->user()->roleSlugs())->map(fn ($r) => ['super_admin' => 'Super Admin', 'manager' => 'Manager', 'member' => 'Member'][$r] ?? $r)->implode(', ') }}</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('account.edit') }}"><x-icon name="user"/> Profil &amp; Password</a></li>
                    <li><a class="dropdown-item" href="{{ config('app.team_url') }}"><x-icon name="external-link"/> Buka aplikasi Tim</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><form method="post" action="{{ route('logout') }}">@csrf<button class="dropdown-item" type="submit"><x-icon name="log-out"/> Keluar</button></form></li>
                </ul>
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
@stack('script')
</body>
</html>
