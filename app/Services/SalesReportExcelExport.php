<?php

namespace App\Services;

use Carbon\Carbon;

class SalesReportExcelExport
{
    public function __construct(
        private array $payload,
        private string $storeName,
    ) {}

    public function filename(): string
    {
        return 'laporan-penjualan-'.$this->payload['from'].'-'.$this->payload['to'].'.xls';
    }

    public function contentType(): string
    {
        return 'application/vnd.ms-excel; charset=UTF-8';
    }

    public function build(): string
    {
        $sheets = [
            $this->sheetRingkasan(),
            $this->sheetHarian(),
            $this->sheetProduk(),
            $this->sheetPembayaran(),
            $this->sheetTransaksi(),
        ];

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<?mso-application progid="Excel.Sheet"?>'."\n"
            .'<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
            .' xmlns:o="urn:schemas-microsoft-com:office:office"'
            .' xmlns:x="urn:schemas-microsoft-com:office:excel"'
            .' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"'
            .' xmlns:html="http://www.w3.org/TR/REC-html40">'."\n"
            .$this->styles()."\n"
            .implode("\n", $sheets)."\n"
            .'</Workbook>';
    }

    private function styles(): string
    {
        return <<<'XML'
<Styles>
    <Style ss:ID="Title">
        <Font ss:Bold="1" ss:Size="12"/>
    </Style>
    <Style ss:ID="Section">
        <Font ss:Bold="1" ss:Size="11"/>
        <Interior ss:Color="#E2E8F0" ss:Pattern="Solid"/>
    </Style>
    <Style ss:ID="Header">
        <Font ss:Bold="1"/>
        <Interior ss:Color="#F1F5F9" ss:Pattern="Solid"/>
        <Borders>
            <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
        </Borders>
    </Style>
    <Style ss:ID="Label">
        <Font ss:Color="#475569"/>
    </Style>
    <Style ss:ID="Currency">
        <NumberFormat ss:Format="#,##0"/>
    </Style>
    <Style ss:ID="Percent">
        <NumberFormat ss:Format="0.00"/>
    </Style>
    <Style ss:ID="Date">
        <NumberFormat ss:Format="dd/mm/yyyy"/>
    </Style>
    <Style ss:ID="DateTime">
        <NumberFormat ss:Format="dd/mm/yyyy hh:mm"/>
    </Style>
</Styles>
XML;
    }

    private function sheetRingkasan(): string
    {
        $summary = $this->payload['summary'];
        $from = $this->formatDate($this->payload['from']);
        $to = $this->formatDate($this->payload['to']);
        $filter = ($this->payload['orderType'] ?? null)
            ? $this->orderTypeLabel($this->payload['orderType'])
            : 'Semua';

        $rows = [
            [$this->cell('Laporan Penjualan & HPP', 'Title'), $this->cell('')],
            [$this->cell($this->storeName, 'Title'), $this->cell('')],
            [$this->cell(''), $this->cell('')],
            [$this->cell('Informasi', 'Section'), $this->cell('')],
            [$this->cell('Periode', 'Label'), $this->cell($from.' s/d '.$to)],
            [$this->cell('Filter tipe order', 'Label'), $this->cell($filter)],
            [$this->cell('Diekspor', 'Label'), $this->cell(now()->format('d/m/Y H:i'))],
            [$this->cell(''), $this->cell('')],
            [$this->cell('Ringkasan Keuangan', 'Section'), $this->cell('')],
            [$this->cell('Metrik', 'Header'), $this->cell('Nilai', 'Header')],
            [$this->cell('Jumlah transaksi', 'Label'), $this->cellInt($summary['trx_count'])],
            [$this->cell('Penjualan kotor', 'Label'), $this->cellMoney($summary['gross_sales'])],
            [$this->cell('Diskon', 'Label'), $this->cellMoney($summary['discount'])],
            [$this->cell('Pajak', 'Label'), $this->cellMoney($summary['tax'])],
            [$this->cell('Penjualan bersih', 'Label'), $this->cellMoney($summary['net_sales'])],
            [$this->cell('HPP', 'Label'), $this->cellMoney($summary['hpp'])],
            [$this->cell('Laba kotor', 'Label'), $this->cellMoney($summary['gross_profit'])],
            [$this->cell('Margin (%)', 'Label'), $this->cellPercent($summary['margin'])],
            [$this->cell('Total qty terjual', 'Label'), $this->cellInt($summary['total_qty'])],
            [$this->cell(''), $this->cell('')],
            [$this->cell('Tipe Order', 'Section'), $this->cell('')],
            [$this->cell('Dine In', 'Label'), $this->cellInt($summary['dine_in'])],
            [$this->cell('Take Away', 'Label'), $this->cellInt($summary['takeaway'])],
        ];

        return $this->worksheet('Ringkasan', $rows, [220, 160]);
    }

