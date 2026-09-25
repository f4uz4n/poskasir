<?php

namespace App\Http\Middleware;

use App\Services\AutoMigrateService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RunPendingMigrations
{
    public function __construct(
        protected AutoMigrateService $autoMigrate
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->autoMigrate->runIfNeeded();

        return $next($request);
    }
}
