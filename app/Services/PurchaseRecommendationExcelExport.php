<?php

namespace App\Services;

use Carbon\Carbon;

class PurchaseRecommendationExcelExport
{
    public function __construct(
        private array $payload,
        private string $storeName,
    ) {}

    public function filename(): string
    {
        return 'penjualan-product-'.($this->payload['dateFrom'] ?? $this->payload['date']).'_'.($this->payload['dateTo'] ?? $this->payload['date']).'.xls';
    }

    public function contentType(): string
    {
        return 'application/vnd.ms-excel; charset=UTF-8';
    }

    public function build(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<?mso-application progid="Excel.Sheet"?>'."\n"
            .'<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
            .' xmlns:o="urn:schemas-microsoft-com:office:office"'
            .' xmlns:x="urn:schemas-microsoft-com:office:excel"'
            .' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"'
            .' xmlns:html="http://www.w3.org/TR/REC-html40">'."\n"
            .$this->styles()."\n"
            .$this->sheetRingkasan()."\n"
            .$this->sheetProduk()."\n"
            .'</Workbook>';
    }

    private function styles(): string
    {
        return <<<'XML'
<Styles>
    <Style ss:ID="Title"><Font ss:Bold="1" ss:Size="12"/></Style>
    <Style ss:ID="Section"><Font ss:Bold="1" ss:Size="11"/><Interior ss:Color="#E2E8F0" ss:Pattern="Solid"/></Style>
    <Style ss:ID="Header"><Font ss:Bold="1"/><Interior ss:Color="#F1F5F9" ss:Pattern="Solid"/></Style>
    <Style ss:ID="Label"><Font ss:Color="#475569"/></Style>
    <Style ss:ID="Currency"><NumberFormat ss:Format="#,##0"/></Style>
</Styles>
XML;
    }

    private function sheetRingkasan(): string
    {
        $s = $this->payload['summary'];
        $dateFrom = $this->formatDate($this->payload['dateFrom'] ?? $this->payload['date']);
        $dateTo = $this->formatDate($this->payload['dateTo'] ?? $this->payload['date']);
        $dateLabel = $dateFrom === $dateTo ? $dateFrom : $dateFrom.' – '.$dateTo;

        $rows = [
            [$this->cell('Penjualan Product', 'Title'), $this->cell('')],
            [$this->cell($this->storeName, 'Title'), $this->cell('')],
            [$this->cell(''), $this->cell('')],
            [$this->cell('Informasi', 'Section'), $this->cell('')],
            [$this->cell('Periode penjualan', 'Label'), $this->cell($dateLabel)],
            [$this->cell('Lookback rata-rata (hari)', 'Label'), $this->cellInt($this->payload['lookbackDays'])],
            [$this->cell('Target stok (hari)', 'Label'), $this->cellInt($this->payload['coverageDays'])],
            [$this->cell('Diekspor', 'Label'), $this->cell(now()->format('d/m/Y H:i'))],
            [$this->cell(''), $this->cell('')],
            [$this->cell('Ringkasan', 'Section'), $this->cell('')],
            [$this->cell('SKU terjual periode ini', 'Label'), $this->cellInt($s['sku_sold_today'])],
            [$this->cell('SKU perlu dibeli', 'Label'), $this->cellInt($s['sku_recommend'])],
            [$this->cell('Prioritas segera (merah)', 'Label'), $this->cellInt($s['urgent'])],
            [$this->cell('Qty terjual periode ini', 'Label'), $this->cellInt($s['total_sold_qty'])],
            [$this->cell('Omzet produk periode ini', 'Label'), $this->cellMoney($s['total_sold_sales'])],
            [$this->cell('Total qty saran', 'Label'), $this->cellInt($s['total_recommend_qty'])],
            [$this->cell('Estimasi biaya', 'Label'), $this->cellMoney($s['total_est_cost'])],
        ];

        return $this->worksheet('Ringkasan', $rows, [220, 160]);
    }

    private function sheetProduk(): string
    {
        $headers = [
            $this->cell('No', 'Header'),
            $this->cell('Beli', 'Header'),
            $this->cell('Prioritas', 'Header'),
            $this->cell('Produk', 'Header'),
            $this->cell('SKU', 'Header'),
            $this->cell('Kategori', 'Header'),
            $this->cell('Stok', 'Header'),
            $this->cell('Terjual hari ini', 'Header'),
            $this->cell('Rata/hari', 'Header'),
            $this->cell('Sisa hari', 'Header'),
            $this->cell('Qty saran', 'Header'),
            $this->cell('HPP', 'Header'),
            $this->cell('Estimasi biaya', 'Header'),
            $this->cell('Satuan', 'Header'),
        ];

        $rows = [$headers];
        $no = 1;

        foreach ($this->payload['items'] as $row) {
            $daysLeft = $row->days_left === null ? '-' : (string) $row->days_left;
            $rows[] = [
                $this->cellInt($no++),
                $this->cell($row->checked_default ? 'Ya' : ''),
                $this->cell($row->priority_label),
                $this->cell($row->name),
                $this->cell((string) ($row->sku ?: '-')),
                $this->cell((string) ($row->category ?: '-')),
                $this->cellInt($row->stock),
                $this->cellInt($row->sold_qty),
                $this->cell((string) $row->avg_daily),
                $this->cell($daysLeft),
                $this->cellInt($row->recommend_qty),
                $this->cellMoney($row->cost),
                $this->cellMoney($row->est_cost),
                $this->cell((string) $row->unit),
            ];
        }

        if (count($rows) === 1) {
            $rows[] = [
                $this->cell(''), $this->cell(''), $this->cell(''),
                $this->cell('Tidak ada data.'),
                $this->cell(''), $this->cell(''), $this->cell(''), $this->cell(''),
                $this->cell(''), $this->cell(''), $this->cell(''), $this->cell(''),
                $this->cell(''), $this->cell(''),
            ];
        }

        return $this->worksheet('Penjualan Product', $rows, [35, 40, 70, 180, 80, 100, 50, 70, 60, 55, 55, 70, 90, 50]);
    }

    /** @param array<int, array<int, string>> $rows */
    private function worksheet(string $name, array $rows, array $columnWidths): string
    {
        $xml = '<Worksheet ss:Name="'.$this->escape($name).'">'."\n".'<Table>'."\n";
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

        return '<Cell'.$styleAttr.'><Data ss:Type="String">'.$this->escape($value).'</Data></Cell>';
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

    private function formatDate(string $date): string
    {
        try {
            return Carbon::parse($date)->format('d/m/Y');
        } catch (\Throwable) {
            return $date;
        }
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
