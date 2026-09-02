const DB_NAME = 'poskasir_db';
const DB_VERSION = 1;
const DEVICE_SETTINGS_KEY = 'kasirflow_device_settings';

function openDb() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, DB_VERSION);
        req.onupgradeneeded = () => {
            const db = req.result;
            if (!db.objectStoreNames.contains('products')) {
                db.createObjectStore('products', { keyPath: 'id' });
            }
            if (!db.objectStoreNames.contains('categories')) {
                db.createObjectStore('categories', { keyPath: 'id' });
            }
            if (!db.objectStoreNames.contains('transactions')) {
                const store = db.createObjectStore('transactions', { keyPath: 'local_id' });
                store.createIndex('synced', 'synced', { unique: false });
            }
            if (!db.objectStoreNames.contains('meta')) {
                db.createObjectStore('meta', { keyPath: 'key' });
            }
        };
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

async function putAll(storeName, items) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(storeName, 'readwrite');
        const store = tx.objectStore(storeName);
        items.forEach((item) => store.put(item));
        tx.oncomplete = () => resolve(true);
        tx.onerror = () => reject(tx.error);
    });
}

async function getAll(storeName) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(storeName, 'readonly');
        const req = tx.objectStore(storeName).getAll();
        req.onsuccess = () => resolve(req.result || []);
        req.onerror = () => reject(req.error);
    });
}

async function putOne(storeName, item) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(storeName, 'readwrite');
        tx.objectStore(storeName).put(item);
        tx.oncomplete = () => resolve(true);
        tx.onerror = () => reject(tx.error);
    });
}

async function setMeta(key, value) {
    return putOne('meta', { key, value });
}

async function getMeta(key, fallback = null) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const tx = db.transaction('meta', 'readonly');
        const req = tx.objectStore('meta').get(key);
        req.onsuccess = () => resolve(req.result ? req.result.value : fallback);
        req.onerror = () => reject(req.error);
    });
}

function readLocalDeviceSettings() {
    try {
        return JSON.parse(localStorage.getItem(DEVICE_SETTINGS_KEY) || 'null');
    } catch (_) {
        return null;
    }
}

function writeLocalDeviceSettings(data) {
    localStorage.setItem(DEVICE_SETTINGS_KEY, JSON.stringify({
        ...data,
        saved_at: new Date().toISOString(),
    }));
}

