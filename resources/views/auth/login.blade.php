@extends('layouts.app')

@section('title', 'Masuk')

@section('content')
<div class="min-h-screen grid lg:grid-cols-2">
    <div class="hidden lg:flex relative overflow-hidden items-end p-12 text-white"
         style="background: linear-gradient(145deg, #065f46 0%, #059669 45%, #34d399 100%);">
        <div class="absolute inset-0 opacity-30"
             style="background-image: radial-gradient(circle at 20% 20%, white 1px, transparent 1px); background-size: 24px 24px;"></div>
        <div class="relative z-10 max-w-md">
            <div class="text-4xl font-extrabold tracking-tight mb-4">{{ config('app.name', 'KasirFlow') }}</div>
            <p class="text-emerald-50 text-lg leading-relaxed">
                Kasir web PWA siap offline. Cetak struk via Bluetooth/USB, scan barcode, dan kelola langganan dalam satu aplikasi.
            </p>
        </div>
    </div>
    <div class="flex items-center justify-center p-6 sm:p-10">
        <div class="w-full max-w-md">
            <div class="lg:hidden mb-8">
                <div class="text-2xl font-extrabold text-brand-700">{{ config('app.name', 'KasirFlow') }}</div>
                <p class="text-slate-500 text-sm">Masuk ke akun toko Anda</p>
            </div>
            <h1 class="text-2xl font-bold mb-1">Masuk</h1>
            <p class="text-slate-500 mb-6">Gunakan akun toko untuk mengakses kasir.</p>

            <form method="POST" action="{{ route('login') }}" class="space-y-4 card p-6">
                @csrf
                <div>
                    <label class="block text-sm font-medium mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="input" required autofocus>
                    @error('email') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Password</label>
                    <input type="password" name="password" class="input" required>
                </div>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Perangkat ini akan tetap masuk. Jika Anda login di perangkat lain, perangkat ini akan diminta masuk ulang.
                </p>
                <x-recaptcha />
                <button class="btn btn-primary w-full">Masuk</button>
            </form>

            <p class="text-center text-sm text-slate-500 mt-6">
                Belum punya akun?
                <a href="{{ route('register') }}" class="text-brand-700 font-semibold">Daftar toko</a>
            </p>
        </div>
    </div>
</div>
@endsection
