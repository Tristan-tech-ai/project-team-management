<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Masuk' }} · {{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/layout.css', 'resources/js/app.js'])
</head>
<body>
<main class="min-vh-100 d-flex align-items-center justify-content-center p-3" style="background:var(--ptm-bg)">
    <div class="panel w-100" style="max-width:420px">
        <div class="d-flex align-items-center gap-2 mb-4">
            <span class="d-grid place-items-center rounded-3 text-white" style="width:36px;height:36px;background:var(--ptm-action);display:grid;place-items:center"><x-icon name="folder-kanban" :size="20"/></span>
            <span class="fw-bold" style="line-height:1.1">PROJECT &amp; TEAM<br><small class="muted fw-semibold" style="font-size:.72rem">MANAGEMENT</small></span>
        </div>
        {{ $slot }}
    </div>
</main>
</body>
</html>
