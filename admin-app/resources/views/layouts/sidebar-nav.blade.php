{{-- Sidebar navigation (U2): every destination is one click. A parent with one submenu is a direct link; a parent with
     several submenus is a titled group that can be folded (remembered per browser), and the group of the current page stays open. --}}
<nav class="side-nav" aria-label="{{ $label }}">
    @foreach($menu as $parent)
        @if(count($parent['children']) === 1)
            @php($child = $parent['children'][0])
            <a class="side-link side-single" href="{{ $child['url'] }}" @if($child['route'] === $activeRoute) aria-current="page" @endif>
                <x-icon :name="$parent['icon'] ?? $child['icon']"/><span>{{ $parent['title'] }}</span></a>
        @else
            @php($open = collect($parent['children'])->contains('route', $activeRoute))
            <div class="side-group" data-nav-group="{{ $parent['slug'] }}" @if($open) data-nav-current @endif>
                <button type="button" class="side-heading" aria-expanded="true" aria-controls="{{ $idPrefix }}-{{ $parent['slug'] }}">
                    <span>{{ $parent['title'] }}</span><x-icon name="chevron-down" :size="16" class="caret"/></button>
                <div class="side-items" id="{{ $idPrefix }}-{{ $parent['slug'] }}">
                    @foreach($parent['children'] as $child)
                        <a class="side-link" href="{{ $child['url'] }}" @if($child['route'] === $activeRoute) aria-current="page" @endif>
                            <x-icon :name="$child['icon']"/><span>{{ $child['title'] }}</span></a>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach
</nav>
