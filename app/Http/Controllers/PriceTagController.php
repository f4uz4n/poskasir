<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PriceTagController extends Controller
{
    public function index(Request $request)
    {
        $ownerId = Auth::user()->storeOwnerId();
        $q = $request->get('q');
        $categoryId = $request->get('category_id');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $sort = $request->get('sort', 'name_asc');

        $products = Product::where('user_id', $ownerId)
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
            ->when($dateFrom, fn ($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('created_at', '<=', $dateTo))
            ->tap(fn (Builder $query) => $this->applySort($query, $sort))
            ->paginate(40)
            ->withQueryString();

        $categories = Category::where('user_id', $ownerId)->orderBy('name')->get();

        return view('price-tags.index', compact(
            'products',
            'categories',
            'q',
            'categoryId',
            'dateFrom',
            'dateTo',
            'sort',
        ));
    }

    public function print(Request $request)
    {
        $payload = $this->buildLabels($request);

        return view('price-tags.print', $payload);
    }

    public function pdf(Request $request)
    {
        $payload = $this->buildLabels($request);

        $pdf = Pdf::loadView('price-tags.pdf', $payload)
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('defaultFont', 'DejaVu Sans');

        $filename = 'label-harga-'.now()->format('Ymd-His').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * @return array{labels: list<\App\Models\Product>, storeName: string}
     */
    private function buildLabels(Request $request): array
    {
        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer'],
            'copies' => ['nullable', 'array'],
            'copies.*' => ['nullable', 'integer', 'min:1', 'max:50'],
            'sort' => ['nullable', 'in:name_asc,name_desc,date_asc,date_desc'],
        ]);

        $ownerId = Auth::user()->storeOwnerId();
        $ids = $data['product_ids'];
        $sort = $data['sort'] ?? 'name_asc';

        $products = Product::where('user_id', $ownerId)
            ->whereIn('id', $ids)
            ->with('category')
            ->get()
            ->keyBy('id');

        $ordered = [];
        foreach ($ids as $id) {
            if ($products->has($id)) {
                $ordered[] = $products->get($id);
            }
        }

        $ordered = $this->sortProducts(collect($ordered), $sort)->values()->all();

        $labels = [];
        foreach ($ordered as $product) {
            $copies = max(1, (int) ($data['copies'][$product->id] ?? 1));
            for ($i = 0; $i < $copies; $i++) {
                $labels[] = $product;
            }
        }

        if (count($labels) === 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'product_ids' => 'Pilih minimal satu produk.',
            ]);
        }

        $storeName = Auth::user()->storeOwner()->storeSetting?->store_name
            ?: Auth::user()->storeOwner()->store_name
            ?: 'Toko';

        return compact('labels', 'storeName');
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'name_desc' => $query->orderByDesc('name'),
            'date_asc' => $query->orderBy('created_at')->orderBy('name'),
            'date_desc' => $query->orderByDesc('created_at')->orderBy('name'),
            default => $query->orderBy('name'),
        };
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     * @return \Illuminate\Support\Collection<int, Product>
     */
    private function sortProducts($products, string $sort)
    {
        return match ($sort) {
            'name_desc' => $products->sortByDesc('name', SORT_NATURAL | SORT_FLAG_CASE)->values(),
            'date_asc' => $products->sort(function ($a, $b) {
                $cmp = ($a->created_at?->timestamp ?? 0) <=> ($b->created_at?->timestamp ?? 0);

                return $cmp !== 0 ? $cmp : strnatcasecmp($a->name, $b->name);
            })->values(),
            'date_desc' => $products->sort(function ($a, $b) {
                $cmp = ($b->created_at?->timestamp ?? 0) <=> ($a->created_at?->timestamp ?? 0);

                return $cmp !== 0 ? $cmp : strnatcasecmp($a->name, $b->name);
            })->values(),
            default => $products->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values(),
        };
    }
}
