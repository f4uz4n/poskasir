@extends('layouts.app')

@section('title', 'Daftar')

@section('content')
<div class="min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-lg">
        <div class="mb-6 text-center">
            <div class="text-2xl font-extrabold text-brand-700">{{ config('app.name', 'KasirFlow') }}</div>
            <p class="text-slate-500">Daftarkan toko Anda — trial 14 hari gratis</p>
        </div>
        <form method="POST" action="{{ route('register') }}" class="card p-6 space-y-4">
            @csrf
            <div class="grid sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1">Nama pemilik</label>
                    <input type="text" name="name" value="{{ old('name') }}" class="input" required>
                    @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="input" required>
                    @error('email') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">No. HP</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" class="input">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1">Nama toko</label>
                    <input type="text" name="store_name" value="{{ old('store_name') }}" class="input" required>
                    @error('store_name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1">Alamat toko</label>
                    <textarea name="store_address" class="input" rows="2">{{ old('store_address') }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Password</label>
                    <input type="password" name="password" class="input" required>
                    @error('password') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Konfirmasi password</label>
                    <input type="password" name="password_confirmation" class="input" required>
                </div>
            </div>
            <x-recaptcha />
            <button class="btn btn-primary w-full">Buat akun & mulai trial</button>
        </form>
        <p class="text-center text-sm text-slate-500 mt-6">
            Sudah punya akun? <a href="{{ route('login') }}" class="text-brand-700 font-semibold">Masuk</a>
        </p>
    </div>
</div>
@endsection
