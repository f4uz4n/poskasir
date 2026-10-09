@extends('layouts.app')

@section('title', 'Penjualan Product')
@section('heading', 'Penjualan Product')
@section('subheading', 'Penjualan produk per periode + checklist belanja (produk merah tercentang otomatis)')

@section('content')
@include('reports._tabs', ['activeTab' => 'product-sales'])

@php
    $dateFrom = $dateFrom ?? $date;
    $dateTo = $dateTo ?? $date;
    $rangeLabel = $dateFrom === $dateTo
        ? \Carbon\Carbon::parse($dateFrom)->format('d M Y')
        : \Carbon\Carbon::parse($dateFrom)->format('d M Y').' – '.\Carbon\Carbon::parse($dateTo)->format('d M Y');
@endphp

<form method="GET" class="card p-4 mb-4 grid sm:grid-cols-2 xl:grid-cols-7 gap-3" id="product-sales-filter">
    <div>
        <label class="text-xs text-slate-500">Dari tanggal</label>
        <input type="date" name="date_from" value="{{ $dateFrom }}" class="input mt-1">
    </div>
    <div>
        <label class="text-xs text-slate-500">Sampai tanggal</label>
        <input type="date" name="date_to" value="{{ $dateTo }}" class="input mt-1">
    </div>
    <div>
        <label class="text-xs text-slate-500">Lookback rata-rata (hari)</label>
        <input type="number" name="lookback_days" min="1" max="90" value="{{ $lookbackDays }}" class="input mt-1">
    </div>
    <div>
        <label class="text-xs text-slate-500">Target stok (hari)</label>
        <input type="number" name="coverage_days" min="1" max="90" value="{{ $coverageDays }}" class="input mt-1">
    </div>
    <div>
        <label class="text-xs text-slate-500">Prioritas</label>
        <select name="priority" class="input mt-1">
            <option value="">Semua</option>
            <option value="urgent" @selected($priority === 'urgent')>Segera (merah)</option>
            <option value="high" @selected($priority === 'high')>Tinggi</option>
            <option value="medium" @selected($priority === 'medium')>Sedang</option>
            <option value="low" @selected($priority === 'low')>Rendah</option>
            <option value="ok" @selected($priority === 'ok')>Cukup</option>
        </select>
    </div>
    <div>
        <label class="text-xs text-slate-500">Per halaman</label>
        <select name="per_page" class="input mt-1">
            @foreach([10, 25, 50, 100] as $n)
                <option value="{{ $n }}" @selected(($perPage ?? 25) == $n)>{{ $n }}</option>
            @endforeach
        </select>
    </div>
    <div class="flex flex-col sm:flex-row items-stretch sm:items-end gap-2">
        <button type="submit" class="btn btn-primary flex-1">Filter</button>
        <a href="#" id="btn-export-excel" data-base="{{ route('reports.product-sales.export.excel', request()->except(['selected', 'page'])) }}" class="btn btn-secondary flex-1 text-center">Excel</a>
        <a href="#" id="btn-export-pdf" data-base="{{ route('reports.product-sales.export.pdf', request()->except(['selected', 'page'])) }}" class="btn btn-secondary flex-1 text-center">PDF</a>
    </div>
</form>

