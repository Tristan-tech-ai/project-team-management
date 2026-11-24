{{-- Account menu (sidebar foot on desktop, top bar on phones): profile, theme, quick commands, Team App, log out. --}}
<ul class="{{ $menuClass }}">
    <li class="dropdown-header">{{ $user->name }} · {{ $roleNames }}</li>
    <li><a class="dropdown-item" href="{{ route('account.edit') }}"><x-icon name="user"/> Profil &amp; kata sandi</a></li>
    <li class="d-none d-lg-block"><button class="dropdown-item" type="button" data-open-palette><x-icon name="search"/> Perintah cepat <kbd class="kbd ms-auto">Ctrl K</kbd></button></li>
    <li><a class="dropdown-item" href="{{ config('app.team_url') }}"><x-icon name="external-link"/> Buka Team App</a></li>
    <li><div class="dropdown-item justify-content-between" role="group" aria-label="Tema"><span>Tema</span>
        <span class="theme-choice">
            <button type="button" data-theme-set="light" aria-pressed="false" aria-label="Terang" title="Terang"><x-icon name="sun" :size="14"/></button>
            <button type="button" data-theme-set="system" aria-pressed="false" aria-label="Ikuti sistem" title="Ikuti sistem"><x-icon name="monitor" :size="14"/></button>
            <button type="button" data-theme-set="dark" aria-pressed="false" aria-label="Gelap" title="Gelap"><x-icon name="moon" :size="14"/></button>
        </span></div></li>
    <li><hr class="dropdown-divider"></li>
    <li><form method="post" action="{{ route('logout') }}">@csrf<button class="dropdown-item" type="submit"><x-icon name="log-out"/> Keluar</button></form></li>
</ul>
