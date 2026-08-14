<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class KasirController extends Controller
{
    public function index()
    {
        $owner = Auth::user();
        abort_unless($owner->isStoreOwner(), 403);
        abort_unless($owner->hasFeature('multi_kasir'), 403);

        $cashiers = $owner->cashiers()->latest()->get();

        return view('kasir.index', compact('cashiers'));
    }

    public function store(Request $request)
    {
        $owner = Auth::user();
        abort_unless($owner->isStoreOwner(), 403);

        if (! $owner->hasFeature('multi_kasir')) {
            return back()->with('error', 'Pendaftaran akun kasir hanya untuk paket berbayar.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        User::create([
            'owner_id' => $owner->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'store_name' => $owner->store_name,
            'store_address' => $owner->store_address,
            'password' => Hash::make($data['password']),
            'role' => 'kasir',
            'is_active' => true,
        ]);

        return back()->with('success', 'Akun kasir berhasil didaftarkan.');
    }

    public function update(Request $request, User $kasir)
    {
        $owner = Auth::user();
        abort_unless($owner->isStoreOwner() && $kasir->owner_id === $owner->id, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        $kasir->name = $data['name'];
        $kasir->phone = $data['phone'] ?? null;
        $kasir->is_active = $request->boolean('is_active', true);

        if (! empty($data['password'])) {
            $kasir->password = Hash::make($data['password']);
        }

        $kasir->save();

        return back()->with('success', 'Akun kasir diperbarui.');
    }

    public function destroy(User $kasir)
    {
        $owner = Auth::user();
        abort_unless($owner->isStoreOwner() && $kasir->owner_id === $owner->id, 403);

        $kasir->delete();

        return back()->with('success', 'Akun kasir dihapus.');
    }
}
