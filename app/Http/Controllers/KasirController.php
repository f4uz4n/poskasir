<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class KasirController extends Controller
{
    public function index()
    {
        $actor = Auth::user();
        abort_unless($actor->canManageStaff(), 403);
        abort_unless($actor->storeOwner()->hasFeature('multi_kasir'), 403);

        $ownerId = $actor->storeOwnerId();
        $staff = User::where('owner_id', $ownerId)->latest()->get();

        return view('kasir.index', [
            'staff' => $staff,
            'staffRoles' => User::STAFF_ROLES,
        ]);
    }

    public function store(Request $request)
    {
        $actor = Auth::user();
        abort_unless($actor->canManageStaff(), 403);

        if (! $actor->storeOwner()->hasFeature('multi_kasir')) {
            return back()->with('error', 'Penambahan akun staff hanya untuk paket berbayar.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(User::STAFF_ROLES)],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $owner = $actor->storeOwner();

        User::create([
            'owner_id' => $owner->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'store_name' => $owner->store_name,
            'store_address' => $owner->store_address,
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'is_active' => true,
        ]);

        return back()->with('success', 'Akun staff berhasil didaftarkan.');
    }

    public function update(Request $request, User $kasir)
    {
        $actor = Auth::user();
        $ownerId = $actor->storeOwnerId();
        abort_unless($actor->canManageStaff() && $kasir->owner_id === $ownerId, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(User::STAFF_ROLES)],
            'is_active' => ['nullable', 'boolean'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        $kasir->name = $data['name'];
        $kasir->phone = $data['phone'] ?? null;
        $kasir->role = $data['role'];
        $kasir->is_active = $request->boolean('is_active', true);

        if (! empty($data['password'])) {
            $kasir->password = Hash::make($data['password']);
        }

        $kasir->save();

        return back()->with('success', 'Akun staff diperbarui.');
    }

    public function destroy(User $kasir)
    {
        $actor = Auth::user();
        $ownerId = $actor->storeOwnerId();
        abort_unless($actor->canManageStaff() && $kasir->owner_id === $ownerId, 403);

        $kasir->delete();

        return back()->with('success', 'Akun staff dihapus.');
    }
}
