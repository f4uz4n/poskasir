@extends('layouts.app')

@section('title', 'Kasir POS')
@section('heading', 'Kasir POS')
@section('subheading', 'Scan barcode atau pilih produk')

@section('content')
<div id="pos-app"
     data-products='@json($products)'
     data-categories='@json($categories)'
     data-settings='@json($settings)'
     class="pos-shell">

    <div class="pos-products-col space-y-4 min-h-0">
        <div class="card p-4 shrink-0">
            <div class="flex flex-col sm:flex-row gap-3">
                <input id="barcode-input" type="text" class="input" placeholder="Scan barcode (keyboard tidak tampil)" autofocus autocomplete="off" inputmode="none" enterkeyhint="done" data-no-keyboard readonly>
                <input id="product-search" type="text" class="input" placeholder="Cari nama produk..." inputmode="text" enterkeyhint="search">
                <div class="sm:w-48 shrink-0">
                    <select id="category-filter" class="input" data-search="true" data-placeholder="Semua kategori">
                        <option value="">Semua kategori</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                    </select>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2 mt-3 text-xs">
                <span id="printer-status" class="device-badge inline-flex items-center px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 font-medium">
                    Printer: menyiapkan…
                </span>
                <span id="scanner-status" class="device-badge inline-flex items-center px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 font-medium">
                    Scanner: siap
                </span>
                <button type="button" id="btn-reconnect-printer" class="btn btn-secondary text-xs py-1.5 px-3 hidden" title="Sambungkan ulang printer">Sambungkan ulang</button>
                <span id="offline-badge" class="px-2.5 py-1 rounded-full bg-amber-100 text-amber-700 font-medium hidden">Mode Offline</span>
                <div class="ml-auto flex items-center gap-0.5 rounded-lg border border-slate-200 p-0.5 bg-slate-50">
                    <button type="button" id="btn-pos-view-grid" class="pos-view-btn active p-2 rounded-md" title="Tampilan grid dengan foto" aria-label="Tampilan grid">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="3" width="7" height="7" rx="1"></rect>
                            <rect x="14" y="3" width="7" height="7" rx="1"></rect>
                            <rect x="3" y="14" width="7" height="7" rx="1"></rect>
                            <rect x="14" y="14" width="7" height="7" rx="1"></rect>
                        </svg>
                    </button>
                    <button type="button" id="btn-pos-view-list" class="pos-view-btn p-2 rounded-md" title="Tampilan list nama barang" aria-label="Tampilan list">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="8" y1="6" x2="21" y2="6"></line>
                            <line x1="8" y1="12" x2="21" y2="12"></line>
                            <line x1="8" y1="18" x2="21" y2="18"></line>
                            <line x1="3" y1="6" x2="3.01" y2="6"></line>
                            <line x1="3" y1="12" x2="3.01" y2="12"></line>
                            <line x1="3" y1="18" x2="3.01" y2="18"></line>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <div id="product-grid" class="pos-product-grid pos-view-grid grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3 pr-1 min-h-0"></div>
    </div>

    <div class="pos-cart-panel card p-4">
        <div class="pos-cart-head">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-bold text-lg">Keranjang</h2>
                <div class="flex items-center gap-2">
                    <button type="button" id="btn-pos-history" class="btn-icon" title="Riwayat transaksi" aria-label="Riwayat transaksi">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </button>
                    <button type="button" id="btn-clear-cart" class="btn btn-danger text-xs py-1.5 px-3">Kosongkan</button>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2 mb-3">
                <button type="button" class="order-type-btn btn btn-primary py-2 text-sm" data-type="dine_in">Dine In</button>
                <button type="button" class="order-type-btn btn btn-ghost py-2 text-sm" data-type="takeaway">Take Away</button>
            </div>
            <div id="table-number-wrap" class="mb-3">
                <input id="table-number" type="text" class="input py-2 text-sm" placeholder="No. meja (opsional)">
            </div>
        </div>

        <div class="pos-cart-body">
            <div id="cart-list" class="pos-cart-list"></div>

            <div class="pos-cart-footer">
                <div class="pos-cart-summary space-y-1.5 text-sm">
                    <div class="flex justify-between"><span>Subtotal</span><span id="cart-subtotal">Rp 0</span></div>
                    <div class="flex justify-between items-center gap-2">
                        <span>Diskon</span>
                        <div class="relative w-36 sm:w-40">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">Rp</span>
                            <input id="cart-discount" type="text" inputmode="numeric" value="0" class="input text-right py-1.5 pl-8 text-sm" placeholder="0">
                        </div>
                    </div>
                    <div class="flex justify-between"><span>Pajak (<span id="tax-label">0</span>%)</span><span id="cart-tax">Rp 0</span></div>
                    <div class="flex justify-between text-base sm:text-lg font-extrabold pt-1"><span>Total</span><span id="cart-total">Rp 0</span></div>
                </div>

                <div class="pos-cart-pay space-y-2">
                    <input id="customer-name" type="text" class="input py-2 text-sm" placeholder="Nama pelanggan (opsional)">
                    <div class="grid grid-cols-2 gap-2">
                        <select id="payment-method" class="input py-2 text-sm">
                            <option value="cash">Tunai</option>
                            <option value="qris">QRIS</option>
                            <option value="transfer">Transfer</option>
                            <option value="card">Kartu</option>
                            <option value="credit">Piutang</option>
                        </select>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">Rp</span>
                            <input id="paid-amount" type="text" inputmode="numeric" class="input pl-8 py-2 text-sm text-right font-semibold" placeholder="0">
                        </div>
                    </div>
                    <div class="pos-quick-pay flex flex-wrap gap-1.5">
                        <button type="button" id="btn-pay-exact" class="btn btn-ghost text-xs py-1 px-2">Uang pas</button>
                        <button type="button" class="btn-quick-pay btn btn-ghost text-xs py-1 px-2" data-amount="10000">10rb</button>
                        <button type="button" class="btn-quick-pay btn btn-ghost text-xs py-1 px-2" data-amount="20000">20rb</button>
                        <button type="button" class="btn-quick-pay btn btn-ghost text-xs py-1 px-2" data-amount="50000">50rb</button>
                        <button type="button" class="btn-quick-pay btn btn-ghost text-xs py-1 px-2" data-amount="100000">100rb</button>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span>Kembalian</span>
                        <span id="change-amount" class="font-semibold">Rp 0</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="pos-cart-action">
            <button type="button" id="btn-checkout" class="btn btn-primary w-full py-3 text-base shadow-sm">Bayar & Cetak</button>
        </div>
    </div>
