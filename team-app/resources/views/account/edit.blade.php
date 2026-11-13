<x-layouts.team title="Profil Saya">
    <div class="page-header"><div><h1 class="mb-0">Profil Saya</h1></div>
        <form method="post" action="{{ route('logout') }}" class="d-lg-none">@csrf<button class="btn btn-outline-secondary btn-sm" type="submit"><x-icon name="log-out"/> Keluar</button></form></div>
    @include('account.form')
</x-layouts.team>
