import './bootstrap';
import OfflineStore from './offline-store';
import printer from './printer';
import { initPos } from './pos';

function toast(message) {
    const el = document.getElementById('toast');
    if (!el) return;
    el.textContent = message;
    el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), 2800);
}

function updateNetStatus() {
    const el = document.getElementById('net-status');
    if (!el) return;
    if (navigator.onLine) {
        el.innerHTML = '<span class="status-dot status-online"></span> Online';
    } else {
        el.innerHTML = '<span class="status-dot status-offline"></span> Offline';
    }
}

async function syncAll() {
    if (!navigator.onLine) {
        toast('Tidak ada koneksi internet');
        return;
    }
    if (!window.POS_CONFIG?.routes?.syncPull) return;

    try {
        toast('Menyinkronkan...');

        const pending = await OfflineStore.getPendingTransactions();
        if (pending.length && window.POS_CONFIG.routes.syncPush) {
            const resPush = await fetch(window.POS_CONFIG.routes.syncPush, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': window.POS_CONFIG.csrf,
                },
                body: JSON.stringify({ transactions: pending }),
            });
            const pushJson = await resPush.json();
            if (pushJson.synced?.length) {
                await OfflineStore.markSynced(pushJson.synced.map((s) => s.local_id));
            }
        }

        const res = await fetch(window.POS_CONFIG.routes.syncPull, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': window.POS_CONFIG.csrf },
        });
        const json = await res.json();
        if (json.success) {
            await OfflineStore.saveCatalog(json.data);
            toast('Sinkronisasi selesai');
        } else {
            toast('Gagal sinkronisasi');
        }
    } catch (err) {
        toast(err.message || 'Gagal sinkronisasi');
    }
}

function serviceWorkerUrl() {
    return window.POS_CONFIG?.swUrl || new URL('sw.js', document.baseURI).href;
}

const OFFLINE_CACHE_NAME = 'poskasir-v3';

async function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) return null;
    return navigator.serviceWorker.register(serviceWorkerUrl());
}

function updateOfflineProgress(done, total, label) {
    const el = document.getElementById('offline-install-progress');
    const pct = total > 0 ? Math.round((done / total) * 100) : 0;
    if (el) {
        el.classList.remove('hidden');
        el.textContent = label || `Mengunduh ke perangkat… ${done}/${total} (${pct}%)`;
    }
}

function hideOfflineProgress() {
    document.getElementById('offline-install-progress')?.classList.add('hidden');
}

async function fetchPrecacheManifest() {
    const url = window.POS_CONFIG?.routes?.offlinePrecache;
    if (!url) return [];

    const res = await fetch(url, {
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': window.POS_CONFIG.csrf },
    });
    const json = await res.json();
    if (!json.success || !Array.isArray(json.urls)) {
        throw new Error(json.message || 'Gagal memuat daftar offline');
    }
    return json.urls;
}

async function precacheUrls(urls, onProgress) {
    if (!('caches' in window)) return { cached: 0, total: urls.length };

    const cache = await caches.open(OFFLINE_CACHE_NAME);
    let cached = 0;

    for (let i = 0; i < urls.length; i += 1) {
        const rawUrl = urls[i];
        try {
            const isExternal = /^https?:\/\//i.test(rawUrl) && !rawUrl.startsWith(window.location.origin);
            const response = await fetch(rawUrl, {
                credentials: isExternal ? 'omit' : 'same-origin',
                mode: isExternal ? 'cors' : 'same-origin',
                cache: 'no-cache',
            });
            if (response.ok) {
                await cache.put(rawUrl, response.clone());
                cached += 1;
            }
        } catch (_) {
            // Lewati URL yang gagal; lanjut ke berikutnya.
        }
        onProgress?.(i + 1, urls.length, cached);
    }

    localStorage.setItem('poskasir_precache_at', new Date().toISOString());
    localStorage.setItem('poskasir_precache_count', String(cached));

    return { cached, total: urls.length };
}

