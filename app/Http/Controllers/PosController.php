<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

class PosController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $ownerId = $user->storeOwnerId();
        $owner = $user->storeOwner();

        $products = Product::where('user_id', $ownerId)
            ->where('is_active', true)
            ->with('category')
            ->orderBy('name')
            ->get();

        $categories = Category::where('user_id', $ownerId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $settings = $owner->storeSetting;

        return view('pos.index', compact('products', 'categories', 'settings'));
    }
}
