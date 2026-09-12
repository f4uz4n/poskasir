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
    public function __construct(
        protected ReportPeriodService $period
    ) {}

    public function salesSummary(int $ownerId, string $from, string $to): array
    {
        $baseQuery = Transaction::query()
            ->where('user_id', $ownerId)
            ->where('status', 'completed');
        $this->period->applySoldAtRange($baseQuery, $from, $to);

        $itemsBase = TransactionItem::query()
            ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->where('transactions.user_id', $ownerId)
            ->where('transactions.status', 'completed');
        $this->period->applySoldAtRange($itemsBase, $from, $to, 'transactions.sold_at');

        $summary = $this->period->salesSummaryFromQuery($baseQuery, $itemsBase);
        $summary['roi'] = $summary['hpp'] > 0
            ? round(($summary['gross_profit'] / $summary['hpp']) * 100, 2)
            : 0;

        return $summary;
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

        $purchaseQuery = Purchase::query()
            ->where('user_id', $ownerId)
            ->where('status', 'completed');
        $this->period->applyPurchasedAtRange($purchaseQuery, $from, $to);

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
        $from = now()->subDays($days - 1)->toDateString();
        $to = now()->toDateString();

        $dateExpr = $this->period->sqlDateExpression('sold_at');
        $dateExprTx = $this->period->sqlDateExpression('transactions.sold_at');

        $salesQuery = Transaction::query()
            ->where('user_id', $ownerId)
            ->where('status', 'completed');
        $this->period->applySoldAtRange($salesQuery, $from, $to);

        $dailySales = (clone $salesQuery)
            ->select(
                DB::raw("{$dateExpr} as date"),
                DB::raw('SUM(subtotal) as gross_sales'),
                DB::raw('SUM(discount) as discount')
            )
            ->groupByRaw($dateExpr)
            ->get()
            ->keyBy(fn ($row) => $this->period->normalizeDateKey($row->date));

        $itemsBase = TransactionItem::query()
            ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->where('transactions.user_id', $ownerId)
            ->where('transactions.status', 'completed');
        $this->period->applySoldAtRange($itemsBase, $from, $to, 'transactions.sold_at');

        $dailyHpp = (clone $itemsBase)
            ->select(
                DB::raw("{$dateExprTx} as date"),
                DB::raw('SUM(transaction_items.cost * transaction_items.qty) as hpp')
            )
            ->groupByRaw($dateExprTx)
            ->get()
            ->keyBy(fn ($row) => $this->period->normalizeDateKey($row->date));

        $rows = collect();
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $salesRow = $dailySales->get($date);
            $revenue = $salesRow
                ? (float) $salesRow->gross_sales - (float) $salesRow->discount
                : 0.0;
            $hpp = (float) ($dailyHpp->get($date)->hpp ?? 0);
            $profit = round($revenue - $hpp, 2);
            $roi = $hpp > 0 ? round(($profit / $hpp) * 100, 2) : 0;

            $rows->push([
                'date' => $date,
                'sales' => $revenue,
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
                'sales' => $summary['revenue'],
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
}