    private function sheetHarian(): string
    {
        $headers = [
            $this->cell('Tanggal', 'Header'),
            $this->cell('Transaksi', 'Header'),
            $this->cell('Dine In', 'Header'),
            $this->cell('Take Away', 'Header'),
            $this->cell('Penjualan', 'Header'),
            $this->cell('HPP', 'Header'),
            $this->cell('Laba', 'Header'),
        ];

        $rows = [$headers];

        foreach ($this->payload['daily'] as $row) {
            $rows[] = [
                $this->cell($this->formatDate($row->date)),
                $this->cellInt($row->trx_count),
                $this->cellInt($row->dine_in),
                $this->cellInt($row->takeaway),
                $this->cellMoney($row->sales),
                $this->cellMoney($row->hpp),
                $this->cellMoney($row->profit),
            ];
        }

        if (count($rows) === 1) {
            $rows[] = [
                $this->cell('Tidak ada data pada periode ini.'),
                $this->cell(''), $this->cell(''), $this->cell(''),
                $this->cell(''), $this->cell(''), $this->cell(''),
            ];
        }

        return $this->worksheet('Penjualan Harian', $rows, [90, 70, 60, 70, 90, 90, 90]);
    }

    private function sheetProduk(): string
    {
        $headers = [
            $this->cell('No', 'Header'),
            $this->cell('Produk', 'Header'),
            $this->cell('Qty', 'Header'),
            $this->cell('Penjualan', 'Header'),
            $this->cell('HPP', 'Header'),
            $this->cell('Laba', 'Header'),
        ];

        $rows = [$headers];
        $no = 1;

        foreach ($this->payload['topProducts'] as $p) {
            $rows[] = [
                $this->cellInt($no++),
                $this->cell($p->product_name),
                $this->cellInt($p->qty),
                $this->cellMoney($p->sales),
                $this->cellMoney($p->hpp),
                $this->cellMoney($p->profit),
            ];
        }

        if (count($rows) === 1) {
            $rows[] = [
                $this->cell(''), $this->cell('Belum ada penjualan produk.'),
                $this->cell(''), $this->cell(''), $this->cell(''), $this->cell(''),
            ];
        }

        return $this->worksheet('Produk Terlaris', $rows, [40, 200, 50, 90, 90, 90]);
    }

    private function sheetPembayaran(): string
    {
        $headers = [
            $this->cell('Metode', 'Header'),
            $this->cell('Jumlah Transaksi', 'Header'),
            $this->cell('Total', 'Header'),
        ];

        $rows = [$headers];

        foreach ($this->payload['byPayment'] as $pay) {
            $rows[] = [
                $this->cell($this->paymentLabel($pay->payment_method)),
                $this->cellInt($pay->trx_count),
                $this->cellMoney($pay->total),
            ];
        }

        if (count($rows) === 1) {
            $rows[] = [
                $this->cell('Tidak ada data.'),
                $this->cell(''), $this->cell(''),
            ];
        }

        return $this->worksheet('Metode Bayar', $rows, [120, 100, 100]);
    }

