<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak Label Harga</title>
    <style>
        @font-face {
            font-family: 'CalculatorDigits';
            font-weight: 700;
            src: url('/fonts/DSEG7Classic-Bold.ttf') format('truetype');
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, Helvetica, "Segoe UI", sans-serif;
            background: #eef6f1;
            color: #0f172a;
        }
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            gap: 0.75rem;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1rem;
            background: white;
            border-bottom: 1px solid #d1fae5;
        }
        .toolbar button, .toolbar a {
            appearance: none;
            border: 0;
            border-radius: 0.5rem;
            padding: 0.55rem 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .btn-print { background: #059669; color: white; }
        .btn-back { background: #e2e8f0; color: #334155; }

        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 12px auto;
            padding: 5mm;
            background: white;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.1);
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, 55mm);
            gap: 2.5mm;
            justify-content: start;
        }

        .tag {
            width: 55mm;
            height: 35mm;
            border: 1.2pt solid #047857;
            background: #fff;
            overflow: hidden;
            break-inside: avoid;
            page-break-inside: avoid;
            display: flex;
            flex-direction: column;
        }

        .head {
            background: #059669;
            color: #fff;
            padding: 1mm 2mm;
            text-align: center;
            flex-shrink: 0;
            min-height: 6mm;
        }

        .prod-name {
            font-size: 7pt;
            font-weight: 800;
            line-height: 1.05;
            text-transform: uppercase;
            max-height: 2.2em;
            overflow: hidden;
        }

        .body {
            padding: 0.8mm 2mm 0.4mm;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            min-height: 0;
        }

        .meta {
            display: flex;
            justify-content: space-between;
            gap: 1.5mm;
            font-size: 5pt;
            font-weight: 600;
            color: #475569;
            flex-shrink: 0;
        }
        .meta-left { flex: 1; word-break: break-all; line-height: 1.1; }
        .meta-right { text-align: right; white-space: nowrap; line-height: 1.1; }
        .cat {
            display: block;
            font-size: 4.5pt;
            font-weight: 500;
            color: #64748b;
        }

        .price-wrap {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1.2mm;
            min-height: 14mm;
        }
        .rp {
            font-size: 9pt;
            font-weight: 800;
            color: #047857;
            margin-top: -6mm;
        }
        .price {
            font-family: 'CalculatorDigits', 'Courier New', monospace;
            font-size: 30pt;
            font-weight: 700;
            line-height: 0.85;
            letter-spacing: 1px;
            color: #064e3b;
        }

        .foot {
            flex-shrink: 0;
            background: #047857;
            text-align: center;
        }
        .accent { height: 1.1mm; background: #10b981; }
        .store {
            color: #fff;
            font-size: 5.5pt;
            font-weight: 800;
            line-height: 3.6mm;
            padding: 0 2mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        @media print {
            body { background: white; }
            .toolbar { display: none !important; }
            .sheet {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
            @page {
                size: A4 portrait;
                margin: 5mm;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <strong>{{ count($labels) }}</strong> label · 5,5 × 3,5 cm · A4 (3 kolom)
        </div>
        <div style="display:flex;gap:0.5rem;">
            <a class="btn-back" href="{{ route('price-tags.index') }}">Kembali</a>
            <button class="btn-print" type="button" onclick="window.print()">Cetak</button>
        </div>
    </div>

    <div class="sheet">
        <div class="grid">
            @foreach($labels as $p)
                @php
                    $codeLeft = $p->barcode ?: ($p->sku ?: 'PRD-'.$p->id);
                    $codeRight = $p->sku ?: (string) $p->id;
                    $loc = $p->category?->name;
                @endphp
                <div class="tag">
                    <div class="head">
                        <div class="prod-name">{{ $p->name }}</div>
                    </div>
                    <div class="body">
                        <div class="meta">
                            <div class="meta-left">{{ $codeLeft }}</div>
                            <div class="meta-right">
                                {{ $codeRight }}
                                @if($loc)<span class="cat">{{ $loc }}</span>@endif
                            </div>
                        </div>
                        <div class="price-wrap">
                            <span class="rp">Rp</span>
                            <span class="price">{{ number_format((float) $p->price, 0, ',', '.') }}</span>
                        </div>
                    </div>
                    <div class="foot">
                        <div class="accent"></div>
                        <div class="store">{{ $storeName }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <script>setTimeout(() => window.print(), 250);</script>
</body>
</html>
