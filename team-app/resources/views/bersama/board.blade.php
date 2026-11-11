{{-- Kanban empat kolom. Desktop: seret (SortableJS). HP: dropdown status di tiap kartu. Endpoint sama: PATCH /tasks/{id}/status. --}}
@php($kolomLabel = \App\Support\Kode::TASK_STATUS)
<div class="kanban" data-kanban>
    @foreach ($kolomLabel as $kode => $label)
        @php($isi = $kolom[$kode] ?? collect())
        <section class="kanban-kolom" aria-labelledby="kol-{{ $kode }}">
            <header class="d-flex align-items-center justify-content-between mb-2">
                <h2 class="h6 mb-0" id="kol-{{ $kode }}">{{ $label }}</h2>
                <span class="lencana lencana-netral angka" data-hitung="{{ $kode }}">{{ $isi->count() }}</span>
            </header>
            <div class="kanban-isi" data-kanban-column data-status="{{ $kode }}">
                @foreach ($isi as $t)
                    @include('bersama.kartu-task', ['t' => $t])
                @endforeach
                <p class="kanban-kosong small teks-2 mb-0" @if($isi->isNotEmpty()) hidden @endif>Belum ada task di sini.</p>
            </div>
        </section>
    @endforeach
</div>
