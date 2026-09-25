<?php

namespace App\Http\Controllers;

use App\Services\PurchaseRecommendationExcelExport;
use App\Services\PurchaseRecommendationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseRecommendationController extends Controller
{
    public function __construct(
        protected PurchaseRecommendationService $recommendations
    ) {}

    public function index(Request $request)
    {
        $payload = $this->recommendations->build(Auth::user()->storeOwnerId(), $request);
        $perPage = max(10, min(100, (int) $request->get('per_page', 25)));
        $page = LengthAwarePaginator::resolveCurrentPage();

        $paginator = new LengthAwarePaginator(
            $payload['items']->forPage($page, $perPage)->values(),
            $payload['items']->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
        $paginator->onEachSide(1);

        return view('reports.purchase-recommendations', [
            ...$payload,
            'items' => $paginator,
            'perPage' => $perPage,
        ]);
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $payload = $this->recommendations->build(Auth::user()->storeOwnerId(), $request, true);
        $storeName = Auth::user()->storeOwner()->store_name ?? 'Toko';
        $exporter = new PurchaseRecommendationExcelExport($payload, $storeName);

        return response()->streamDownload(
            fn () => print($exporter->build()),
            $exporter->filename(),
            ['Content-Type' => $exporter->contentType()],
        );
    }

    public function exportPdf(Request $request)
    {
        $payload = $this->recommendations->build(Auth::user()->storeOwnerId(), $request, true);
        $storeName = Auth::user()->storeOwner()->store_name ?? 'Toko';

        $pdf = Pdf::loadView('reports.pdf-purchase-recommendations', [
            ...$payload,
            'storeName' => $storeName,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('penjualan-product-'.$payload['date'].'.pdf');
    }
}
