<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PriceTagController extends Controller
{
    public function index(Request $request)
    {
        $ownerId = Auth::user()->storeOwnerId();
        $q = $request->get('q');
        $categoryId = $request->get('category_id');

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
            ->orderBy('name')
            ->paginate(40)
            ->withQueryString();

        $categories = Category::where('user_id', $ownerId)->orderBy('name')->get();

        return view('price-tags.index', compact('products', 'categories', 'q', 'categoryId'));
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
        ]);

        $ownerId = Auth::user()->storeOwnerId();
        $ids = $data['product_ids'];

        $products = Product::where('user_id', $ownerId)
            ->whereIn('id', $ids)
            ->with('category')
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        $labels = [];
        foreach ($ids as $id) {
            $product = $products->get($id);
            if (! $product) {
                continue;
            }
            $copies = max(1, (int) ($data['copies'][$id] ?? 1));
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
}