<p class="text-xs text-slate-500 mb-4">
    Menampilkan penjualan periode <strong>{{ $rangeLabel }}</strong>.
    Saran beli dihitung dari rata-rata {{ $lookbackDays }} hari ({{ \Carbon\Carbon::parse($lookbackFrom)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}) × target {{ $coverageDays }} hari − stok.
    Checklist default: produk prioritas <span class="text-red-600 font-semibold">merah / segera</span>. Centang tersimpan saat pindah halaman.
</p>

<div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="card p-5">
        <div class="text-sm text-slate-500">SKU terjual periode ini</div>
        <div class="text-2xl font-extrabold mt-1">{{ number_format($summary['sku_sold_today'], 0, ',', '.') }}</div>
        <div class="text-xs text-slate-400 mt-1">Qty {{ number_format($summary['total_sold_qty'], 0, ',', '.') }} · Rp {{ number_format($summary['total_sold_sales'], 0, ',', '.') }}</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Prioritas segera (merah)</div>
        <div class="text-2xl font-extrabold mt-1 text-red-600">{{ $summary['urgent'] }}</div>
        <div class="text-xs text-slate-400 mt-1">Tinggi {{ $summary['high'] }} · Sedang {{ $summary['medium'] }} · Rendah {{ $summary['low'] }}</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Dicentang untuk dibeli</div>
        <div class="text-2xl font-extrabold mt-1 text-brand-700" id="checked-count">{{ number_format($summary['checked_default_count'], 0, ',', '.') }}</div>
        <div class="text-xs text-slate-400 mt-1">Qty <span id="checked-qty">{{ number_format($summary['checked_default_qty'], 0, ',', '.') }}</span></div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Est. biaya tercentang</div>
        <div class="text-2xl font-extrabold mt-1 text-amber-700" id="checked-cost">Rp {{ number_format($summary['checked_default_cost'], 0, ',', '.') }}</div>
        <div class="text-xs text-slate-400 mt-1">Total saran semua: Rp {{ number_format($summary['total_est_cost'], 0, ',', '.') }}</div>
    </div>
</div>

<div class="card p-5 overflow-x-auto"
     id="product-sales-panel"
     data-storage-key="product-sales-checked:{{ $dateFrom }}:{{ $dateTo }}:{{ md5(($q ?? '').'|'.($priority ?? '').'|'.$lookbackDays.'|'.$coverageDays) }}"
     data-default-checked='@json($defaultCheckedIds)'
     data-items-meta='@json($itemsMeta)'>
    <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
        <div class="min-w-0">
            <h2 class="font-bold">Daftar penjualan product — {{ $rangeLabel }}</h2>
            <p class="text-xs text-slate-500 mt-1">Centang produk yang akan dibelanjakan. Produk merah tercentang otomatis.</p>
        </div>
        <form method="GET" class="flex flex-wrap items-end gap-2" id="product-search-form">
            @foreach(request()->except(['q', 'page']) as $key => $value)
                @if(is_array($value))
                    @foreach($value as $v)
                        <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                    @endforeach
                @else
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            <div>
                <label class="text-xs text-slate-500">Cari di checklist</label>
                <input type="search" name="q" value="{{ $q }}" class="input mt-1 min-w-[14rem]" placeholder="Nama / SKU / barcode" autofocus>
            </div>
            <button type="submit" class="btn btn-primary">Cari</button>
            @if($q)
                <a href="{{ route('reports.product-sales', request()->except(['q', 'page'])) }}" class="btn btn-ghost">Reset</a>
            @endif
        </form>
    </div>

    <div class="flex flex-wrap gap-2 mb-4">
        <button type="button" class="btn btn-ghost text-sm" id="btn-check-red">Centang merah</button>
        <button type="button" class="btn btn-ghost text-sm" id="btn-check-all">Centang semua (filter)</button>
        <button type="button" class="btn btn-ghost text-sm" id="btn-uncheck-all">Hapus centang</button>
        @if($q)
            <span class="text-xs text-slate-500 self-center">Hasil pencarian: <strong>{{ $items->total() }}</strong> produk</span>
        @endif
    </div>

    <table class="w-full text-sm" id="product-sales-table">
        <thead>
            <tr class="text-left text-slate-500 border-b">
                <th class="py-2 pr-2 w-10">
                    <input type="checkbox" id="check-all" class="align-middle" title="Centang semua di halaman ini">
                </th>
                <th class="py-2 pr-2">Prioritas</th>
                <th class="py-2 pr-2">Produk</th>
                <th class="py-2 pr-2 text-right">Stok</th>
                <th class="py-2 pr-2 text-right">Terjual hari ini</th>
                <th class="py-2 pr-2 text-right">Rata/hari</th>
                <th class="py-2 pr-2 text-right">Sisa hari</th>
                <th class="py-2 pr-2 text-right">Qty saran</th>
                <th class="py-2 text-right">Est. biaya</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $row)
                @php
                    $badge = match($row->priority) {
                        'urgent' => 'bg-red-100 text-red-700',
                        'high' => 'bg-orange-100 text-orange-700',
                        'medium' => 'bg-amber-100 text-amber-700',
                        'low' => 'bg-slate-100 text-slate-600',
                        default => 'bg-emerald-100 text-emerald-700',
                    };
                @endphp
                <tr class="border-b border-slate-100 align-top {{ $row->priority === 'urgent' ? 'bg-red-50/40' : '' }}"
                    data-product-id="{{ $row->product_id }}">
                    <td class="py-2.5 pr-2">
                        <input type="checkbox"
                               class="buy-check align-middle"
                               value="{{ $row->product_id }}"
                               data-qty="{{ $row->recommend_qty }}"
                               data-cost="{{ $row->est_cost }}"
                               data-red="{{ $row->priority === 'urgent' ? '1' : '0' }}">
                    </td>
                    <td class="py-2.5 pr-2">
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $badge }}">{{ $row->priority_label }}</span>
                    </td>
                    <td class="py-2.5 pr-2">
                        <div class="font-medium">{{ $row->name }}</div>
                        <div class="text-xs text-slate-400">
                            {{ $row->category ?: 'Tanpa kategori' }}
                            @if($row->sku) · {{ $row->sku }} @endif
                        </div>
                    </td>
                    <td class="py-2.5 pr-2 text-right font-semibold {{ $row->stock <= 0 ? 'text-red-600' : '' }}">
                        {{ number_format($row->stock, 0, ',', '.') }}
                        <div class="text-[11px] text-slate-400 font-normal">{{ $row->unit }}</div>
                    </td>
                    <td class="py-2.5 pr-2 text-right font-semibold">{{ number_format($row->sold_qty, 0, ',', '.') }}</td>
                    <td class="py-2.5 pr-2 text-right">{{ number_format($row->avg_daily, 2, ',', '.') }}</td>
                    <td class="py-2.5 pr-2 text-right">
                        @if($row->days_left === null)
                            <span class="text-slate-400">∞</span>
                        @else
                            {{ number_format($row->days_left, 1, ',', '.') }}
                        @endif
                    </td>
                    <td class="py-2.5 pr-2 text-right font-bold {{ $row->recommend_qty > 0 ? 'text-brand-700' : 'text-slate-400' }}">
                        {{ number_format($row->recommend_qty, 0, ',', '.') }}
                    </td>
                    <td class="py-2.5 text-right">
                        @if($row->recommend_qty > 0)
                            Rp {{ number_format($row->est_cost, 0, ',', '.') }}
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="py-10 text-center text-slate-500">
                        @if($q)
                            Tidak ada produk cocok untuk pencarian “{{ $q }}”.
                        @else
                            Tidak ada penjualan product pada periode ini. Ubah rentang tanggal atau pastikan produk sudah terjual.
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if($items->total() > 0)
            <tfoot>
                <tr class="border-t-2 border-slate-200 font-bold bg-slate-50">
                    <td class="py-2.5 pr-2" colspan="7">Total tercentang untuk dibeli (semua halaman)</td>
                    <td class="py-2.5 pr-2 text-right text-brand-700" id="foot-qty">{{ number_format($summary['checked_default_qty'], 0, ',', '.') }}</td>
                    <td class="py-2.5 text-right text-amber-700" id="foot-cost">Rp {{ number_format($summary['checked_default_cost'], 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    @if($items->hasPages() || $items->total() > 0)
        <div class="mt-4">{{ $items->withQueryString()->links() }}</div>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    const panel = document.getElementById('product-sales-panel');
    if (!panel) return;

    const storageKey = panel.dataset.storageKey;
    const defaultChecked = JSON.parse(panel.dataset.defaultChecked || '[]').map(String);
    const itemsMeta = JSON.parse(panel.dataset.itemsMeta || '[]');
    const metaById = Object.fromEntries(itemsMeta.map((m) => [String(m.id), m]));
    const fmt = (n) => new Intl.NumberFormat('id-ID').format(Math.round(n || 0));
    const checks = () => Array.from(document.querySelectorAll('.buy-check'));

    function loadSelected() {
        try {
            const raw = sessionStorage.getItem(storageKey);
            if (raw === null) return new Set(defaultChecked);
            return new Set(JSON.parse(raw).map(String));
        } catch (e) {
            return new Set(defaultChecked);
        }
    }

    function saveSelected(set) {
        sessionStorage.setItem(storageKey, JSON.stringify(Array.from(set)));
    }

    let selected = loadSelected();

    function applyPageChecks() {
        checks().forEach((c) => {
            c.checked = selected.has(String(c.value));
        });
    }

    function syncFromPage() {
        checks().forEach((c) => {
            const id = String(c.value);
            if (c.checked) selected.add(id);
            else selected.delete(id);
        });
        saveSelected(selected);
        refreshSummary();
    }

    function refreshSummary() {
        let qty = 0;
        let cost = 0;
        selected.forEach((id) => {
            const m = metaById[id];
            if (!m) return;
            qty += Number(m.qty || 0);
            cost += Number(m.cost || 0);
        });

        const countEl = document.getElementById('checked-count');
        const qtyEl = document.getElementById('checked-qty');
        const costEl = document.getElementById('checked-cost');
        const footQty = document.getElementById('foot-qty');
        const footCost = document.getElementById('foot-cost');
        const checkAll = document.getElementById('check-all');
        const pageChecks = checks();
        const pageSelected = pageChecks.filter((c) => c.checked).length;

        if (countEl) countEl.textContent = fmt(selected.size);
        if (qtyEl) qtyEl.textContent = fmt(qty);
        if (costEl) costEl.textContent = 'Rp ' + fmt(cost);
        if (footQty) footQty.textContent = fmt(qty);
        if (footCost) footCost.textContent = 'Rp ' + fmt(cost);
        if (checkAll) {
            checkAll.checked = pageChecks.length > 0 && pageSelected === pageChecks.length;
            checkAll.indeterminate = pageSelected > 0 && pageSelected < pageChecks.length;
        }

        updateExportLinks();
    }

    function withSelected(base) {
        const url = new URL(base, window.location.origin);
        const ids = Array.from(selected);
        if (ids.length) url.searchParams.set('selected', ids.join(','));
        else url.searchParams.delete('selected');
        return url.pathname + url.search;
    }

    function updateExportLinks() {
        ['btn-export-excel', 'btn-export-pdf'].forEach((id) => {
            const el = document.getElementById(id);
            if (!el) return;
            el.href = withSelected(el.dataset.base);
        });
    }

    document.getElementById('check-all')?.addEventListener('change', (e) => {
        checks().forEach((c) => { c.checked = e.target.checked; });
        syncFromPage();
    });

    document.getElementById('btn-check-red')?.addEventListener('click', () => {
        selected = new Set(itemsMeta.filter((m) => m.red).map((m) => String(m.id)));
        saveSelected(selected);
        applyPageChecks();
        refreshSummary();
    });

    document.getElementById('btn-check-all')?.addEventListener('click', () => {
        selected = new Set(itemsMeta.map((m) => String(m.id)));
        saveSelected(selected);
        applyPageChecks();
        refreshSummary();
    });

    document.getElementById('btn-uncheck-all')?.addEventListener('click', () => {
        selected = new Set();
        saveSelected(selected);
        applyPageChecks();
        refreshSummary();
    });

    checks().forEach((c) => c.addEventListener('change', syncFromPage));
    applyPageChecks();
    refreshSummary();
})();
</script>
@endpush
