<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function store(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        Category::create([
            'user_id' => $user->storeOwnerId(),
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.$user->storeOwnerId(),
            'description' => $data['description'] ?? null,
        ]);

        return back()->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function destroy(Category $category)
    {
        abort_unless($category->user_id === Auth::user()->storeOwnerId(), 403);
        abort_unless(Auth::user()->isStoreOwner(), 403);
        $category->delete();

        return back()->with('success', 'Kategori berhasil dihapus.');
    }
}
