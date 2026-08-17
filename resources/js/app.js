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

async function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) return null;
    return navigator.serviceWorker.register(serviceWorkerUrl());
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
        toast('Aplikasi sudah terpasang');
        updateInstallUi();
        return false;
    }

    if (deferredInstallPrompt) {
        deferredInstallPrompt.prompt();
        const choice = await deferredInstallPrompt.userChoice;
        deferredInstallPrompt = null;
        if (choice?.outcome === 'accepted') {
            toast('Aplikasi dipasang');
            updateInstallUi();
            return true;
        }
        toast('Pemasangan dibatalkan');
        updateInstallUi();
        return false;
    }

    if (isIosSafari()) {
        toast('Di Safari: Bagikan → Tambah ke Layar Utama');
        return false;
    }

    toast('Buka menu browser, lalu pilih Install app');
    return false;
}

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredInstallPrompt = event;
    updateInstallUi();
});

window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = null;
    toast('Aplikasi berhasil dipasang');
    updateInstallUi();
});

async function enableOffline() {
    if (!window.POS_CONFIG?.routes?.offlineEnable) return;

    try {
        await registerServiceWorker();

        const res = await fetch(window.POS_CONFIG.routes.offlineEnable, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': window.POS_CONFIG.csrf,
            },
        });
        const json = await res.json();
        OfflineStore.setOfflineEnabled(true);
        await syncAll();
        const label = document.getElementById('offline-mode-label');
        if (label) {
            label.textContent = 'Aktif';
            label.classList.add('text-emerald-600');
        }
        toast(json.message || 'Mode offline aktif');
    } catch (err) {
        toast(err.message || 'Gagal mengaktifkan offline');
    }
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
    updateInstallUi,
    OfflineStore,
};

window.PosPrinter = printer;
window.OfflineStore = OfflineStore;

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

    initPos();
});