async function prepareOfflineApp({ enableOnServer = true, labelPrefix = 'Menyiapkan offline' } = {}) {
    if (!navigator.onLine) {
        toast('Butuh koneksi internet untuk unduh menu & script offline');
        return false;
    }

    try {
        toast(`${labelPrefix}…`);
        await registerServiceWorker();

        updateOfflineProgress(0, 1, `${labelPrefix}…`);
        const urls = await fetchPrecacheManifest();
        if (!urls.length) {
            throw new Error('Daftar halaman offline kosong');
        }

        await precacheUrls(urls, (done, total, cached) => {
            updateOfflineProgress(done, total, `${labelPrefix}… ${cached} file siap`);
        });

        if (enableOnServer && window.POS_CONFIG?.routes?.offlineEnable) {
            const res = await fetch(window.POS_CONFIG.routes.offlineEnable, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': window.POS_CONFIG.csrf,
                },
            });
            const json = await res.json();
            OfflineStore.setOfflineEnabled(true);
            const offlineLabel = document.getElementById('offline-mode-label');
            if (offlineLabel) {
                offlineLabel.textContent = 'Aktif';
                offlineLabel.classList.add('text-emerald-600');
            }
            if (json.message) toast(json.message);
        }

        await syncAll();
        hideOfflineProgress();
        toast('Aplikasi siap dipakai offline — semua menu sudah tersimpan di perangkat');
        return true;
    } catch (err) {
        hideOfflineProgress();
        toast(err.message || 'Gagal menyiapkan offline');
        return false;
    }
}

let deferredInstallPrompt = null;

function isPwaInstalled() {
    const standalone = window.matchMedia('(display-mode: standalone)').matches
        || window.matchMedia('(display-mode: fullscreen)').matches
        || window.navigator.standalone === true;
    return standalone;
}

function isIosSafari() {
    const ua = window.navigator.userAgent || '';
    const iOS = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const webkit = /WebKit/.test(ua);
    const notChrome = !/CriOS|FxiOS|EdgiOS/.test(ua);
    return iOS && webkit && notChrome;
}

function updateInstallUi() {
    const btn = document.getElementById('btn-install-app');
    const hint = document.getElementById('install-app-hint');
    if (!btn) return;

    if (isPwaInstalled()) {
        btn.disabled = true;
        btn.textContent = 'Aplikasi sudah terpasang';
        if (hint) hint.textContent = 'KasirFlow sudah berjalan sebagai aplikasi di perangkat ini.';
        return;
    }

    if (deferredInstallPrompt) {
        btn.disabled = false;
        btn.textContent = 'Install aplikasi';
        if (hint) hint.textContent = 'Pasang ke layar HP/laptop agar bisa dibuka seperti aplikasi.';
        return;
    }

    btn.disabled = false;
    btn.textContent = 'Install aplikasi';
    if (hint) {
        hint.textContent = isIosSafari()
            ? 'Di iPhone/iPad: ketuk tombol Bagikan, lalu pilih Tambah ke Layar Utama.'
            : 'Jika dialog tidak muncul, buka menu browser lalu pilih Install app / Tambahkan ke layar utama.';
    }
}

async function installPwa() {
    if (isPwaInstalled()) {
        toast('Memperbarui cache offline…');
        await prepareOfflineApp({ labelPrefix: 'Memperbarui cache' });
        updateInstallUi();
        return true;
    }

    let installed = false;
    window.__poskasir_install_in_progress = true;

    try {
        if (deferredInstallPrompt) {
            deferredInstallPrompt.prompt();
            const choice = await deferredInstallPrompt.userChoice;
            deferredInstallPrompt = null;
            installed = choice?.outcome === 'accepted';
            if (! installed) {
                toast('Pemasangan dibatalkan — tetap mengunduh data offline…');
            }
        } else if (isIosSafari()) {
            toast('Di Safari: Bagikan → Tambah ke Layar Utama. Mengunduh menu offline…');
        } else {
            toast('Mengunduh menu & script offline…');
        }

        await prepareOfflineApp({ labelPrefix: 'Install aplikasi' });
        updateInstallUi();
        return installed;
    } finally {
        window.__poskasir_install_in_progress = false;
    }
}

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredInstallPrompt = event;
    updateInstallUi();
});

