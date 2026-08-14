<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class RecaptchaService
{
    public function shouldChallenge(?Request $request = null): bool
    {
        if (! AppSetting::recaptchaEnabled()) {
            return false;
        }

        // Mode offline / koneksi hilang: lewati captcha
        if ($request && $request->boolean('offline_mode')) {
            return false;
        }

        return true;
    }

    public function siteKey(): ?string
    {
        return AppSetting::get('recaptcha_site_key');
    }

    public function verifyOrFail(Request $request, string $field = 'g-recaptcha-response'): void
    {
        if (! $this->shouldChallenge($request)) {
            return;
        }

        $token = $request->input($field);

        if (! $token) {
            throw ValidationException::withMessages([
                $field => 'Centang Google reCAPTCHA terlebih dahulu.',
            ]);
        }

        $secret = AppSetting::get('recaptcha_secret_key');

        $response = Http::asForm()->timeout(10)->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $request->ip(),
        ]);

        if (! $response->ok() || ! ($response->json('success') === true)) {
            throw ValidationException::withMessages([
                $field => 'Verifikasi reCAPTCHA gagal. Coba lagi.',
            ]);
        }
    }
}
