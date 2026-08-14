<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStoreOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isStoreOwner()) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Hanya pemilik toko yang dapat mengakses menu ini.');
        }

        return $next($request);
    }
}
