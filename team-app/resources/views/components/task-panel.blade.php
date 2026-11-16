{{-- I3: task detail as a side panel over the board, list, or dashboard. The page behind keeps its filters, column, and
     scroll position; actions inside go through the same endpoints, and the page behind refreshes when the panel closes. --}}
<div class="offcanvas offcanvas-end task-panel" tabindex="-1" id="task-panel" aria-labelledby="task-panel-title">
    <div class="offcanvas-header">
        <div class="min-w-0 flex-grow-1">
            <div class="small muted text-truncate" data-task-panel-project></div>
            <h2 class="offcanvas-title h5 mb-0 text-truncate" id="task-panel-title">Detail task</h2>
        </div>
        <a class="btn btn-icon" href="#" data-task-panel-full title="Buka halaman penuh"><x-icon name="external-link"/><span class="visually-hidden">Buka halaman penuh</span></a>
        <button type="button" class="btn btn-icon" data-bs-dismiss="offcanvas" title="Tutup"><x-icon name="x"/><span class="visually-hidden">Tutup panel</span></button>
    </div>
    <div class="offcanvas-body" data-task-panel-body></div>
</div>
