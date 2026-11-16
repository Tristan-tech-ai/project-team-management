{{-- I13 bulk actions: tick tasks in the list, then start them, change their PIC, or add an assignee in one go.
     Each task still goes through its own permission and status rules; tasks that cannot change are listed with the reason.
     Needs: $bulk (TaskBulkService::options), $table (id of the task table). --}}
<form class="bulk-bar" data-bulk-form data-url="{{ route('tasks.bulk') }}" data-table="#{{ $table }}" hidden aria-label="Aksi untuk task terpilih">
    <span class="bulk-count" role="status" aria-live="polite"><strong class="numeric" data-bulk-count>0</strong> task dipilih</span>
    <div class="bulk-controls">
        <label class="visually-hidden" for="{{ $table }}-bulk-action">Aksi untuk task terpilih</label>
        <select id="{{ $table }}-bulk-action" name="action" class="form-select" data-bulk-action>
            @foreach($bulk['actions'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
        </select>
        <label class="visually-hidden" for="{{ $table }}-bulk-user">Orang</label>
        <select id="{{ $table }}-bulk-user" name="user_id" class="form-select" data-bulk-user hidden>
            <option value="">Pilih orang</option>
            @foreach($bulk['people'] as $p)<option value="{{ $p['id'] }}">{{ $p['name'] }} ({{ $p['open'] }} task aktif)</option>@endforeach
        </select>
        <button class="btn btn-primary" type="submit" data-bulk-apply>Terapkan</button>
        {{-- Phones show rows as cards without the header tick box, so the bar offers "select all on this page". --}}
        <button class="btn btn-outline-secondary d-md-none" type="button" data-bulk-page>Pilih semua<span class="visually-hidden"> di halaman ini</span></button>
        <button class="btn btn-outline-secondary" type="button" data-bulk-clear>Batal pilih</button>
    </div>
    <div class="bulk-result w-100" data-bulk-result hidden></div>
</form>
