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
     * Gabungkan pengaturan server (sumber kebenaran setelah Simpan)
     * dengan token pairing Bluetooth lokal (hanya ada di browser).
     */
    mergeSettings(serverSettings = {}, localDevice = null) {
        const local = localDevice || readLocalDeviceSettings() || {};
        const server = serverSettings || {};
        const hasLocal = local && Object.keys(local).length > 0;

        if (!hasLocal) {
            return {
                ...server,
                scanner_enabled: server.scanner_enabled ?? server.extra?.scanner_enabled ?? true,
                bt_paired: false,
                bt_device_id: null,
                bt_device_name: null,
                printer_setup_done: Boolean(server.printer_type && server.printer_type !== 'none'),
            };
        }

        const printerType = server.printer_type || local.printer_type || null;
        const setupDone = Boolean(
            local.printer_setup_done
            || server.extra?.printer_setup_done
            || local.extra?.printer_setup_done
            || (printerType && printerType !== 'none' && (server.printer_name || server.extra?.windows_printer || local.bt_paired || local.bt_device_id))
            || (printerType === 'usb' || printerType === 'bluetooth')
        );

        return {
            ...server,
            printer_type: printerType,
            printer_name: server.printer_name || local.printer_name || '',
            paper_width: server.paper_width ?? local.paper_width ?? 58,
            receipt_header: server.receipt_header ?? local.receipt_header ?? '',
            receipt_footer: server.receipt_footer ?? local.receipt_footer ?? '',
            store_name: server.store_name ?? local.store_name ?? '',
            store_address: server.store_address ?? local.store_address ?? '',
            store_phone: server.store_phone ?? local.store_phone ?? '',
            logo_url: server.logo_url ?? local.logo_url ?? null,
            tax_percent: server.tax_percent ?? local.tax_percent ?? 0,
            scanner_enabled: local.scanner_enabled
                ?? local.extra?.scanner_enabled
                ?? server.scanner_enabled
                ?? server.extra?.scanner_enabled
                ?? true,
            bt_paired: Boolean(local.bt_paired || local.bt_device_id),
            bt_device_id: local.bt_device_id || null,
            bt_device_name: local.bt_device_name || local.printer_name || null,
            printer_setup_done: setupDone,
            extra: {
                ...(local.extra || {}),
                ...(server.extra || {}),
                printer_setup_done: setupDone || Boolean(server.extra?.printer_setup_done || local.extra?.printer_setup_done),
            },
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
