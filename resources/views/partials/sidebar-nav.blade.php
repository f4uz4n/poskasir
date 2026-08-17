{{-- Shared SVG icons for sidebar --}}
@php
$icon = fn (string $name) => match ($name) {
    'dashboard' => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>',
    'pos' => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M6 8h4M6 12h8M6 16h5"/></svg>',
    'products' => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.3 7 12 12l8.7-5M12 22V12"/></svg>',
    'transactions' => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h6"/></svg>',
    'reports' => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 16l4-6 4 3 5-8"/></svg>',
    'stock' => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M12 22V12"/><path d="M3.3 7 12 12l8.7-5"/></svg>',
    'opname' => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
    'expiry' => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
    'purchase' => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>',
    'piutang' => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
    'hutang' => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>',
    'pl' => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 14l4-4 4 2 5-6"/></svg>',
    'tag' => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>',
    'kasir' => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    'backup' => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>',
    'subscription' => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><path d="M1 10h22"/></svg>',
    'settings' => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9c.3.6.9 1 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg>',
    'price' => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
    'home' => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 10v10h14V10"/></svg>',
    'store' => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9 5.5 4h13L21 9"/><path d="M3 9h18v11H3z"/><path d="M9 20v-6h6v6"/></svg>',
    'code' => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>',
    'wallet' => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="14" rx="2"/><path d="M2 10h20"/><circle cx="16" cy="15" r="1.2"/></svg>',
    'chevron' => '<svg class="nav-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>',
    default => '',
};

$openIf = function (bool $active) {
    return $active ? 'open' : '';
};
@endphp

@if(auth()->user()->isDeveloper())
<details class="nav-dropdown open" data-nav-group="developer" open>
    <summary class="nav-dropdown-toggle" title="Developer">
        <span class="nav-toggle-left">
            {!! $icon('code') !!}
            <span class="nav-label">Developer</span>
        </span>
        {!! $icon('chevron') !!}
    </summary>
    <div class="nav-dropdown-menu">
        <a href="{{ route('developer.dashboard') }}" class="sidebar-link {{ request()->routeIs('developer.dashboard') ? 'active' : '' }}" title="Monitoring Akun">
            {!! $icon('dashboard') !!}<span class="nav-label">Monitoring Akun</span>
        </a>
        <a href="{{ route('developer.payments.index') }}" class="sidebar-link {{ request()->routeIs('developer.payments.*') ? 'active' : '' }}" title="Verifikasi Pembayaran">
            {!! $icon('wallet') !!}<span class="nav-label">Verifikasi Pembayaran</span>
            @php $awaitingProof = \App\Models\Payment::awaitingManualReview()->count(); @endphp
            @if($awaitingProof > 0)
                <span class="ml-auto nav-label px-1.5 py-0.5 rounded-full bg-amber-500 text-white text-[10px] font-bold">{{ $awaitingProof }}</span>
            @endif
        </a>
        <a href="{{ route('developer.plans.index') }}" class="sidebar-link {{ request()->routeIs('developer.plans.*') ? 'active' : '' }}" title="Harga Langganan">
            {!! $icon('price') !!}<span class="nav-label">Harga Langganan</span>
        </a>
        <a href="{{ route('developer.settings.index') }}" class="sidebar-link {{ request()->routeIs('developer.settings.*') ? 'active' : '' }}" title="reCAPTCHA">
            {!! $icon('settings') !!}<span class="nav-label">Mode & Captcha</span>
        </a>
    </div>
</details>
@else

<details class="nav-dropdown {{ $openIf(request()->routeIs('dashboard') || request()->routeIs('pos.*')) }}" data-nav-group="utama" @if(request()->routeIs('dashboard') || request()->routeIs('pos.*')) open @endif>
    <summary class="nav-dropdown-toggle" title="Utama">
        <span class="nav-toggle-left">
            {!! $icon('home') !!}
            <span class="nav-label">Utama</span>
        </span>
        {!! $icon('chevron') !!}
    </summary>
    <div class="nav-dropdown-menu">
        <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" title="Dashboard">
            {!! $icon('dashboard') !!}<span class="nav-label">Dashboard</span>
        </a>
        <a href="{{ route('pos.index') }}" class="sidebar-link {{ request()->routeIs('pos.*') ? 'active' : '' }}" title="Kasir POS">
            {!! $icon('pos') !!}<span class="nav-label">Kasir POS</span>
        </a>
    </div>
</details>

