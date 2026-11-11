<div class="modal fade" id="modal-konfirmasi" tabindex="-1" aria-labelledby="modal-konfirmasi-judul" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px">
            <div class="modal-header border-0 pb-0"><h2 class="modal-title h5" id="modal-konfirmasi-judul">Yakin?</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body"><p class="mb-0" data-isi></p></div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" data-ya>Ya, lanjutkan</button>
            </div>
        </div>
    </div>
</div>
@if (session('ok'))<div data-flash="{{ session('ok') }}" data-jenis="success" hidden></div>@endif
@if (session('galat'))<div data-flash="{{ session('galat') }}" data-jenis="error" hidden></div>@endif