    private function sheetTransaksi(): string
    {
        $headers = [
            $this->cell('No', 'Header'),
            $this->cell('Invoice', 'Header'),
            $this->cell('Tanggal', 'Header'),
            $this->cell('Tipe', 'Header'),
            $this->cell('Metode', 'Header'),
            $this->cell('Pelanggan', 'Header'),
            $this->cell('Subtotal', 'Header'),
            $this->cell('Diskon', 'Header'),
            $this->cell('Pajak', 'Header'),
            $this->cell('Total', 'Header'),
            $this->cell('Bayar', 'Header'),
            $this->cell('Kembali', 'Header'),
            $this->cell('Status', 'Header'),
        ];

        $rows = [$headers];
        $no = 1;

        foreach ($this->payload['allTransactions'] as $trx) {
            $rows[] = [
                $this->cellInt($no++),
                $this->cell($trx->invoice_number),
                $this->cell(optional($trx->sold_at)->format('d/m/Y H:i')),
                $this->cell($this->orderTypeLabel($trx->order_type)),
                $this->cell($this->paymentLabel($trx->payment_method)),
                $this->cell($trx->customer_name ?: '-'),
                $this->cellMoney($trx->subtotal),
                $this->cellMoney($trx->discount),
                $this->cellMoney($trx->tax),
                $this->cellMoney($trx->total),
                $this->cellMoney($trx->paid),
                $this->cellMoney($trx->change),
                $this->cell($this->statusLabel($trx->status)),
            ];
        }

        if (count($rows) === 1) {
            $rows[] = array_fill(0, 13, $this->cell(''));
            $rows[1][1] = $this->cell('Tidak ada transaksi pada periode ini.');
        }

        return $this->worksheet('Detail Transaksi', $rows, [35, 110, 110, 70, 80, 120, 80, 70, 70, 80, 80, 70, 70]);
    }

    /** @param array<int, array<int, string>> $rows */
    private function worksheet(string $name, array $rows, array $columnWidths): string
    {
        $xml = '<Worksheet ss:Name="'.$this->escape($name).'">'."\n"
            .'<Table>'."\n";

        foreach ($columnWidths as $width) {
            $xml .= '<Column ss:Width="'.$width.'"/>'."\n";
        }

        foreach ($rows as $row) {
            $xml .= '<Row>'."\n";
            foreach ($row as $cell) {
                $xml .= $cell."\n";
            }
            $xml .= '</Row>'."\n";
        }

        $xml .= '</Table>'."\n".'</Worksheet>';

        return $xml;
    }

    private function cell(string $value, ?string $style = null): string
    {
        $styleAttr = $style ? ' ss:StyleID="'.$style.'"' : '';
        $type = is_numeric($value) && $value !== '' ? 'Number' : 'String';

        return '<Cell'.$styleAttr.'><Data ss:Type="'.$type.'">'.$this->escape($value).'</Data></Cell>';
    }

    private function cellInt(int|float|string $value): string
    {
        return '<Cell><Data ss:Type="Number">'.(int) $value.'</Data></Cell>';
    }

    private function cellMoney(int|float|string|null $value): string
    {
        return '<Cell ss:StyleID="Currency"><Data ss:Type="Number">'
            .number_format((float) ($value ?? 0), 2, '.', '')
            .'</Data></Cell>';
    }

    private function cellPercent(int|float|string $value): string
    {
        return '<Cell ss:StyleID="Percent"><Data ss:Type="Number">'
            .number_format((float) $value, 2, '.', '')
            .'</Data></Cell>';
    }

    private function formatDate(string $date): string
    {
        try {
            return Carbon::parse($date)->format('d/m/Y');
        } catch (\Throwable) {
            return $date;
        }
    }

    private function orderTypeLabel(?string $type): string
    {
        return match ($type) {
            'dine_in' => 'Dine In',
            'takeaway' => 'Take Away',
            default => '-',
        };
    }

    private function paymentLabel(?string $method): string
    {
        return match (strtolower((string) $method)) {
            'cash' => 'Tunai',
            'qris' => 'QRIS',
            'transfer' => 'Transfer',
            'card' => 'Kartu',
            'credit' => 'Piutang',
            'other' => 'Lainnya',
            default => strtoupper((string) $method),
        };
    }

    private function statusLabel(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'completed' => 'Selesai',
            'void' => 'Batal',
            'pending' => 'Pending',
            default => (string) $status,
        };
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
