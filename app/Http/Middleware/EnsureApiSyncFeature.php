<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiSyncFeature
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user->hasActiveSubscription()) {
            return response()->json([
                'success' => false,
                'message' => 'Langganan tidak aktif.',
            ], 403);
        }

        if (! $user->hasFeature('api_sync')) {
            return response()->json([
                'success' => false,
                'message' => 'Fitur API sinkron hanya tersedia pada paket Berbayar. Upgrade langganan untuk mengakses API.',
            ], 403);
        }

        return $next($request);
    }
}
