<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="60">
    <title>Pantau Jarak Jauh — {{ $summary['store_name'] }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="bg-app antialiased min-h-screen p-4 sm:p-6">
    <div class="max-w-5xl mx-auto">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3 mb-6">
            <div>
                <div class="text-sm text-brand-700 font-semibold">Pemantauan jarak jauh</div>
                <h1 class="text-2xl sm:text-3xl font-extrabold">{{ $summary['store_name'] }}</h1>
                <p class="text-slate-500 text-sm">Auto-refresh setiap 60 detik · {{ now()->format('d M Y H:i') }}</p>
            </div>
            <div class="text-xs text-slate-400">Link aman · jangan dibagikan sembarangan</div>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="card p-5">
                <div class="text-sm text-slate-500">Penjualan hari ini</div>
                <div class="text-2xl font-extrabold mt-1">Rp {{ number_format($summary['today_sales'], 0, ',', '.') }}</div>
            </div>
            <div class="card p-5">
                <div class="text-sm text-slate-500">Transaksi hari ini</div>
                <div class="text-2xl font-extrabold mt-1">{{ $summary['today_count'] }}</div>
            </div>
            <div class="card p-5">
                <div class="text-sm text-slate-500">HPP hari ini</div>
                <div class="text-2xl font-extrabold mt-1 text-amber-700">Rp {{ number_format($summary['today_hpp'], 0, ',', '.') }}</div>
            </div>
            <div class="card p-5">
                <div class="text-sm text-slate-500">Laba kotor hari ini</div>
                <div class="text-2xl font-extrabold mt-1 text-brand-700">Rp {{ number_format($summary['today_profit'], 0, ',', '.') }}</div>
            </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-4">
            <div class="card p-5">
                <h2 class="font-bold mb-3">Penjualan per jam (hari ini)</h2>
                <div class="space-y-2">
                    @forelse($hourly as $h)
                        <div class="flex items-center gap-3 text-sm">
                            <div class="w-14 text-slate-500">{{ str_pad($h->hour, 2, '0', STR_PAD_LEFT) }}:00</div>
                            <div class="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden">
                                @php $max = max(1, $hourly->max('total')); @endphp
                                <div class="h-full bg-brand-500" style="width: {{ ($h->total / $max) * 100 }}%"></div>
                            </div>
                            <div class="w-28 text-right font-semibold">Rp {{ number_format($h->total, 0, ',', '.') }}</div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Belum ada penjualan hari ini.</p>
                    @endforelse
                </div>
                <div class="text-xs text-slate-400 mt-4">Bulan ini: Rp {{ number_format($summary['month_sales'], 0, ',', '.') }}</div>
            </div>

            <div class="card p-5">
                <h2 class="font-bold mb-3">Transaksi terbaru</h2>
                <div class="space-y-3">
                    @forelse($recent as $trx)
                        <div class="flex items-start justify-between gap-3 text-sm border-b border-slate-100 pb-2">
                            <div>
                                <div class="font-semibold">{{ $trx->invoice_number }}</div>
                                <div class="text-xs text-slate-500">
                                    {{ optional($trx->sold_at)->format('d/m H:i') }}
                                    · {{ ($trx->order_type ?? 'dine_in') === 'takeaway' ? 'Take Away' : 'Dine In' }}
                                    @if($trx->cashier) · {{ $trx->cashier->name }} @endif
                                </div>
                            </div>
                            <div class="font-bold">Rp {{ number_format($trx->total, 0, ',', '.') }}</div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Belum ada transaksi.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</body>
</html>
