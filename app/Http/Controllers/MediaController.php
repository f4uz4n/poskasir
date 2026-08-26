<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function show(Request $request, string $path)
    {
        abort_unless(Auth::check(), 403);

        $path = str_replace(['..', '\\'], ['', '/'], $path);
        abort_unless(Storage::disk('public')->exists($path), 404);

        if (str_starts_with($path, 'products/')) {
            $ownerId = Auth::user()->storeOwnerId();
            $owns = Product::where('user_id', $ownerId)->where('image', $path)->exists();
            abort_unless($owns || Auth::user()->isDeveloper(), 403);
        }

        if (str_starts_with($path, 'purchase-invoices/')) {
            $ownerId = Auth::user()->storeOwnerId();
            $owns = \App\Models\Purchase::where('user_id', $ownerId)->where('supplier_invoice', $path)->exists();
            abort_unless($owns || Auth::user()->isDeveloper(), 403);
        }

        return Storage::disk('public')->response($path);
    }
}
