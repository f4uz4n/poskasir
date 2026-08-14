@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')
@section('subheading', 'Ringkasan penjualan toko')

@section('content')
<div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="card p-5">
        <div class="text-sm text-slate-500">Penjualan hari ini</div>
        <div class="text-2xl font-extrabold mt-1">Rp {{ number_format($stats['today_sales'], 0, ',', '.') }}</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Transaksi hari ini</div>
        <div class="text-2xl font-extrabold mt-1">{{ $stats['today_count'] }}</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Penjualan bulan ini</div>
        <div class="text-2xl font-extrabold mt-1">Rp {{ number_format($stats['month_sales'], 0, ',', '.') }}</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Total produk</div>
        <div class="text-2xl font-extrabold mt-1">{{ $stats['products'] }}</div>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-4 mb-6">
    <div class="card p-5 lg:col-span-2">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-bold">Penjualan 7 hari</h2>
            <a href="{{ route('pos.index') }}" class="btn btn-primary text-sm">Buka Kasir</a>
        </div>
        <div class="space-y-2">
            @forelse($chart as $row)
                <div class="flex items-center gap-3">
                    <div class="w-24 text-xs text-slate-500">{{ \Carbon\Carbon::parse($row->date)->format('d M') }}</div>
                    <div class="flex-1 h-3 rounded-full bg-slate-100 overflow-hidden">
                        @php $max = max(1, $chart->max('total')); $pct = ($row->total / $max) * 100; @endphp
                        <div class="h-full bg-brand-500 rounded-full" style="width: {{ $pct }}%"></div>
                    </div>
                    <div class="w-28 text-right text-sm font-semibold">Rp {{ number_format($row->total, 0, ',', '.') }}</div>
                </div>
            @empty
                <p class="text-slate-500 text-sm">Belum ada data penjualan.</p>
            @endforelse
        </div>
    </div>

    <div class="card p-5">
        <h2 class="font-bold mb-3">Status langganan</h2>
        @if($subscription)
            <div class="rounded-xl bg-emerald-50 border border-emerald-100 p-4">
                <div class="font-bold text-brand-800">{{ $subscription->plan->name }}</div>
                <div class="text-sm text-slate-600 mt-1">
                    @if($subscription->ends_at)
                        Aktif sampai {{ $subscription->ends_at->format('d M Y') }}
                    @else
                        Aktif selamanya
                    @endif
                </div>
                @if($subscription->ends_at)
                    <div class="text-xs text-slate-500 mt-2">{{ $subscription->ends_at->diffForHumans() }}</div>
                @endif
            </div>
        @else
            <div class="rounded-xl bg-amber-50 border border-amber-100 p-4">
                <div class="font-bold text-amber-800">Tidak aktif</div>
                <p class="text-sm text-amber-700 mt-1">Aktifkan paket Gratis atau Berbayar.</p>
            </div>
        @endif
        @if(auth()->user()->isStoreOwner())
            <a href="{{ route('subscription.index') }}" class="btn btn-secondary w-full mt-4 text-sm">Kelola langganan</a>
        @endif
    </div>
</div>

@php
    $yearSales = (float) ($yearRoi['net_sales'] ?? 0);
    $yearHpp = (float) ($yearRoi['hpp'] ?? 0);
    $yearProfit = (float) ($yearRoi['gross_profit'] ?? 0);
    $yearMargin = (float) ($yearRoi['margin'] ?? 0);
    $hppPct = $yearSales > 0 ? round(($yearHpp / $yearSales) * 100, 1) : 0;
    $profitPct = $yearSales > 0 ? round(($yearProfit / $yearSales) * 100, 1) : 0;
@endphp

