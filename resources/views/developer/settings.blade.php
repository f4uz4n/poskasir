@extends('layouts.app')

@section('title', 'Konfigurasi Captcha')
@section('heading', 'Mode Online & Google reCAPTCHA')
@section('subheading', 'Online = captcha aktif · Offline = tanpa captcha')

@section('content')
<div class="card p-5 mb-4 text-sm text-slate-600 space-y-2">
    <p><strong>Mode Offline:</strong> cocok untuk instalasi lokal/XAMPP. Login, daftar, dan langganan <em>tanpa</em> Google reCAPTCHA.</p>
    <p><strong>Mode Online:</strong> untuk deployment internet. Aktifkan reCAPTCHA agar form login, daftar, dan berlangganan dilindungi bot.</p>
    <p>Jika pengguna sedang offline (tanpa koneksi), widget captcha otomatis disembunyikan dan verifikasi dilewati.</p>
</div>

<form method="POST" action="{{ route('developer.settings.update') }}" class="card p-5 max-w-2xl space-y-4">
    @csrf
    @method('PUT')

    <div>
        <label class="block text-sm font-medium mb-1">Mode deployment</label>
        <select name="deployment_mode" class="input" id="deployment_mode">
            <option value="offline" @selected($settings['deployment_mode'] === 'offline')>Offline (tanpa captcha)</option>
            <option value="online" @selected($settings['deployment_mode'] === 'online')>Online (captcha tersedia)</option>
        </select>
    </div>

    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="recaptcha_enabled" value="1" @checked($settings['recaptcha_enabled']) id="recaptcha_enabled">
        Aktifkan Google reCAPTCHA (hanya efektif di mode Online)
    </label>

    <div>
        <label class="block text-sm font-medium mb-1">Site Key</label>
        <input type="text" name="recaptcha_site_key" class="input font-mono text-sm" value="{{ $settings['recaptcha_site_key'] }}" placeholder="dari Google reCAPTCHA admin">
        @error('recaptcha_site_key') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Secret Key</label>
        <input type="text" name="recaptcha_secret_key" class="input font-mono text-sm" value="{{ $settings['recaptcha_secret_key'] }}" placeholder="rahasia — jangan dibagikan">
        @error('recaptcha_secret_key') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="rounded-xl bg-slate-50 border border-slate-200 p-3 text-xs text-slate-500">
        Buat kunci di
        <a href="https://www.google.com/recaptcha/admin" target="_blank" class="text-brand-700 font-medium">Google reCAPTCHA Admin</a>
        (tipe v2 Checkbox). Status saat ini:
        <strong>
            @if(\App\Models\AppSetting::recaptchaEnabled())
                Captcha AKTIF
            @else
                Captcha NONAKTIF
            @endif
        </strong>
    </div>

    <button class="btn btn-primary">Simpan konfigurasi</button>
</form>
@endsection
