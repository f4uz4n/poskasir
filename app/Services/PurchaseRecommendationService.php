<?php

namespace App\Services;

use App\Models\Product;
use App\Models\TransactionItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PurchaseRecommendationService
{
    public function __construct(
        protected ReportPeriodService $period
    ) {}

    /**
     * @return array{
     *   date:string,
     *   lookbackDays:int,
     *   coverageDays:int,
     *   lookbackFrom:string,
     *   priority:string|null,
     *   q:string|null,
     *   items:Collection<int, object>,
     *   itemsMeta:Collection<int, array{id:int, qty:int, cost:float, red:bool}>,
     *   defaultCheckedIds:Collection<int, int>,
     *   summary:array<string, int|float>
     * }
     */
    public function build(int $ownerId, Request $request, bool $applySelectedFilter = false): array
    {
        $date = $request->get('date', now()->toDateString());
        $lookbackDays = max(1, min(90, (int) $request->get('lookback_days', 14)));
        $coverageDays = max(1, min(90, (int) $request->get('coverage_days', 14)));
        $priority = $request->get('priority') ?: null;
        $q = trim((string) $request->get('q', '')) ?: null;

        $lookbackFrom = Carbon::parse($date)->subDays($lookbackDays - 1)->toDateString();
        $lookbackTo = $date;

        // Penjualan hari terpilih
        $daySalesQuery = TransactionItem::query()
            ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->where('transactions.user_id', $ownerId)
            ->where('transactions.status', 'completed')
            ->whereNotNull('transaction_items.product_id');
        $this->period->applySoldAtRange($daySalesQuery, $date, $date, 'transactions.sold_at');

        $daySalesMap = (clone $daySalesQuery)
            ->select(
                'transaction_items.product_id',
                DB::raw('SUM(transaction_items.qty) as sold_qty'),
                DB::raw('SUM(transaction_items.subtotal) as sold_sales'),
                DB::raw('COUNT(DISTINCT transactions.id) as trx_count')
            )
            ->groupBy('transaction_items.product_id')
            ->get()
            ->keyBy('product_id');

        // Rata-rata dari lookback (untuk saran beli)
        $lookbackQuery = TransactionItem::query()
            ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->where('transactions.user_id', $ownerId)
            ->where('transactions.status', 'completed')
            ->whereNotNull('transaction_items.product_id');
        $this->period->applySoldAtRange($lookbackQuery, $lookbackFrom, $lookbackTo, 'transactions.sold_at');

        $lookbackMap = $lookbackQuery
            ->select(
                'transaction_items.product_id',
                DB::raw('SUM(transaction_items.qty) as sold_qty')
            )
            ->groupBy('transaction_items.product_id')
            ->get()
            ->keyBy('product_id');

        $productIds = $daySalesMap->keys()
            ->merge($lookbackMap->keys())
            ->unique()
            ->values();

        $products = Product::query()
            ->with('category:id,name')
            ->where('user_id', $ownerId)
            ->where('is_active', true)
            ->where('track_stock', true)
            ->when($productIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $productIds))
            ->when($productIds->isEmpty(), fn ($query) => $query->whereRaw('1 = 0'))
            ->when($q, function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('sku', 'like', "%{$q}%")
                        ->orWhere('barcode', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        $items = $productIds
            ->map(function ($productId) use ($products, $daySalesMap, $lookbackMap, $lookbackDays, $coverageDays) {
                $product = $products->get($productId);
                if (! $product) {
                    return null;
                }

                $day = $daySalesMap->get($productId);
                $lookback = $lookbackMap->get($productId);

                $soldQty = (int) ($day->sold_qty ?? 0);
                $soldSales = (float) ($day->sold_sales ?? 0);
                $trxCount = (int) ($day->trx_count ?? 0);
                $lookbackSold = (int) ($lookback->sold_qty ?? 0);
                $stock = (int) $product->stock;
                $avgDaily = round($lookbackSold / $lookbackDays, 2);
                $targetStock = (int) ceil($avgDaily * $coverageDays);
                $recommendQty = max(0, $targetStock - max(0, $stock));
                $daysLeft = $avgDaily > 0
                    ? round(max(0, $stock) / $avgDaily, 1)
                    : ($stock > 0 ? null : 0.0);

                $level = $this->priorityLevel($soldQty > 0 ? $soldQty : $lookbackSold, $stock, $daysLeft, $recommendQty);
                $estCost = round($recommendQty * (float) $product->cost, 2);
                $isRed = $level === 'urgent';

                return (object) [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'unit' => $product->unit ?: 'pcs',
                    'category' => $product->category?->name,
                    'stock' => $stock,
                    'cost' => (float) $product->cost,
                    'price' => (float) $product->price,
                    'sold_qty' => $soldQty,
                    'sold_sales' => $soldSales,
                    'trx_count' => $trxCount,
                    'lookback_sold' => $lookbackSold,
                    'avg_daily' => $avgDaily,
                    'days_left' => $daysLeft,
                    'target_stock' => $targetStock,
                    'recommend_qty' => $recommendQty,
                    'est_cost' => $estCost,
                    'priority' => $level,
                    'priority_rank' => $this->priorityRank($level),
                    'priority_label' => $this->priorityLabel($level),
                    'checked_default' => $isRed && $recommendQty > 0,
                ];
            })
            ->filter()
            ->filter(fn ($row) => $row->sold_qty > 0)
            ->filter(fn ($row) => ! $priority || $row->priority === $priority)
            ->sortBy([
                ['priority_rank', 'asc'],
                ['sold_qty', 'desc'],
                ['recommend_qty', 'desc'],
                ['name', 'asc'],
            ])
            ->values();

        // Filter selected IDs for export only
        if ($applySelectedFilter) {
            $selectedIds = collect(explode(',', (string) $request->get('selected', '')))
                ->map(fn ($id) => (int) trim($id))
                ->filter()
                ->values();

            if ($selectedIds->isNotEmpty()) {
                $items = $items
                    ->filter(fn ($row) => $selectedIds->contains($row->product_id))
                    ->map(function ($row) {
                        $row->checked_default = true;

                        return $row;
                    })
                    ->values();
            }
        }

        $needBuy = $items->filter(fn ($row) => $row->recommend_qty > 0);
        $checkedDefaults = $items->filter(fn ($row) => $row->checked_default);

        $itemsMeta = $items->map(fn ($row) => [
            'id' => $row->product_id,
            'qty' => (int) $row->recommend_qty,
            'cost' => (float) $row->est_cost,
            'red' => $row->priority === 'urgent',
        ])->values();

        $defaultCheckedIds = $checkedDefaults->pluck('product_id')->values();

        $summary = [
            'sku_reviewed' => $items->count(),
            'sku_sold_today' => $items->where('sold_qty', '>', 0)->count(),
            'sku_recommend' => $needBuy->count(),
            'urgent' => $items->where('priority', 'urgent')->count(),
            'high' => $items->where('priority', 'high')->count(),
            'medium' => $items->where('priority', 'medium')->count(),
            'low' => $items->where('priority', 'low')->count(),
            'ok' => $items->where('priority', 'ok')->count(),
            'total_recommend_qty' => (int) $needBuy->sum('recommend_qty'),
            'total_est_cost' => (float) $needBuy->sum('est_cost'),
            'total_sold_qty' => (int) $items->sum('sold_qty'),
            'total_sold_sales' => (float) $items->sum('sold_sales'),
            'checked_default_count' => $checkedDefaults->count(),
            'checked_default_qty' => (int) $checkedDefaults->sum('recommend_qty'),
            'checked_default_cost' => (float) $checkedDefaults->sum('est_cost'),
        ];

        return compact(
            'date',
            'lookbackDays',
            'coverageDays',
            'lookbackFrom',
            'priority',
            'q',
            'items',
            'itemsMeta',
            'defaultCheckedIds',
            'summary'
        );
    }

    private function priorityLevel(int $soldQty, int $stock, ?float $daysLeft, int $recommendQty): string
    {
        if ($recommendQty <= 0) {
            return 'ok';
        }

        if ($stock <= 0) {
            return 'urgent';
        }

        if ($daysLeft !== null && $daysLeft <= 3) {
            return 'high';
        }

        if ($daysLeft !== null && $daysLeft <= 7) {
            return 'medium';
        }

        return 'low';
    }

    private function priorityRank(string $level): int
    {
        return match ($level) {
            'urgent' => 1,
            'high' => 2,
            'medium' => 3,
            'low' => 4,
            default => 5,
        };
    }

    private function priorityLabel(string $level): string
    {
        return match ($level) {
            'urgent' => 'Segera',
            'high' => 'Tinggi',
            'medium' => 'Sedang',
            'low' => 'Rendah',
            default => 'Cukup',
        };
    }
}
