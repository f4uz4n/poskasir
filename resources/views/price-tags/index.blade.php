@extends('layouts.app')

@section('title', 'Label Harga')
@section('heading', 'Cetak Label Harga')
@section('subheading', 'Pilih produk, atur jumlah, lalu cetak atau unduh PDF (5,5 × 3,5 cm)')

@section('content')
<form method="GET" class="card p-4 mb-4 space-y-3">
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <input type="text" name="q" value="{{ $q }}" class="input" placeholder="Cari produk / barcode">
        <select name="category_id" class="input" data-search="true" data-placeholder="Semua kategori">
            <option value="">Semua kategori</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" @selected($categoryId == $cat->id)>{{ $cat->name }}</option>
            @endforeach
        </select>
        <select name="sort" class="input" data-no-select2>
            <option value="name_asc" @selected(($sort ?? 'name_asc') === 'name_asc')>Urut: Nama A→Z</option>
            <option value="name_desc" @selected(($sort ?? '') === 'name_desc')>Urut: Nama Z→A</option>
            <option value="date_desc" @selected(($sort ?? '') === 'date_desc')>Urut: Input terbaru</option>
            <option value="date_asc" @selected(($sort ?? '') === 'date_asc')>Urut: Input terlama</option>
        </select>
        <button class="btn btn-secondary">Terapkan filter</button>
    </div>
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
        <div>
            <label class="text-xs font-medium text-slate-600">Tanggal input dari</label>
            <input type="date" name="date_from" value="{{ $dateFrom }}" class="input mt-1">
        </div>
        <div>
            <label class="text-xs font-medium text-slate-600">Tanggal input sampai</label>
            <input type="date" name="date_to" value="{{ $dateTo }}" class="input mt-1">
        </div>
        <p class="text-xs text-slate-500 sm:col-span-2 lg:col-span-2">
            Filter berdasarkan tanggal produk pertama kali ditambahkan ke sistem.
        </p>
    </div>
</form>

<form method="POST" action="{{ route('price-tags.print') }}" target="_blank" id="price-tag-form">
    @csrf
    <input type="hidden" name="sort" value="{{ $sort ?? 'name_asc' }}">
    <div class="card p-4 sm:p-5 mb-4 flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
        <div class="text-sm text-slate-600">
            Ukuran label tetap: <strong>5,5 cm × 3,5 cm</strong> (A4)
        </div>
        <div class="flex flex-wrap items-center gap-2 sm:gap-3 ml-auto">
            <span class="text-sm text-slate-500" id="selected-count">0 dipilih</span>
            <button type="submit" name="output" value="print" class="btn btn-secondary" formaction="{{ route('price-tags.print') }}">Cetak label</button>
            <button type="submit" name="output" value="pdf" class="btn btn-primary" formaction="{{ route('price-tags.pdf') }}" formtarget="_self">Unduh PDF</button>
        </div>
    </div>

    <div class="flex items-center justify-between mb-3">
        <label class="price-opt-check">
            <input type="checkbox" id="check-all">
            <span class="price-opt-box"></span>
            <span class="text-sm font-medium">Pilih semua di halaman ini</span>
        </label>
    </div>

    <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-3">
        @forelse($products as $p)
            <label class="price-pick-card" data-product-card>
                <div class="price-pick-top">
                    <input type="checkbox" class="product-check sr-only" name="product_ids[]" value="{{ $p->id }}">
                    <span class="price-pick-check" aria-hidden="true"></span>
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold text-sm leading-snug truncate">{{ $p->name }}</div>
                        <div class="text-xs text-slate-500 truncate">{{ $p->category?->name ?? 'Tanpa kategori' }} · Input {{ $p->created_at?->format('d/m/Y') ?? '-' }}</div>
                    </div>
                    <div class="text-brand-700 font-extrabold text-sm whitespace-nowrap">
                        Rp {{ number_format($p->price, 0, ',', '.') }}
                    </div>
                </div>
                <div class="price-pick-copies" onclick="event.preventDefault()">
                    <span class="text-xs text-slate-500">Jumlah cetak</span>
                    <div class="qty-stepper">
                        <button type="button" class="qty-btn" data-qty-minus aria-label="Kurangi">−</button>
                        <input type="number" min="1" max="50" name="copies[{{ $p->id }}]" value="1" class="qty-input copies-input" readonly>
                        <button type="button" class="qty-btn" data-qty-plus aria-label="Tambah">+</button>
                    </div>
                </div>
            </label>
        @empty
            <div class="card p-10 text-center text-slate-500 sm:col-span-2 xl:col-span-3">Tidak ada produk.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $products->links() }}</div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const cards = () => [...document.querySelectorAll('[data-product-card]')];
    const checks = () => [...document.querySelectorAll('.product-check')];
    const countEl = document.getElementById('selected-count');

    function syncCard(card) {
        const checked = card.querySelector('.product-check')?.checked;
        card.classList.toggle('is-selected', !!checked);
    }

    function syncCount() {
        const n = checks().filter(c => c.checked).length;
        if (countEl) countEl.textContent = n + ' dipilih';
        cards().forEach(syncCard);
    }

    document.getElementById('check-all')?.addEventListener('change', function () {
        checks().forEach(c => { c.checked = this.checked; });
        syncCount();
    });

    document.addEventListener('change', (e) => {
        if (e.target.classList.contains('product-check')) syncCount();
    });

    document.addEventListener('click', (e) => {
        const minus = e.target.closest('[data-qty-minus]');
        const plus = e.target.closest('[data-qty-plus]');
        if (!minus && !plus) return;
        e.preventDefault();
        e.stopPropagation();
        const wrap = e.target.closest('.qty-stepper');
        const input = wrap?.querySelector('.qty-input');
        if (!input) return;
        let val = Number(input.value || 1);
        if (minus) val = Math.max(1, val - 1);
        if (plus) val = Math.min(50, val + 1);
        input.value = val;

        const card = e.target.closest('[data-product-card]');
        const check = card?.querySelector('.product-check');
        if (check && !check.checked) {
            check.checked = true;
            syncCount();
        }
    });

    document.getElementById('price-tag-form')?.addEventListener('submit', (e) => {
        if (!checks().some(c => c.checked)) {
            e.preventDefault();
            alert('Pilih minimal satu produk.');
        }
    });

    syncCount();
})();
</script>
@endpush
