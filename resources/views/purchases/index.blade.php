@extends('layouts.app')

@section('title', 'Pembelian')
@section('heading', 'Pembelian / Restock')
@section('subheading', 'Restock produk dari supplier')

@section('content')
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
    <form method="GET" class="flex flex-wrap gap-2 flex-1">
        <input type="text" name="q" value="{{ request('q') }}" class="input" placeholder="Cari kode / supplier">
        <select name="payment_status" class="input">
            <option value="">Semua status bayar</option>
            <option value="paid" @selected(request('payment_status')==='paid')>Lunas</option>
            <option value="partial" @selected(request('payment_status')==='partial')>Sebagian</option>
            <option value="unpaid" @selected(request('payment_status')==='unpaid')>Belum bayar</option>
        </select>
        <button class="btn btn-secondary">Filter</button>
    </form>
    <a href="{{ route('purchases.create') }}" class="btn btn-primary">+ Pembelian Baru</a>
</div>

<div class="card p-4 sm:p-5">
    <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[720px]">
            <thead>
                <tr class="text-left text-slate-500 border-b">
                    <th class="py-2 pr-2">Kode</th>
                    <th class="py-2 pr-2">Supplier</th>
                    <th class="py-2 pr-2">Tanggal</th>
                    <th class="py-2 pr-2 text-right">Total</th>
                    <th class="py-2 pr-2">Bayar</th>
                    <th class="py-2">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($purchases as $p)
                    <tr class="border-b border-slate-100">
                        <td class="py-3 pr-2 font-mono text-xs">{{ $p->code }}</td>
                        <td class="py-3 pr-2">
                            <div class="font-medium">{{ $p->supplier_name }}</div>
                            <div class="text-xs text-slate-400">{{ $p->items_count }} item · {{ $p->creator?->name }}</div>
                        </td>
                        <td class="py-3 pr-2">{{ $p->purchased_at->format('d/m/Y') }}</td>
                        <td class="py-3 pr-2 text-right font-semibold">Rp {{ number_format($p->total, 0, ',', '.') }}</td>
                        <td class="py-3 pr-2">
                            <span class="px-2 py-1 rounded-full text-xs
                                {{ $p->payment_status === 'paid' ? 'bg-emerald-100 text-emerald-700' : ($p->payment_status === 'partial' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">
                                {{ $p->payment_status }}
                            </span>
                        </td>
                        <td class="py-3"><a href="{{ route('purchases.show', $p) }}" class="text-brand-700 font-medium">Detail</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-10 text-center text-slate-500">Belum ada pembelian.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $purchases->links() }}</div>
</div>
@endsection
