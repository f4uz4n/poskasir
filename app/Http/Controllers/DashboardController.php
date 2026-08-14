<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\StoreReportService;

class DashboardController extends Controller
{
    public function __construct(private StoreReportService $reports) {}

    public function index()
    {
        $actor = Auth::user();

        if ($actor->isDeveloper()) {
            return redirect()->route('developer.dashboard');
        }

        $ownerId = $actor->storeOwnerId();
        $today = now()->toDateString();

        $stats = [
            'products' => Product::where('user_id', $ownerId)->count(),
            'today_sales' => Transaction::where('user_id', $ownerId)
                ->whereDate('sold_at', $today)
                ->where('status', 'completed')
                ->sum('total'),
            'today_count' => Transaction::where('user_id', $ownerId)
                ->whereDate('sold_at', $today)
                ->where('status', 'completed')
                ->count(),
            'month_sales' => Transaction::where('user_id', $ownerId)
                ->whereMonth('sold_at', now()->month)
                ->whereYear('sold_at', now()->year)
                ->where('status', 'completed')
                ->sum('total'),
        ];

        $recent = Transaction::where('user_id', $ownerId)
            ->with(['items', 'cashier'])
            ->latest('sold_at')
            ->limit(8)
            ->get();

        $chart = Transaction::where('user_id', $ownerId)
            ->where('status', 'completed')
            ->where('sold_at', '>=', now()->subDays(6)->startOfDay())
            ->select(DB::raw('DATE(sold_at) as date'), DB::raw('SUM(total) as total'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $roiYear = (int) now()->year;
        $roiChart = $this->reports->roiMonthlyChart($ownerId, $roiYear);
        $yearFrom = sprintf('%04d-01-01', $roiYear);
        $yearTo = now()->toDateString();
        $yearRoi = $this->reports->salesSummary($ownerId, $yearFrom, $yearTo);
        $avgMonthlySales = $yearRoi['net_sales'] / max(1, now()->month);

        $subscription = $actor->storeOwner()->subscriptions()
            ->with('plan')
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->latest()
            ->first();

        return view('dashboard', compact('stats', 'recent', 'chart', 'roiChart', 'yearRoi', 'roiYear', 'yearTo', 'avgMonthlySales', 'subscription', 'actor'));
    }
}
