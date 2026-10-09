<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Penjualan Product</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 15px; margin: 0 0 4px; }
        .muted { color: #555; font-size: 9px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { border: 1px solid #ccc; padding: 4px 5px; text-align: left; }
        th { background: #f1f5f9; font-size: 9px; }
        .right { text-align: right; }
        .section { margin-top: 8px; font-weight: bold; font-size: 11px; }
        .summary td:last-child { text-align: right; font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ $storeName }}</h1>
    <div class="muted">
        Penjualan Product ·
        @if(($dateFrom ?? $date) === ($dateTo ?? $date))
            {{ \Carbon\Carbon::parse($dateTo ?? $date)->format('d/m/Y') }}
        @else
            {{ \Carbon\Carbon::parse($dateFrom ?? $date)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($dateTo ?? $date)->format('d/m/Y') }}
        @endif
        · Lookback {{ $lookbackDays }} hari · Target stok {{ $coverageDays }} hari
        · Diekspor {{ now()->format('d/m/Y H:i') }}
    </div>

    <table class="summary">
        <tr><td>SKU terjual periode ini</td><td>{{ number_format($summary['sku_sold_today'], 0, ',', '.') }}</td></tr>
        <tr><td>Qty / omzet periode</td><td>{{ number_format($summary['total_sold_qty'], 0, ',', '.') }} / Rp {{ number_format($summary['total_sold_sales'], 0, ',', '.') }}</td></tr>
        <tr><td>Prioritas segera (merah)</td><td>{{ $summary['urgent'] }}</td></tr>
        <tr><td>SKU perlu dibeli</td><td>{{ number_format($summary['sku_recommend'], 0, ',', '.') }}</td></tr>
        <tr><td>Total qty saran</td><td>{{ number_format($summary['total_recommend_qty'], 0, ',', '.') }}</td></tr>
        <tr><td>Estimasi biaya</td><td>Rp {{ number_format($summary['total_est_cost'], 0, ',', '.') }}</td></tr>
    </table>

    <div class="section">Detail penjualan product</div>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Beli</th>
                <th>Prioritas</th>
                <th>Produk</th>
                <th>Kategori</th>
                <th class="right">Stok</th>
                <th class="right">Terjual</th>
                <th class="right">Rata/hari</th>
                <th class="right">Qty saran</th>
                <th class="right">Est. biaya</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $i => $row)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $row->checked_default ? 'Ya' : '' }}</td>
                    <td>{{ $row->priority_label }}</td>
                    <td>{{ $row->name }}@if($row->sku) ({{ $row->sku }})@endif</td>
                    <td>{{ $row->category ?: '-' }}</td>
                    <td class="right">{{ number_format($row->stock, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format($row->sold_qty, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format($row->avg_daily, 2, ',', '.') }}</td>
                    <td class="right">{{ number_format($row->recommend_qty, 0, ',', '.') }}</td>
                    <td class="right">{{ $row->recommend_qty > 0 ? number_format($row->est_cost, 0, ',', '.') : '-' }}</td></tr>
            @empty
                <tr><td colspan="10">Tidak ada data.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
