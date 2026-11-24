<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Tim' }} · PTM Team</title>
    @include('shared.head')
    @fonts
    @vite(['resources/css/layout.css', 'resources/js/app.js'])
</head>
<body data-table-page-length="{{ \App\Support\UiSettings::tablePageLength() }}">
<a href="#content" class="skip-link">Lewati ke isi</a>
@php
    $u = auth()->user();
    $nav = [
        ['home', 'Beranda', 'house', true],
        ['tasks.index', 'Task Saya', 'list-todo', $u->hasPermission('tasks.view')],
        ['projects.index', 'Project Saya', 'folder-kanban', $u->hasPermission('projects.view')],
        ['calendar.index', 'Kalender', 'calendar-days', $u->hasPermission('calendar.view')],
        ['activities.index', 'Aktivitas', 'activity', $u->hasPermission('activities.view')],
        ['review.index', 'Review Task', 'clipboard-check', $u->hasPermission('tasks.review')],
        ['account.edit', 'Profil', 'user', true],
    ];
    $active = fn (string $r) => request()->routeIs($r) || request()->routeIs(explode('.', $r)[0].'.*');
    $roleNames = collect($u->roleSlugs())->map(fn ($r) => ['super_admin' => 'Super Admin', 'manager' => 'Manager', 'member' => 'Member'][$r] ?? $r)->implode(', ');
@endphp
{{-- Desktop: sidebar menggantikan bottom navigation (d-none d-lg-flex). --}}
<nav class="desktop-sidebar d-none d-lg-flex" aria-label="Menu utama">
    <a class="brand" href="{{ route('home') }}"><span class="brand-mark"><x-icon name="folder-kanban" :size="16"/></span><span class="brand-name">Project &amp; Team<small>Team App</small></span></a>
    <div class="side-nav side-scroll" data-side-scroll="team">
        @foreach($nav as [$r, $l, $i, $can])
            @if($can)<a class="side-link" href="{{ route($r) }}" @if($active($r)) aria-current="page" @endif><x-icon :name="$i"/><span>{{ $l }}</span></a>@endif
        @endforeach
    </div>
    <div class="side-foot dropup">
        <button class="side-user" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="avatar" aria-hidden="true">{{ $u->initials() }}</span>
            <span class="user-text truncate"><span class="d-block fw-medium truncate">{{ $u->name }}</span><span class="d-block small truncate">{{ $roleNames }}</span></span>
            <x-icon name="chevrons-up-down" :size="16" class="ms-auto muted"/>
        </button>
        @include('layouts.account-menu', ['menuClass' => 'dropdown-menu w-100'])
    </div>
</nav>
<header class="team-header">
    <div class="flex-grow-1 min-w-0"><div class="brand-small d-lg-none">Project &amp; Team</div><div class="header-title text-truncate">{{ $title ?? 'Tim' }}</div></div>
    <button class="btn btn-icon theme-toggle" type="button" data-theme-toggle aria-label="Ganti tema terang atau gelap" title="Ganti tema terang atau gelap"><x-icon name="moon" class="theme-icon-moon"/><x-icon name="sun" class="theme-icon-sun"/></button>
    @if($u->hasPermission('tasks.create'))<a href="{{ route('tasks.create') }}" class="btn btn-primary btn-sm"><x-icon name="plus" :size="16"/> Task</a>@endif
    <div class="dropdown d-lg-none">
        <button class="user-button" type="button" data-bs-toggle="dropdown" aria-expanded="false"><span class="avatar" aria-hidden="true">{{ $u->initials() }}</span><span class="visually-hidden">Akun</span></button>
        @include('layouts.account-menu', ['menuClass' => 'dropdown-menu dropdown-menu-end'])
    </div>
</header>
<main id="content" class="app-main" tabindex="-1">
    @if($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif
    {{ $slot }}
</main>
{{-- Phone: four bottom buttons Home, Tasks, Calendar, Profile (Technical Guide 5.2). --}}
<nav class="mobile-bottom-nav d-lg-none" aria-label="Navigasi bawah">
    <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif><span class="nav-pill"><x-icon name="house" :size="20"/></span>Beranda</a>
    <a href="{{ route('tasks.index') }}" @if(request()->routeIs('tasks.*') || request()->routeIs('projects.*') || request()->routeIs('review.*')) aria-current="page" @endif><span class="nav-pill"><x-icon name="list-todo" :size="20"/></span>Tasks</a>
    <a href="{{ route('calendar.index') }}" @if(request()->routeIs('calendar.*') || request()->routeIs('activities.*')) aria-current="page" @endif><span class="nav-pill"><x-icon name="calendar-days" :size="20"/></span>Kalender</a>
    <a href="{{ route('account.edit') }}" @if(request()->routeIs('account.*')) aria-current="page" @endif><span class="nav-pill"><x-icon name="user" :size="20"/></span>Profil</a>
</nav>
<x-confirm/>
<x-task-panel/>
</body>
</html>
