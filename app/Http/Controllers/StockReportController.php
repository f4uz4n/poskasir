<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\TransactionItem;
use App\Services\ReportPeriodService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockReportController extends Controller
{
    public function __construct(
        protected ReportPeriodService $period
    ) {}

    public function index(Request $request)
    {
        $user = Auth::user();
        $ownerId = $user->storeOwnerId();

        $q = $request->get('q');
        $categoryId = $request->get('category_id');
        $filter = $request->get('filter'); // all|low|out|expired|near_expired
        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to = $request->get('to', now()->toDateString());

        $base = Product::where('user_id', $ownerId)
            ->where('track_stock', true)
            ->where('is_active', true);

        $summary = [
            'sku_count' => (clone $base)->count(),
            'total_qty' => (clone $base)->sum('stock'),
            'stock_value_cost' => (clone $base)->selectRaw('COALESCE(SUM(stock * cost), 0) as v')->value('v'),
            'stock_value_sell' => (clone $base)->selectRaw('COALESCE(SUM(stock * price), 0) as v')->value('v'),
            'low_stock' => (clone $base)->where('stock', '>', 0)->where('stock', '<=', 5)->count(),
            'out_of_stock' => (clone $base)->where('stock', '<=', 0)->count(),
            'expired' => (clone $base)->where('has_expiry', true)->whereDate('expired_at', '<', now())->count(),
            'near_expired' => (clone $base)->where('has_expiry', true)
                ->whereDate('expired_at', '>=', now())
                ->whereDate('expired_at', '<=', now()->addDays(30))
                ->count(),
        ];

        $soldQtyQuery = TransactionItem::query()
            ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->where('transactions.user_id', $ownerId)
            ->where('transactions.status', 'completed')
            ->whereNotNull('transaction_items.product_id');
        $this->period->applySoldAtRange($soldQtyQuery, $from, $to, 'transactions.sold_at');

        $soldQty = (int) (clone $soldQtyQuery)->sum('transaction_items.qty');
        $soldHpp = (float) (clone $soldQtyQuery)
            ->selectRaw('COALESCE(SUM(transaction_items.cost * transaction_items.qty), 0) as v')
            ->value('v');

        $purchaseQtyQuery = PurchaseItem::query()
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->where('purchases.user_id', $ownerId)
            ->where('purchases.status', 'completed')
            ->whereNotNull('purchase_items.product_id');
        $this->period->applyPurchasedAtRange($purchaseQtyQuery, $from, $to, 'purchases.purchased_at');

        $purchasedQty = (int) (clone $purchaseQtyQuery)->sum('purchase_items.qty');
        $purchasedValue = (float) (clone $purchaseQtyQuery)
            ->selectRaw('COALESCE(SUM(purchase_items.subtotal), 0) as v')
            ->value('v');

        $purchasesBySupplier = DB::table('purchases')
            ->where('user_id', $ownerId)
            ->where('status', 'completed')
            ->whereDate('purchased_at', '>=', $from)
            ->whereDate('purchased_at', '<=', $to)
            ->select(
                'supplier_name',
                DB::raw('COUNT(*) as purchase_count'),
                DB::raw('SUM(total) as total')
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
                ];
            })
            ->sortByDesc('total')
            ->take(10)
            ->values();

        $topSold = (clone $soldQtyQuery)
            ->leftJoin('products', 'products.id', '=', 'transaction_items.product_id')
            ->select(
                'transaction_items.product_id',
                DB::raw('COALESCE(products.name, transaction_items.product_name) as product_name'),
                DB::raw('SUM(transaction_items.qty) as qty'),
                DB::raw('SUM(transaction_items.cost * transaction_items.qty) as hpp')
            )
            ->groupByRaw('transaction_items.product_id, COALESCE(products.name, transaction_items.product_name)')
            ->orderByDesc('qty')
            ->limit(10)
            ->get();

        $topPurchased = (clone $purchaseQtyQuery)
            ->leftJoin('products', 'products.id', '=', 'purchase_items.product_id')
            ->select(
                'purchase_items.product_id',
                DB::raw('COALESCE(products.name, purchase_items.product_name) as product_name'),
                DB::raw('SUM(purchase_items.qty) as qty'),
                DB::raw('SUM(purchase_items.subtotal) as value')
            )
            ->groupByRaw('purchase_items.product_id, COALESCE(products.name, purchase_items.product_name)')
            ->orderByDesc('qty')
            ->limit(10)
            ->get();

        $movements = [
            'from' => $from,
            'to' => $to,
            'sold_qty' => $soldQty,
            'sold_hpp' => $soldHpp,
            'purchased_qty' => $purchasedQty,
            'purchased_value' => $purchasedValue,
            'net_qty' => $purchasedQty - $soldQty,
        ];

        $products = Product::where('user_id', $ownerId)
            ->where('track_stock', true)
            ->where('is_active', true)
            ->with('category')
            ->when($q, function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('barcode', 'like', "%{$q}%")
                        ->orWhere('sku', 'like', "%{$q}%");
                });
            })
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->when($filter === 'low', fn ($query) => $query->where('stock', '>', 0)->where('stock', '<=', 5))
            ->when($filter === 'out', fn ($query) => $query->where('stock', '<=', 0))
            ->when($filter === 'expired', fn ($query) => $query->where('has_expiry', true)->whereDate('expired_at', '<', now()))
            ->when($filter === 'near_expired', function ($query) {
                $query->where('has_expiry', true)
                    ->whereDate('expired_at', '>=', now())
                    ->whereDate('expired_at', '<=', now()->addDays(30));
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $categories = Category::where('user_id', $ownerId)->orderBy('name')->get();

        return view('reports.stock', compact(
            'summary',
            'products',
            'categories',
            'q',
            'categoryId',
            'filter',
            'movements',
            'purchasesBySupplier',
            'topSold',
            'topPurchased',
            'from',
            'to'
        ));
    }
}
