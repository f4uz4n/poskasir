<?php

namespace App\Http\Controllers;

use App\Models\Payable;
use App\Models\Purchase;
use App\Models\Receivable;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Services\ReportPeriodService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProfitLossController extends Controller
{
    public function __construct(
        protected ReportPeriodService $period
    ) {}

    public function index(Request $request)
    {
        $ownerId = Auth::user()->storeOwnerId();
        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to = $request->get('to', now()->toDateString());

        $salesQuery = Transaction::query()
            ->where('user_id', $ownerId)
            ->where('status', 'completed');
        $this->period->applySoldAtRange($salesQuery, $from, $to);

        $itemsBase = TransactionItem::query()
            ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->where('transactions.user_id', $ownerId)
            ->where('transactions.status', 'completed');
        $this->period->applySoldAtRange($itemsBase, $from, $to, 'transactions.sold_at');

        $sales = $this->period->salesSummaryFromQuery($salesQuery, $itemsBase);

        $purchaseQuery = Purchase::query()
            ->where('user_id', $ownerId)
            ->where('status', 'completed');
        $this->period->applyPurchasedAtRange($purchaseQuery, $from, $to);

        $purchaseTotal = (float) (clone $purchaseQuery)->sum('total');
        $purchasePaid = (float) (clone $purchaseQuery)->sum('paid');
        $purchaseCount = (clone $purchaseQuery)->count();

        $purchasesBySupplier = (clone $purchaseQuery)
            ->select(
                'supplier_name',
                DB::raw('COUNT(*) as purchase_count'),
                DB::raw('SUM(total) as total'),
                DB::raw('SUM(paid) as paid')
            )
            ->groupBy('supplier_name')
            ->orderByDesc('total')
            ->get()
            ->map(function ($row) {
                $row->supplier = filled($row->supplier_name) ? $row->supplier_name : 'Tanpa supplier';

                return $row;
            })
            ->groupBy('supplier')
            ->map(function ($rows) {
                return (object) [
                    'supplier' => $rows->first()->supplier,
                    'purchase_count' => (int) $rows->sum('purchase_count'),
                    'total' => (float) $rows->sum('total'),
                    'paid' => (float) $rows->sum('paid'),
                ];
            })
            ->sortByDesc('total')
            ->values();

        $receivableCollected = (float) DB::table('finance_payments')
            ->where('user_id', $ownerId)
            ->where('payable_type', 'receivable')
            ->whereDate('paid_at', '>=', $from)
            ->whereDate('paid_at', '<=', $to)
            ->sum('amount');

        $payablePaid = (float) DB::table('finance_payments')
            ->where('user_id', $ownerId)
            ->where('payable_type', 'payable')
            ->whereDate('paid_at', '>=', $from)
            ->whereDate('paid_at', '<=', $to)
            ->sum('amount');

        $outstandingReceivable = (float) Receivable::where('user_id', $ownerId)
            ->whereIn('status', ['unpaid', 'partial'])
            ->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as v')
            ->value('v');

        $outstandingPayable = (float) Payable::where('user_id', $ownerId)
            ->whereIn('status', ['unpaid', 'partial'])
            ->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as v')
            ->value('v');

        $dateExpr = $this->period->sqlDateExpression('sold_at');
        $dateExprTx = $this->period->sqlDateExpression('transactions.sold_at');

        $daily = (clone $salesQuery)
            ->select(
                DB::raw("{$dateExpr} as date"),
                DB::raw('SUM(subtotal) as gross_sales'),
                DB::raw('SUM(discount) as discount'),
                DB::raw('SUM(total) as sales')
            )
            ->groupByRaw($dateExpr)
            ->orderBy('date')
            ->get();

        $dailyHpp = (clone $itemsBase)
            ->select(
                DB::raw("{$dateExprTx} as date"),
                DB::raw('SUM(transaction_items.cost * transaction_items.qty) as hpp')
            )
            ->groupByRaw($dateExprTx)
            ->get()
            ->keyBy(fn ($row) => $this->period->normalizeDateKey($row->date));

        $dailyRows = $daily->map(function ($row) use ($dailyHpp) {
            $key = $this->period->normalizeDateKey($row->date);
            $hppDay = (float) ($dailyHpp->get($key)->hpp ?? 0);
            $revenue = (float) $row->gross_sales - (float) $row->discount;

            return [
                'date' => $key,
                'sales' => (float) $row->sales,
                'revenue' => round($revenue, 2),
                'hpp' => $hppDay,
                'profit' => round($revenue - $hppDay, 2),
            ];
        });

        $dailyTotals = [
            'sales' => (float) $dailyRows->sum('sales'),
            'revenue' => (float) $dailyRows->sum('revenue'),
            'hpp' => (float) $dailyRows->sum('hpp'),
            'profit' => (float) $dailyRows->sum('profit'),
        ];

        $summary = [
            'netSales' => $sales['net_sales'],
            'grossSales' => $sales['gross_sales'],
            'discount' => $sales['discount'],
            'tax' => $sales['tax'],
            'revenue' => $sales['revenue'],
            'trxCount' => $sales['trx_count'],
            'hpp' => $sales['hpp'],
            'grossProfit' => $sales['gross_profit'],
            'netProfit' => $sales['gross_profit'],
            'margin' => $sales['margin'],
            'purchaseTotal' => $purchaseTotal,
            'purchasePaid' => $purchasePaid,
            'purchaseCount' => $purchaseCount,
            'receivableCollected' => $receivableCollected,
            'payablePaid' => $payablePaid,
            'outstandingReceivable' => $outstandingReceivable,
            'outstandingPayable' => $outstandingPayable,
        ];

        return view('reports.profit-loss', compact(
            'from',
            'to',
            'summary',
            'dailyRows',
            'dailyTotals',
            'purchasesBySupplier'
        ));
    }
}
