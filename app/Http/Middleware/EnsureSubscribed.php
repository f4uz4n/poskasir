<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscribed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->isDeveloper()) {
            return redirect()->route('developer.dashboard');
        }

        if (! $user->hasActiveSubscription()) {
            return redirect()
                ->route('subscription.index')
                ->with('error', 'Langganan Anda tidak aktif. Silakan perpanjang untuk menggunakan fitur POS.');
        }

        return $next($request);
    }
}
