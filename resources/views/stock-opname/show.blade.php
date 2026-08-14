@extends('layouts.app')

@section('title', $stockOpname->code)
@section('heading', 'Detail Stock Opname')
@section('subheading', $stockOpname->code.' · '.$stockOpname->title)

@section('content')
<div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="card p-5">
        <div class="text-sm text-slate-500">Status</div>
        <div class="text-xl font-extrabold mt-1 uppercase">{{ $stockOpname->status }}</div>
        <div class="text-xs text-slate-400 mt-1">{{ optional($stockOpname->completed_at)->format('d/m/Y H:i') ?: $stockOpname->created_at->format('d/m/Y H:i') }}</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Item / cocok</div>
        <div class="text-xl font-extrabold mt-1">{{ $stats['items'] }} / {{ $stats['match'] }}</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Lebih / kurang</div>
        <div class="text-xl font-extrabold mt-1"><span class="text-emerald-600">+{{ $stats['plus'] }}</span> / <span class="text-red-600">-{{ $stats['minus'] }}</span></div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Dampak nilai HPP</div>
        <div class="text-xl font-extrabold mt-1 {{ $stats['value_diff'] >= 0 ? 'text-emerald-700' : 'text-red-600' }}">
            Rp {{ number_format($stats['value_diff'], 0, ',', '.') }}
        </div>
    </div>
</div>

<div class="card p-4 sm:p-5 mb-4">
    <div class="text-sm text-slate-600 space-y-1">
        <div><strong>Dibuat oleh:</strong> {{ $stockOpname->creator?->name ?? '-' }}</div>
        @if($stockOpname->notes)
            <div><strong>Catatan:</strong> {{ $stockOpname->notes }}</div>
        @endif
    </div>
</div>

<div class="card p-4 sm:p-5 mb-4">
    <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[640px]">
            <thead>
                <tr class="text-left text-slate-500 border-b">
                    <th class="py-2 pr-2">Produk</th>
                    <th class="py-2 pr-2 text-right">Sistem</th>
                    <th class="py-2 pr-2 text-right">Fisik</th>
                    <th class="py-2 pr-2 text-right">Selisih</th>
                    <th class="py-2 pr-2 text-right">Nilai HPP</th>
                    <th class="py-2">Catatan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($stockOpname->items as $item)
                    <tr class="border-b border-slate-100">
                        <td class="py-3 pr-2 font-medium">{{ $item->product_name }}</td>
                        <td class="py-3 pr-2 text-right">{{ $item->system_stock }}</td>
                        <td class="py-3 pr-2 text-right">{{ $item->physical_stock }}</td>
                        <td class="py-3 pr-2 text-right font-semibold {{ $item->difference > 0 ? 'text-emerald-600' : ($item->difference < 0 ? 'text-red-600' : '') }}">
                            {{ $item->difference > 0 ? '+' : '' }}{{ $item->difference }}
                        </td>
                        <td class="py-3 pr-2 text-right">Rp {{ number_format($item->difference * $item->cost, 0, ',', '.') }}</td>
                        <td class="py-3 text-xs text-slate-500">{{ $item->notes ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="flex flex-col-reverse sm:flex-row gap-2">
    <a href="{{ route('stock-opname.index') }}" class="btn btn-ghost flex-1">Kembali</a>
    @if($stockOpname->status === 'draft')
        <form method="POST" action="{{ route('stock-opname.complete', $stockOpname) }}" class="flex-1" onsubmit="return confirm('Selesaikan dan sesuaikan stok?')">
            @csrf
            <button class="btn btn-primary w-full">Selesaikan & sesuaikan stok</button>
        </form>
        @if(auth()->user()->isStoreOwner())
            <form method="POST" action="{{ route('stock-opname.destroy', $stockOpname) }}" class="flex-1" onsubmit="return confirm('Hapus draft?')">
                @csrf @method('DELETE')
                <button class="btn btn-danger w-full">Hapus draft</button>
            </form>
        @endif
    @endif
</div>
@endsection
