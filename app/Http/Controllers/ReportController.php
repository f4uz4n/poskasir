<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Services\ReportPeriodService;
use App\Services\SalesReportExcelExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        protected ReportPeriodService $period
    ) {}

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
     * @return array<string, mixed>
     */
    private function buildReportPayload(Request $request): array
    {
        $ownerId = Auth::user()->storeOwnerId();

        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to = $request->get('to', now()->toDateString());
        $orderType = $request->get('order_type') ?: null;

        $baseQuery = Transaction::query()
            ->where('user_id', $ownerId)
            ->where('status', 'completed');
        $this->period->applySoldAtRange($baseQuery, $from, $to);
        if ($orderType) {
            $baseQuery->where('order_type', $orderType);
        }

        $itemsBase = TransactionItem::query()
            ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->where('transactions.user_id', $ownerId)
            ->where('transactions.status', 'completed');
        $this->period->applySoldAtRange($itemsBase, $from, $to, 'transactions.sold_at');
        if ($orderType) {
            $itemsBase->where('transactions.order_type', $orderType);
        }

        $summary = $this->period->salesSummaryFromQuery($baseQuery, $itemsBase);

        $dateExpr = $this->period->sqlDateExpression('sold_at');
        $dateExprTx = $this->period->sqlDateExpression('transactions.sold_at');

        $daily = (clone $baseQuery)
            ->select(
                DB::raw("{$dateExpr} as date"),
                DB::raw('COUNT(*) as trx_count'),
                DB::raw('SUM(subtotal) as gross_sales'),
                DB::raw('SUM(discount) as discount'),
                DB::raw('SUM(tax) as tax'),
                DB::raw('SUM(total) as sales'),
                DB::raw("SUM(CASE WHEN order_type = 'dine_in' THEN 1 ELSE 0 END) as dine_in"),
                DB::raw("SUM(CASE WHEN order_type = 'takeaway' THEN 1 ELSE 0 END) as takeaway")
            )
            ->groupByRaw($dateExpr)
            ->orderBy('date')
            ->get();

        $dailyHpp = (clone $itemsBase)
            ->select(
                DB::raw("{$dateExprTx} as date"),
                DB::raw('SUM(transaction_items.cost * transaction_items.qty) as hpp'),
                DB::raw('SUM(transaction_items.qty) as qty')
            )
            ->groupByRaw($dateExprTx)
            ->get()
            ->keyBy(fn ($row) => $this->period->normalizeDateKey($row->date));

        $daily = $daily->map(function ($row) use ($dailyHpp) {
            $key = $this->period->normalizeDateKey($row->date);
            $row->date = $key;
            $hppRow = $dailyHpp->get($key);
            $row->hpp = (float) ($hppRow->hpp ?? 0);
            $row->qty = (int) ($hppRow->qty ?? 0);
            $revenue = (float) $row->gross_sales - (float) $row->discount;
            $row->revenue = round($revenue, 2);
            $row->profit = round($revenue - (float) $row->hpp, 2);

            return $row;
        });

        $dailyTotals = [
            'trx_count' => (int) $daily->sum('trx_count'),
            'sales' => (float) $daily->sum('sales'),
            'revenue' => (float) $daily->sum('revenue'),
            'hpp' => (float) $daily->sum('hpp'),
            'profit' => (float) $daily->sum('profit'),
        ];

        // Alokasi diskon header proporsional ke item agar laba produk selaras dengan laba periode
        $topProducts = (clone $itemsBase)
            ->select(
                'transaction_items.product_name',
                DB::raw('SUM(transaction_items.qty) as qty'),
                DB::raw('SUM(transaction_items.subtotal) as sales'),
                DB::raw('SUM(transaction_items.cost * transaction_items.qty) as hpp')
            )
            ->groupBy('transaction_items.product_name')
            ->orderByDesc('qty')
            ->limit(15)
            ->get()
            ->map(function ($p) use ($summary) {
                $itemSales = (float) $p->sales;
                $share = $summary['item_sales'] > 0 ? ($itemSales / $summary['item_sales']) : 0;
                $allocatedDiscount = $summary['discount'] * $share;
                $revenue = round($itemSales - $allocatedDiscount, 2);
                $hpp = (float) $p->hpp;
                $p->sales = $itemSales;
                $p->revenue = $revenue;
                $p->hpp = $hpp;
                $p->profit = round($revenue - $hpp, 2);

                return $p;
            });

        $byPayment = (clone $baseQuery)
            ->select('payment_method', DB::raw('COUNT(*) as trx_count'), DB::raw('SUM(total) as total'))
            ->groupBy('payment_method')
            ->get();

        $allTransactions = (clone $baseQuery)
            ->latest('sold_at')
            ->get();

        $detailTotals = [
            'trx_count' => $allTransactions->count(),
            'sales' => (float) $allTransactions->sum('total'),
            'revenue' => round((float) $allTransactions->sum('subtotal') - (float) $allTransactions->sum('discount'), 2),
        ];

        return compact(
            'from',
            'to',
            'orderType',
            'summary',
            'daily',
            'dailyTotals',
            'topProducts',
            'byPayment',
            'allTransactions',
            'detailTotals',
            'baseQuery'
        );
    }
}
