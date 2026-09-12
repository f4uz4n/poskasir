@extends('layouts.app')

@section('title', 'Laporan Stok')
@section('heading', 'Laporan Stok')
@section('subheading', 'Persediaan saat ini + pergerakan dari penjualan selesai & pembelian supplier')

@section('content')
<div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="card p-5">
        <div class="text-sm text-slate-500">SKU berstok (aktif)</div>
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
    <form method="GET" class="grid sm:grid-cols-2 lg:grid-cols-6 gap-3">
        <div>
            <label class="text-xs text-slate-500">Dari (pergerakan)</label>
            <input type="date" name="from" value="{{ $from }}" class="input mt-1">
        </div>
        <div>
            <label class="text-xs text-slate-500">Sampai</label>
            <input type="date" name="to" value="{{ $to }}" class="input mt-1">
        </div>
        <input type="text" name="q" value="{{ $q }}" class="input lg:mt-5" placeholder="Cari produk / barcode">
        <select name="category_id" class="input lg:mt-5" data-search="true" data-placeholder="Semua kategori">
            <option value="">Semua kategori</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" @selected($categoryId == $cat->id)>{{ $cat->name }}</option>
            @endforeach
        </select>
        <select name="filter" class="input lg:mt-5">
            <option value="">Semua stok</option>
            <option value="low" @selected($filter === 'low')>Stok menipis</option>
            <option value="out" @selected($filter === 'out')>Stok habis</option>
            <option value="expired" @selected($filter === 'expired')>Sudah expired</option>
            <option value="near_expired" @selected($filter === 'near_expired')>Hampir expired</option>
        </select>
        <div class="flex gap-2 lg:mt-5">
            <button class="btn btn-primary flex-1">Filter</button>
            <a href="{{ route('stock-opname.create') }}" class="btn btn-secondary flex-1 whitespace-nowrap">Opname</a>
        </div>
    </form>
</div>

<div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="card p-5">
        <div class="text-sm text-slate-500">Qty terjual (periode)</div>
        <div class="text-2xl font-extrabold mt-1 text-red-600">−{{ number_format($movements['sold_qty'], 0, ',', '.') }}</div>
        <div class="text-xs text-slate-400 mt-1">HPP keluar Rp {{ number_format($movements['sold_hpp'], 0, ',', '.') }}</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Qty beli supplier</div>
        <div class="text-2xl font-extrabold mt-1 text-emerald-700">+{{ number_format($movements['purchased_qty'], 0, ',', '.') }}</div>
        <div class="text-xs text-slate-400 mt-1">Nilai beli Rp {{ number_format($movements['purchased_value'], 0, ',', '.') }}</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Net pergerakan qty</div>
        <div class="text-2xl font-extrabold mt-1 {{ $movements['net_qty'] >= 0 ? 'text-emerald-700' : 'text-red-600' }}">
            {{ $movements['net_qty'] >= 0 ? '+' : '' }}{{ number_format($movements['net_qty'], 0, ',', '.') }}
        </div>
        <div class="text-xs text-slate-400 mt-1">Beli − jual (tanpa opname)</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Catatan</div>
        <div class="text-xs text-slate-600 mt-2 leading-relaxed">
            Hanya penjualan <strong>selesai</strong> (void sudah dikembalikan ke stok, tidak mengurangi).
            Stok aktual juga dipengaruhi opname.
        </div>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-4 mb-6">
    <div class="card p-4 sm:p-5">
        <h2 class="font-bold mb-3">Pembelian per supplier</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b">
                        <th class="py-2">Supplier</th>
                        <th class="py-2 text-right">Nota</th>
                        <th class="py-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($purchasesBySupplier as $row)
                        <tr class="border-b border-slate-100">
                            <td class="py-2 font-medium">{{ $row->supplier }}</td>
                            <td class="py-2 text-right">{{ $row->purchase_count }}</td>
                            <td class="py-2 text-right">Rp {{ number_format($row->total, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-6 text-center text-slate-500">Belum ada pembelian.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card p-4 sm:p-5">
        <h2 class="font-bold mb-3">Produk paling banyak terjual</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b">
                        <th class="py-2">Produk</th>
                        <th class="py-2 text-right">Qty</th>
                        <th class="py-2 text-right">HPP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($topSold as $row)
                        <tr class="border-b border-slate-100">
                            <td class="py-2 font-medium">{{ $row->product_name }}</td>
                            <td class="py-2 text-right">{{ $row->qty }}</td>
                            <td class="py-2 text-right">Rp {{ number_format($row->hpp, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-6 text-center text-slate-500">Belum ada penjualan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card p-4 sm:p-5">
        <h2 class="font-bold mb-3">Produk paling banyak dibeli</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b">
                        <th class="py-2">Produk</th>
                        <th class="py-2 text-right">Qty</th>
                        <th class="py-2 text-right">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($topPurchased as $row)
                        <tr class="border-b border-slate-100">
                            <td class="py-2 font-medium">{{ $row->product_name }}</td>
                            <td class="py-2 text-right">{{ $row->qty }}</td>
                            <td class="py-2 text-right">Rp {{ number_format($row->value, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-6 text-center text-slate-500">Belum ada pembelian item.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card p-4 sm:p-5">
    <h2 class="font-bold mb-3">Stok saat ini</h2>
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
