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
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="bg-app antialiased">
    @auth
    <div id="app-shell" class="min-h-screen">
        <div id="sidebar-backdrop" class="sidebar-backdrop" aria-hidden="true"></div>

        <aside id="app-sidebar" class="app-sidebar flex flex-col" aria-label="Menu navigasi">
            <div class="sidebar-brand px-4 py-5 flex items-center gap-3 min-h-[76px] shrink-0">
                @php $storeLogo = auth()->user()?->storeOwner()?->storeSetting?->logo_url; @endphp
                @if($storeLogo)
                    <img src="{{ $storeLogo }}" alt="Logo" class="h-10 w-10 shrink-0 rounded-xl object-cover border border-slate-200 bg-white">
                @else
                    <div class="h-10 w-10 shrink-0 rounded-xl bg-brand-600 text-white grid place-items-center font-bold">K</div>
                @endif
                <div class="brand-text overflow-hidden min-w-0">
                    <div class="font-extrabold text-lg tracking-tight whitespace-nowrap">{{ config('app.name', 'KasirFlow') }}</div>
                    <div class="text-xs text-slate-500 truncate">
                        {{ auth()->user()->isDeveloper() ? 'Developer Panel' : (auth()->user()->store_name ?: 'Toko') }}
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav flex-1 min-h-0 px-2 pb-2 space-y-1 overflow-y-auto overscroll-contain">
                @include('partials.sidebar-nav')
            </nav>

            <div class="p-3 border-t border-slate-100 space-y-2 shrink-0">
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

        <div id="app-main" class="app-main min-h-screen">
            <header class="app-header sticky top-0 z-40 border-b border-slate-200/70 bg-white/90 backdrop-blur">
                <div class="px-3 sm:px-6 py-3 flex items-center justify-between gap-2 sm:gap-3">
                    <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                        <button id="menu-toggle" class="btn btn-ghost px-2.5 sm:px-3 shrink-0 relative z-50" type="button" title="Menu" aria-label="Buka menu" aria-controls="app-sidebar" aria-expanded="false">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                        </button>
                        <div class="min-w-0">
                            <h1 class="font-bold text-base sm:text-lg leading-tight truncate">@yield('heading', 'Dashboard')</h1>
                            <p class="text-xs text-slate-500 truncate">@yield('subheading', 'Kelola toko Anda')</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button id="btn-sync" class="btn btn-ghost text-sm hidden sm:inline-flex" type="button">Sinkron</button>
                        <div class="text-right hidden md:block">
                            <div class="text-sm font-semibold truncate max-w-[10rem]">{{ auth()->user()->name }}</div>
                            <div class="text-xs text-slate-500 truncate max-w-[10rem]">{{ auth()->user()->email }}</div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-3 sm:p-4 md:p-6">
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
        } else if (scope && window.jQuery && scope.jquery) {
            $targets = scope.is('select') ? scope : scope.find('select');
        } else {
            const $root = scope ? jQuery(scope) : jQuery(document);
            $targets = $root.find('select');
        }

        $targets.not('[data-no-select2]').each(function () {
            const el = this;
            const $el = jQuery(el);
            if (el.tagName !== 'SELECT' || $el.attr('data-no-select2') != null) return;

            try {
                const $wrapParent = $el.parent();
                if (
                    !$wrapParent.hasClass('select2-wrap')
                    && !$wrapParent.hasClass('select2-compact')
                    && !$el.hasClass('select2-hidden-accessible')
                ) {
                    $el.wrap('<div class="select2-wrap"></div>');
                }

                if ($el.hasClass('select2-hidden-accessible')) return;

                const parentSel = $el.data('dropdown-parent');
                let $dropdownParent = parentSel ? jQuery(parentSel) : $el.closest('.modal-panel, .modal-backdrop.open, dialog');
                if (!$dropdownParent.length) $dropdownParent = jQuery(document.body);

                const forceSearch = $el.data('search') === true || $el.data('search') === 1 || $el.data('search') === 'true';
                const optionCount = $el.find('option').not('[disabled]').length;
                const emptyOpt = $el.find('option[value=""]').first();
                const placeholder = $el.data('placeholder')
                    || $el.attr('placeholder')
                    || (emptyOpt.length ? (emptyOpt.text() || '').trim() : undefined);
                const allowClearRequested = $el.data('allow-clear') === true || $el.data('allow-clear') === 1 || $el.data('allow-clear') === 'true';
                const allowClear = Boolean(placeholder) && (allowClearRequested || (emptyOpt.length > 0 && !$el.prop('required')));

                $el.select2({
                    width: '100%',
                    dropdownParent: $dropdownParent,
                    minimumResultsForSearch: forceSearch || optionCount > 5 ? 0 : Infinity,
                    placeholder: placeholder || undefined,
                    allowClear: allowClear,
                    language: {
                        noResults: () => 'Tidak ditemukan',
                        searching: () => 'Mencari…',
                    },
                });
            } catch (err) {
                console.warn('Select2 gagal diinisialisasi', el, err);
            }
        });
    };

    window.reinitSelect2 = function (el) {
        if (!window.jQuery || !el) return;
        const $el = jQuery(el);
        if ($el.hasClass('select2-hidden-accessible')) {
            try { $el.select2('destroy'); } catch (e) {}
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
        setTimeout(function () { window.initSelect2?.(document); }, 600);
    });

    (function () {
        let timer;
        const observer = new MutationObserver(function () {
            clearTimeout(timer);
            timer = setTimeout(function () { window.initSelect2?.(document); }, 80);
        });
        document.addEventListener('DOMContentLoaded', function () {
            if (document.body) observer.observe(document.body, { childList: true, subtree: true });
        });
    })();
    </script>
</body>
</html>
