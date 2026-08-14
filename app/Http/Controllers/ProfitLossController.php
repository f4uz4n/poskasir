<?php

namespace App\Http\Controllers;

use App\Models\Payable;
use App\Models\Purchase;
use App\Models\Receivable;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProfitLossController extends Controller
{
    public function index(Request $request)
    {
        $ownerId = Auth::user()->storeOwnerId();
        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to = $request->get('to', now()->toDateString());

        $salesQuery = Transaction::where('user_id', $ownerId)
            ->where('status', 'completed')
            ->whereDate('sold_at', '>=', $from)
            ->whereDate('sold_at', '<=', $to);

        $netSales = (clone $salesQuery)->sum('total');
        $discount = (clone $salesQuery)->sum('discount');
        $trxCount = (clone $salesQuery)->count();

        $hpp = (float) TransactionItem::query()
            ->whereHas('transaction', function ($q) use ($ownerId, $from, $to) {
                $q->where('user_id', $ownerId)
                    ->where('status', 'completed')
                    ->whereDate('sold_at', '>=', $from)
                    ->whereDate('sold_at', '<=', $to);
            })
            ->selectRaw('COALESCE(SUM(cost * qty), 0) as v')
            ->value('v');

        $grossProfit = (float) $netSales - $hpp;

        $purchaseQuery = Purchase::where('user_id', $ownerId)
            ->where('status', 'completed')
            ->whereDate('purchased_at', '>=', $from)
            ->whereDate('purchased_at', '<=', $to);

        $purchaseTotal = (clone $purchaseQuery)->sum('total');
        $purchasePaid = (clone $purchaseQuery)->sum('paid');
        $purchaseCount = (clone $purchaseQuery)->count();

        // Penerimaan piutang & pembayaran hutang di periode (arus kas, info)
        $receivableCollected = DB::table('finance_payments')
            ->where('user_id', $ownerId)
            ->where('payable_type', 'receivable')
            ->whereDate('paid_at', '>=', $from)
            ->whereDate('paid_at', '<=', $to)
            ->sum('amount');

        $payablePaid = DB::table('finance_payments')
            ->where('user_id', $ownerId)
            ->where('payable_type', 'payable')
            ->whereDate('paid_at', '>=', $from)
            ->whereDate('paid_at', '<=', $to)
            ->sum('amount');

        $outstandingReceivable = Receivable::where('user_id', $ownerId)
            ->whereIn('status', ['unpaid', 'partial'])
            ->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as v')
            ->value('v');

        $outstandingPayable = Payable::where('user_id', $ownerId)
            ->whereIn('status', ['unpaid', 'partial'])
            ->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as v')
            ->value('v');

        // Laba bersih sederhana = laba kotor (beban operasional belum dimodelkan terpisah)
        $netProfit = $grossProfit;
        $margin = $netSales > 0 ? round(($netProfit / $netSales) * 100, 2) : 0;

        $daily = (clone $salesQuery)
            ->select(
                DB::raw('DATE(sold_at) as date'),
                DB::raw('SUM(total) as sales')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

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

        $dailyRows = $daily->map(function ($row) use ($dailyHpp) {
            $hppDay = (float) ($dailyHpp[$row->date] ?? 0);
            $sales = (float) $row->sales;

            return [
                'date' => $row->date,
                'sales' => $sales,
                'hpp' => $hppDay,
                'profit' => $sales - $hppDay,
            ];
        });

        $summary = compact(
            'netSales',
            'discount',
            'trxCount',
            'hpp',
            'grossProfit',
            'netProfit',
            'margin',
            'purchaseTotal',
            'purchasePaid',
            'purchaseCount',
            'receivableCollected',
            'payablePaid',
            'outstandingReceivable',
            'outstandingPayable'
        );

        return view('reports.profit-loss', compact('from', 'to', 'summary', 'dailyRows'));
    }
}
