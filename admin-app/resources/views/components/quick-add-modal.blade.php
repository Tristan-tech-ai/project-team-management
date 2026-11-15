{{-- One dialog for every quick add button on the page (U5). --}}
<div class="modal fade" id="quick-add-modal" tabindex="-1" aria-labelledby="quick-add-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" novalidate>
            <div class="modal-header border-0 pb-0"><h2 class="modal-title h5" id="quick-add-title" data-quick-add-title>Tambah</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label" for="qa-name">Nama <span class="text-danger" aria-hidden="true">*</span></label>
                    <input class="form-control" id="qa-name" name="name" required maxlength="200" autocomplete="off" aria-describedby="qa-name-error">
                    <div class="invalid-feedback" id="qa-name-error" data-error-for="name"></div></div>
                <div class="mb-1"><label class="form-label" for="qa-email">Email <span class="muted fw-normal">(opsional)</span></label>
                    <input class="form-control" id="qa-email" name="email" type="email" maxlength="180" autocomplete="off" aria-describedby="qa-email-error">
                    <div class="invalid-feedback" id="qa-email-error" data-error-for="email"></div></div>
                <p class="form-text mb-0">Kode dibuat otomatis dari nama dan status langsung aktif. Detail lain bisa dilengkapi nanti di Master Data.</p>
                <div class="alert alert-danger mt-3 mb-0 d-none" role="alert" data-error-general></div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan dan pilih</button>
            </div>
        </form>
    </div>
</div>
