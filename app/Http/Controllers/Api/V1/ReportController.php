<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\StoreReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function __construct(private StoreReportService $reports) {}

    public function sales(Request $request)
    {
        $ownerId = Auth::user()->storeOwnerId();
        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to = $request->get('to', now()->toDateString());

        return response()->json([
            'success' => true,
            'from' => $from,
            'to' => $to,
            'summary' => $this->reports->salesSummary($ownerId, $from, $to),
        ]);
    }

    public function stock()
    {
        $ownerId = Auth::user()->storeOwnerId();

        return response()->json([
            'success' => true,
            'summary' => $this->reports->stockSummary($ownerId),
        ]);
    }

    public function profitLoss(Request $request)
    {
        $ownerId = Auth::user()->storeOwnerId();
        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to = $request->get('to', now()->toDateString());

        return response()->json([
            'success' => true,
            'from' => $from,
            'to' => $to,
            'summary' => $this->reports->profitLossSummary($ownerId, $from, $to),
        ]);
    }

    public function roi(Request $request)
    {
        $ownerId = Auth::user()->storeOwnerId();

        if ($request->filled('year')) {
            $year = (int) $request->get('year');
            $chart = $this->reports->roiMonthlyChart($ownerId, $year);
            $summary = $this->reports->salesSummary($ownerId, "{$year}-01-01", "{$year}-12-31");

            return response()->json([
                'success' => true,
                'year' => $year,
                'average_roi' => $summary['roi'],
                'summary' => $summary,
                'chart' => $chart,
            ]);
        }

        $days = min(90, max(1, (int) $request->get('days', 7)));
        $chart = $this->reports->roiChart($ownerId, $days);

        $totalHpp = $chart->sum('hpp');
        $totalProfit = $chart->sum('profit');
        $avgRoi = $totalHpp > 0 ? round(($totalProfit / $totalHpp) * 100, 2) : 0;

        return response()->json([
            'success' => true,
            'days' => $days,
            'average_roi' => $avgRoi,
            'chart' => $chart,
        ]);
    }
}
