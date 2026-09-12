<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Sumber kebenaran filter & metrik laporan penjualan/pembelian/stok.
 * Hanya transaksi status=completed yang masuk omzet & HPP.
 * Laba kotor = (subtotal - diskon) - HPP = (total - pajak) - HPP
 * (pajak tidak dihitung sebagai laba).
 */
class ReportPeriodService
{
    public function parseDate(string $value, bool $endOfDay = false): Carbon
    {
        $date = Carbon::parse($value, config('app.timezone'))->startOfDay();

        return $endOfDay ? $date->copy()->endOfDay() : $date;
    }

    /**
     * Rentang sold_at inklusif berdasarkan kalender aplikasi (Asia/Jakarta).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function soldAtBounds(string $from, string $to): array
    {
        return [$this->parseDate($from), $this->parseDate($to, true)];
    }

    public function applySoldAtRange(Builder $query, string $from, string $to, string $column = 'sold_at'): Builder
    {
        [$start, $end] = $this->soldAtBounds($from, $to);

        return $query->whereBetween($column, [$start->toDateTimeString(), $end->toDateTimeString()]);
    }

    public function applyPurchasedAtRange(Builder $query, string $from, string $to, string $column = 'purchased_at'): Builder
    {
        return $query
            ->whereDate($column, '>=', $from)
            ->whereDate($column, '<=', $to);
    }

    /** Ekspresi tanggal kalender untuk GROUP BY (MySQL / SQLite). */
    public function sqlDateExpression(string $column): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return "date({$column})";
        }

        // MySQL: kolom datetime disimpan sesuai timezone app saat insert.
        return "DATE({$column})";
    }

    /**
     * @return array{
     *   trx_count:int,
     *   gross_sales:float,
     *   discount:float,
     *   tax:float,
     *   net_sales:float,
     *   revenue:float,
     *   hpp:float,
     *   gross_profit:float,
     *   margin:float,
     *   total_qty:int,
     *   item_sales:float,
     *   dine_in:int,
     *   takeaway:int
     * }
     */
    public function salesSummaryFromQuery(Builder $salesQuery, ?Builder $itemsAggQuery = null): array
    {
        $grossSales = (float) (clone $salesQuery)->sum('subtotal');
        $discount = (float) (clone $salesQuery)->sum('discount');
        $tax = (float) (clone $salesQuery)->sum('tax');
        $netSales = (float) (clone $salesQuery)->sum('total');
        $revenue = round($grossSales - $discount, 2);
        // Cadangan jika data tidak konsisten: total - tax
        if (abs($revenue - ($netSales - $tax)) > 0.5) {
            $revenue = round($netSales - $tax, 2);
        }

        $hpp = 0.0;
        $itemSales = 0.0;
        $totalQty = 0;
        if ($itemsAggQuery) {
            $agg = (clone $itemsAggQuery)
                ->selectRaw('COALESCE(SUM(transaction_items.cost * transaction_items.qty), 0) as total_hpp')
                ->selectRaw('COALESCE(SUM(transaction_items.subtotal), 0) as item_sales')
                ->selectRaw('COALESCE(SUM(transaction_items.qty), 0) as total_qty')
                ->first();
            $hpp = (float) ($agg->total_hpp ?? 0);
            $itemSales = (float) ($agg->item_sales ?? 0);
            $totalQty = (int) ($agg->total_qty ?? 0);
        }

        $grossProfit = round($revenue - $hpp, 2);
        $margin = $revenue > 0 ? round(($grossProfit / $revenue) * 100, 2) : 0.0;

        return [
            'trx_count' => (clone $salesQuery)->count(),
            'gross_sales' => $grossSales,
            'discount' => $discount,
            'tax' => $tax,
            'net_sales' => $netSales,
            'revenue' => $revenue,
            'hpp' => $hpp,
            'gross_profit' => $grossProfit,
            'margin' => $margin,
            'total_qty' => $totalQty,
            'item_sales' => $itemSales,
            'dine_in' => (clone $salesQuery)->where('order_type', 'dine_in')->count(),
            'takeaway' => (clone $salesQuery)->where('order_type', 'takeaway')->count(),
        ];
    }

    public function normalizeDateKey(mixed $date): string
    {
        if ($date instanceof Carbon) {
            return $date->toDateString();
        }

        return Carbon::parse((string) $date)->toDateString();
    }
}
