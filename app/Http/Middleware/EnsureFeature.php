<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasFeature($feature)) {
            return redirect()
                ->route('subscription.index')
                ->with('error', 'Fitur ini hanya tersedia pada paket berbayar. Silakan upgrade langganan.');
        }

        return $next($request);
    }
}
