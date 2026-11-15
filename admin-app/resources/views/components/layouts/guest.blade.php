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
<main class="guest-main {{ isset($aside) ? 'guest-split' : '' }}">
    @isset($aside)
        <section class="guest-aside" aria-label="Tentang aplikasi">{{ $aside }}</section>
    @endisset
    <div class="guest-form">
        <div class="panel guest-card">
            <div class="d-flex align-items-center gap-2 mb-4">
                <span class="guest-mark"><x-icon name="folder-kanban" :size="20"/></span>
                <span class="fw-bold lh-sm">Project &amp; Team<br><small class="muted fw-semibold" style="font-size:.75rem">Management</small></span>
            </div>
            {{ $slot }}
        </div>
    </div>
</main>
</body>
</html>