export const OfflineStore = {
    async saveCatalog({ products, categories, settings }) {
        if (products) await putAll('products', products);
        if (categories) await putAll('categories', categories);
        if (settings) {
            await setMeta('settings', settings);
            this.saveDeviceSettings(settings);
        }
        await setMeta('last_pull', new Date().toISOString());
        localStorage.setItem('poskasir_offline_ready', '1');
        localStorage.setItem('poskasir_last_sync', new Date().toISOString());
    },

    /** Simpan pengaturan printer/scanner lokal (selalu, tanpa mode offline). */
    saveDeviceSettings(partial = {}) {
        const prev = readLocalDeviceSettings() || {};
        const next = {
            ...prev,
            ...partial,
            extra: {
                ...(prev.extra || {}),
                ...(partial.extra || {}),
            },
        };

        // Jangan hapus token pairing Bluetooth hanya karena field kosong di partial
        if (!next.bt_device_id && prev.bt_device_id && partial.bt_device_id !== '') {
            next.bt_device_id = prev.bt_device_id;
        }
        if (!next.bt_device_name && prev.bt_device_name) {
            next.bt_device_name = prev.bt_device_name;
        }
        if (partial.printer_type === 'usb' || partial.printer_type === 'none') {
            // pindah dari Bluetooth → boleh clear pairing
            if (partial.bt_paired === false) next.bt_paired = false;
        } else if (!next.bt_paired && prev.bt_paired && partial.bt_paired !== false) {
            next.bt_paired = true;
        }

        if (partial.printer_type || partial.printer_name || partial.extra?.windows_printer) {
            next.printer_setup_done = true;
        } else if (prev.printer_setup_done) {
            next.printer_setup_done = true;
        }

        writeLocalDeviceSettings(next);
        setMeta('device_settings', next).catch(() => {});
        return next;
    },

    async getDeviceSettings() {
        const local = readLocalDeviceSettings();
        if (local) return local;
        try {
            return await getMeta('device_settings', null);
        } catch (_) {
            return null;
        }
    },

    /**
     * Gabungkan pengaturan server dengan pengaturan printer di perangkat ini.
     * Koneksi printer (USB/BT) mengutamakan localStorage agar Kasir tidak minta pairing ulang.
     */
    mergeSettings(serverSettings = {}, localDevice = null) {
        const local = localDevice || readLocalDeviceSettings() || {};
        const server = serverSettings || {};
        const hasLocal = local && Object.keys(local).length > 0;
        const serverExtra = (server.extra && typeof server.extra === 'object') ? server.extra : {};
        const localExtra = (local.extra && typeof local.extra === 'object') ? local.extra : {};

        if (!hasLocal) {
            const type = server.printer_type || null;
            return {
                ...server,
                scanner_enabled: server.scanner_enabled ?? serverExtra.scanner_enabled ?? true,
                bt_paired: false,
                bt_device_id: null,
                bt_device_name: null,
                printer_setup_done: Boolean(type && type !== 'none'),
                extra: { ...serverExtra },
            };
        }

        // Pengaturan toko (server) menang untuk tipe printer; local untuk state pairing BT
        const printerType = server.printer_type || local.printer_type || 'bluetooth';
        const printerName = local.printer_name
            || server.printer_name
            || localExtra.windows_printer
            || serverExtra.windows_printer
            || '';
        const extra = {
            ...serverExtra,
            ...localExtra,
            windows_printer: localExtra.windows_printer
                || serverExtra.windows_printer
                || (printerType === 'usb' && printerName && !/^COM\d+$/i.test(printerName) ? printerName : null)
                || null,
            com_port: localExtra.com_port
                || serverExtra.com_port
                || (printerType === 'usb' && printerName && /^COM\d+$/i.test(printerName) ? String(printerName).toUpperCase() : null)
                || null,
            usb_port: localExtra.usb_port
                || serverExtra.usb_port
                || null,
            printer_usb_mode: localExtra.printer_usb_mode || serverExtra.printer_usb_mode || 'windows',
            printer_setup_done: true,
        };

        const setupDone = Boolean(
            local.printer_setup_done
            || extra.printer_setup_done
            || (printerType && printerType !== 'none')
        );

        return {
            ...server,
            printer_type: printerType,
            printer_name: printerName,
            paper_width: server.paper_width ?? local.paper_width ?? 58,
            receipt_header: server.receipt_header ?? local.receipt_header ?? '',
            receipt_footer: server.receipt_footer ?? local.receipt_footer ?? '',
            store_name: server.store_name ?? local.store_name ?? '',
            store_address: server.store_address ?? local.store_address ?? '',
            store_phone: server.store_phone ?? local.store_phone ?? '',
            logo_url: server.logo_url ?? local.logo_url ?? null,
            tax_percent: server.tax_percent ?? local.tax_percent ?? 0,
            scanner_enabled: local.scanner_enabled
                ?? localExtra.scanner_enabled
                ?? server.scanner_enabled
                ?? serverExtra.scanner_enabled
                ?? true,
            bt_paired: Boolean(local.bt_paired || local.bt_device_id),
            bt_device_id: local.bt_device_id || null,
            bt_device_name: local.bt_device_name || null,
            printer_setup_done: setupDone,
            extra,
        };
    },

    async getProducts() {
        return getAll('products');
    },

    async getCategories() {
        return getAll('categories');
    },

    async getSettings() {
        return getMeta('settings', null);
    },

    async saveTransaction(trx) {
        await putOne('transactions', trx);
        const pending = JSON.parse(localStorage.getItem('poskasir_pending_trx') || '[]');
        if (!pending.includes(trx.local_id)) {
            pending.push(trx.local_id);
            localStorage.setItem('poskasir_pending_trx', JSON.stringify(pending));
        }
        return trx;
    },

    async getAllTransactions() {
        const all = await getAll('transactions');
        return all.sort((a, b) => String(b.sold_at || '').localeCompare(String(a.sold_at || '')));
    },

    async getPendingTransactions() {
        const all = await getAll('transactions');
        return all.filter((t) => !t.synced);
    },

    async markSynced(localIds) {
        const all = await getAll('transactions');
        for (const trx of all) {
            if (localIds.includes(trx.local_id)) {
                trx.synced = true;
                await putOne('transactions', trx);
            }
        }
        const pending = JSON.parse(localStorage.getItem('poskasir_pending_trx') || '[]')
            .filter((id) => !localIds.includes(id));
        localStorage.setItem('poskasir_pending_trx', JSON.stringify(pending));
    },

    isOfflineEnabled() {
        return localStorage.getItem('poskasir_offline_enabled') === '1'
            || window.POS_CONFIG?.offlineEnabled === true;
    },

    setOfflineEnabled(enabled) {
        localStorage.setItem('poskasir_offline_enabled', enabled ? '1' : '0');
    },

    /** Hapus katalog & transaksi lokal setelah format data di server. */
    async clearBusinessData({ keepDeviceSettings = true } = {}) {
        const db = await openDb();
        await new Promise((resolve, reject) => {
            const tx = db.transaction(['products', 'categories', 'transactions', 'meta'], 'readwrite');
            tx.objectStore('products').clear();
            tx.objectStore('categories').clear();
            tx.objectStore('transactions').clear();
            const meta = tx.objectStore('meta');
            meta.delete('settings');
            meta.delete('last_pull');
            if (!keepDeviceSettings) {
                meta.delete('device_settings');
            }
            tx.oncomplete = () => resolve(true);
            tx.onerror = () => reject(tx.error);
        });

        localStorage.removeItem('poskasir_pending_trx');
        localStorage.removeItem('poskasir_offline_ready');
        localStorage.removeItem('poskasir_last_sync');
        if (!keepDeviceSettings) {
            localStorage.removeItem(DEVICE_SETTINGS_KEY);
        }

        return true;
    },
};

export default OfflineStore;
