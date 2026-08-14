<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockOpnameController extends Controller
{
    public function index()
    {
        $ownerId = Auth::user()->storeOwnerId();

        $opnames = StockOpname::where('user_id', $ownerId)
            ->with('creator')
            ->withCount('items')
            ->latest()
            ->paginate(15);

        return view('stock-opname.index', compact('opnames'));
    }

    public function create()
    {
        $ownerId = Auth::user()->storeOwnerId();

        $products = Product::where('user_id', $ownerId)
            ->where('track_stock', true)
            ->where('is_active', true)
            ->with('category')
            ->orderBy('name')
            ->get();

        return view('stock-opname.create', compact('products'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'action' => ['required', 'in:draft,complete'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.physical_stock' => ['required', 'integer', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $actor = Auth::user();
        $ownerId = $actor->storeOwnerId();

        $opname = DB::transaction(function () use ($data, $actor, $ownerId) {
            $opname = StockOpname::create([
                'user_id' => $ownerId,
                'created_by' => $actor->id,
                'code' => 'SO-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
                'title' => $data['title'] ?: 'Stock Opname '.now()->format('d/m/Y H:i'),
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $row) {
                $product = Product::where('user_id', $ownerId)
                    ->where('track_stock', true)
                    ->find($row['product_id']);

                if (! $product) {
                    continue;
                }

                $physical = (int) $row['physical_stock'];
                $system = (int) $product->stock;

                StockOpnameItem::create([
                    'stock_opname_id' => $opname->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'system_stock' => $system,
                    'physical_stock' => $physical,
                    'difference' => $physical - $system,
                    'cost' => $product->cost,
                    'notes' => $row['notes'] ?? null,
                ]);
            }

            if ($data['action'] === 'complete') {
                $this->applyOpname($opname);
            }

            return $opname->load('items');
        });

        $msg = $data['action'] === 'complete'
            ? 'Stock opname selesai. Stok produk telah disesuaikan.'
            : 'Draft stock opname disimpan.';

        return redirect()->route('stock-opname.show', $opname)->with('success', $msg);
    }

    public function show(StockOpname $stockOpname)
    {
        abort_unless($stockOpname->user_id === Auth::user()->storeOwnerId(), 403);

        $stockOpname->load(['items.product', 'creator']);

        $stats = [
            'items' => $stockOpname->items->count(),
            'plus' => $stockOpname->items->where('difference', '>', 0)->sum('difference'),
            'minus' => abs($stockOpname->items->where('difference', '<', 0)->sum('difference')),
            'match' => $stockOpname->items->where('difference', 0)->count(),
            'value_diff' => $stockOpname->items->sum(fn ($i) => $i->difference * (float) $i->cost),
        ];

        return view('stock-opname.show', compact('stockOpname', 'stats'));
    }

    public function complete(StockOpname $stockOpname)
    {
        abort_unless($stockOpname->user_id === Auth::user()->storeOwnerId(), 403);

        if ($stockOpname->status !== 'draft') {
            return back()->with('error', 'Stock opname sudah diproses.');
        }

        DB::transaction(fn () => $this->applyOpname($stockOpname));

        return back()->with('success', 'Stock opname diselesaikan. Stok telah disesuaikan.');
    }

    public function destroy(StockOpname $stockOpname)
    {
        abort_unless($stockOpname->user_id === Auth::user()->storeOwnerId(), 403);
        abort_unless(Auth::user()->isStoreOwner(), 403);

        if ($stockOpname->status === 'completed') {
            return back()->with('error', 'Stock opname yang sudah selesai tidak bisa dihapus.');
        }

        $stockOpname->delete();

        return redirect()->route('stock-opname.index')->with('success', 'Draft stock opname dihapus.');
    }

    protected function applyOpname(StockOpname $opname): void
    {
        $opname->load('items');

        foreach ($opname->items as $item) {
            Product::where('id', $item->product_id)
                ->where('user_id', $opname->user_id)
                ->where('track_stock', true)
                ->update(['stock' => $item->physical_stock]);
        }

        $opname->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }
}
