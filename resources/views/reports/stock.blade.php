@extends('layouts.app')

@section('title', 'Laporan Stok')
@section('heading', 'Laporan Stok')
@section('subheading', 'Nilai persediaan, stok menipis, dan expired')

@section('content')
<div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="card p-5">
        <div class="text-sm text-slate-500">SKU berstok</div>
        <div class="text-2xl font-extrabold mt-1">{{ number_format($summary['sku_count'], 0, ',', '.') }}</div>
        <div class="text-xs text-slate-400 mt-1">Qty {{ number_format($summary['total_qty'], 0, ',', '.') }}</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Nilai stok (HPP)</div>
        <div class="text-2xl font-extrabold mt-1">Rp {{ number_format($summary['stock_value_cost'], 0, ',', '.') }}</div>
        <div class="text-xs text-slate-400 mt-1">Jual Rp {{ number_format($summary['stock_value_sell'], 0, ',', '.') }}</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Stok menipis / habis</div>
        <div class="text-2xl font-extrabold mt-1 text-amber-700">{{ $summary['low_stock'] }} / {{ $summary['out_of_stock'] }}</div>
        <div class="text-xs text-slate-400 mt-1">Menipis ≤ 5 · Habis = 0</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Expired / hampir expired</div>
        <div class="text-2xl font-extrabold mt-1 text-red-600">{{ $summary['expired'] }} / {{ $summary['near_expired'] }}</div>
        <div class="text-xs text-slate-400 mt-1">Hampir = 30 hari ke depan</div>
    </div>
</div>

<div class="card p-4 sm:p-5 mb-4">
    <form method="GET" class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <input type="text" name="q" value="{{ $q }}" class="input" placeholder="Cari produk / barcode">
        <select name="category_id" class="input" data-search="true" data-placeholder="Semua kategori">
            <option value="">Semua kategori</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" @selected($categoryId == $cat->id)>{{ $cat->name }}</option>
            @endforeach
        </select>
        <select name="filter" class="input">
            <option value="">Semua stok</option>
            <option value="low" @selected($filter === 'low')>Stok menipis</option>
            <option value="out" @selected($filter === 'out')>Stok habis</option>
            <option value="expired" @selected($filter === 'expired')>Sudah expired</option>
            <option value="near_expired" @selected($filter === 'near_expired')>Hampir expired</option>
        </select>
        <div class="flex gap-2">
            <button class="btn btn-primary flex-1">Filter</button>
            <a href="{{ route('expiry.index') }}" class="btn btn-secondary flex-1 whitespace-nowrap">Expired</a>
            <a href="{{ route('stock-opname.create') }}" class="btn btn-secondary flex-1 whitespace-nowrap">Opname</a>
        </div>
    </form>
</div>

<div class="card p-4 sm:p-5">
    <div class="overflow-x-auto -mx-4 sm:mx-0">
        <table class="w-full text-sm min-w-[720px]">
            <thead>
                <tr class="text-left text-slate-500 border-b">
                    <th class="py-2 px-4 sm:px-2">Produk</th>
                    <th class="py-2 pr-2 text-right">Stok</th>
                    <th class="py-2 pr-2 text-right">HPP</th>
                    <th class="py-2 pr-2 text-right">Nilai HPP</th>
                    <th class="py-2 pr-2 text-right">Harga jual</th>
                    <th class="py-2 pr-4 sm:pr-2">Expired</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $p)
                    @php
                        $status = 'ok';
                        if ($p->stock <= 0) $status = 'out';
                        elseif ($p->stock <= 5) $status = 'low';
                        if ($p->has_expiry && $p->expired_at && $p->expired_at->isPast()) $status = 'expired';
                    @endphp
                    <tr class="border-b border-slate-100">
                        <td class="py-3 px-4 sm:px-2">
                            <div class="font-semibold">{{ $p->name }}</div>
                            <div class="text-xs text-slate-500">{{ $p->category?->name ?? '-' }} · {{ $p->barcode ?: $p->sku ?: '-' }}</div>
                        </td>
                        <td class="py-3 pr-2 text-right">
                            <span class="font-bold {{ $status === 'out' || $status === 'expired' ? 'text-red-600' : ($status === 'low' ? 'text-amber-600' : '') }}">
                                {{ $p->stock }}
                            </span>
                        </td>
                        <td class="py-3 pr-2 text-right">Rp {{ number_format($p->cost, 0, ',', '.') }}</td>
                        <td class="py-3 pr-2 text-right font-semibold">Rp {{ number_format($p->stock * $p->cost, 0, ',', '.') }}</td>
                        <td class="py-3 pr-2 text-right">Rp {{ number_format($p->price, 0, ',', '.') }}</td>
                        <td class="py-3 pr-4 sm:pr-2 text-xs">
                            @if($p->has_expiry && $p->expired_at)
                                <span class="{{ $p->expired_at->isPast() ? 'text-red-600 font-semibold' : ($p->expired_at->lte(now()->addDays(30)) ? 'text-amber-600' : 'text-slate-600') }}">
                                    {{ $p->expired_at->format('d/m/Y') }}
                                </span>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-10 text-center text-slate-500">Tidak ada data stok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $products->links() }}</div>
</div>
@endsection
