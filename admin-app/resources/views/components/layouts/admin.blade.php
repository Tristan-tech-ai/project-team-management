<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $judul ?? 'Admin' }} · PTM Admin</title>
    @fonts
    @vite(['resources/css/tata-letak.css', 'resources/js/app.js'])
</head>
<body>
<a href="#isi" class="lewati">Lewati ke isi</a>
@php
    $menu = app(\App\Services\MenuService::class)->untuk(auth()->user());
    $routeKini = request()->route()?->getName() ?? '';
    $bagianKini = explode('.', $routeKini)[0];
@endphp
@php
    $navigasi = function () use ($menu, $routeKini, $bagianKini) {
        $html = '';
        foreach ($menu as $induk) {
            $anakAktif = false;
            foreach ($induk['children'] as $a) {
                if ($a['route'] === $routeKini || explode('.', $a['route'])[0] === $bagianKini) { $anakAktif = true; }
            }
            $html .= view('layouts.menu-induk', ['induk' => $induk, 'routeKini' => $routeKini, 'aktif' => $anakAktif])->render();
        }
        return $html;
    };
@endphp
<aside class="admin-sidebar desktop d-none d-lg-block" aria-label="Menu utama">
    <a class="merek" href="{{ route('dashboard.index') }}"><span class="merek-tanda"><x-ikon name="folder-kanban" :size="20"/></span>
        <span class="merek-nama">PROJECT &amp; TEAM<small>MANAGEMENT</small></span></a>
    <nav><ul class="nav-induk">{!! $navigasi() !!}</ul></nav>
</aside>
<div class="offcanvas offcanvas-start admin-sidebar" tabindex="-1" id="menu-hp" aria-labelledby="menu-hp-judul">
    <div class="offcanvas-header"><span id="menu-hp-judul" class="merek-nama text-white">PROJECT &amp; TEAM<small>MANAGEMENT</small></span>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Tutup menu"></button></div>
    <div class="offcanvas-body p-0"><nav aria-label="Menu utama"><ul class="nav-induk">{!! $navigasi() !!}</ul></nav></div>
</div>

<div class="admin-utama">
    <header class="admin-header">
        <div class="isi">
            <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#menu-hp" aria-label="Buka menu"><x-ikon name="menu"/></button>
            <form class="cari-global" role="search" action="{{ route('tasks.index') }}" method="get">
                <x-ikon name="search"/>
                <label for="cari-global" class="visually-hidden">Cari task</label>
                <input id="cari-global" type="search" name="cari" value="{{ request('cari') }}" class="form-control" placeholder="Cari task">
            </form>
            <div class="dropdown ms-auto">
                <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="d-none d-sm-inline">{{ auth()->user()->name }}</span><span class="d-sm-none">{{ auth()->user()->inisial() }}</span>
                    <span class="lencana lencana-info ms-1">{{ collect(auth()->user()->roleSlugs())->map(fn ($r) => ['super_admin' => 'Super Admin', 'manager' => 'Manager', 'member' => 'Member'][$r] ?? $r)->implode(', ') }}</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('akun.edit') }}"><x-ikon name="user"/> Profil &amp; Password</a></li>
                    <li><a class="dropdown-item" href="{{ config('app.team_url') }}"><x-ikon name="external-link"/> Buka aplikasi Tim</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><form method="post" action="{{ route('logout') }}">@csrf<button class="dropdown-item" type="submit"><x-ikon name="log-out"/> Keluar</button></form></li>
                </ul>
            </div>
        </div>
    </header>
    <main id="isi" class="konten" tabindex="-1">
        @if ($errors->any() && ! ($sembunyikanGalatUmum ?? false))
            <div class="alert alert-danger" role="alert">{{ $errors->first() }}@if($errors->count() > 1) <span class="d-block small mt-1">Ada {{ $errors->count() }} isian yang perlu diperbaiki; lihat tanda merah di formulir.</span>@endif</div>
        @endif
        {{ $slot }}
    </main>
</div>
<x-konfirmasi/>
@stack('skrip')
</body>
</html>
