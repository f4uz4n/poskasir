<?php

namespace App\Http\Middleware;

use App\Models\StoreSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Header Authorization: Bearer {token} wajib diisi.',
            ], 401);
        }

        $settings = StoreSetting::where('api_token', hash('sha256', $token))
            ->with('user')
            ->first();

        if (! $settings?->user) {
            return response()->json([
                'success' => false,
                'message' => 'Token API tidak valid.',
            ], 401);
        }

        if (! $settings->user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun toko tidak aktif.',
            ], 403);
        }

        Auth::setUser($settings->user);
        $request->attributes->set('store_settings', $settings);

        return $next($request);
    }
}