</div>

<div id="checkout-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="card p-6 w-full max-w-md">
        <h3 class="text-xl font-bold mb-1">Transaksi berhasil</h3>
        <p id="checkout-message" class="text-slate-500 text-sm mb-4"></p>
        <div class="flex gap-2">
            <button type="button" id="btn-reprint" class="btn btn-secondary flex-1">Cetak ulang</button>
            <button type="button" id="btn-close-modal" class="btn btn-primary flex-1">Tutup</button>
        </div>
    </div>
</div>

<div id="history-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="card p-0 w-full max-w-lg max-h-[85vh] flex flex-col overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
            <div>
                <h3 class="text-lg font-bold">Riwayat transaksi</h3>
                <p class="text-xs text-slate-500">Klik nomor struk untuk detail, cetak ulang, batalkan, atau bagikan WhatsApp</p>
                @if(auth()->user()->canAccessArea('void'))
                <a href="{{ route('transactions.void.create') }}" class="text-xs text-red-600 font-medium mt-1 inline-block">Batalkan transaksi (nomor struk)</a>
                @endif
            </div>
            <button type="button" id="btn-close-history" class="btn-icon" title="Tutup" aria-label="Tutup">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div id="history-list" class="flex-1 overflow-y-auto p-3 text-sm">
            <p class="text-slate-400 text-center py-8">Memuat...</p>
        </div>
    </div>
</div>

@include('partials.transaction-detail-modal', ['showDetailBack' => true])
@endsection
