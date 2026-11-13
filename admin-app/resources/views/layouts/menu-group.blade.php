<li class="{{ $active ? 'active' : '' }}">
    <details @if($active) open @endif>
        <summary><x-icon :name="$parent['icon'] ?? 'folder'"/><span>{{ $parent['title'] }}</span><x-icon name="chevron-down" :size="16" class="caret"/></summary>
        <ul class="nav-child">
            @foreach($parent['children'] as $children)
                <li><a href="{{ $children['url'] }}" @if($children['route'] === $currentRoute) aria-current="page" @endif>{{ $children['title'] }}</a></li>
            @endforeach
        </ul>
    </details>
</li>
