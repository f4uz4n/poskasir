<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StoreReportService
{
    public function salesSummary(int $ownerId, string $from, string $to): array
    {
        $baseQuery = Transaction::where('user_id', $ownerId)
            ->where('status', 'completed')
            ->whereDate('sold_at', '>=', $from)
            ->whereDate('sold_at', '<=', $to);

        $netSales = (float) (clone $baseQuery)->sum('total');
        $hpp = $this->totalHpp($ownerId, $from, $to);
        $grossProfit = $netSales - $hpp;

        return [
            'trx_count' => (clone $baseQuery)->count(),
            'gross_sales' => (float) (clone $baseQuery)->sum('subtotal'),
            'discount' => (float) (clone $baseQuery)->sum('discount'),
            'tax' => (float) (clone $baseQuery)->sum('tax'),
            'net_sales' => $netSales,
            'hpp' => $hpp,
            'gross_profit' => $grossProfit,
            'margin' => $netSales > 0 ? round(($grossProfit / $netSales) * 100, 2) : 0,
            'roi' => $hpp > 0 ? round(($grossProfit / $hpp) * 100, 2) : 0,
        ];
    }

    public function stockSummary(int $ownerId): array
    {
        $base = Product::where('user_id', $ownerId)
            ->where('track_stock', true)
            ->where('is_active', true);

        return [
            'sku_count' => (clone $base)->count(),
            'total_qty' => (int) (clone $base)->sum('stock'),
            'stock_value_cost' => (float) (clone $base)->selectRaw('COALESCE(SUM(stock * cost), 0) as v')->value('v'),
            'stock_value_sell' => (float) (clone $base)->selectRaw('COALESCE(SUM(stock * price), 0) as v')->value('v'),
            'low_stock' => (clone $base)->where('stock', '>', 0)->where('stock', '<=', 5)->count(),
            'out_of_stock' => (clone $base)->where('stock', '<=', 0)->count(),
        ];
    }

    public function profitLossSummary(int $ownerId, string $from, string $to): array
    {
        $sales = $this->salesSummary($ownerId, $from, $to);

        $purchaseQuery = Purchase::where('user_id', $ownerId)
            ->where('status', 'completed')
            ->whereDate('purchased_at', '>=', $from)
            ->whereDate('purchased_at', '<=', $to);

        return array_merge($sales, [
            'purchase_total' => (float) (clone $purchaseQuery)->sum('total'),
            'purchase_paid' => (float) (clone $purchaseQuery)->sum('paid'),
            'purchase_count' => (clone $purchaseQuery)->count(),
            'net_profit' => $sales['gross_profit'],
        ]);
    }

    /** ROI harian: (laba kotor / HPP) × 100 */
    public function roiChart(int $ownerId, int $days = 7): Collection
    {
        $from = now()->subDays($days - 1)->startOfDay()->toDateString();
        $to = now()->toDateString();

        $dailySales = Transaction::where('user_id', $ownerId)
            ->where('status', 'completed')
            ->whereDate('sold_at', '>=', $from)
            ->whereDate('sold_at', '<=', $to)
            ->select(DB::raw('DATE(sold_at) as date'), DB::raw('SUM(total) as sales'))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('sales', 'date');

        $dailyHpp = TransactionItem::query()
            ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->where('transactions.user_id', $ownerId)
            ->where('transactions.status', 'completed')
            ->whereDate('transactions.sold_at', '>=', $from)
            ->whereDate('transactions.sold_at', '<=', $to)
            ->select(
                DB::raw('DATE(transactions.sold_at) as date'),
                DB::raw('SUM(transaction_items.cost * transaction_items.qty) as hpp')
            )
            ->groupBy('date')
            ->pluck('hpp', 'date');

        $rows = collect();
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $sales = (float) ($dailySales[$date] ?? 0);
            $hpp = (float) ($dailyHpp[$date] ?? 0);
            $profit = $sales - $hpp;
            $roi = $hpp > 0 ? round(($profit / $hpp) * 100, 2) : 0;

            $rows->push([
                'date' => $date,
                'sales' => $sales,
                'hpp' => $hpp,
                'profit' => $profit,
                'roi' => $roi,
            ]);
        }

        return $rows;
    }

    /** ROI bulanan per tahun: Jan–Des */
    public function roiMonthlyChart(int $ownerId, int $year): Collection
    {
        $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $rows = collect();

        for ($month = 1; $month <= 12; $month++) {
            $from = sprintf('%04d-%02d-01', $year, $month);
            $to = date('Y-m-t', strtotime($from));
            $summary = $this->salesSummary($ownerId, $from, $to);

            $rows->push([
                'month' => $month,
                'label' => $labels[$month - 1],
                'sales' => $summary['net_sales'],
                'hpp' => $summary['hpp'],
                'profit' => $summary['gross_profit'],
                'roi' => $summary['roi'],
            ]);
        }

        return $rows;
    }

    /** Tahun yang punya data penjualan (untuk dropdown) */
    public function availableRoiYears(int $ownerId): array
    {
        $minDate = Transaction::where('user_id', $ownerId)
            ->where('status', 'completed')
            ->min('sold_at');

        $maxDate = Transaction::where('user_id', $ownerId)
            ->where('status', 'completed')
            ->max('sold_at');

        if (! $minDate) {
            return [(int) now()->year];
        }

        $start = (int) date('Y', strtotime($minDate));
        $end = (int) date('Y', strtotime($maxDate ?: now()));

        return range($start, $end);
    }

    private function totalHpp(int $ownerId, string $from, string $to): float
    {
        return (float) TransactionItem::query()
            ->whereHas('transaction', function ($q) use ($ownerId, $from, $to) {
                $q->where('user_id', $ownerId)
                    ->where('status', 'completed')
                    ->whereDate('sold_at', '>=', $from)
                    ->whereDate('sold_at', '<=', $to);
            })
            ->selectRaw('COALESCE(SUM(cost * qty), 0) as v')
            ->value('v');
    }
}
