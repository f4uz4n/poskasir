<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Label Harga</title>
    <style>
        @page { margin: 5mm; size: A4 portrait; }
        @font-face {
            font-family: 'CalculatorDigits';
            font-weight: bold;
            font-style: normal;
            src: url('file:///{{ str_replace('\\', '/', storage_path('fonts/DSEG7Classic-Bold.ttf')) }}') format('truetype');
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #0f172a;
            font-size: 8pt;
        }
        table.grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 2.5mm 2.5mm;
        }
        td.cell {
            width: 33.33%;
            vertical-align: top;
            padding: 0;
        }
        .tag {
            width: 55mm;
            height: 35mm;
            border: 1.2pt solid #047857;
            overflow: hidden;
            page-break-inside: avoid;
            position: relative;
            background: #fff;
        }
        .head {
            background: #059669;
            color: #fff;
            padding: 1mm 1.8mm;
            text-align: center;
            height: 6mm;
        }
        .prod-name {
            font-size: 6.5pt;
            font-weight: bold;
            line-height: 1.05;
            text-transform: uppercase;
            max-height: 4.5mm;
            overflow: hidden;
        }
        .body {
            padding: 0.6mm 1.8mm 0;
            height: 23.5mm;
        }
        .meta {
            width: 100%;
            font-size: 4.5pt;
            color: #475569;
            margin-bottom: 0.3mm;
        }
        .meta td { vertical-align: top; padding: 0; }
        .meta-left { text-align: left; width: 58%; word-wrap: break-word; }
        .meta-right { text-align: right; width: 42%; }
        .cat { display: block; color: #64748b; font-size: 4pt; }
        .price-wrap {
            text-align: center;
            padding-top: 0.2mm;
        }
        .rp {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8pt;
            font-weight: bold;
            color: #047857;
            vertical-align: top;
        }
            .price {
            font-family: 'CalculatorDigits', DejaVu Sans Mono, monospace;
            font-size: 30pt;
            font-weight: bold;
            color: #064e3b;
            letter-spacing: 1px;
            line-height: 0.9;
        }
        .foot {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 4.8mm;
            background: #047857;
            text-align: center;
        }
        .accent { height: 1mm; background: #10b981; }
        .store {
            color: #fff;
            font-size: 5pt;
            font-weight: bold;
            line-height: 3.6mm;
            white-space: nowrap;
            overflow: hidden;
            padding: 0 2mm;
        }
    </style>
</head>
<body>
@php $chunks = array_chunk($labels, 3); @endphp
@foreach($chunks as $row)
    <table class="grid">
        <tr>
            @foreach($row as $p)
                @php
                    $codeLeft = $p->barcode ?: ($p->sku ?: 'PRD-'.$p->id);
                    $codeRight = $p->sku ?: (string) $p->id;
                    $loc = $p->category?->name;
                    $priceText = number_format((float) $p->price, 0, ',', '.');
                @endphp
                <td class="cell">
                    <div class="tag">
                        <div class="head">
                            <div class="prod-name">{{ \Illuminate\Support\Str::limit(mb_strtoupper($p->name), 38, '') }}</div>
                        </div>
                        <div class="body">
                            <table class="meta">
                                <tr>
                                    <td class="meta-left">{{ $codeLeft }}</td>
                                    <td class="meta-right">
                                        {{ $codeRight }}
                                        @if($loc)<span class="cat">{{ \Illuminate\Support\Str::limit($loc, 14, '') }}</span>@endif
                                    </td>
                                </tr>
                            </table>
                            <div class="price-wrap">
                                <span class="rp">Rp</span>
                                <span class="price">{{ $priceText }}</span>
                            </div>
                        </div>
                        <div class="foot">
                            <div class="accent"></div>
                            <div class="store">{{ \Illuminate\Support\Str::limit($storeName, 26, '') }}</div>
                        </div>
                    </div>
                </td>
            @endforeach
            @for($i = count($row); $i < 3; $i++)
                <td class="cell"></td>
            @endfor
        </tr>
    </table>
@endforeach
</body>
</html>
