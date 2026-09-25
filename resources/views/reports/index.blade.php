@extends('layouts.app')

@section('title', 'Laporan')
@section('heading', 'Laporan Penjualan & HPP')
@section('subheading', 'Analisis omzet, HPP, dan laba kotor — hanya transaksi selesai (bukan void)')

@section('content')
@include('reports._tabs', ['activeTab' => 'sales'])

<form method="GET" class="card p-4 mb-4 grid sm:grid-cols-4 gap-3" id="report-filter-form">
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
    <div class="flex flex-col sm:flex-row items-stretch sm:items-end gap-2">
        <button type="submit" class="btn btn-primary flex-1">Filter</button>
        <a href="{{ route('reports.export.excel', request()->query()) }}" class="btn btn-secondary flex-1 text-center whitespace-nowrap">Unduh Excel</a>
        <a href="{{ route('reports.export.pdf', request()->query()) }}" class="btn btn-secondary flex-1 text-center whitespace-nowrap">Unduh PDF</a>
    </div>
</form>

<p class="text-xs text-slate-500 mb-4">
    Laba kotor = (penjualan kotor − diskon) − HPP. Pajak tidak dihitung sebagai laba.
    Rekap harian &amp; detail transaksi memakai filter yang sama; jumlah halaman detail hanya 20 baris per halaman — total periode ada di bawah tabel.
</p>

