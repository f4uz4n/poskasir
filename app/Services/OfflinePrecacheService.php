<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Route;

class OfflinePrecacheService
{
    /** @return list<string> */
    public function urlsFor(User $user): array
    {
        $urls = array_merge(
            $this->staticAssets(),
            $this->cdnAssets(),
            $this->viteAssets(),
        );

        if ($user->isDeveloper()) {
            $urls = array_merge($urls, $this->developerPages());
        } else {
            $urls = array_merge($urls, $this->storePages($user));
        }

        return array_values(array_unique(array_filter($urls)));
    }

    /** @return list<string> */
    protected function developerPages(): array
    {
        return $this->routes([
            'developer.dashboard',
            'developer.payments.index',
            'developer.plans.index',
            'developer.settings.index',
        ]);
    }

    /** @return list<string> */
    protected function storePages(User $user): array
    {
        $owner = $user->storeOwner();

        $routes = [
            'login',
            'dashboard',
        ];

        if ($user->hasActiveSubscription()) {
            $routes = array_merge($routes, [
                'pos.index',
                'products.index',
                'purchases.index',
                'purchases.create',
                'price-tags.index',
                'stock-opname.index',
                'stock-opname.create',
                'expiry.index',
                'reports.stock',
                'transactions.index',
                'receivables.index',
                'payables.index',
                'reports.index',
                'reports.profit-loss',
                'settings.index',
                'api.sync.pull',
            ]);

            if ($owner->isStoreOwner()) {
                $routes[] = 'subscription.index';
                $routes[] = 'backup.index';
            }

            if ($owner->isStoreOwner() && $owner->hasFeature('multi_kasir')) {
                $routes[] = 'kasir.index';
            }
        }

        return $this->routes($routes);
    }

    /** @param  list<string>  $names */
    protected function routes(array $names): array
    {
        $urls = [];

        foreach ($names as $name) {
            if (! Route::has($name)) {
                continue;
            }

            try {
                $urls[] = route($name);
            } catch (\Throwable) {
                // Lewati route yang butuh parameter dinamis.
            }
        }

        return $urls;
    }

    /** @return list<string> */
    protected function staticAssets(): array
    {
        return [
            url('/'),
            route('login'),
            asset('manifest.json'),
            asset('sw.js'),
            asset('icons/icon-192.png'),
            asset('icons/icon-512.png'),
        ];
    }

    /** @return list<string> */
    protected function cdnAssets(): array
    {
        return [
            'https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
        ];
    }

    /** @return list<string> */
    protected function viteAssets(): array
    {
        $manifestPath = public_path('build/manifest.json');
        if (! is_readable($manifestPath)) {
            return [];
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($manifest)) {
            return [];
        }

        $urls = [];

        foreach ($manifest as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            if (! empty($entry['file'])) {
                $urls[] = asset('build/'.$entry['file']);
            }

            foreach ($entry['css'] ?? [] as $css) {
                $urls[] = asset('build/'.$css);
            }

            if (! empty($entry['imports']) && is_array($entry['imports'])) {
                foreach ($entry['imports'] as $importKey) {
                    $import = $manifest[$importKey]['file'] ?? null;
                    if ($import) {
                        $urls[] = asset('build/'.$import);
                    }
                }
            }
        }

        return array_values(array_unique($urls));
    }
}
