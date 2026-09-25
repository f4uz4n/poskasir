<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak Voucher</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Plus Jakarta Sans", Arial, Helvetica, sans-serif;
            background: #eef6f1;
            color: #0f172a;
        }
        .toolbar {
            position: sticky; top: 0; z-index: 10;
            display: flex; gap: 0.75rem; align-items: center; justify-content: space-between;
            padding: 0.75rem 1rem; background: #fff; border-bottom: 1px solid #d1fae5;
        }
        .toolbar button, .toolbar a {
            appearance: none; border: 0; border-radius: 0.5rem;
            padding: 0.55rem 1rem; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 0.9rem;
        }
        .btn-print { background: #059669; color: #fff; }
        .btn-back { background: #e2e8f0; color: #334155; }
        .sheet {
            width: 210mm; min-height: 297mm; margin: 12px auto; padding: 8mm;
            background: #fff; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.1);
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 6mm;
        }
        .voucher {
            border: 1.6pt solid #047857;
            border-radius: 3mm;
            overflow: hidden;
            break-inside: avoid;
            page-break-inside: avoid;
            min-height: 55mm;
            display: flex;
            flex-direction: column;
        }
        .head {
            background: linear-gradient(90deg, #059669, #047857);
            color: #fff;
            padding: 3mm 4mm;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 3mm;
        }
        .head .store { font-size: 9pt; font-weight: 800; letter-spacing: 0.02em; }
        .head .label { font-size: 8pt; opacity: 0.95; font-weight: 600; }
        .body { padding: 3.5mm 4mm; flex: 1; display: flex; flex-direction: column; gap: 2mm; }
        .title { font-size: 11pt; font-weight: 800; }
        .amount { font-size: 22pt; font-weight: 800; color: #047857; line-height: 1; }
        .meta { font-size: 8pt; color: #64748b; }
        .code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 10pt; font-weight: 700; letter-spacing: 0.04em;
        }
        .barcode-wrap { text-align: center; margin-top: auto; padding-top: 2mm; }
        .barcode-wrap svg { max-width: 100%; height: 38px; }
        .foot {
            background: #ecfdf5; border-top: 1px dashed #a7f3d0;
            padding: 2mm 4mm; font-size: 7.5pt; color: #065f46; font-weight: 600;
        }
        @media print {
            body { background: #fff; }
            .toolbar { display: none !important; }
            .sheet { width: auto; min-height: auto; margin: 0; padding: 0; box-shadow: none; }
            @page { size: A4 portrait; margin: 8mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div><strong>{{ $vouchers->count() }}</strong> voucher siap dicetak</div>
        <div style="display:flex;gap:0.5rem;">
            <a class="btn-back" href="{{ route('vouchers.index') }}">Kembali</a>
            <button class="btn-print" type="button" onclick="window.print()">Cetak</button>
        </div>
    </div>

    <div class="sheet">
        <div class="grid">
            @foreach($vouchers as $v)
                <div class="voucher">
                    <div class="head">
                        <div class="store">{{ $storeName }}</div>
                        <div class="label">VOUCHER</div>
                    </div>
                    <div class="body">
                        <div class="title">{{ $v->title ?: 'Voucher Belanja' }}</div>
                        <div class="amount">Rp {{ number_format((float) $v->amount, 0, ',', '.') }}</div>
                        <div class="meta">
                            @if($v->expires_at)
                                Berlaku s/d {{ $v->expires_at->format('d/m/Y H:i') }}
                            @else
                                Berlaku tanpa batas waktu
                            @endif
                        </div>
                        <div class="code">{{ $v->code }}</div>
                        <div class="barcode-wrap">
                            <svg class="voucher-barcode" data-code="{{ $v->code }}"></svg>
                        </div>
                    </div>
                    <div class="foot">Tunjukkan / scan barcode di kasir untuk membayar</div>
                </div>
            @endforeach
        </div>
    </div>

    <script>
        document.querySelectorAll('.voucher-barcode').forEach((el) => {
            try {
                JsBarcode(el, el.dataset.code, {
                    format: 'CODE128',
                    width: 1.6,
                    height: 42,
                    displayValue: false,
                    margin: 0,
                });
            } catch (e) {}
        });
        setTimeout(() => window.print(), 350);
    </script>
</body>
</html>
