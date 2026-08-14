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

    document.getElementById('menu-toggle')?.addEventListener('click', () => {
        const isDesktop = window.matchMedia('(min-width: 1024px)').matches;
        if (isDesktop) {
            const collapsed = document.documentElement.classList.toggle('sidebar-collapsed');
            localStorage.setItem('poskasir_sidebar_collapsed', collapsed ? '1' : '0');
        } else {
            document.getElementById('mobile-nav')?.classList.toggle('hidden');
        }
    });

    // Sidebar dropdown: hanya buka grup yang berisi menu aktif
    document.querySelectorAll('details.nav-dropdown[data-nav-group]').forEach((el) => {
        el.open = !!el.querySelector('.sidebar-link.active');
    });

    document.getElementById('btn-sync')?.addEventListener('click', syncAll);

    if (OfflineStore.isOfflineEnabled() || window.POS_CONFIG?.offlineEnabled) {
        registerServiceWorker().catch(() => {});
    }

    initPos();
});
