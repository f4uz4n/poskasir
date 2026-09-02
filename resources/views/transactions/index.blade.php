@extends('layouts.app')

@section('title', 'Transaksi')
@section('heading', 'Riwayat Transaksi')
@section('subheading', 'Semua penjualan toko')

@section('content')
@if(auth()->user()->canAccessArea('void'))
<div class="mb-4">
    <a href="{{ route('transactions.void.create') }}" class="btn btn-danger text-sm">Batalkan transaksi (butuh password pimpinan)</a>
</div>
@endif
<div id="transactions-app" class="card p-5" data-settings='@json($settings)'>
    <form method="GET" class="grid sm:grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
        <input type="text" name="q" value="{{ request('q') }}" class="input lg:col-span-2" placeholder="Cari invoice / pelanggan / meja">
        <div>
            <label class="text-xs text-slate-500">Dari tanggal</label>
            <input type="date" name="date_from" value="{{ $dateFrom }}" class="input mt-1">
        </div>
        <div>
            <label class="text-xs text-slate-500">Sampai tanggal</label>
            <input type="date" name="date_to" value="{{ $dateTo }}" class="input mt-1">
        </div>
        <div class="flex flex-col sm:flex-row gap-2">
            <select name="order_type" class="input flex-1" data-placeholder="Semua tipe">
                <option value="">Semua tipe</option>
                <option value="dine_in" @selected(request('order_type') === 'dine_in')>Dine In</option>
                <option value="takeaway" @selected(request('order_type') === 'takeaway')>Take Away</option>
            </select>
            <button class="btn btn-secondary shrink-0">Filter</button>
        </div>
    </form>

    <p class="text-xs text-slate-500 mb-3">Klik nomor invoice untuk detail, cetak ulang, atau bagikan WhatsApp.</p>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b">
                    <th class="py-2 pr-3">Invoice</th>
                    <th class="py-2 pr-3">Waktu</th>
                    <th class="py-2 pr-3">Tipe</th>
                    <th class="py-2 pr-3">Item</th>
                    <th class="py-2 pr-3">Metode</th>
                    <th class="py-2 pr-3">Status</th>
                    <th class="py-2 pr-3 text-right">Total</th>
                    <th class="py-2">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $trx)
                    <tr class="border-b border-slate-100">
                        <td class="py-3 pr-3">
                            <button type="button"
                                class="history-invoice-btn"
                                data-transaction-show="{{ route('transactions.show', $trx) }}"
                                title="Lihat detail struk">
                                {{ $trx->invoice_number }}
                            </button>
                            @if($trx->local_id)
                                <div class="text-[10px] text-slate-400">offline: {{ \Illuminate\Support\Str::limit($trx->local_id, 12) }}</div>
                            @endif
                            @if($trx->customer_name)
                                <div class="text-xs text-slate-500">{{ $trx->customer_name }}</div>
                            @endif
                        </td>
                        <td class="py-3 pr-3">{{ optional($trx->sold_at)->format('d/m/Y H:i') }}</td>
                        <td class="py-3 pr-3">
                            <span class="px-2 py-1 rounded-full text-xs {{ ($trx->order_type ?? 'dine_in') === 'takeaway' ? 'bg-sky-100 text-sky-700' : 'bg-emerald-100 text-emerald-700' }}">
                                {{ ($trx->order_type ?? 'dine_in') === 'takeaway' ? 'Take Away' : 'Dine In' }}
                            </span>
                            @if($trx->table_number)
                                <div class="text-xs text-slate-400 mt-1">Meja {{ $trx->table_number }}</div>
                            @endif
                        </td>
                        <td class="py-3 pr-3">{{ $trx->items->sum('qty') }} item</td>
                        <td class="py-3 pr-3 uppercase">{{ $trx->payment_method }}</td>
                        <td class="py-3 pr-3">
                            <span class="px-2 py-1 rounded-full text-xs {{ $trx->status === 'completed' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                {{ $trx->status }}
                            </span>
                        </td>
                        <td class="py-3 pr-3 text-right font-semibold">Rp {{ number_format($trx->total, 0, ',', '.') }}</td>
                        <td class="py-3">
                            @if($trx->status === 'completed' && auth()->user()->canAccessArea('void'))
                                <a href="{{ route('transactions.void.create', ['invoice' => $trx->invoice_number]) }}"
                                    class="text-red-600 text-xs font-medium">Batalkan</a>
                            @elseif($trx->status === 'completed' && auth()->user()->isStoreOwner())
                                <form method="POST" action="{{ route('transactions.void', $trx) }}" onsubmit="return confirm('Batalkan transaksi? Stok akan dikembalikan.')">
                                    @csrf
                                    <button class="text-red-600 text-xs font-medium">Void</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-8 text-center text-slate-500">Belum ada transaksi pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $transactions->withQueryString()->links() }}</div>
</div>

@include('partials.transaction-detail-modal', ['showDetailBack' => false])
@endsection
