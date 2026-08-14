<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#059669">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="description" content="KasirFlow — POS PWA offline dengan printer Bluetooth/USB, laporan, dan API sinkron">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="icon" href="{{ asset('icons/icon-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <title>@yield('title', 'Dashboard') — {{ config('app.name', 'KasirFlow') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    @stack('head')
</head>
<body class="bg-app antialiased">
    @auth
    <div id="app-shell" class="min-h-screen lg:flex">
        <aside id="app-sidebar" class="app-sidebar hidden lg:flex flex-col fixed inset-y-0 z-50 border-r border-slate-200/70 bg-white/90 backdrop-blur">
            <div class="sidebar-brand px-4 py-5 flex items-center gap-3 min-h-[76px]">
                @php $storeLogo = auth()->user()?->storeOwner()?->storeSetting?->logo_url; @endphp
                @if($storeLogo)
                    <img src="{{ $storeLogo }}" alt="Logo" class="h-10 w-10 shrink-0 rounded-xl object-cover border border-slate-200 bg-white">
                @else
                    <div class="h-10 w-10 shrink-0 rounded-xl bg-brand-600 text-white grid place-items-center font-bold">K</div>
                @endif
                <div class="brand-text overflow-hidden">
                    <div class="font-extrabold text-lg tracking-tight whitespace-nowrap">{{ config('app.name', 'KasirFlow') }}</div>
                    <div class="text-xs text-slate-500 truncate">
                        {{ auth()->user()->isDeveloper() ? 'Developer Panel' : (auth()->user()->store_name ?: 'Toko') }}
                    </div>
                </div>
            </div>

            <nav class="flex-1 px-2 space-y-1 overflow-y-auto">
                @include('partials.sidebar-nav')
            </nav>

            <div class="p-3 border-t border-slate-100 space-y-2">
                <div class="sidebar-status flex items-center justify-between text-sm px-2">
                    <span class="nav-label text-slate-500">Status</span>
                    <span id="net-status" class="inline-flex items-center gap-2 font-medium">
                        <span class="status-dot status-online"></span>
                        <span class="nav-label">Online</span>
                    </span>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-ghost w-full text-sm" title="Keluar">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                        <span class="nav-label">Keluar</span>
                    </button>
                </form>
            </div>
        </aside>

        <div id="app-main" class="app-main flex-1 min-h-screen">
            <header class="sticky top-0 z-40 border-b border-slate-200/70 bg-white/80 backdrop-blur">
                <div class="px-4 sm:px-6 py-3 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <button id="menu-toggle" class="btn btn-ghost px-3" type="button" title="Menu" aria-label="Menu">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                        </button>
                        <div>
                            <h1 class="font-bold text-lg leading-tight">@yield('heading', 'Dashboard')</h1>
                            <p class="text-xs text-slate-500">@yield('subheading', 'Kelola toko Anda')</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button id="btn-sync" class="btn btn-ghost text-sm hidden sm:inline-flex" type="button">Sinkron</button>
                        <div class="text-right hidden sm:block">
                            <div class="text-sm font-semibold">{{ auth()->user()->name }}</div>
                            <div class="text-xs text-slate-500">{{ auth()->user()->email }}</div>
                        </div>
                    </div>
                </div>
            </header>

            <div id="mobile-nav" class="hidden lg:hidden border-b border-slate-200 bg-white px-3 py-2 space-y-1">
                @include('partials.sidebar-nav')
            </div>

            <main class="p-4 sm:p-6">
                @if(session('success'))
                    <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-800 px-4 py-3">{{ session('error') }}</div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
    @else
        @yield('content')
    @endauth

    <div id="toast" class="toast"></div>

    <script>
        window.POS_CONFIG = {
            csrf: document.querySelector('meta[name="csrf-token"]').content,
            routes: {
                syncPull: @json(auth()->check() ? route('api.sync.pull') : null),
                syncPush: @json(auth()->check() ? route('api.sync.push') : null),
                offlineEnable: @json(auth()->check() ? route('settings.offline.enable') : null),
                offlineDisable: @json(auth()->check() ? route('settings.offline.disable') : null),
                transactionsStore: @json(auth()->check() ? route('transactions.store') : null),
                transactionsRecent: @json(auth()->check() ? route('transactions.recent') : null),
                printerDevices: @json(auth()->check() ? route('printer.devices') : null),
                printerRaw: @json(auth()->check() ? route('printer.raw') : null),
            },
            userId: @json(auth()->id()),
            offlineEnabled: @json(optional(auth()->user()?->storeOwner()->storeSetting)->offline_enabled ?? false),
            swUrl: @json(asset('sw.js')),
            assetBase: @json(rtrim(asset('/'), '/').'/'),
        };

        (function () {
            const collapsed = localStorage.getItem('poskasir_sidebar_collapsed') === '1';
            if (collapsed) document.documentElement.classList.add('sidebar-collapsed');
        })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    @stack('scripts')
    <script>
    window.initSelect2 = function (scope) {
        if (!window.jQuery || !jQuery.fn.select2) return;

        let $targets;
        if (scope && scope.nodeType === 1 && scope.tagName === 'SELECT') {
            $targets = jQuery(scope);
        } else {
            const $root = scope ? jQuery(scope) : jQuery(document);
            $targets = $root.find('select');
        }

        $targets.not('[data-no-select2]').each(function () {
            const $el = jQuery(this);
            if ($el.hasClass('select2-hidden-accessible')) return;

            const parentSel = $el.data('dropdown-parent');
            const forceSearch = $el.data('search') === true || $el.data('search') === 1;
            const optionCount = $el.find('option').length;
            const emptyOpt = $el.find('option[value=""]');
            const placeholder = $el.data('placeholder') || (emptyOpt.length ? emptyOpt.first().text().trim() : undefined);
            const allowClear = $el.data('allow-clear') === true || $el.data('allow-clear') === 1 || emptyOpt.length > 0;

            $el.select2({
                width: $el.data('width') || '100%',
                dropdownParent: parentSel ? jQuery(parentSel) : jQuery('body'),
                minimumResultsForSearch: forceSearch || optionCount > 6 ? 0 : Infinity,
                placeholder: placeholder || undefined,
                allowClear: allowClear,
                language: {
                    noResults: () => 'Tidak ditemukan',
                    searching: () => 'Mencari…',
                },
            });
        });
    };

    window.reinitSelect2 = function (el) {
        if (!window.jQuery || !el) return;
        const $el = jQuery(el);
        if ($el.hasClass('select2-hidden-accessible')) {
            $el.select2('destroy');
        }
        window.initSelect2(el);
    };

    window.refreshSelect2 = function (scope) {
        window.initSelect2(scope || document);
    };

    jQuery(function () {
        window.initSelect2(document);
    });

    document.addEventListener('DOMContentLoaded', function () {
        window.initSelect2?.(document);
        setTimeout(function () { window.initSelect2?.(document); }, 150);
    });

    (function () {
        let timer;
        const observer = new MutationObserver(function () {
            clearTimeout(timer);
            timer = setTimeout(function () { window.initSelect2?.(document); }, 60);
        });
        document.addEventListener('DOMContentLoaded', function () {
            if (document.body) observer.observe(document.body, { childList: true, subtree: true });
        });
    })();
    </script>
</body>
</html>