<div class="card roi-widget mb-6">
    <div class="grid lg:grid-cols-[1fr_300px] xl:grid-cols-[1fr_320px]">
        <div>
            <div class="roi-chart-panel px-4 pt-4 pb-2 sm:px-6 sm:pt-5">
                <div class="relative h-56 sm:h-64 lg:h-72">
                    <canvas id="roi-line-chart" aria-label="Grafik Sales, HPP, dan Profit bulanan"></canvas>
                </div>
            </div>
            <div class="roi-metric-bar grid sm:grid-cols-3">
                <div class="roi-metric-item">
                    <div class="roi-metric-icon">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 14l4-4 4 2 5-6"/></svg>
                    </div>
                    <div>
                        <div class="text-sm font-bold">Rp {{ number_format($stats['today_sales'], 0, ',', '.') }}</div>
                        <div class="text-xs text-slate-500">Penjualan Hari Ini</div>
                    </div>
                </div>
                <div class="roi-metric-item">
                    <div class="roi-metric-icon">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 14l4-4 4 2 5-6"/></svg>
                    </div>
                    <div>
                        <div class="text-sm font-bold">Rp {{ number_format($avgMonthlySales, 0, ',', '.') }}</div>
                        <div class="text-xs text-slate-500">Rata-rata Bulanan</div>
                    </div>
                </div>
                <div class="roi-metric-item">
                    <div class="roi-metric-icon">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 14l4-4 4 2 5-6"/></svg>
                    </div>
                    <div>
                        <div class="text-sm font-bold">Rp {{ number_format($yearSales, 0, ',', '.') }}</div>
                        <div class="text-xs text-slate-500">Total Penjualan</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="roi-summary-panel p-5 sm:p-6">
            <h2 class="font-bold text-base">Ringkasan Tahun {{ $roiYear }}</h2>
            <p class="text-xs text-slate-500 mt-1 mb-5">Komposisi Sales, HPP (COGS), dan Profit</p>

            <div class="roi-breakdown-row">
                <div class="flex items-center justify-between text-sm">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-sky-400 shrink-0"></span>
                        <span class="font-medium">Sales</span>
                    </div>
                    <span class="font-semibold text-sky-600">100%</span>
                </div>
                <div class="roi-breakdown-track">
                    <div class="roi-breakdown-fill bg-sky-400" style="width: 100%"></div>
                </div>
                <div class="text-xs text-slate-500 mt-1">Rp {{ number_format($yearSales, 0, ',', '.') }}</div>
            </div>

            <div class="roi-breakdown-row">
                <div class="flex items-center justify-between text-sm">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-orange-400 shrink-0"></span>
                        <span class="font-medium">HPP (COGS)</span>
                    </div>
                    <span class="font-semibold text-orange-500">{{ number_format($hppPct, 1, ',', '.') }}%</span>
                </div>
                <div class="roi-breakdown-track">
                    <div class="roi-breakdown-fill bg-orange-400" style="width: {{ min(100, $hppPct) }}%"></div>
                </div>
                <div class="text-xs text-slate-500 mt-1">Rp {{ number_format($yearHpp, 0, ',', '.') }}</div>
            </div>

            <div class="roi-breakdown-row">
                <div class="flex items-center justify-between text-sm">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-lime-400 shrink-0"></span>
                        <span class="font-medium">Profit</span>
                    </div>
                    <span class="font-semibold text-lime-600">{{ number_format($profitPct, 1, ',', '.') }}%</span>
                </div>
                <div class="roi-breakdown-track">
                    <div class="roi-breakdown-fill bg-lime-400" style="width: {{ min(100, max(0, $profitPct)) }}%"></div>
                </div>
                <div class="text-xs text-slate-500 mt-1">Rp {{ number_format($yearProfit, 0, ',', '.') }}</div>
            </div>

            <div class="mt-6 pt-4 border-t">
                <div class="flex items-center justify-between mb-2">
                    <span class="font-bold text-sm">Margin Profit</span>
                    <span class="font-extrabold text-emerald-600">{{ number_format($yearMargin, 1, ',', '.') }}%</span>
                </div>
                <div class="roi-breakdown-track h-1.5">
                    <div class="roi-breakdown-fill bg-emerald-500" style="width: {{ min(100, max(0, $yearMargin)) }}%"></div>
                </div>
            </div>

            <a href="{{ route('reports.profit-loss', ['from' => $roiYear . '-01-01', 'to' => $yearTo]) }}" class="btn btn-secondary w-full mt-5 text-sm">Lihat laba rugi</a>
        </div>
    </div>
</div>

<div class="card p-5">
    <h2 class="font-bold mb-4">Transaksi terbaru</h2>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b">
                    <th class="py-2 pr-3">Invoice</th>
                    <th class="py-2 pr-3">Waktu</th>
                    <th class="py-2 pr-3">Metode</th>
                    <th class="py-2 pr-3">Status</th>
                    <th class="py-2 text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recent as $trx)
                    <tr class="border-b border-slate-100">
                        <td class="py-3 pr-3 font-medium">{{ $trx->invoice_number }}</td>
                        <td class="py-3 pr-3">{{ optional($trx->sold_at)->format('d/m/Y H:i') }}</td>
                        <td class="py-3 pr-3 uppercase">{{ $trx->payment_method }}</td>
                        <td class="py-3 pr-3">
                            <span class="px-2 py-1 rounded-full text-xs {{ $trx->status === 'completed' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                {{ $trx->status }}
                            </span>
                        </td>
                        <td class="py-3 text-right font-semibold">Rp {{ number_format($trx->total, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-center text-slate-500">Belum ada transaksi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(function () {
    const canvas = document.getElementById('roi-line-chart');
    if (!canvas || typeof Chart === 'undefined') return;

    const labels = @json($roiChart->pluck('label'));
    const salesData = @json($roiChart->pluck('sales'));
    const hppData = @json($roiChart->pluck('hpp'));
    const profitData = @json($roiChart->pluck('profit'));

    const fmt = (v) => 'Rp ' + Number(v).toLocaleString('id-ID');

    Chart.defaults.color = '#ffffff';
    Chart.defaults.borderColor = 'rgba(255,255,255,0.15)';

    new Chart(canvas, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Sales',
                    data: salesData,
                    borderColor: '#81d4fa',
                    backgroundColor: 'transparent',
                    borderWidth: 2.5,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#81d4fa',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.4,
                },
                {
                    label: 'HPP (COGS)',
                    data: hppData,
                    borderColor: '#ffb74d',
                    backgroundColor: 'transparent',
                    borderWidth: 2.5,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#ffb74d',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.4,
                },
                {
                    label: 'Profit',
                    data: profitData,
                    borderColor: '#dce775',
                    backgroundColor: 'transparent',
                    borderWidth: 2.5,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#dce775',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.4,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: '#ffffff',
                        boxWidth: 12,
                        padding: 16,
                        font: { size: 12 },
                    },
                },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.92)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    callbacks: {
                        label(ctx) {
                            return ` ${ctx.dataset.label}: ${fmt(ctx.raw)}`;
                        },
                    },
                },
            },
            scales: {
                x: {
                    grid: { color: 'rgba(255,255,255,0.12)' },
                    ticks: { color: '#ffffff', font: { size: 11 } },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(255,255,255,0.12)' },
                    ticks: {
                        color: '#ffffff',
                        font: { size: 11 },
                        callback(v) {
                            if (v >= 1_000_000_000) return (v / 1_000_000_000).toFixed(0) + 'M';
                            if (v >= 1_000_000) return (v / 1_000_000).toFixed(0) + 'jt';
                            if (v >= 1_000) return (v / 1_000).toFixed(0) + 'rb';
                            return v;
                        },
                    },
                },
            },
        },
    });
})();
</script>
@endpush
