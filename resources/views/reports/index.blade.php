@extends('layouts.app')

@section('title', 'Laporan')
@section('heading', 'Laporan Penjualan & HPP')
@section('subheading', 'Analisis omzet, HPP, dan laba kotor')

@section('content')
<div class="flex flex-wrap gap-2 mb-4">
    <a href="{{ route('reports.profit-loss') }}" class="btn btn-secondary">Laba / Rugi</a>
    <a href="{{ route('reports.stock') }}" class="btn btn-secondary">Laporan Stok</a>
    <a href="{{ route('receivables.index') }}" class="btn btn-secondary">Piutang</a>
    <a href="{{ route('payables.index') }}" class="btn btn-secondary">Hutang</a>
    <a href="{{ route('purchases.index') }}" class="btn btn-secondary">Pembelian</a>
    <a href="{{ route('expiry.index') }}" class="btn btn-secondary">Monitor Expired</a>
    <a href="{{ route('stock-opname.index') }}" class="btn btn-secondary">Stock Opname</a>
</div>

<form method="GET" class="card p-4 mb-4 grid sm:grid-cols-4 gap-3">
    <div>
        <label class="text-xs text-slate-500">Dari tanggal</label>
        <input type="date" name="from" value="{{ $from }}" class="input mt-1">
    </div>
    <div>
        <label class="text-xs text-slate-500">Sampai tanggal</label>
        <input type="date" name="to" value="{{ $to }}" class="input mt-1">
    </div>
    <div>
        <label class="text-xs text-slate-500">Tipe order</label>
        <select name="order_type" class="input mt-1" data-placeholder="Semua tipe">
            <option value="">Semua</option>
            <option value="dine_in" @selected($orderType === 'dine_in')>Dine In</option>
            <option value="takeaway" @selected($orderType === 'takeaway')>Take Away</option>
        </select>
    </div>
    <div class="flex items-end">
        <button class="btn btn-primary w-full">Filter</button>
    </div>
</form>

