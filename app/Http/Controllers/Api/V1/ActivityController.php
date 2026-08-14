<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Payable;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Receivable;
use App\Models\StockOpname;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityController extends Controller
{
    public function products(Request $request)
    {
        $ownerId = Auth::user()->storeOwnerId();
        $since = $request->date('since');

        $products = Product::where('user_id', $ownerId)
            ->with('category')
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->when($request->boolean('active_only'), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->paginate(min(200, (int) $request->get('per_page', 100)));

        return response()->json(['success' => true, 'products' => $products]);
    }

    public function categories(Request $request)
    {
        $ownerId = Auth::user()->storeOwnerId();
        $since = $request->date('since');

        $categories = Category::where('user_id', $ownerId)
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'categories' => $categories]);
    }

    public function transactions(Request $request)
    {
        $ownerId = Auth::user()->storeOwnerId();
        $since = $request->date('since');

        $transactions = Transaction::where('user_id', $ownerId)
            ->with(['items', 'cashier:id,name'])
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->when($request->get('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->date('from'), fn ($q, $from) => $q->whereDate('sold_at', '>=', $from))
            ->when($request->date('to'), fn ($q, $to) => $q->whereDate('sold_at', '<=', $to))
            ->latest('sold_at')
            ->paginate(min(200, (int) $request->get('per_page', 50)));

        return response()->json(['success' => true, 'transactions' => $transactions]);
    }

    public function purchases(Request $request)
    {
        $ownerId = Auth::user()->storeOwnerId();
        $since = $request->date('since');

        $purchases = Purchase::where('user_id', $ownerId)
            ->with('items')
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->when($request->date('from'), fn ($q, $from) => $q->whereDate('purchased_at', '>=', $from))
            ->when($request->date('to'), fn ($q, $to) => $q->whereDate('purchased_at', '<=', $to))
            ->latest('purchased_at')
            ->paginate(min(200, (int) $request->get('per_page', 50)));

        return response()->json(['success' => true, 'purchases' => $purchases]);
    }

    public function receivables(Request $request)
    {
        $ownerId = Auth::user()->storeOwnerId();
        $since = $request->date('since');

        $receivables = Receivable::where('user_id', $ownerId)
            ->with('payments')
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->when($request->get('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest('recorded_at')
            ->paginate(min(200, (int) $request->get('per_page', 50)));

        return response()->json(['success' => true, 'receivables' => $receivables]);
    }

    public function payables(Request $request)
    {
        $ownerId = Auth::user()->storeOwnerId();
        $since = $request->date('since');

        $payables = Payable::where('user_id', $ownerId)
            ->with('payments')
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->when($request->get('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest('recorded_at')
            ->paginate(min(200, (int) $request->get('per_page', 50)));

        return response()->json(['success' => true, 'payables' => $payables]);
    }

    public function stockOpnames(Request $request)
    {
        $ownerId = Auth::user()->storeOwnerId();
        $since = $request->date('since');

        $stockOpnames = StockOpname::where('user_id', $ownerId)
            ->with('items')
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->when($request->get('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(min(100, (int) $request->get('per_page', 30)));

        return response()->json(['success' => true, 'stock_opnames' => $stockOpnames]);
    }
}
