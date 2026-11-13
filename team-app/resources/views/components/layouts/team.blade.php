<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Tim' }} · PTM Team</title>
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
@endphp
{{-- Desktop: sidebar menggantikan bottom navigation (d-none d-lg-flex). --}}
<nav class="desktop-sidebar d-none d-lg-flex" aria-label="Menu utama">
    <a class="brand" href="{{ route('home') }}"><span class="brand-mark"><x-icon name="folder-kanban" :size="20"/></span><span>PROJECT &amp; TEAM<small>Aplikasi Tim</small></span></a>
    @foreach($nav as [$r, $l, $i, $can])
        @if($can)<a class="nav-team" href="{{ route($r) }}" @if($active($r)) aria-current="page" @endif><x-icon :name="$i"/> {{ $l }}</a>@endif
    @endforeach
    <form method="post" action="{{ route('logout') }}" class="mt-auto">@csrf<button class="nav-team btn btn-link justify-content-start w-100 text-decoration-none" style="color:#b9c6dc" type="submit"><x-icon name="log-out"/> Keluar</button></form>
</nav>
<header class="team-header">
    <div class="flex-grow-1 min-w-0"><div class="brand-small">PROJECT &amp; TEAM</div><div class="fw-semibold text-truncate">{{ $u->name }}</div></div>
    @if($u->hasPermission('tasks.create'))<a href="{{ route('tasks.create') }}" class="btn btn-primary btn-sm"><x-icon name="plus"/> Task</a>@endif
</header>
<main id="content" class="app-main" tabindex="-1">
    @if($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif
    {{ $slot }}
</main>
{{-- Phone: four bottom buttons Home, Tasks, Calendar, Profile (Technical Guide 5.2). --}}
<nav class="mobile-bottom-nav d-lg-none" aria-label="Navigasi bawah">
    <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif><x-icon name="house" :size="22"/>Beranda</a>
    <a href="{{ route('tasks.index') }}" @if(request()->routeIs('tasks.*') || request()->routeIs('projects.*') || request()->routeIs('review.*')) aria-current="page" @endif><x-icon name="list-todo" :size="22"/>Tasks</a>
    <a href="{{ route('calendar.index') }}" @if(request()->routeIs('calendar.*') || request()->routeIs('activities.*')) aria-current="page" @endif><x-icon name="calendar-days" :size="22"/>Kalender</a>
    <a href="{{ route('account.edit') }}" @if(request()->routeIs('account.*')) aria-current="page" @endif><x-icon name="user" :size="22"/>Profil</a>
</nav>
<x-confirm/>
</body>
</html>
