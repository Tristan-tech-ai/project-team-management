{{-- I1 "Perlu kamu kerjakan": one list of what needs this person now, each linking straight to the action. --}}
@php($icons = ['review' => 'clipboard-check', 'schedule' => 'calendar-clock', 'revision' => 'rotate-ccw', 'late' => 'triangle-alert', 'due' => 'clock'])
<section class="panel todo h-100" aria-labelledby="todo-title" data-refresh-region="todo">
    <div class="panel-head"><h2 class="panel-title" id="todo-title">Perlu kamu kerjakan</h2>@if($todo)<span class="pill pill-plain pill-info numeric">{{ count($todo) }}</span>@endif</div>
    @forelse($todo as $item)
        <a class="todo-item tone-{{ $item['tone'] }}" href="{{ $item['url'] }}" @if($item['panel'] ?? false) data-task-panel @endif>
            <span class="todo-icon" aria-hidden="true"><x-icon :name="$icons[$item['kind']] ?? 'circle-dot'"/></span>
            <span class="min-w-0 flex-grow-1"><span class="todo-title">{{ $item['title'] }}</span><span class="todo-detail">{{ $item['detail'] }}</span></span>
            @if($item['when'])<span class="todo-when">{{ $item['when'] }}</span>@endif
            <x-icon name="chevron-right" :size="16" class="todo-go"/>
        </a>
    @empty
        <div class="empty-state py-3"><span class="empty-icon"><x-icon name="circle-check" :size="22"/></span><strong>Semua beres.</strong><span>Tidak ada review, pengajuan, atau deadline dekat yang menunggumu.</span></div>
    @endforelse
</section>