window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = null;
    toast('Aplikasi berhasil dipasang');
    if (! window.__poskasir_install_in_progress) {
        prepareOfflineApp({ labelPrefix: 'Install aplikasi' }).finally(updateInstallUi);
    } else {
        updateInstallUi();
    }
});

async function enableOffline() {
    await prepareOfflineApp({ enableOnServer: true, labelPrefix: 'Aktifkan offline' });
}

async function disableOffline() {
    if (!window.POS_CONFIG?.routes?.offlineDisable) return;
    await fetch(window.POS_CONFIG.routes.offlineDisable, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': window.POS_CONFIG.csrf,
        },
    });
    OfflineStore.setOfflineEnabled(false);
    const label = document.getElementById('offline-mode-label');
    if (label) {
        label.textContent = 'Nonaktif';
        label.classList.remove('text-emerald-600');
    }
    toast('Mode offline dinonaktifkan');
}

window.PosApp = {
    toast,
    syncAll,
    enableOffline,
    disableOffline,
    installPwa,
    prepareOfflineApp,
    updateInstallUi,
    OfflineStore,
};

window.PosPrinter = printer;
window.OfflineStore = OfflineStore;

function suppressVirtualKeyboard(el) {
    if (!el || el.dataset.noKeyboardBound === '1') return;
    el.dataset.noKeyboardBound = '1';
    el.setAttribute('inputmode', 'none');
    el.setAttribute('autocomplete', 'off');
    el.setAttribute('autocorrect', 'off');
    el.setAttribute('spellcheck', 'false');

    const hide = () => {
        el.setAttribute('readonly', 'readonly');
        requestAnimationFrame(() => {
            el.removeAttribute('readonly');
        });
    };

    el.addEventListener('touchstart', hide, { passive: true });
    el.addEventListener('focus', hide);
    el.removeAttribute('readonly');
}

function initNoKeyboardFields(scope = document) {
    scope.querySelectorAll('input[data-no-keyboard], input#barcode-input, input#product-barcode').forEach(suppressVirtualKeyboard);
}

window.PosApp = window.PosApp || {};
window.PosApp.suppressVirtualKeyboard = suppressVirtualKeyboard;
window.PosApp.initNoKeyboardFields = initNoKeyboardFields;

document.addEventListener('DOMContentLoaded', () => {
    updateNetStatus();
    window.addEventListener('online', () => {
        updateNetStatus();
        if (OfflineStore.isOfflineEnabled()) syncAll();
    });
    window.addEventListener('offline', updateNetStatus);

    const menuToggle = document.getElementById('menu-toggle');
    const sidebarBackdrop = document.getElementById('sidebar-backdrop');
    const desktopMq = window.matchMedia('(min-width: 1024px)');

    const setMobileOpen = (open) => {
        document.documentElement.classList.toggle('sidebar-open', open);
        menuToggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    const closeMobileSidebar = () => setMobileOpen(false);

    menuToggle?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (desktopMq.matches) {
            const collapsed = document.documentElement.classList.toggle('sidebar-collapsed');
            localStorage.setItem('poskasir_sidebar_collapsed', collapsed ? '1' : '0');
            return;
        }
        setMobileOpen(!document.documentElement.classList.contains('sidebar-open'));
    });

    sidebarBackdrop?.addEventListener('click', closeMobileSidebar);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeMobileSidebar();
    });

    // Tutup drawer mobile setelah pilih menu
    document.getElementById('app-sidebar')?.addEventListener('click', (e) => {
        const link = e.target.closest('a.sidebar-link');
        if (link && !desktopMq.matches) {
            closeMobileSidebar();
        }
    });

    desktopMq.addEventListener('change', (e) => {
        if (e.matches) closeMobileSidebar();
    });

    // Sidebar dropdown: hanya buka grup yang berisi menu aktif
    document.querySelectorAll('details.nav-dropdown[data-nav-group]').forEach((el) => {
        el.open = !!el.querySelector('.sidebar-link.active');
    });

    document.getElementById('btn-sync')?.addEventListener('click', syncAll);
    document.getElementById('btn-install-app')?.addEventListener('click', () => installPwa());
    updateInstallUi();

    registerServiceWorker().catch(() => {});

    initNoKeyboardFields();
    initPos();
});