<details class="nav-dropdown {{ $openIf(request()->routeIs(['products.*','purchases.*','suppliers.*','price-tags.*','stock-opname.*','expiry.*','reports.stock'])) }}" data-nav-group="inventori" @if(request()->routeIs(['products.*','purchases.*','suppliers.*','price-tags.*','stock-opname.*','expiry.*','reports.stock'])) open @endif>
    <summary class="nav-dropdown-toggle" title="Inventori">
        <span class="nav-toggle-left">
            {!! $icon('products') !!}
            <span class="nav-label">Inventori</span>
        </span>
        {!! $icon('chevron') !!}
    </summary>
    <div class="nav-dropdown-menu">
        <a href="{{ route('products.index') }}" class="sidebar-link {{ request()->routeIs('products.*') ? 'active' : '' }}" title="Produk">
            {!! $icon('products') !!}<span class="nav-label">Produk</span>
        </a>
        <a href="{{ route('purchases.index') }}" class="sidebar-link {{ request()->routeIs('purchases.*') ? 'active' : '' }}" title="Pembelian">
            {!! $icon('purchase') !!}<span class="nav-label">Pembelian</span>
        </a>
        <a href="{{ route('suppliers.index') }}" class="sidebar-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}" title="Supplier">
            {!! $icon('store') !!}<span class="nav-label">Supplier</span>
        </a>
        <a href="{{ route('price-tags.index') }}" class="sidebar-link {{ request()->routeIs('price-tags.*') ? 'active' : '' }}" title="Label Harga">
            {!! $icon('tag') !!}<span class="nav-label">Label Harga</span>
        </a>
        <a href="{{ route('stock-opname.index') }}" class="sidebar-link {{ request()->routeIs('stock-opname.*') ? 'active' : '' }}" title="Stock Opname">
            {!! $icon('opname') !!}<span class="nav-label">Stock Opname</span>
        </a>
        <a href="{{ route('expiry.index') }}" class="sidebar-link {{ request()->routeIs('expiry.*') ? 'active' : '' }}" title="Monitor Expired">
            {!! $icon('expiry') !!}<span class="nav-label">Monitor Expired</span>
        </a>
        <a href="{{ route('reports.stock') }}" class="sidebar-link {{ request()->routeIs('reports.stock') ? 'active' : '' }}" title="Laporan Stok">
            {!! $icon('stock') !!}<span class="nav-label">Laporan Stok</span>
        </a>
    </div>
</details>

<details class="nav-dropdown {{ $openIf(request()->routeIs(['transactions.*','receivables.*','payables.*'])) }}" data-nav-group="keuangan" @if(request()->routeIs(['transactions.*','receivables.*','payables.*'])) open @endif>
    <summary class="nav-dropdown-toggle" title="Keuangan">
        <span class="nav-toggle-left">
            {!! $icon('wallet') !!}
            <span class="nav-label">Keuangan</span>
        </span>
        {!! $icon('chevron') !!}
    </summary>
    <div class="nav-dropdown-menu">
        <a href="{{ route('transactions.index') }}" class="sidebar-link {{ request()->routeIs('transactions.*') ? 'active' : '' }}" title="Transaksi">
            {!! $icon('transactions') !!}<span class="nav-label">Transaksi</span>
        </a>
        <a href="{{ route('receivables.index') }}" class="sidebar-link {{ request()->routeIs('receivables.*') ? 'active' : '' }}" title="Piutang">
            {!! $icon('piutang') !!}<span class="nav-label">Piutang</span>
        </a>
        <a href="{{ route('payables.index') }}" class="sidebar-link {{ request()->routeIs('payables.*') ? 'active' : '' }}" title="Hutang">
            {!! $icon('hutang') !!}<span class="nav-label">Hutang</span>
        </a>
    </div>
</details>

<details class="nav-dropdown {{ $openIf(request()->routeIs('reports.index') || request()->routeIs('reports.profit-loss')) }}" data-nav-group="laporan" @if(request()->routeIs('reports.index') || request()->routeIs('reports.profit-loss')) open @endif>
    <summary class="nav-dropdown-toggle" title="Laporan">
        <span class="nav-toggle-left">
            {!! $icon('reports') !!}
            <span class="nav-label">Laporan</span>
        </span>
        {!! $icon('chevron') !!}
    </summary>
    <div class="nav-dropdown-menu">
        <a href="{{ route('reports.index') }}" class="sidebar-link {{ request()->routeIs('reports.index') ? 'active' : '' }}" title="Penjualan & HPP">
            {!! $icon('reports') !!}<span class="nav-label">Penjualan & HPP</span>
        </a>
        <a href="{{ route('reports.profit-loss') }}" class="sidebar-link {{ request()->routeIs('reports.profit-loss') ? 'active' : '' }}" title="Laba Rugi">
            {!! $icon('pl') !!}<span class="nav-label">Laba / Rugi</span>
        </a>
    </div>
</details>

<details class="nav-dropdown {{ $openIf(request()->routeIs(['kasir.*','backup.*','subscription.*','settings.*'])) }}" data-nav-group="toko" @if(request()->routeIs(['kasir.*','backup.*','subscription.*','settings.*'])) open @endif>
    <summary class="nav-dropdown-toggle" title="Toko">
        <span class="nav-toggle-left">
            {!! $icon('store') !!}
            <span class="nav-label">Toko</span>
        </span>
        {!! $icon('chevron') !!}
    </summary>
    <div class="nav-dropdown-menu">
        @if(auth()->user()->isStoreOwner() && auth()->user()->hasFeature('multi_kasir'))
        <a href="{{ route('kasir.index') }}" class="sidebar-link {{ request()->routeIs('kasir.*') ? 'active' : '' }}" title="Akun Kasir">
            {!! $icon('kasir') !!}<span class="nav-label">Akun Kasir</span>
        </a>
        @endif
        @if(auth()->user()->isStoreOwner())
        <a href="{{ route('backup.index') }}" class="sidebar-link {{ request()->routeIs('backup.*') ? 'active' : '' }}" title="Backup">
            {!! $icon('backup') !!}<span class="nav-label">Backup</span>
        </a>
        <a href="{{ route('subscription.index') }}" class="sidebar-link {{ request()->routeIs('subscription.*') ? 'active' : '' }}" title="Langganan">
            {!! $icon('subscription') !!}<span class="nav-label">Langganan</span>
        </a>
        @endif
        <a href="{{ route('settings.index') }}" class="sidebar-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" title="Pengaturan">
            {!! $icon('settings') !!}<span class="nav-label">Pengaturan</span>
        </a>
    </div>
</details>

@endif