<div class="grid sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-6">
    <div class="card p-5">
        <div class="text-sm text-slate-500">Harga jual (kotor)</div>
        <div class="text-2xl font-extrabold mt-1">Rp {{ number_format($summary['gross_sales'], 0, ',', '.') }}</div>
        <div class="text-xs text-slate-400 mt-1">{{ $summary['trx_count'] }} transaksi · Dine {{ $summary['dine_in'] }} / TA {{ $summary['takeaway'] }}</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Diskon</div>
        <div class="text-2xl font-extrabold mt-1 text-rose-600">Rp {{ number_format($summary['discount'], 0, ',', '.') }}</div>
        <div class="text-xs text-slate-400 mt-1">Pengurang omzet sebelum pajak</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Omzet setelah diskon</div>
        <div class="text-2xl font-extrabold mt-1">Rp {{ number_format($summary['revenue'], 0, ',', '.') }}</div>
        <div class="text-xs text-slate-400 mt-1">Total bayar Rp {{ number_format($summary['net_sales'], 0, ',', '.') }}</div>
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
                        <th class="py-2 pr-2 text-right">Harga jual</th>
                        <th class="py-2 pr-2 text-right">Diskon</th>
                        <th class="py-2 pr-2 text-right">Omzet</th>
                        <th class="py-2 pr-2 text-right">HPP</th>
                        <th class="py-2 text-right">Laba</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($daily as $row)
                        <tr class="border-b border-slate-100">
                            <td class="py-2 pr-2">{{ \Carbon\Carbon::parse($row->date)->format('d M Y') }}</td>
                            <td class="py-2 pr-2">{{ $row->trx_count }}</td>
                            <td class="py-2 pr-2 text-right">Rp {{ number_format($row->gross_sales, 0, ',', '.') }}</td>
                            <td class="py-2 pr-2 text-right {{ $row->discount > 0 ? 'text-rose-600 font-medium' : 'text-slate-400' }}">
                                {{ $row->discount > 0 ? '- ' : '' }}Rp {{ number_format($row->discount, 0, ',', '.') }}
                            </td>
                            <td class="py-2 pr-2 text-right">Rp {{ number_format($row->revenue, 0, ',', '.') }}</td>
                            <td class="py-2 pr-2 text-right">Rp {{ number_format($row->hpp, 0, ',', '.') }}</td>
                            <td class="py-2 text-right font-semibold text-brand-700">Rp {{ number_format($row->profit, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-6 text-center text-slate-500">Belum ada data di periode ini.</td></tr>
                    @endforelse
                </tbody>
                @if($daily->isNotEmpty())
                <tfoot>
                    <tr class="border-t-2 border-slate-200 font-bold bg-slate-50">
                        <td class="py-2 pr-2" colspan="2">Total rekap · {{ $dailyTotals['trx_count'] }} trx</td>
                        <td class="py-2 pr-2 text-right">Rp {{ number_format($dailyTotals['gross_sales'], 0, ',', '.') }}</td>
                        <td class="py-2 pr-2 text-right text-rose-600">- Rp {{ number_format($dailyTotals['discount'], 0, ',', '.') }}</td>
                        <td class="py-2 pr-2 text-right">Rp {{ number_format($dailyTotals['revenue'], 0, ',', '.') }}</td>
                        <td class="py-2 pr-2 text-right">Rp {{ number_format($dailyTotals['hpp'], 0, ',', '.') }}</td>
                        <td class="py-2 text-right text-brand-700">Rp {{ number_format($dailyTotals['profit'], 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
                @endif
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
            <div class="flex justify-between"><span>Harga jual (kotor)</span><span>Rp {{ number_format($summary['gross_sales'], 0, ',', '.') }}</span></div>
            <div class="flex justify-between text-rose-600"><span>Diskon</span><span>- Rp {{ number_format($summary['discount'], 0, ',', '.') }}</span></div>
            <div class="flex justify-between"><span>Omzet setelah diskon</span><span>Rp {{ number_format($summary['revenue'], 0, ',', '.') }}</span></div>
            <div class="flex justify-between"><span>Pajak</span><span>Rp {{ number_format($summary['tax'], 0, ',', '.') }}</span></div>
            <div class="flex justify-between font-bold pt-2"><span>Total bayar</span><span>Rp {{ number_format($summary['net_sales'], 0, ',', '.') }}</span></div>
            <div class="flex justify-between text-amber-700"><span>HPP</span><span>Rp {{ number_format($summary['hpp'], 0, ',', '.') }}</span></div>
            <div class="flex justify-between font-bold text-brand-700"><span>Laba kotor</span><span>Rp {{ number_format($summary['gross_profit'], 0, ',', '.') }}</span></div>
        </div>
    </div>
</div>

<div class="grid lg:grid-cols-2 gap-4 mb-6">
    <div class="card p-5">
        <h2 class="font-bold mb-1">Produk terlaris + HPP</h2>
        <p class="text-xs text-slate-500 mb-4">Diskon struk dialokasikan proporsional ke produk agar laba selaras dengan laba periode.</p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b">
                        <th class="py-2 pr-2">Produk</th>
                        <th class="py-2 pr-2 text-right">Qty</th>
                        <th class="py-2 pr-2 text-right">Harga jual</th>
                        <th class="py-2 pr-2 text-right">Diskon</th>
                        <th class="py-2 pr-2 text-right">Net</th>
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
                            <td class="py-2 pr-2 text-right {{ $p->discount > 0 ? 'text-rose-600' : 'text-slate-400' }}">
                                {{ $p->discount > 0 ? '- ' : '' }}Rp {{ number_format($p->discount, 0, ',', '.') }}
                            </td>
                            <td class="py-2 pr-2 text-right">Rp {{ number_format($p->revenue, 0, ',', '.') }}</td>
                            <td class="py-2 pr-2 text-right">Rp {{ number_format($p->hpp, 0, ',', '.') }}</td>
                            <td class="py-2 text-right font-semibold">Rp {{ number_format($p->profit, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-6 text-center text-slate-500">Belum ada penjualan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card p-5">
        <h2 class="font-bold mb-1">Transaksi periode</h2>
        <p class="text-xs text-slate-500 mb-4">
            Total periode: harga jual <strong>Rp {{ number_format($detailTotals['gross_sales'], 0, ',', '.') }}</strong>
            · diskon <strong class="text-rose-600">Rp {{ number_format($detailTotals['discount'], 0, ',', '.') }}</strong>
            · laba <strong class="text-brand-700">Rp {{ number_format($detailTotals['profit'], 0, ',', '.') }}</strong>
            · {{ $detailTotals['trx_count'] }} trx
        </p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b">
                        <th class="py-2 pr-2">Invoice</th>
                        <th class="py-2 pr-2 text-right">Harga jual</th>
                        <th class="py-2 pr-2 text-right">Diskon</th>
                        <th class="py-2 pr-2 text-right">HPP</th>
                        <th class="py-2 text-right">Laba</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $trx)
                        <tr class="border-b border-slate-100">
                            <td class="py-2 pr-2">
                                <div class="font-medium">{{ $trx->invoice_number }}</div>
                                <div class="text-xs text-slate-400">
                                    {{ optional($trx->sold_at)->format('d/m H:i') }}
                                    · {{ $trx->order_type === 'takeaway' ? 'Take Away' : 'Dine In' }}
                                    @if($trx->table_number) · Meja {{ $trx->table_number }} @endif
                                </div>
                            </td>
                            <td class="py-2 pr-2 text-right">Rp {{ number_format($trx->gross_sales, 0, ',', '.') }}</td>
                            <td class="py-2 pr-2 text-right {{ $trx->discount_amount > 0 ? 'text-rose-600 font-medium' : 'text-slate-400' }}">
                                {{ $trx->discount_amount > 0 ? '- ' : '' }}Rp {{ number_format($trx->discount_amount, 0, ',', '.') }}
                            </td>
                            <td class="py-2 pr-2 text-right">Rp {{ number_format($trx->hpp, 0, ',', '.') }}</td>
                            <td class="py-2 text-right font-semibold text-brand-700">Rp {{ number_format($trx->profit, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-slate-500">Kosong.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $transactions->links() }}</div>
    </div>
</div>
@endsection
