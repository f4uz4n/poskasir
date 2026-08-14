@extends('layouts.app')

@section('title', 'Monitor Expired')
@section('heading', 'Monitor Produk Expired')
@section('subheading', 'Pantau produk kedaluwarsa dan yang hampir expired')

@section('content')
<div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="card p-5">
        <div class="text-sm text-slate-500">Produk ber-expired</div>
        <div class="text-2xl font-extrabold mt-1">{{ number_format($summary['total'], 0, ',', '.') }}</div>
        <div class="text-xs text-slate-400 mt-1">Aman {{ $summary['safe'] }} SKU</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Sudah expired</div>
        <div class="text-2xl font-extrabold mt-1 text-red-600">{{ $summary['expired'] }}</div>
        <div class="text-xs text-slate-400 mt-1">
            Qty {{ number_format($summary['expired_qty'], 0, ',', '.') }}
            · Rp {{ number_format($summary['expired_value'], 0, ',', '.') }}
        </div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Hampir expired ({{ $days }} hari)</div>
        <div class="text-2xl font-extrabold mt-1 text-amber-600">{{ $summary['near'] }}</div>
        <div class="text-xs text-slate-400 mt-1">
            Qty {{ number_format($summary['near_qty'], 0, ',', '.') }}
            · Rp {{ number_format($summary['near_value'], 0, ',', '.') }}
        </div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Perlu perhatian</div>
        <div class="text-2xl font-extrabold mt-1">{{ $summary['expired'] + $summary['near'] }}</div>
        <div class="text-xs text-slate-400 mt-1">Expired + hampir expired</div>
    </div>
</div>

<div class="card p-4 sm:p-5 mb-4">
    <form method="GET" class="grid sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <input type="text" name="q" value="{{ $q }}" class="input" placeholder="Cari produk / barcode">
        <select name="category_id" class="input" data-search="true" data-placeholder="Semua kategori">
            <option value="">Semua kategori</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" @selected($categoryId == $cat->id)>{{ $cat->name }}</option>
            @endforeach
        </select>
        <select name="filter" class="input">
            <option value="attention" @selected($filter === 'attention')>Perlu perhatian</option>
            <option value="expired" @selected($filter === 'expired')>Sudah expired</option>
            <option value="near" @selected($filter === 'near')>Hampir expired</option>
            <option value="safe" @selected($filter === 'safe')>Masih aman</option>
            <option value="all" @selected($filter === 'all')>Semua ber-expired</option>
        </select>
        <select name="days" class="input">
            @foreach([7, 14, 30, 60, 90] as $d)
                <option value="{{ $d }}" @selected($days == $d)>Ambang {{ $d }} hari</option>
            @endforeach
        </select>
        <button class="btn btn-primary">Filter</button>
    </form>
</div>

<div class="card p-4 sm:p-5">
    <div class="overflow-x-auto -mx-4 sm:mx-0">
        <table class="w-full text-sm min-w-[760px]">
            <thead>
                <tr class="text-left text-slate-500 border-b">
                    <th class="py-2 px-4 sm:px-2">Produk</th>
                    <th class="py-2 pr-2 text-right">Stok</th>
                    <th class="py-2 pr-2 text-right">Nilai HPP</th>
                    <th class="py-2 pr-2">Tanggal expired</th>
                    <th class="py-2 pr-2">Sisa hari</th>
                    <th class="py-2 pr-4 sm:pr-2">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $p)
                    @php
                        $exp = $p->expired_at->copy()->startOfDay();
                        $diff = (int) round(now()->startOfDay()->diffInDays($exp, false));
                        if ($diff < 0) {
                            $status = 'expired';
                            $label = 'Expired';
                            $badge = 'bg-red-100 text-red-700';
                            $daysLabel = abs($diff).' hari lalu';
                        } elseif ($diff <= $days) {
                            $status = 'near';
                            $label = 'Hampir';
                            $badge = 'bg-amber-100 text-amber-700';
                            $daysLabel = $diff === 0 ? 'Hari ini' : $diff.' hari lagi';
                        } else {
                            $status = 'safe';
                            $label = 'Aman';
                            $badge = 'bg-emerald-100 text-emerald-700';
                            $daysLabel = $diff.' hari lagi';
                        }
                    @endphp
                    <tr class="border-b border-slate-100">
                        <td class="py-3 px-4 sm:px-2">
                            <div class="font-semibold">{{ $p->name }}</div>
                            <div class="text-xs text-slate-500">{{ $p->category?->name ?? '-' }} · {{ $p->barcode ?: $p->sku ?: '-' }}</div>
                        </td>
                        <td class="py-3 pr-2 text-right font-semibold {{ $status === 'expired' ? 'text-red-600' : '' }}">{{ $p->stock }}</td>
                        <td class="py-3 pr-2 text-right">Rp {{ number_format($p->stock * $p->cost, 0, ',', '.') }}</td>
                        <td class="py-3 pr-2 font-medium {{ $status === 'expired' ? 'text-red-600' : ($status === 'near' ? 'text-amber-600' : '') }}">
                            {{ $p->expired_at->format('d/m/Y') }}
                        </td>
                        <td class="py-3 pr-2 text-xs {{ $status === 'expired' ? 'text-red-600' : ($status === 'near' ? 'text-amber-600' : 'text-slate-500') }}">
                            {{ $daysLabel }}
                        </td>
                        <td class="py-3 pr-4 sm:pr-2">
                            <span class="px-2 py-1 rounded-full text-xs font-medium {{ $badge }}">{{ $label }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-10 text-center text-slate-500">
                            Tidak ada produk dengan tanggal expired pada filter ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $products->links() }}</div>
</div>
@endsection
