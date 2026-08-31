<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Penjualan</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .muted { color: #555; font-size: 10px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 1px solid #ccc; padding: 5px 6px; text-align: left; }
        th { background: #f1f5f9; font-size: 10px; }
        .right { text-align: right; }
        .section { margin-top: 12px; font-weight: bold; font-size: 12px; }
        .summary td:last-child { text-align: right; font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ $storeName }}</h1>
    <div class="muted">Laporan Penjualan & HPP · {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</div>

    <table class="summary">
        <tr><td>Penjualan bersih</td><td>Rp {{ number_format($summary['net_sales'], 0, ',', '.') }}</td></tr>
        <tr><td>HPP</td><td>Rp {{ number_format($summary['hpp'], 0, ',', '.') }}</td></tr>
        <tr><td>Laba kotor</td><td>Rp {{ number_format($summary['gross_profit'], 0, ',', '.') }}</td></tr>
        <tr><td>Margin</td><td>{{ number_format($summary['margin'], 2, ',', '.') }}%</td></tr>
        <tr><td>Transaksi</td><td>{{ $summary['trx_count'] }} (Dine In {{ $summary['dine_in'] }} / Take Away {{ $summary['takeaway'] }})</td></tr>
    </table>

    <div class="section">Penjualan harian</div>
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th class="right">Trx</th>
                <th class="right">Penjualan</th>
                <th class="right">HPP</th>
                <th class="right">Laba</th>
            </tr>
        </thead>
        <tbody>
            @forelse($daily as $row)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($row->date)->format('d/m/Y') }}</td>
                    <td class="right">{{ $row->trx_count }}</td>
                    <td class="right">{{ number_format($row->sales, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format($row->hpp, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format($row->profit, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Tidak ada data.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section">Produk terlaris</div>
    <table>
        <thead>
            <tr>
                <th>Produk</th>
                <th class="right">Qty</th>
                <th class="right">Penjualan</th>
                <th class="right">Laba</th>
            </tr>
        </thead>
        <tbody>
            @forelse($topProducts as $p)
                <tr>
                    <td>{{ $p->product_name }}</td>
                    <td class="right">{{ $p->qty }}</td>
                    <td class="right">{{ number_format($p->sales, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format($p->profit, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Tidak ada data.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section">Detail transaksi</div>
    <table>
        <thead>
            <tr>
                <th>Invoice</th>
                <th>Tanggal</th>
                <th>Tipe</th>
                <th>Metode</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($allTransactions as $trx)
                <tr>
                    <td>{{ $trx->invoice_number }}</td>
                    <td>{{ optional($trx->sold_at)->format('d/m/Y H:i') }}</td>
                    <td>{{ $trx->order_type === 'takeaway' ? 'Take Away' : 'Dine In' }}</td>
                    <td>{{ strtoupper($trx->payment_method) }}</td>
                    <td class="right">{{ number_format($trx->total, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Tidak ada transaksi.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
