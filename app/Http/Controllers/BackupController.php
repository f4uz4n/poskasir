<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BackupController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        abort_unless($user->isStoreOwner(), 403);

        $backups = collect(Storage::disk('local')->files('backups/'.$user->id))
            ->filter(fn ($f) => str_ends_with($f, '.json'))
            ->map(fn ($f) => [
                'path' => $f,
                'name' => basename($f),
                'size' => Storage::disk('local')->size($f),
                'modified' => Storage::disk('local')->lastModified($f),
            ])
            ->sortByDesc('modified')
            ->values();

        return view('backup.index', compact('backups'));
    }

    public function export()
    {
        $user = Auth::user();
        abort_unless($user->isStoreOwner(), 403);

        $ownerId = $user->id;

        $payload = [
            'version' => 1,
            'exported_at' => now()->toIso8601String(),
            'store' => [
                'name' => $user->store_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'address' => $user->store_address,
            ],
            'settings' => StoreSetting::where('user_id', $ownerId)->first(),
            'categories' => Category::where('user_id', $ownerId)->get(),
            'products' => Product::where('user_id', $ownerId)->get(),
            'transactions' => Transaction::where('user_id', $ownerId)->with('items')->get(),
        ];

        $filename = 'backup-poskasir-'.now()->format('Ymd-His').'.json';
        $relative = 'backups/'.$ownerId.'/'.$filename;
        Storage::disk('local')->put($relative, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, $filename, ['Content-Type' => 'application/json']);
    }

    public function restore(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->isStoreOwner(), 403);

        $request->validate([
            'backup_file' => ['required', 'file', 'mimes:json,txt', 'max:51200'],
            'mode' => ['required', 'in:merge,replace'],
        ]);

        $json = json_decode(file_get_contents($request->file('backup_file')->getRealPath()), true);

        if (! is_array($json) || empty($json['products'])) {
            return back()->with('error', 'File backup tidak valid.');
        }

        $ownerId = $user->id;

        DB::transaction(function () use ($json, $ownerId, $request, $user) {
            if ($request->mode === 'replace') {
                TransactionItem::whereIn('transaction_id', Transaction::where('user_id', $ownerId)->pluck('id'))->delete();
                Transaction::where('user_id', $ownerId)->delete();
                Product::where('user_id', $ownerId)->delete();
                Category::where('user_id', $ownerId)->delete();
            }

            $categoryMap = [];
            foreach ($json['categories'] ?? [] as $cat) {
                $created = Category::updateOrCreate(
                    [
                        'user_id' => $ownerId,
                        'name' => $cat['name'],
                    ],
                    [
                        'slug' => $cat['slug'] ?? Str::slug($cat['name']).'-'.$ownerId,
                        'description' => $cat['description'] ?? null,
                        'is_active' => $cat['is_active'] ?? true,
                    ]
                );
                if (isset($cat['id'])) {
                    $categoryMap[$cat['id']] = $created->id;
                }
            }

            $productMap = [];
            foreach ($json['products'] ?? [] as $p) {
                $created = Product::updateOrCreate(
                    [
                        'user_id' => $ownerId,
                        'barcode' => $p['barcode'] ?: null,
                        'name' => $p['name'],
                    ],
                    [
                        'category_id' => isset($p['category_id']) ? ($categoryMap[$p['category_id']] ?? null) : null,
                        'sku' => $p['sku'] ?? null,
                        'description' => $p['description'] ?? null,
                        'price' => $p['price'] ?? 0,
                        'cost' => $p['cost'] ?? 0,
                        'stock' => $p['stock'] ?? 0,
                        'unit' => $p['unit'] ?? 'pcs',
                        'is_active' => $p['is_active'] ?? true,
                        'stock_locked' => $p['stock_locked'] ?? false,
                    ]
                );
                if (isset($p['id'])) {
                    $productMap[$p['id']] = $created->id;
                }
            }

            if (! empty($json['settings'])) {
                $s = $json['settings'];
                StoreSetting::updateOrCreate(
                    ['user_id' => $ownerId],
                    [
                        'store_name' => $s['store_name'] ?? $user->store_name,
                        'store_phone' => $s['store_phone'] ?? $user->phone,
                        'store_address' => $s['store_address'] ?? $user->store_address,
                        'receipt_header' => $s['receipt_header'] ?? null,
                        'receipt_footer' => $s['receipt_footer'] ?? null,
                        'tax_percent' => $s['tax_percent'] ?? 0,
                        'printer_type' => $s['printer_type'] ?? 'bluetooth',
                        'paper_width' => $s['paper_width'] ?? 58,
                    ]
                );
            }

            if ($request->mode === 'replace') {
                foreach ($json['transactions'] ?? [] as $trx) {
                    $transaction = Transaction::create([
                        'user_id' => $ownerId,
                        'cashier_id' => null,
                        'invoice_number' => $trx['invoice_number'] ?? ('RST-'.Str::upper(Str::random(8))),
                        'local_id' => $trx['local_id'] ?? null,
                        'customer_name' => $trx['customer_name'] ?? null,
                        'order_type' => $trx['order_type'] ?? 'dine_in',
                        'table_number' => $trx['table_number'] ?? null,
                        'subtotal' => $trx['subtotal'] ?? 0,
                        'discount' => $trx['discount'] ?? 0,
                        'tax' => $trx['tax'] ?? 0,
                        'total' => $trx['total'] ?? 0,
                        'paid' => $trx['paid'] ?? 0,
                        'change' => $trx['change'] ?? 0,
                        'payment_method' => $trx['payment_method'] ?? 'cash',
                        'status' => $trx['status'] ?? 'completed',
                        'is_synced' => true,
                        'notes' => $trx['notes'] ?? null,
                        'sold_at' => $trx['sold_at'] ?? now(),
                    ]);

                    foreach ($trx['items'] ?? [] as $item) {
                        TransactionItem::create([
                            'transaction_id' => $transaction->id,
                            'product_id' => isset($item['product_id']) ? ($productMap[$item['product_id']] ?? null) : null,
                            'product_name' => $item['product_name'],
                            'product_sku' => $item['product_sku'] ?? null,
                            'price' => $item['price'] ?? 0,
                            'cost' => $item['cost'] ?? 0,
                            'qty' => $item['qty'] ?? 1,
                            'discount' => $item['discount'] ?? 0,
                            'subtotal' => $item['subtotal'] ?? 0,
                        ]);
                    }
                }
            }
        });

        return back()->with('success', 'Restore data berhasil (mode: '.$request->mode.').');
    }

    public function download(string $filename)
    {
        $user = Auth::user();
        abort_unless($user->isStoreOwner(), 403);

        $path = 'backups/'.$user->id.'/'.basename($filename);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path);
    }
}
