@php
    $activeTab = $activeTab ?? '';
@endphp
<nav class="report-page-tabs flex flex-wrap gap-2 mb-4" aria-label="Tab laporan">
    <a href="{{ route('reports.index') }}"
       class="btn {{ $activeTab === 'sales' ? 'btn-primary' : 'btn-secondary' }}">
        Penjualan &amp; HPP
    </a>
    <a href="{{ route('reports.profit-loss') }}"
       class="btn {{ $activeTab === 'profit-loss' ? 'btn-primary' : 'btn-secondary' }}">
        Laba / Rugi
    </a>
    <a href="{{ route('reports.product-sales') }}"
       class="btn {{ $activeTab === 'product-sales' ? 'btn-primary' : 'btn-secondary' }}">
        Penjualan Product
    </a>
</nav>
