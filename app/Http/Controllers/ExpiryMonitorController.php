<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpiryMonitorController extends Controller
{
    public function index(Request $request)
    {
        $ownerId = Auth::user()->storeOwnerId();

        $q = $request->get('q');
        $categoryId = $request->get('category_id');
        $filter = $request->get('filter', 'attention'); // attention|expired|near|all
        $days = max(1, min(365, (int) $request->get('days', 30)));

        $today = now()->startOfDay();
        $nearUntil = now()->addDays($days)->endOfDay();

        $base = Product::where('user_id', $ownerId)
            ->where('has_expiry', true)
            ->whereNotNull('expired_at')
            ->where('is_active', true);

        $summary = [
            'total' => (clone $base)->count(),
            'expired' => (clone $base)->whereDate('expired_at', '<', $today)->count(),
            'near' => (clone $base)
                ->whereDate('expired_at', '>=', $today)
                ->whereDate('expired_at', '<=', $nearUntil)
                ->count(),
            'safe' => (clone $base)->whereDate('expired_at', '>', $nearUntil)->count(),
            'expired_qty' => (clone $base)->whereDate('expired_at', '<', $today)->sum('stock'),
            'expired_value' => (clone $base)
                ->whereDate('expired_at', '<', $today)
                ->selectRaw('COALESCE(SUM(stock * cost), 0) as v')
                ->value('v'),
            'near_qty' => (clone $base)
                ->whereDate('expired_at', '>=', $today)
                ->whereDate('expired_at', '<=', $nearUntil)
                ->sum('stock'),
            'near_value' => (clone $base)
                ->whereDate('expired_at', '>=', $today)
                ->whereDate('expired_at', '<=', $nearUntil)
                ->selectRaw('COALESCE(SUM(stock * cost), 0) as v')
                ->value('v'),
        ];

        $products = Product::where('user_id', $ownerId)
            ->where('has_expiry', true)
            ->whereNotNull('expired_at')
            ->with('category')
            ->when($q, function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('barcode', 'like', "%{$q}%")
                        ->orWhere('sku', 'like', "%{$q}%");
                });
            })
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->when($filter === 'expired', fn ($query) => $query->whereDate('expired_at', '<', $today))
            ->when($filter === 'near', function ($query) use ($today, $nearUntil) {
                $query->whereDate('expired_at', '>=', $today)
                    ->whereDate('expired_at', '<=', $nearUntil);
            })
            ->when($filter === 'attention', function ($query) use ($today, $nearUntil) {
                $query->whereDate('expired_at', '<=', $nearUntil);
            })
            ->when($filter === 'safe', fn ($query) => $query->whereDate('expired_at', '>', $nearUntil))
            ->orderBy('expired_at')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $categories = Category::where('user_id', $ownerId)->orderBy('name')->get();

        return view('expiry.index', compact(
            'summary',
            'products',
            'categories',
            'q',
            'categoryId',
            'filter',
            'days'
        ));
    }
}
