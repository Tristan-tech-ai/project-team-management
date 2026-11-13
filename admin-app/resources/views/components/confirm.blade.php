<div class="modal fade" id="confirm-modal" tabindex="-1" aria-labelledby="confirm-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px">
            <div class="modal-header border-0 pb-0"><h2 class="modal-title h5" id="confirm-modal-title">Yakin?</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body"><p class="mb-0" data-content></p></div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" data-confirm-yes>Ya, lanjutkan</button>
            </div>
        </div>
    </div>
</div>
@if(session('ok'))<div data-flash="{{ session('ok') }}" data-kind="success" hidden></div>@endif
@if(session('error'))<div data-flash="{{ session('error') }}" data-kind="error" hidden></div>@endif
