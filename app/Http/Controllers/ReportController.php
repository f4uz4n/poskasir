<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Services\SalesReportExcelExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $payload = $this->buildReportPayload($request);
        $transactions = (clone $payload['baseQuery'])
            ->with('items')
            ->latest('sold_at')
            ->paginate(20)
            ->withQueryString();

        return view('reports.index', [
            ...$payload,
            'transactions' => $transactions,
        ]);
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $payload = $this->buildReportPayload($request);
        $storeName = Auth::user()->storeOwner()->store_name ?? 'Toko';
        $exporter = new SalesReportExcelExport($payload, $storeName);

        return response()->streamDownload(
            fn () => print($exporter->build()),
            $exporter->filename(),
            ['Content-Type' => $exporter->contentType()],
        );
    }

    public function exportPdf(Request $request)
    {
        $payload = $this->buildReportPayload($request);
        $storeName = Auth::user()->storeOwner()->store_name ?? 'Toko';

        $pdf = Pdf::loadView('reports.pdf-sales', [
            ...$payload,
            'storeName' => $storeName,
        ])->setPaper('a4', 'portrait');

        $filename = 'laporan-penjualan-'.$payload['from'].'-'.$payload['to'].'.pdf';

        return $pdf->download($filename);
    }

    /**
     * @return array{
     *     from: string,
     *     to: string,
     *     orderType: ?string,
     *     summary: array<string, mixed>,
     *     daily: \Illuminate\Support\Collection,
     *     topProducts: \Illuminate\Support\Collection,
     *     byPayment: \Illuminate\Support\Collection,
     *     allTransactions: \Illuminate\Database\Eloquent\Collection,
     *     baseQuery: \Illuminate\Database\Eloquent\Builder
     * }
     */
    private function buildReportPayload(Request $request): array
    {
        $ownerId = Auth::user()->storeOwnerId();

        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to = $request->get('to', now()->toDateString());
        $orderType = $request->get('order_type');

        $baseQuery = Transaction::where('user_id', $ownerId)
            ->where('status', 'completed')
            ->whereDate('sold_at', '>=', $from)
            ->whereDate('sold_at', '<=', $to)
            ->when($orderType, fn ($q) => $q->where('order_type', $orderType));

        $summary = [
            'trx_count' => (clone $baseQuery)->count(),
            'gross_sales' => (clone $baseQuery)->sum('subtotal'),
            'discount' => (clone $baseQuery)->sum('discount'),
            'tax' => (clone $baseQuery)->sum('tax'),
            'net_sales' => (clone $baseQuery)->sum('total'),
            'dine_in' => (clone $baseQuery)->where('order_type', 'dine_in')->count(),
            'takeaway' => (clone $baseQuery)->where('order_type', 'takeaway')->count(),
        ];

        $hpp = TransactionItem::query()
            ->whereHas('transaction', function ($q) use ($ownerId, $from, $to, $orderType) {
                $q->where('user_id', $ownerId)
                    ->where('status', 'completed')
                    ->whereDate('sold_at', '>=', $from)
                    ->whereDate('sold_at', '<=', $to)
                    ->when($orderType, fn ($qq) => $qq->where('order_type', $orderType));
            })
            ->selectRaw('COALESCE(SUM(cost * qty), 0) as total_hpp, COALESCE(SUM(subtotal), 0) as item_sales, COALESCE(SUM(qty), 0) as total_qty')
            ->first();

        $summary['hpp'] = (float) ($hpp->total_hpp ?? 0);
        $summary['item_sales'] = (float) ($hpp->item_sales ?? 0);
        $summary['total_qty'] = (int) ($hpp->total_qty ?? 0);
        $summary['gross_profit'] = (float) $summary['net_sales'] - (float) $summary['hpp'];
        $summary['margin'] = $summary['net_sales'] > 0
            ? round(($summary['gross_profit'] / $summary['net_sales']) * 100, 2)
            : 0;

        $daily = (clone $baseQuery)
            ->select(
                DB::raw('DATE(sold_at) as date'),
                DB::raw('COUNT(*) as trx_count'),
                DB::raw('SUM(total) as sales'),
                DB::raw("SUM(CASE WHEN order_type = 'dine_in' THEN 1 ELSE 0 END) as dine_in"),
                DB::raw("SUM(CASE WHEN order_type = 'takeaway' THEN 1 ELSE 0 END) as takeaway")
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
            ->when($orderType, fn ($q) => $q->where('transactions.order_type', $orderType))
            ->select(
                DB::raw('DATE(transactions.sold_at) as date'),
                DB::raw('SUM(transaction_items.cost * transaction_items.qty) as hpp')
            )
            ->groupBy('date')
            ->pluck('hpp', 'date');

        $daily = $daily->map(function ($row) use ($dailyHpp) {
            $row->hpp = (float) ($dailyHpp[$row->date] ?? 0);
            $row->profit = (float) $row->sales - $row->hpp;

            return $row;
        });

        $topProducts = TransactionItem::query()
            ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->where('transactions.user_id', $ownerId)
            ->where('transactions.status', 'completed')
            ->whereDate('transactions.sold_at', '>=', $from)
            ->whereDate('transactions.sold_at', '<=', $to)
            ->when($orderType, fn ($q) => $q->where('transactions.order_type', $orderType))
            ->select(
                'transaction_items.product_name',
                DB::raw('SUM(transaction_items.qty) as qty'),
                DB::raw('SUM(transaction_items.subtotal) as sales'),
                DB::raw('SUM(transaction_items.cost * transaction_items.qty) as hpp'),
                DB::raw('SUM(transaction_items.subtotal) - SUM(transaction_items.cost * transaction_items.qty) as profit')
            )
            ->groupBy('transaction_items.product_name')
            ->orderByDesc('qty')
            ->limit(15)
            ->get();

        $byPayment = (clone $baseQuery)
            ->select('payment_method', DB::raw('COUNT(*) as trx_count'), DB::raw('SUM(total) as total'))
            ->groupBy('payment_method')
            ->get();

        $allTransactions = (clone $baseQuery)
            ->latest('sold_at')
            ->get();

        return compact('from', 'to', 'orderType', 'summary', 'daily', 'topProducts', 'byPayment', 'allTransactions', 'baseQuery');
    }
}
