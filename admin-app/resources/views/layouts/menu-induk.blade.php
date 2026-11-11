<li class="{{ $aktif ? 'aktif' : '' }}">
    <details @if($aktif) open @endif>
        <summary><x-ikon :name="$induk['icon'] ?? 'folder'"/><span>{{ $induk['title'] }}</span><x-ikon name="chevron-down" :size="16" class="panah"/></summary>
        <ul class="nav-anak">
            @foreach ($induk['children'] as $anak)
                <li><a href="{{ $anak['url'] }}" @if($anak['route'] === $routeKini) aria-current="page" @endif>{{ $anak['title'] }}</a></li>
            @endforeach
        </ul>
    </details>
</li>
