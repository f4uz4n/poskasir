<div id="history-detail-modal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/40 p-4">
    <div class="card p-0 w-full max-w-lg max-h-[85vh] flex flex-col overflow-hidden">
        <div class="flex items-center gap-2 px-4 py-3 border-b border-slate-100">
            @if(!empty($showDetailBack))
            <button type="button" id="btn-history-detail-back" class="btn-icon shrink-0" title="Kembali" aria-label="Kembali">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>
            @endif
            <div class="min-w-0 flex-1">
                <h3 id="history-detail-title" class="text-lg font-bold truncate">Detail struk</h3>
                <p id="history-detail-subtitle" class="text-xs text-slate-500 truncate"></p>
            </div>
            <button type="button" id="btn-close-history-detail" class="btn-icon shrink-0" title="Tutup" aria-label="Tutup">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div id="history-detail-body" class="flex-1 overflow-y-auto p-4 text-sm"></div>
        <div id="history-detail-void-panel" class="hidden border-t border-red-100 bg-red-50 p-4 space-y-3">
            <p class="text-sm font-medium text-red-800">Batalkan transaksi — wajib password pimpinan toko</p>
            <textarea id="history-detail-void-reason" rows="2" class="input text-sm" placeholder="Alasan pembatalan"></textarea>
            <input type="password" id="history-detail-void-password" class="input text-sm" placeholder="Password pimpinan toko" autocomplete="current-password">
            <div class="flex gap-2">
                <button type="button" id="btn-history-detail-void-submit" class="btn btn-danger flex-1 text-sm">Konfirmasi batalkan</button>
                <button type="button" id="btn-history-detail-void-cancel" class="btn btn-secondary text-sm">Batal</button>
            </div>
        </div>
        <div class="flex flex-wrap gap-2 p-4 border-t border-slate-100 bg-slate-50">
            <button type="button" id="btn-history-detail-print" class="btn btn-secondary flex-1 min-w-[7rem]">Cetak ulang</button>
            <button type="button" id="btn-history-detail-wa" class="btn btn-primary flex-1 min-w-[7rem]">WhatsApp</button>
            <button type="button" id="btn-history-detail-void" class="btn btn-danger flex-1 min-w-[7rem] hidden">Batalkan</button>
        </div>
    </div>
</div>