<div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="card p-5">
        <div class="text-sm text-slate-500">Penjualan bersih</div>
        <div class="text-2xl font-extrabold mt-1">Rp {{ number_format($summary['net_sales'], 0, ',', '.') }}</div>
        <div class="text-xs text-slate-400 mt-1">{{ $summary['trx_count'] }} transaksi</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">HPP</div>
        <div class="text-2xl font-extrabold mt-1 text-amber-700">Rp {{ number_format($summary['hpp'], 0, ',', '.') }}</div>
        <div class="text-xs text-slate-400 mt-1">{{ number_format($summary['total_qty'], 0, ',', '.') }} item terjual</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Laba kotor</div>
        <div class="text-2xl font-extrabold mt-1 text-brand-700">Rp {{ number_format($summary['gross_profit'], 0, ',', '.') }}</div>
        <div class="text-xs text-slate-400 mt-1">Margin {{ number_format($summary['margin'], 2, ',', '.') }}%</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Dine In / Take Away</div>
        <div class="text-2xl font-extrabold mt-1">{{ $summary['dine_in'] }} / {{ $summary['takeaway'] }}</div>
        <div class="text-xs text-slate-400 mt-1">Diskon Rp {{ number_format($summary['discount'], 0, ',', '.') }}</div>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-4 mb-6">
    <div class="card p-5 lg:col-span-2">
        <h2 class="font-bold mb-4">Penjualan harian</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b">
                        <th class="py-2 pr-2">Tanggal</th>
                        <th class="py-2 pr-2">Trx</th>
                        <th class="py-2 pr-2">Dine/TA</th>
                        <th class="py-2 pr-2 text-right">Penjualan</th>
                        <th class="py-2 pr-2 text-right">HPP</th>
                        <th class="py-2 text-right">Laba</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($daily as $row)
                        <tr class="border-b border-slate-100">
                            <td class="py-2 pr-2">{{ \Carbon\Carbon::parse($row->date)->format('d M Y') }}</td>
                            <td class="py-2 pr-2">{{ $row->trx_count }}</td>
                            <td class="py-2 pr-2">{{ $row->dine_in }}/{{ $row->takeaway }}</td>
                            <td class="py-2 pr-2 text-right">Rp {{ number_format($row->sales, 0, ',', '.') }}</td>
                            <td class="py-2 pr-2 text-right">Rp {{ number_format($row->hpp, 0, ',', '.') }}</td>
                            <td class="py-2 text-right font-semibold text-brand-700">Rp {{ number_format($row->profit, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-slate-500">Belum ada data di periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card p-5">
        <h2 class="font-bold mb-4">Metode bayar</h2>
        <div class="space-y-3">
            @forelse($byPayment as $pay)
                <div class="flex items-center justify-between text-sm">
                    <div>
                        <div class="font-semibold uppercase">{{ $pay->payment_method }}</div>
                        <div class="text-xs text-slate-500">{{ $pay->trx_count }} transaksi</div>
                    </div>
                    <div class="font-bold">Rp {{ number_format($pay->total, 0, ',', '.') }}</div>
                </div>
            @empty
                <p class="text-sm text-slate-500">Tidak ada data.</p>
            @endforelse
        </div>

        <div class="mt-6 pt-4 border-t border-slate-100 text-sm space-y-1">
            <div class="flex justify-between"><span>Penjualan kotor</span><span>Rp {{ number_format($summary['gross_sales'], 0, ',', '.') }}</span></div>
            <div class="flex justify-between"><span>Diskon</span><span>- Rp {{ number_format($summary['discount'], 0, ',', '.') }}</span></div>
            <div class="flex justify-between"><span>Pajak</span><span>Rp {{ number_format($summary['tax'], 0, ',', '.') }}</span></div>
            <div class="flex justify-between font-bold pt-2"><span>Net sales</span><span>Rp {{ number_format($summary['net_sales'], 0, ',', '.') }}</span></div>
            <div class="flex justify-between text-amber-700"><span>HPP</span><span>Rp {{ number_format($summary['hpp'], 0, ',', '.') }}</span></div>
            <div class="flex justify-between font-bold text-brand-700"><span>Laba kotor</span><span>Rp {{ number_format($summary['gross_profit'], 0, ',', '.') }}</span></div>
        </div>
    </div>
</div>

<div class="grid lg:grid-cols-2 gap-4 mb-6">
    <div class="card p-5">
        <h2 class="font-bold mb-4">Produk terlaris + HPP</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b">
                        <th class="py-2 pr-2">Produk</th>
                        <th class="py-2 pr-2 text-right">Qty</th>
                        <th class="py-2 pr-2 text-right">Sales</th>
                        <th class="py-2 pr-2 text-right">HPP</th>
                        <th class="py-2 text-right">Laba</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($topProducts as $p)
                        <tr class="border-b border-slate-100">
                            <td class="py-2 pr-2 font-medium">{{ $p->product_name }}</td>
                            <td class="py-2 pr-2 text-right">{{ $p->qty }}</td>
                            <td class="py-2 pr-2 text-right">Rp {{ number_format($p->sales, 0, ',', '.') }}</td>
                            <td class="py-2 pr-2 text-right">Rp {{ number_format($p->hpp, 0, ',', '.') }}</td>
                            <td class="py-2 text-right font-semibold">Rp {{ number_format($p->profit, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-slate-500">Belum ada penjualan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card p-5">
        <h2 class="font-bold mb-4">Transaksi periode</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b">
                        <th class="py-2 pr-2">Invoice</th>
                        <th class="py-2 pr-2">Tipe</th>
                        <th class="py-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $trx)
                        <tr class="border-b border-slate-100">
                            <td class="py-2 pr-2">
                                <div class="font-medium">{{ $trx->invoice_number }}</div>
                                <div class="text-xs text-slate-400">{{ optional($trx->sold_at)->format('d/m H:i') }}</div>
                            </td>
                            <td class="py-2 pr-2">
                                <span class="px-2 py-0.5 rounded-full text-xs {{ $trx->order_type === 'takeaway' ? 'bg-sky-100 text-sky-700' : 'bg-emerald-100 text-emerald-700' }}">
                                    {{ $trx->order_type === 'takeaway' ? 'Take Away' : 'Dine In' }}
                                </span>
                                @if($trx->table_number)
                                    <div class="text-xs text-slate-400 mt-1">Meja {{ $trx->table_number }}</div>
                                @endif
                            </td>
                            <td class="py-2 text-right font-semibold">Rp {{ number_format($trx->total, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-6 text-center text-slate-500">Kosong.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $transactions->links() }}</div>
    </div>
</div>
@endsection
