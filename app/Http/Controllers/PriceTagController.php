<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
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
        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer'],
            'copies' => ['nullable', 'array'],
            'copies.*' => ['nullable', 'integer', 'min:1', 'max:50'],
            'size' => ['nullable', 'in:small,medium,large'],
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
            return back()->with('error', 'Pilih minimal satu produk.');
        }

        $size = $data['size'] ?? 'medium';
        $storeName = Auth::user()->storeOwner()->storeSetting?->store_name
            ?: Auth::user()->storeOwner()->store_name
            ?: 'Toko';

        return view('price-tags.print', compact(
            'labels',
            'size',
            'storeName'
        ));
    }
}
