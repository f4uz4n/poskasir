@extends('layouts.app')

@section('title', 'Ganti Password')
@section('heading', 'Ganti Password')
@section('subheading', 'Ubah password akun login Anda')

@section('content')
<div class="max-w-md">
    <div class="card p-5">
        <div class="mb-4 pb-4 border-b border-slate-100">
            <div class="font-semibold">{{ $user->name }}</div>
            <div class="text-sm text-slate-500">{{ $user->email }}</div>
            @if($user->isStaff())
                <div class="text-xs text-sky-700 mt-1">{{ $user->roleLabel() }} · {{ $user->store_name }}</div>
            @elseif($user->isStoreOwner())
                <div class="text-xs text-emerald-700 mt-1">{{ $user->roleLabel() }}</div>
            @endif
        </div>

        <form method="POST" action="{{ route('profile.password.update') }}" class="space-y-3">
            @csrf
            @method('PUT')

            <div>
                <label class="text-sm font-medium text-slate-700">Password saat ini</label>
                <input type="password" name="current_password" class="input mt-1" required autocomplete="current-password">
                @error('current_password') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Password baru</label>
                <input type="password" name="password" class="input mt-1" required autocomplete="new-password">
                @error('password') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Konfirmasi password baru</label>
                <input type="password" name="password_confirmation" class="input mt-1" required autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary w-full">Simpan password baru</button>
        </form>

        <p class="text-xs text-slate-500 mt-4">Fitur ini tersedia untuk semua akun, termasuk paket gratis.</p>
    </div>
</div>
@endsection
