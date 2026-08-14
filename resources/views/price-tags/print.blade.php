<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak Label Harga</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, Helvetica, "Segoe UI", sans-serif;
            background: #dbe3ec;
            color: #111;
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
            border-bottom: 1px solid #cbd5e1;
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
        .btn-print { background: #ed1c24; color: white; }
        .btn-back { background: #e2e8f0; color: #334155; }

        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 12px auto;
            padding: 7mm;
            background: white;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
        }

        /* Selalu 3 kolom seperti rak Indomaret di A4 */
        .grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 3.5mm;
        }

        .tag {
            border: 1.2pt solid #222;
            background: #fff;
            overflow: hidden;
            break-inside: avoid;
            page-break-inside: avoid;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        /* 1. Header kuning + nama produk */
        .tag-yellow {
            background: #ffd100;
            padding: 2.5mm 3mm;
            text-align: center;
            border-bottom: 0.6pt solid #c4a000;
        }

        .prod-name {
            font-size: 11.5pt;
            font-weight: 800;
            line-height: 1.15;
            text-transform: uppercase;
            letter-spacing: 0.01em;
            color: #111;
            max-height: 2.4em;
            overflow: hidden;
        }

        .tag-mid {
            padding: 1.2mm 3mm 1mm;
            display: flex;
            flex-direction: column;
            align-items: stretch;
        }

        /* 2 & 3. Kode produk */
        .codes {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 2mm;
            font-size: 7pt;
            color: #222;
            font-weight: 600;
            margin-bottom: 0.8mm;
        }
        .code-left {
            flex: 1;
            word-break: break-all;
            line-height: 1.2;
        }
        .code-right {
            text-align: right;
            white-space: nowrap;
            line-height: 1.2;
        }
        .loc {
            display: block;
            font-size: 6.5pt;
            font-weight: 500;
            color: #444;
            margin-top: 0.4mm;
        }

        /* Harga besar — center & dekat footer */
        .price-row {
            margin: 0.5mm 0 1mm;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            gap: 1.5mm;
        }
        .rp {
            font-size: 12pt;
            font-weight: 800;
            line-height: 1;
            margin-top: 2.5mm;
        }
        .price {
            font-size: 34pt;
            font-weight: 900;
            line-height: 0.9;
            letter-spacing: -0.03em;
            color: #111;
        }

        /* Footer strip + nama toko */
        .tag-foot {
            position: relative;
            margin-top: 0;
        }
        .stripes {
            height: 5mm;
            display: flex;
            flex-direction: column;
        }
        .stripes span {
            flex: 1;
            display: block;
        }
        .s-red { background: #ed1c24; }
        .s-blue { background: #0054a6; }
        .s-yellow { background: #ffd100; }

        .store-badge {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -55%);
            background: #0054a6;
            color: #fff;
            border-radius: 999px;
            padding: 1mm 3.2mm;
            font-size: 7pt;
            font-weight: 800;
            letter-spacing: 0.02em;
            white-space: nowrap;
            max-width: 92%;
            overflow: hidden;
            text-overflow: ellipsis;
            box-shadow: 0 0 0 0.7pt #fff;
            line-height: 1.1;
        }

        /* Ukuran: tetap 3 kolom */
        .size-small .prod-name { font-size: 10pt; }
        .size-small .price { font-size: 26pt; }
        .size-small .rp { font-size: 10pt; margin-top: 2mm; }
        .size-small .tag-mid { padding: 1mm 2.5mm 0.8mm; }
        .size-small .store-badge { font-size: 6.5pt; padding: 0.8mm 2.5mm; }

        .size-medium .prod-name { font-size: 11.5pt; }
        .size-medium .price { font-size: 32pt; }
        .size-medium .price-row { margin: 0.4mm 0 0.8mm; }

        .size-large .prod-name { font-size: 13pt; }
        .size-large .price { font-size: 40pt; }
        .size-large .rp { font-size: 14pt; margin-top: 3mm; }
        .size-large .tag-mid { padding: 1.5mm 3.5mm 1.2mm; }
        .size-large .stripes { height: 6mm; }
        .size-large .price-row { margin: 1mm 0 1.2mm; }
        .size-large .store-badge { font-size: 8.5pt; padding: 1.2mm 4mm; }

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
                margin: 7mm;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <strong>{{ count($labels) }}</strong> label · A4 · 3 kolom
        </div>
        <div style="display:flex;gap:0.5rem;">
            <a class="btn-back" href="{{ route('price-tags.index') }}">Kembali</a>
            <button class="btn-print" type="button" onclick="window.print()">Cetak</button>
        </div>
    </div>

    <div class="sheet">
        <div class="grid size-{{ $size }}">
            @foreach($labels as $p)
                @php
                    $codeLeft = $p->barcode ?: ($p->sku ?: 'PRD-'.$p->id);
                    $codeRight = $p->sku ?: (string) $p->id;
                    $loc = $p->category?->name;
                @endphp
                <div class="tag">
                    <div class="tag-yellow">
                        <div class="prod-name">{{ $p->name }}</div>
                    </div>

                    <div class="tag-mid">
                        <div class="codes">
                            <div class="code-left">{{ $codeLeft }}</div>
                            <div class="code-right">
                                {{ $codeRight }}
                                @if($loc)
                                    <span class="loc">{{ $loc }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="price-row">
                            <span class="rp">Rp</span>
                            <span class="price">{{ number_format((float) $p->price, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="tag-foot">
                        <div class="stripes">
                            <span class="s-red"></span>
                            <span class="s-blue"></span>
                            <span class="s-yellow"></span>
                        </div>
                        <div class="store-badge">{{ $storeName }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <script>setTimeout(() => window.print(), 250);</script>
</body>
</html>
