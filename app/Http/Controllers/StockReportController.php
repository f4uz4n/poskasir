<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StockReportController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $ownerId = $user->storeOwnerId();

        $q = $request->get('q');
        $categoryId = $request->get('category_id');
        $filter = $request->get('filter'); // all|low|out|expired|near_expired

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

        $products = Product::where('user_id', $ownerId)
            ->where('track_stock', true)
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

        return view('reports.stock', compact('summary', 'products', 'categories', 'q', 'categoryId', 'filter'));
    }
}
