<?php

namespace App\Http\Controllers;

use App\Models\Payable;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $ownerId = Auth::user()->storeOwnerId();

        $purchases = Purchase::where('user_id', $ownerId)
            ->with('creator')
            ->withCount('items')
            ->when($request->get('q'), function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('code', 'like', "%{$search}%")
                        ->orWhere('supplier_name', 'like', "%{$search}%");
                });
            })
            ->when($request->get('payment_status'), fn ($q, $s) => $q->where('payment_status', $s))
            ->latest('purchased_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('purchases.index', compact('purchases'));
    }

    public function create()
    {
        $ownerId = Auth::user()->storeOwnerId();
        $products = Product::where('user_id', $ownerId)
            ->where('is_active', true)
            ->where('track_stock', true)
            ->orderBy('name')
            ->get(['id', 'name', 'barcode', 'sku', 'cost', 'stock', 'unit']);

        $suppliers = Supplier::where('user_id', $ownerId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        return view('purchases.create', compact('products', 'suppliers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'purchased_at' => ['required', 'date'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'paid' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', 'in:cash,transfer,qris,other,credit'],
            'update_product_cost' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.cost' => ['required', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $actor = Auth::user();
        $ownerId = $actor->storeOwnerId();

        $purchase = DB::transaction(function () use ($data, $actor, $ownerId, $request) {
            $rows = [];
            $subtotal = 0;

            foreach ($data['items'] as $row) {
                $product = Product::where('user_id', $ownerId)
                    ->where('track_stock', true)
                    ->find($row['product_id']);

                if (! $product) {
                    continue;
                }

                $qty = (int) $row['qty'];
                $cost = (float) $row['cost'];
                $line = $qty * $cost;
                $subtotal += $line;

                $rows[] = [
                    'product' => $product,
                    'qty' => $qty,
                    'cost' => $cost,
                    'subtotal' => $line,
                    'notes' => $row['notes'] ?? null,
                ];
            }

            if (count($rows) === 0) {
                abort(422, 'Tidak ada item pembelian valid.');
            }

            $discount = (float) ($data['discount'] ?? 0);
            $tax = (float) ($data['tax'] ?? 0);
            $total = max(0, $subtotal - $discount + $tax);
            $paid = min($total, (float) ($data['paid'] ?? $total));

            if (($data['payment_method'] ?? '') === 'credit') {
                $paid = min($paid, $total);
            }

            $paymentStatus = 'paid';
            if ($paid <= 0) {
                $paymentStatus = 'unpaid';
            } elseif ($paid + 0.0001 < $total) {
                $paymentStatus = 'partial';
            }

            $supplier = null;
            $supplierName = $data['supplier_name'] ?: 'Supplier';
            if (! empty($data['supplier_id'])) {
                $supplier = Supplier::where('user_id', $ownerId)->find($data['supplier_id']);
                if ($supplier) {
                    $supplierName = $supplier->name;
                }
            }

            $purchase = Purchase::create([
                'user_id' => $ownerId,
                'created_by' => $actor->id,
                'code' => 'PO-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
                'supplier_id' => $supplier?->id,
                'supplier_name' => $supplierName,
                'purchased_at' => $data['purchased_at'],
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
                'paid' => $paid,
                'payment_method' => $data['payment_method'],
                'status' => 'completed',
                'payment_status' => $paymentStatus,
                'update_product_cost' => $request->boolean('update_product_cost', true),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($rows as $row) {
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $row['product']->id,
                    'product_name' => $row['product']->name,
                    'qty' => $row['qty'],
                    'cost' => $row['cost'],
                    'subtotal' => $row['subtotal'],
                    'notes' => $row['notes'],
                ]);

                $product = $row['product'];
                $product->increment('stock', $row['qty']);

                if ($purchase->update_product_cost) {
                    $product->update(['cost' => $row['cost']]);
                }
            }

            $remaining = max(0, $total - $paid);
            if ($remaining > 0) {
                Payable::create([
                    'user_id' => $ownerId,
                    'created_by' => $actor->id,
                    'code' => 'HT-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
                    'party_name' => $purchase->supplier_name,
                    'source' => 'purchase',
                    'purchase_id' => $purchase->id,
                    'amount' => $remaining,
                    'paid_amount' => 0,
                    'due_date' => now()->addDays(14)->toDateString(),
                    'status' => 'unpaid',
                    'notes' => 'Hutang dari pembelian '.$purchase->code,
                    'recorded_at' => now(),
                ]);
            }

            return $purchase->load('items');
        });

        return redirect()->route('purchases.show', $purchase)
            ->with('success', 'Pembelian tersimpan. Stok produk telah ditambah.');
    }

    public function show(Purchase $purchase)
    {
        abort_unless($purchase->user_id === Auth::user()->storeOwnerId(), 403);
        $purchase->load(['items.product', 'creator', 'payable']);

        return view('purchases.show', compact('purchase'));
    }
}
