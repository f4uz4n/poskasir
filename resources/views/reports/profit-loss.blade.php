@extends('layouts.app')

@section('title', 'Laba / Rugi')
@section('heading', 'Laporan Laba / Rugi')
@section('subheading', 'Penjualan selesai − HPP; pembelian supplier ditampilkan terpisah (bukan mengurangi laba yang sudah memakai HPP)')

@section('content')
<form method="GET" class="card p-4 mb-4 grid sm:grid-cols-3 gap-3">
    <div>
        <label class="text-xs text-slate-500">Dari</label>
        <input type="date" name="from" value="{{ $from }}" class="input mt-1">
    </div>
    <div>
        <label class="text-xs text-slate-500">Sampai</label>
        <input type="date" name="to" value="{{ $to }}" class="input mt-1">
    </div>
    <div class="flex items-end">
        <button class="btn btn-primary w-full">Filter</button>
    </div>
</form>

<div class="grid lg:grid-cols-2 gap-4 mb-6">
    <div class="card p-5 space-y-3">
        <h2 class="font-bold text-lg">Laba / Rugi periode</h2>
        <div class="flex justify-between text-sm border-b border-slate-100 py-2">
            <span>Penjualan kotor</span>
            <strong>Rp {{ number_format($summary['grossSales'], 0, ',', '.') }}</strong>
        </div>
        <div class="flex justify-between text-sm border-b border-slate-100 py-2 text-slate-500">
            <span>(−) Diskon ({{ $summary['trxCount'] }} trx selesai)</span>
            <span>Rp {{ number_format($summary['discount'], 0, ',', '.') }}</span>
        </div>
        <div class="flex justify-between text-sm border-b border-slate-100 py-2">
            <span>Omzet setelah diskon</span>
            <strong>Rp {{ number_format($summary['revenue'], 0, ',', '.') }}</strong>
        </div>
        <div class="flex justify-between text-sm border-b border-slate-100 py-2 text-slate-500">
            <span>Pajak (bukan laba)</span>
            <span>Rp {{ number_format($summary['tax'], 0, ',', '.') }}</span>
        </div>
        <div class="flex justify-between text-sm border-b border-slate-100 py-2">
            <span>Total bayar pelanggan</span>
            <strong>Rp {{ number_format($summary['netSales'], 0, ',', '.') }}</strong>
        </div>
        <div class="flex justify-between text-sm border-b border-slate-100 py-2">
            <span>(−) HPP dari penjualan</span>
            <strong class="text-amber-700">Rp {{ number_format($summary['hpp'], 0, ',', '.') }}</strong>
        </div>
        <div class="flex justify-between text-base py-2">
            <span class="font-semibold">Laba kotor / laba bersih*</span>
            <strong class="{{ $summary['netProfit'] >= 0 ? 'text-emerald-700' : 'text-red-600' }} text-xl">
                Rp {{ number_format($summary['netProfit'], 0, ',', '.') }}
            </strong>
        </div>
        <div class="text-xs text-slate-400">Margin {{ $summary['margin'] }}% · *belum termasuk beban operasional terpisah · void tidak dihitung</div>
    </div>

    <div class="card p-5 space-y-3">
        <h2 class="font-bold text-lg">Pembelian &amp; arus kas</h2>
        <p class="text-xs text-slate-500">Pembelian menambah stok/HPP barang; jangan dikurangi lagi dari laba di atas agar tidak double-count.</p>
        <div class="flex justify-between text-sm border-b border-slate-100 py-2">
            <span>Pembelian / restock ({{ $summary['purchaseCount'] }})</span>
            <strong>Rp {{ number_format($summary['purchaseTotal'], 0, ',', '.') }}</strong>
        </div>
        <div class="flex justify-between text-sm border-b border-slate-100 py-2 text-slate-500">
            <span>Dibayar ke supplier (di nota beli)</span>
            <span>Rp {{ number_format($summary['purchasePaid'], 0, ',', '.') }}</span>
        </div>
        <div class="flex justify-between text-sm border-b border-slate-100 py-2">
            <span>Penerimaan piutang</span>
            <strong class="text-emerald-700">Rp {{ number_format($summary['receivableCollected'], 0, ',', '.') }}</strong>
        </div>
        <div class="flex justify-between text-sm border-b border-slate-100 py-2">
            <span>Pembayaran hutang</span>
            <strong class="text-red-600">Rp {{ number_format($summary['payablePaid'], 0, ',', '.') }}</strong>
        </div>
        <div class="flex justify-between text-sm py-2">
            <span>Piutang outstanding</span>
            <strong>Rp {{ number_format($summary['outstandingReceivable'], 0, ',', '.') }}</strong>
        </div>
        <div class="flex justify-between text-sm py-2">
            <span>Hutang outstanding</span>
            <strong>Rp {{ number_format($summary['outstandingPayable'], 0, ',', '.') }}</strong>
        </div>
    </div>
</div>

<div class="grid lg:grid-cols-2 gap-4 mb-6">
    <div class="card p-4 sm:p-5">
        <h2 class="font-bold mb-4">Laba harian</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b">
                        <th class="py-2">Tanggal</th>
                        <th class="py-2 text-right">Omzet bayar</th>
                        <th class="py-2 text-right">HPP</th>
                        <th class="py-2 text-right">Laba</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dailyRows as $row)
                        <tr class="border-b border-slate-100">
                            <td class="py-3">{{ \Carbon\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                            <td class="py-3 text-right">Rp {{ number_format($row['sales'], 0, ',', '.') }}</td>
                            <td class="py-3 text-right">Rp {{ number_format($row['hpp'], 0, ',', '.') }}</td>
                            <td class="py-3 text-right font-semibold {{ $row['profit'] >= 0 ? 'text-emerald-700' : 'text-red-600' }}">
                                Rp {{ number_format($row['profit'], 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-10 text-center text-slate-500">Tidak ada data periode ini.</td></tr>
                    @endforelse
                </tbody>
                @if($dailyRows->isNotEmpty())
                <tfoot>
                    <tr class="border-t-2 border-slate-200 font-bold bg-slate-50">
                        <td class="py-2">Total</td>
                        <td class="py-2 text-right">Rp {{ number_format($dailyTotals['sales'], 0, ',', '.') }}</td>
                        <td class="py-2 text-right">Rp {{ number_format($dailyTotals['hpp'], 0, ',', '.') }}</td>
                        <td class="py-2 text-right text-emerald-700">Rp {{ number_format($dailyTotals['profit'], 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    <div class="card p-4 sm:p-5">
        <h2 class="font-bold mb-4">Pembelian per supplier</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b">
                        <th class="py-2">Supplier</th>
                        <th class="py-2 text-right">Nota</th>
                        <th class="py-2 text-right">Total</th>
                        <th class="py-2 text-right">Dibayar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($purchasesBySupplier as $row)
                        <tr class="border-b border-slate-100">
                            <td class="py-3 font-medium">{{ $row->supplier }}</td>
                            <td class="py-3 text-right">{{ $row->purchase_count }}</td>
                            <td class="py-3 text-right">Rp {{ number_format($row->total, 0, ',', '.') }}</td>
                            <td class="py-3 text-right">Rp {{ number_format($row->paid, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-10 text-center text-slate-500">Belum ada pembelian di periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
