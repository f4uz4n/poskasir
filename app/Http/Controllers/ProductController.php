<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $ownerId = $user->storeOwnerId();
        $q = $request->get('q');
        $settings = $user->storeOwner()->storeSetting;
        $canLockStock = $user->hasFeature('kunci_stok');

        $products = Product::where('user_id', $ownerId)
            ->with('category')
            ->when($q, function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('barcode', 'like', "%{$q}%")
                        ->orWhere('sku', 'like', "%{$q}%");
                });
            })
            ->latest()
            ->paginate(20);

        $categories = Category::where('user_id', $ownerId)->orderBy('name')->get();

        return view('products.index', compact('products', 'categories', 'q', 'settings', 'canLockStock', 'user'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->isStoreOwner() || $user->hasFeature('multi_kasir'), 403);

        $data = $this->validatedProduct($request);
        $data['user_id'] = $user->storeOwnerId();
        $data['unit'] = $data['unit'] ?? 'pcs';
        $data['track_stock'] = $request->boolean('track_stock');
        $data['has_expiry'] = $request->boolean('has_expiry');
        $data['stock'] = $data['track_stock'] ? (int) ($data['stock'] ?? 0) : 0;
        $data['expired_at'] = $data['has_expiry'] ? ($data['expired_at'] ?? null) : null;
        $data['cost'] = $data['cost'] ?? 0;

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        Product::create($data);

        return back()->with('success', 'Produk berhasil ditambahkan.');
    }

    public function update(Request $request, Product $product)
    {
        $user = Auth::user();
        abort_unless($product->user_id === $user->storeOwnerId(), 403);

        $data = $this->validatedProduct($request, true);
        $data['track_stock'] = $request->boolean('track_stock');
        $data['has_expiry'] = $request->boolean('has_expiry');
        $data['stock'] = $data['track_stock'] ? (int) ($data['stock'] ?? 0) : 0;
        $data['expired_at'] = $data['has_expiry'] ? ($data['expired_at'] ?? null) : null;
        $data['cost'] = $data['cost'] ?? 0;
        $data['is_active'] = $request->boolean('is_active', true);

        $settings = $user->storeOwner()->storeSetting;
        $stockLockedGlobally = $user->hasFeature('kunci_stok') && ($settings?->stock_lock_enabled || $product->stock_locked);

        if ($user->isKasir() && $stockLockedGlobally) {
            unset($data['stock']);
        } elseif ($product->stock_locked && ! $user->isStoreOwner()) {
            unset($data['stock']);
        }

        if (! $user->hasFeature('kunci_stok') || ! $user->isStoreOwner()) {
            unset($data['stock_locked']);
        } else {
            $data['stock_locked'] = $request->boolean('stock_locked');
        }

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        if ($request->boolean('remove_image') && $product->image) {
            Storage::disk('public')->delete($product->image);
            $data['image'] = null;
        }

        $product->update($data);

        return back()->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Product $product)
    {
        $user = Auth::user();
        abort_unless($user->isStoreOwner() && $product->user_id === $user->storeOwnerId(), 403);

        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return back()->with('success', 'Produk berhasil dihapus.');
    }

    public function toggleLock(Product $product)
    {
        $user = Auth::user();
        abort_unless($user->isStoreOwner() && $product->user_id === $user->storeOwnerId(), 403);

        if (! $user->hasFeature('kunci_stok')) {
            return back()->with('error', 'Kunci stok hanya tersedia di paket berbayar.');
        }

        $product->update(['stock_locked' => ! $product->stock_locked]);

        return back()->with('success', $product->stock_locked ? 'Stok produk dikunci.' : 'Stok produk dibuka.');
    }

    protected function validatedProduct(Request $request, bool $isUpdate = false): array
    {
        $trackStock = $request->boolean('track_stock');
        $hasExpiry = $request->boolean('has_expiry');

        $ownerId = Auth::user()->storeOwnerId();
        $productId = $isUpdate ? $request->route('product')?->id : null;

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'sku' => ['nullable', 'string', 'max:100'],
            'barcode' => [
                'required',
                'string',
                'max:100',
                Rule::unique('products', 'barcode')
                    ->where(fn ($q) => $q->where('user_id', $ownerId))
                    ->ignore($productId),
            ],
            'price' => ['required', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'stock' => [$trackStock ? 'required' : 'nullable', 'integer', 'min:0'],
            'expired_at' => [$hasExpiry ? 'required' : 'nullable', 'date'],
            'unit' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:4096'],
            'is_active' => ['nullable', 'boolean'],
            'stock_locked' => ['nullable', 'boolean'],
            'track_stock' => ['nullable', 'boolean'],
            'has_expiry' => ['nullable', 'boolean'],
            'remove_image' => ['nullable', 'boolean'],
        ]);
    }
}
