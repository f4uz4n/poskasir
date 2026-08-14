@extends('layouts.app')

@section('title', $purchase->code)
@section('heading', 'Detail Pembelian')
@section('subheading', $purchase->code)

@section('content')
<div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="card p-5">
        <div class="text-sm text-slate-500">Supplier</div>
        <div class="text-xl font-extrabold mt-1">{{ $purchase->supplier_name }}</div>
        <div class="text-xs text-slate-400 mt-1">{{ $purchase->purchased_at->format('d/m/Y') }}</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Total</div>
        <div class="text-xl font-extrabold mt-1">Rp {{ number_format($purchase->total, 0, ',', '.') }}</div>
        <div class="text-xs text-slate-400 mt-1">Dibayar Rp {{ number_format($purchase->paid, 0, ',', '.') }}</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Status bayar</div>
        <div class="text-xl font-extrabold mt-1 uppercase">{{ $purchase->payment_status }}</div>
        <div class="text-xs text-slate-400 mt-1">{{ $purchase->payment_method }}</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Hutang terkait</div>
        @if($purchase->payable)
            <div class="text-xl font-extrabold mt-1">Rp {{ number_format($purchase->payable->remaining(), 0, ',', '.') }}</div>
            <a href="{{ route('payables.index') }}?q={{ urlencode($purchase->payable->code) }}" class="text-xs text-brand-700">Lihat hutang</a>
        @else
            <div class="text-xl font-extrabold mt-1 text-emerald-700">Lunas</div>
        @endif
    </div>
</div>

<div class="card p-4 sm:p-5 mb-4">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b">
                    <th class="py-2">Produk</th>
                    <th class="py-2 text-right">Qty</th>
                    <th class="py-2 text-right">HPP</th>
                    <th class="py-2 text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($purchase->items as $item)
                    <tr class="border-b border-slate-100">
                        <td class="py-3 font-medium">{{ $item->product_name }}</td>
                        <td class="py-3 text-right">{{ $item->qty }}</td>
                        <td class="py-3 text-right">Rp {{ number_format($item->cost, 0, ',', '.') }}</td>
                        <td class="py-3 text-right font-semibold">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<a href="{{ route('purchases.index') }}" class="btn btn-ghost">Kembali</a>
@endsection
