import ReceiptPrinterEncoder from '@point-of-sale/receipt-printer-encoder';
import OfflineStore from './offline-store';

const STORAGE_BT = 'kasirflow_bt_printer';

/** UUID BLE printer thermal 58/80mm (Rongta, Xprinter, Epson, Star, dll). */
const BLE_SERVICES = [
    'e7810a71-73ae-499d-8c15-faa9aef0c3f2', // Rongta / RPP02N / Goojprt / MTP
    '000018f0-0000-1000-8000-00805f9b34fb', // Feasycom / POS generic
    '0000ff00-0000-1000-8000-00805f9b34fb',
    '0000ff10-0000-1000-8000-00805f9b34fb',
    '0000fff0-0000-1000-8000-00805f9b34fb',
    '0000ffe0-0000-1000-8000-00805f9b34fb',
    '0000ae30-0000-1000-8000-00805f9b34fb',
    '0000ffb0-0000-1000-8000-00805f9b34fb',
    '0000ff80-0000-1000-8000-00805f9b34fb',
    '0000fd00-0000-1000-8000-00805f9b34fb', // Generic BLE thermal
    '0000fee7-0000-1000-8000-00805f9b34fb', // Telink / OEM
    '0000ab00-0000-1000-8000-00805f9b34fb',
    '49535343-fe7d-4ae5-8fa9-9fafd205e455', // ISSC / Epson TM-P
    '6e400001-b5a3-f393-e0a9-e50e24dcca9e', // Nordic UART
    '0000fee0-0000-1000-8000-00805f9b34fb',
    '0000ff20-0000-1000-8000-00805f9b34fb',
];

const BLE_WRITE_CHARS = [
    'bef8d6c9-9c21-4c9e-b632-bd58c1009f9f', // Rongta write (RPP02N)
    '00002af1-0000-1000-8000-00805f9b34fb',
    '0000ffe1-0000-1000-8000-00805f9b34fb',
    '0000fff2-0000-1000-8000-00805f9b34fb',
    '0000ff02-0000-1000-8000-00805f9b34fb',
    '0000ae01-0000-1000-8000-00805f9b34fb',
    '6e400002-b5a3-f393-e0a9-e50e24dcca9e',
    '49535343-8841-43f4-a8d4-ecbe34729bb3',
    '0000fee1-0000-1000-8000-00805f9b34fb',
    '0000fd02-0000-1000-8000-00805f9b34fb',
    '0000ab01-0000-1000-8000-00805f9b34fb',
    '0000ff12-0000-1000-8000-00805f9b34fb',
];

const PROFILES = {
    auto: { chunkSize: 20, chunkDelay: 30, autoCut: false, paper: 58, baud: 9600, mapping: 'youku' },
    rpp02n: { chunkSize: 20, chunkDelay: 40, autoCut: false, paper: 58, baud: 9600, mapping: 'youku' },
    hakpost: { chunkSize: 20, chunkDelay: 30, autoCut: false, paper: 58, baud: 9600, mapping: 'youku' },
    generic58: { chunkSize: 20, chunkDelay: 30, autoCut: false, paper: 58, baud: 9600, mapping: 'pos-5890' },
    gp58mb: { chunkSize: 20, chunkDelay: 30, autoCut: false, paper: 58, baud: 9600, mapping: 'pos-5890' },
    generic80: { chunkSize: 20, chunkDelay: 20, autoCut: true, paper: 80, baud: 115200, mapping: 'xprinter' },
    xprinter: { chunkSize: 20, chunkDelay: 20, autoCut: true, paper: 80, baud: 9600, mapping: 'xprinter' },
    gprinter: { chunkSize: 20, chunkDelay: 25, autoCut: true, paper: 80, baud: 9600, mapping: 'xprinter' },
    epson: { chunkSize: 20, chunkDelay: 15, autoCut: true, paper: 80, baud: 38400, mapping: 'epson' },
    star: { chunkSize: 20, chunkDelay: 20, autoCut: true, paper: 80, baud: 9600, mapping: 'epson' },
    bixolon: { chunkSize: 20, chunkDelay: 20, autoCut: true, paper: 80, baud: 9600, mapping: 'epson' },
};

function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

function money(n) {
    return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
}

function padLine(left, right, width) {
    const l = String(left ?? '');
    const r = String(right ?? '');
    const space = Math.max(1, width - l.length - r.length);

    return l + ' '.repeat(space) + r;
}

function columnsFor(paper) {
    return Number(paper) === 80 ? 48 : 32;
}

function detectProfileKey(name = '', paperWidth = 58) {
    const n = String(name || '').toLowerCase();
    if (/hakpost|hprt|hpc|sprt/i.test(n)) return 'hakpost';
    if (/gp-?58|gp58|58mb|gainscha.*58/i.test(n)) return 'gp58mb';
    if (/rpp|rongta|goojprt|mtp-|zjiang|zhongyi|inner|printer058/i.test(n)) return 'rpp02n';
    if (/xprinter|xp-|zj-/i.test(n)) return 'xprinter';
    if (/gprinter|gp-|gainscha|gs-/i.test(n)) return Number(paperWidth) === 80 ? 'gprinter' : 'gp58mb';
    if (/epson|tm-|tm\d/i.test(n)) return 'epson';
    if (/star|sm-|tsp/i.test(n)) return 'star';
    if (/bixolon|srp-/i.test(n)) return 'bixolon';
    if (/citizen|ct-/i.test(n)) return 'epson';
    if (/tsc|ta200|te200/i.test(n)) return 'generic80';
    if (/zebra|zd4|zpl/i.test(n)) return 'generic80';
    if (/munbyn|mUNBYN|imin|sunmi|bluetooth printer|thermal|pos-|58mm|80mm|receipt/i.test(n)) {
        return Number(paperWidth) === 80 ? 'generic80' : 'generic58';
    }
    return Number(paperWidth) === 80 ? 'generic80' : 'generic58';
}

function btChunkSize(profile = {}, characteristic = null) {
    const key = profile.key || 'generic58';
    if (/rpp|rongta|goojprt|hakpost/i.test(key)) return 20;
    if (characteristic?.properties?.writeWithoutResponse) {
        return profile.paper === 80 ? 100 : 80;
    }
    return profile.chunkSize || 20;
}

function resolveProfile(settings = {}, deviceName = '') {
    const extra = settings.extra || {};
    let key = extra.printer_profile || settings.printer_profile || '';
    const name = String(deviceName || settings.printer_name || settings.bt_device_name || '');

    if (!key || key === 'auto') {
        key = detectProfileKey(name, settings.paper_width);
    }

    const base = { ...(PROFILES[key] || PROFILES.generic58) };
    if (Number(settings.paper_width) === 80 || Number(settings.paper_width) === 58) {
        base.paper = Number(settings.paper_width);
    }
    if (extra.printer_baud) base.baud = Number(extra.printer_baud);
    if (typeof extra.printer_auto_cut === 'boolean') base.autoCut = extra.printer_auto_cut;
    if (['rpp02n', 'hakpost', 'generic58'].includes(key)) {
        base.autoCut = extra.printer_auto_cut === true;
    }

    return { key, ...base };
}

function concatBytes(...parts) {
    const total = parts.reduce((n, p) => n + (p?.length || 0), 0);
    const out = new Uint8Array(total);
    let offset = 0;
    parts.forEach((part) => {
        if (!part?.length) return;
        out.set(part, offset);
        offset += part.length;
    });
    return out;
}

/**
 * Kick cash drawer — SATU pulse saja (ESC/POS ESC p).
 * Hakpost/HPRT/Rongta sensitif: banyak pulse membuat laci buka berulang.
 */
export function drawerKickBytes(pin = '2') {
    const pins = (pin === '5' || pin === 1 || pin === '1')
        ? [1]
        : (pin === 'both')
            ? [0]
            : [0]; // default Pin 2 (m=0) — paling umum termasuk Hakpost

    const cmds = [];
    pins.forEach((m) => {
        // ESC p m t1 t2 — on 50ms / off ~250ms
        cmds.push(0x1b, 0x70, m, 0x32, 0xfa);
    });
    return new Uint8Array(cmds);
}

export function shouldOpenDrawer(transaction = {}, settings = {}, options = {}) {
    if (options.openDrawer === true) return true;
    if (options.openDrawer === false) return false;
    const extra = settings.extra || {};
    if (extra.cash_drawer === false) return false;
    const when = extra.cash_drawer_when || 'cash';
    const method = String(transaction.payment_method || 'cash').toLowerCase();
    if (when === 'always') return true;
    return method === 'cash';
}

function loadLogoImage(url) {
    if (!url) return Promise.resolve(null);
    return new Promise((resolve) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => resolve(img);
        img.onerror = () => resolve(null);
        img.src = url;
    });
}

function paymentLabel(method = '') {
    const m = String(method || '').toLowerCase();
    if (m === 'cash') return 'Tunai';
    if (m === 'qris') return 'QRIS';
    if (m === 'transfer') return 'Transfer';
    if (m === 'card') return 'Kartu';
    if (m === 'credit') return 'Piutang';
    if (m === 'other') return 'Lainnya';
    return method || '-';
}

export function buildReceiptText(transaction, settings = {}, profile = null) {
    const resolved = profile || resolveProfile(settings);
    const cols = columnsFor(resolved.paper);
    const header = settings.receipt_header || settings.store_name || 'KasirFlow';
    const footer = settings.receipt_footer || 'Terima kasih';
    const soldAt = transaction.sold_at
        ? new Date(transaction.sold_at).toLocaleString('id-ID')
        : new Date().toLocaleString('id-ID');
    const lines = [];

    const push = (t = '') => lines.push(String(t));
    const rule = () => push('-'.repeat(cols));

    push(header.toUpperCase());
    if (settings.store_address) push(settings.store_address);
    if (settings.store_phone) push(settings.store_phone);
    rule();
    push(`No   : ${transaction.invoice_number || transaction.local_id || '-'}`);
    push(`Tgl  : ${soldAt}`);
    push(`Tipe : ${transaction.order_type === 'takeaway' ? 'Take Away' : 'Dine In'}`);
    if (transaction.order_type === 'dine_in' && transaction.table_number) {
        push(`Meja : ${transaction.table_number}`);
    }
    if (transaction.customer_name) push(`Cust : ${transaction.customer_name}`);
    rule();

    (transaction.items || []).forEach((item) => {
        push(item.product_name || '-');
        push(padLine(
            `  ${item.qty} x ${money(item.price)}`,
            money(item.subtotal ?? (item.qty * item.price)),
            cols,
        ));
    });

    rule();
    push(padLine('Subtotal', money(transaction.subtotal), cols));
    if (Number(transaction.discount) > 0) push(padLine('Diskon', money(transaction.discount), cols));
    if (Number(transaction.tax) > 0) push(padLine('Pajak', money(transaction.tax), cols));
    push(padLine('TOTAL', money(transaction.total), cols));
    push(padLine(`Bayar (${paymentLabel(transaction.payment_method)})`, money(transaction.paid), cols));
    push(padLine('Kembali', money(transaction.change), cols));
    rule();
    String(footer || 'Terima kasih')
        .split(/\r\n|\n|\r/)
        .map((line) => line.trim())
        .filter((line) => line.length > 0)
        .forEach((line) => push(line));
    push('');
    push('');
    push('');

    return lines.join('\r\n');
}

export function buildReceipt(transaction, settings = {}, profile = null, options = {}) {
    const resolved = profile || resolveProfile(settings);
    const printOptions = options.printOptions || options || profile?.printOptions || {};
    const cols = columnsFor(resolved.paper);
    const header = settings.receipt_header || settings.store_name || 'KasirFlow';
    const footer = settings.receipt_footer || 'Terima kasih';
    const soldAt = transaction.sold_at
        ? new Date(transaction.sold_at).toLocaleString('id-ID')
        : new Date().toLocaleString('id-ID');

    try {
        const encoder = new ReceiptPrinterEncoder({
            language: 'esc-pos',
            columns: cols,
            codepageMapping: resolved.mapping || 'youku',
            newline: '\n',
            feedBeforeCut: 0,
            errors: 'relaxed',
        });

    encoder.initialize();
    encoder.align('center');
    const logoImg = options.logoImage || settings.logoImage || null;
    if (logoImg) {
        try {
            const maxW = Number(resolved.paper) === 80 ? 384 : 256;
            let w = Math.min(maxW, logoImg.width || maxW);
            w = Math.max(64, Math.floor(w / 8) * 8);
            const h = Math.max(32, Math.round((logoImg.height || w) * (w / (logoImg.width || w))));
            encoder.image(logoImg, w, h, 'atkinson');
            encoder.newline();
        } catch (e) {
            console.warn('Logo struk gagal dicetak', e);
        }
    }
    encoder.bold(true).line(header).bold(false);
    if (settings.store_address) encoder.line(settings.store_address);
    if (settings.store_phone) encoder.line(settings.store_phone);
    encoder.align('left').line('-'.repeat(cols));
    encoder.line(`No: ${transaction.invoice_number || transaction.local_id || '-'}`);
    encoder.line(`Tgl: ${soldAt}`);
    encoder.line(`Tipe: ${transaction.order_type === 'takeaway' ? 'Take Away' : 'Dine In'}`);
    if (transaction.order_type === 'dine_in' && transaction.table_number) {
        encoder.line(`Meja: ${transaction.table_number}`);
    }
    if (transaction.customer_name) encoder.line(`Pelanggan: ${transaction.customer_name}`);
    encoder.line('-'.repeat(cols));

    (transaction.items || []).forEach((item) => {
        encoder.line(item.product_name || '-');
        encoder.line(padLine(
            `  ${item.qty} x ${money(item.price)}`,
            money(item.subtotal ?? (item.qty * item.price)),
            cols,
        ));
    });

    encoder.line('-'.repeat(cols));
    encoder.line(padLine('Subtotal', money(transaction.subtotal), cols));
    if (Number(transaction.discount) > 0) encoder.line(padLine('Diskon', money(transaction.discount), cols));
    if (Number(transaction.tax) > 0) encoder.line(padLine('Pajak', money(transaction.tax), cols));
    encoder.bold(true).line(padLine('TOTAL', money(transaction.total), cols)).bold(false);
    encoder.line(padLine(`Bayar (${transaction.payment_method || '-'})`, money(transaction.paid), cols));
    encoder.line(padLine('Kembali', money(transaction.change), cols));
    encoder.line('-'.repeat(cols));
    encoder.align('center');
    String(footer || 'Terima kasih')
        .split(/\r\n|\n|\r/)
        .map((line) => line.trim())
        .filter((line) => line.length > 0)
        .forEach((line) => encoder.line(line));
    encoder.newline(4);

    if (resolved.autoCut) {
        encoder.cut('partial');
    }

        let bytes = encoder.encode();
        // Laci dibuka terpisah sekali di printReceipt — jangan sisipkan di sini
        // agar Hakpost/dll tidak buka berulang.
        return bytes;
    } catch (err) {
        console.warn('Encoder gagal, memakai ESC/POS cadangan', err);
        const lines = [];
        const push = (t) => lines.push(t);
        push('\x1b\x40');
        push('\x1b\x61\x01');
        push(`${header}\n`);
        push('\x1b\x61\x00');
        push(`${'-'.repeat(cols)}\n`);
        push(`No: ${transaction.invoice_number || transaction.local_id || '-'}\n`);
        (transaction.items || []).forEach((item) => {
            push(`${item.product_name}\n`);
            push(`  ${item.qty} x ${money(item.price)}\n`);
        });
        push(`${'-'.repeat(cols)}\nTOTAL: ${money(transaction.total)}\n`);
        push('\n\n\n\n');
        const blob = lines.join('');
        return new TextEncoder().encode(blob);
    }
}

class PosPrinter {
    constructor() {
        this.btDevice = null;
        this.btCharacteristic = null;
        this.serialPort = null;
        this.writer = null;
        this.usbDevice = null;
        this.usbEndpoint = null;
        this.windowsPrinter = null;
        this.comPort = null;
        this.usbPort = null;
        this.type = null;
        this.profile = PROFILES.rpp02n;
        this.settings = {};
        this.onStatusChange = null;
    }

    setSettings(settings = {}) {
        this.settings = settings || {};
        this.profile = resolveProfile(this.settings, this.btDevice?.name);
    }

    isConnected() {
        if (this.type === 'bluetooth' && this.btCharacteristic) {
            return Boolean(this.btDevice?.gatt?.connected);
        }
        if (this.type === 'usb' && this.writer) return true;
        if (this.type === 'webusb' && this.usbDevice) return true;
        if (this.type === 'windows') return true;

        return false;
    }

    status() {
        if (this.type === 'bluetooth' && this.isConnected()) {
            return this.btDevice?.name ? `BT: ${this.btDevice.name}` : 'Bluetooth terhubung';
        }
        if (this.type === 'usb' && this.isConnected()) return 'USB Serial terhubung';
        if (this.type === 'webusb' && this.isConnected()) return 'USB Printer terhubung';
        if (this.type === 'windows') {
            return this.windowsPrinter ? `USB: ${this.windowsPrinter}` : 'USB Windows siap';
        }

        return 'Printer: belum terhubung';
    }

    emitStatus() {
        this.onStatusChange?.(this.status(), this.isConnected(), this.type);
    }

    rememberBluetoothDevice(device) {
        if (!device?.id) return;
        const payload = {
            id: device.id,
            name: device.name || this.settings.printer_name || '',
            saved_at: Date.now(),
        };
        localStorage.setItem(STORAGE_BT, JSON.stringify(payload));
        try {
            sessionStorage.setItem(STORAGE_BT, JSON.stringify(payload));
        } catch (_) {}
        OfflineStore.saveDeviceSettings({
            printer_type: this.settings.printer_type || 'bluetooth',
            printer_name: payload.name || this.settings.printer_name || '',
            printer_setup_done: true,
            bt_paired: true,
            bt_device_id: payload.id,
            bt_device_name: payload.name,
            extra: this.settings.extra || {},
        });
    }

    loadSavedBluetooth() {
        for (const store of [localStorage, sessionStorage]) {
            try {
                const raw = store.getItem(STORAGE_BT);
                if (!raw) continue;
                const parsed = JSON.parse(raw);
                if (parsed?.id) return parsed;
            } catch (_) {}
        }

        try {
            const deviceRaw = localStorage.getItem('kasirflow_device_settings');
            if (deviceRaw) {
                const device = JSON.parse(deviceRaw);
                if (device?.bt_device_id) {
                    return { id: device.bt_device_id, name: device.bt_device_name || '' };
                }
            }
        } catch (_) {}

        return null;
    }

    isPairedLocally() {
        if (this.settings.bt_paired) return true;
        if (this.loadSavedBluetooth()?.id) return true;
        try {
            const device = JSON.parse(localStorage.getItem('kasirflow_device_settings') || 'null');
            return Boolean(device?.bt_paired || device?.bt_device_id);
        } catch (_) {
            return false;
        }
    }

    /** Sudah pernah dipasangkan di browser ini (tidak harus sedang GATT connected). */
    async isPaired() {
        const type = this.settings.printer_type || 'bluetooth';
        if (type === 'none') return false;
        if (type === 'usb') return true;
        if (this.isConnected()) return true;
        if (this.isPairedLocally()) return true;

        const saved = this.loadSavedBluetooth();
        if (saved?.id) return true;

        if (!navigator.bluetooth?.getDevices) return false;
        try {
            const devices = await navigator.bluetooth.getDevices();
            return devices.length > 0;
        } catch (_) {
            return false;
        }
    }

    pickBluetoothDevice(devices, saved = null) {
        if (!devices?.length) return null;
        const byId = saved?.id ? devices.find((d) => d.id === saved.id) : null;
        if (byId) return byId;

        const wanted = String(this.settings.printer_name || saved?.name || '')
            .trim()
            .toLowerCase();
        if (wanted) {
            const byName = devices.find((d) => {
                const name = String(d.name || '').toLowerCase();
                return name && (name === wanted || name.includes(wanted) || wanted.includes(name));
            });
            if (byName) return byName;
        }

        return devices[0];
    }

    async connectBluetooth() {
        if (!navigator.bluetooth) {
            throw new Error('Web Bluetooth tidak didukung. Gunakan Chrome/Edge (HTTPS atau localhost).');
        }

        this.btDevice = await navigator.bluetooth.requestDevice({
            acceptAllDevices: true,
            optionalServices: BLE_SERVICES,
        });

        await this.bindBluetoothDevice(this.btDevice);
        this.rememberBluetoothDevice(this.btDevice);

        return this.status();
    }

    async waitForAdvertisement(device, timeoutMs = 2500) {
        if (!device?.watchAdvertisements) return false;
        try {
            const seen = await Promise.race([
                new Promise(async (resolve, reject) => {
                    const onAd = () => {
                        device.removeEventListener('advertisementreceived', onAd);
                        resolve(true);
                    };
                    device.addEventListener('advertisementreceived', onAd);
                    try {
                        await device.watchAdvertisements();
                    } catch (err) {
                        device.removeEventListener('advertisementreceived', onAd);
                        reject(err);
                    }
                }),
                sleep(timeoutMs).then(() => false),
            ]);
            return Boolean(seen);
        } catch (_) {
            return false;
        }
    }

    async reconnectBluetooth() {
        if (!navigator.bluetooth) return false;
        if (this.isConnected() && this.type === 'bluetooth') return true;
        if (!navigator.bluetooth.getDevices) return false;

        const saved = this.loadSavedBluetooth();
        let devices = [];
        try {
            devices = await navigator.bluetooth.getDevices();
        } catch (_) {
            return false;
        }
        if (!devices.length) return false;

        // Coba semua perangkat yang sudah diizinkan browser (bukan hanya 1)
        const ordered = [];
        const preferred = this.pickBluetoothDevice(devices, saved);
        if (preferred) ordered.push(preferred);
        devices.forEach((d) => {
            if (!ordered.includes(d)) ordered.push(d);
        });

        // Langsung gatt.connect — jangan tunggu iklan dulu (itu yang bikin lambat & gagal)
        for (const device of ordered) {
            try {
                if (device.gatt?.connected) {
                    try { device.gatt.disconnect(); } catch (_) {}
                    await sleep(150);
                }
                await this.bindBluetoothDevice(device);
                this.rememberBluetoothDevice(device);
                return true;
            } catch (_) {
                this.btCharacteristic = null;
            }
        }

        // Fallback: tunggu iklan singkat lalu coba lagi
        for (const device of ordered) {
            try {
                await this.waitForAdvertisement(device, 2000);
                await this.bindBluetoothDevice(device);
                this.rememberBluetoothDevice(device);
                return true;
            } catch (_) {
                try {
                    if (device.gatt?.connected) device.gatt.disconnect();
                } catch (__) {}
                this.btCharacteristic = null;
            }
        }

        return false;
    }

    /** Reconnect diam-diam berkali-kali (saat kembali ke Kasir setelah pindah menu). */
    async reconnectBluetoothPersistent({ tries = 5, gapMs = 700 } = {}) {
        for (let i = 0; i < tries; i += 1) {
            if (this.isConnected() && this.type === 'bluetooth') return true;
            const ok = await this.reconnectBluetooth();
            if (ok) return true;
            if (i < tries - 1) await sleep(gapMs);
        }
        return false;
    }

    async bindBluetoothDevice(device) {
        this.btDevice = device;
        if (!device._kasirflowDisconnectBound) {
            device._kasirflowDisconnectBound = true;
            device.addEventListener('gattserverdisconnected', () => {
                this.btCharacteristic = null;
                // Jangan emit "belum terkoneksi" agresif — biarkan POS tetap "siap"
                // sambil reconnect diam-diam
                if ((this.settings.printer_type || 'bluetooth') === 'bluetooth') {
                    setTimeout(() => {
                        this.reconnectBluetoothPersistent({ tries: 6, gapMs: 600 })
                            .then((ok) => {
                                if (ok) this.emitStatus();
                            })
                            .catch(() => {});
                    }, 400);
                }
            });
        }

        const gatt = this.btDevice.gatt;
        if (!gatt) {
            throw new Error('Perangkat Bluetooth tidak mendukung GATT.');
        }

        const server = gatt.connected
            ? gatt
            : await Promise.race([
                gatt.connect(),
                sleep(12000).then(() => {
                    throw new Error('Timeout koneksi Bluetooth. Pastikan printer menyala dan dekat.');
                }),
            ]);

        const characteristic = await this.discoverWriteCharacteristic(server);
        if (!characteristic) {
            throw new Error('Printer Bluetooth tidak mendukung cetak. Pastikan printer thermal BLE (bukan Classic SPP saja) dan sudah dipasangkan.');
        }

        this.btCharacteristic = characteristic;
        this.type = 'bluetooth';
        this.profile = resolveProfile(this.settings, this.btDevice.name);
        this.emitStatus();
    }

    /** Hubungkan otomatis sesuai pengaturan toko (tanpa klik di Kasir). */
    async autoConnect() {
        const type = this.settings.printer_type || 'bluetooth';
        if (type === 'none') return false;

        if (type === 'usb') {
            if (this.isConnected() && this.type === 'windows' && this.windowsPrinter) return true;
            await this.connectWindowsUsb();
            OfflineStore.saveDeviceSettings({
                printer_type: 'usb',
                printer_name: this.windowsPrinter || this.settings.printer_name || '',
                printer_setup_done: true,
                bt_paired: false,
                extra: {
                    ...(this.settings.extra || {}),
                    windows_printer: this.windowsPrinter || this.settings.extra?.windows_printer || null,
                    printer_usb_mode: 'windows',
                },
            });
            return Boolean(this.windowsPrinter || this.settings.printer_name);
        }

        if (this.isConnected() && this.type === 'bluetooth') return true;
        return this.reconnectBluetoothPersistent({ tries: 4, gapMs: 600 });
    }

    /**
     * Deteksi printer sesuai pilihan user: bluetooth | usb.
     */
    async detectAndConnect({ allowPrompt = true, preferType = null } = {}) {
        const prefer = preferType || this.settings.printer_type || 'bluetooth';

        if (prefer === 'usb') {
            try {
                const devices = await this.fetchWindowsDevices();
                const thermal = this.pickThermalWindowsPrinter(devices);
                if (!thermal?.name) {
                    return {
                        ok: false,
                        type: 'usb',
                        name: null,
                        message: 'Printer USB/COM belum ditemukan. Tancapkan printer, instal di Windows, atau pilih port COM di daftar.',
                        printers: devices.pos_printers || [],
                        com_ports: devices.com_ports || [],
                    };
                }

                const isCom = /^COM\d+$/i.test(thermal.name);
                const usbPort = this.resolveUsbPortFromDevice(thermal);
                this.settings = {
                    ...this.settings,
                    printer_type: 'usb',
                    printer_name: thermal.name,
                    paper_width: this.settings.paper_width || 58,
                    printer_setup_done: true,
                    extra: {
                        ...(this.settings.extra || {}),
                        windows_printer: isCom ? null : thermal.name,
                        com_port: isCom ? thermal.name.toUpperCase() : (this.settings.extra?.com_port || null),
                        usb_port: usbPort,
                        printer_usb_mode: 'windows',
                        printer_profile: this.settings.extra?.printer_profile || 'auto',
                    },
                };
                this.setSettings(this.settings);
                this.windowsPrinter = isCom ? null : thermal.name;
                this.comPort = isCom ? thermal.name.toUpperCase() : (this.comPort || null);
                this.usbPort = usbPort;
                await this.connectWindowsUsb();
                OfflineStore.saveDeviceSettings({
                    printer_type: 'usb',
                    printer_name: thermal.name,
                    printer_setup_done: true,
                    bt_paired: false,
                    extra: this.settings.extra,
                });
                return {
                    ok: true,
                    type: 'usb',
                    name: thermal.name,
                    message: isCom
                        ? `Port USB/Serial terdeteksi: ${thermal.name}`
                        : `Printer USB terdeteksi: ${thermal.name}`,
                    printers: devices.pos_printers || [],
                    com_ports: devices.com_ports || [],
                };
            } catch (err) {
                return {
                    ok: false,
                    type: 'usb',
                    name: null,
                    message: err.message || 'Gagal mendeteksi printer USB.',
                    printers: [],
                };
            }
        }

        // Bluetooth
        if (this.isConnected() && this.type === 'bluetooth') {
            const name = this.btDevice?.name || this.settings.bt_device_name || 'Bluetooth';
            return { ok: true, type: 'bluetooth', name, message: `Bluetooth terhubung: ${name}` };
        }

        try {
            const reconnected = await this.reconnectBluetooth();
            if (reconnected) {
                const name = this.btDevice?.name || this.settings.bt_device_name || 'Bluetooth';
                this.settings = { ...this.settings, printer_type: 'bluetooth', printer_name: name };
                this.setSettings(this.settings);
                OfflineStore.saveDeviceSettings({
                    printer_type: 'bluetooth',
                    printer_name: name,
                    printer_setup_done: true,
                    bt_paired: true,
                    bt_device_id: this.btDevice?.id || null,
                    bt_device_name: name,
                    extra: this.settings.extra || {},
                });
                return {
                    ok: true,
                    type: 'bluetooth',
                    name,
                    message: `Printer Bluetooth terhubung: ${name}`,
                };
            }
        } catch (_) {}

        if (allowPrompt && navigator.bluetooth) {
            await this.connectBluetooth();
            const name = this.btDevice?.name || 'Bluetooth';
            this.settings = { ...this.settings, printer_type: 'bluetooth', printer_name: name };
            this.setSettings(this.settings);
            this.rememberBluetoothDevice(this.btDevice);
            OfflineStore.saveDeviceSettings({
                printer_type: 'bluetooth',
                printer_name: name,
                printer_setup_done: true,
                bt_paired: true,
                bt_device_id: this.btDevice?.id || null,
                bt_device_name: name,
                extra: this.settings.extra || {},
            });
            return {
                ok: true,
                type: 'bluetooth',
                name,
                message: `Printer Bluetooth dipasangkan: ${name}`,
            };
        }

        return {
            ok: false,
            type: 'bluetooth',
            name: null,
            message: 'Printer Bluetooth belum terdeteksi. Nyalakan printer lalu klik Deteksi lagi.',
        };
    }

    pickThermalWindowsPrinter(devices = {}) {
        const list = [
            ...(devices.pos_printers || []),
            ...(devices.printers || []),
        ];
        const comPorts = devices.com_ports || [];

        const isVirtual = (p) => /onenote|one note|microsoft print to pdf|microsoft xps|fax|send to|adobe pdf|pdfcreator|pdf24|cutepdf|bullzip|virtual|document writer|portprompt/i
            .test(String((p?.name || '') + ' ' + (p?.driver || '') + ' ' + (p?.port || '')));

        const isComName = (name) => /^COM\d+$/i.test(String(name || ''));

        const isPos = (p) => {
            if (!p?.name || isVirtual(p)) return false;
            if (isComName(p.name) || isComName(p.port)) return true;
            const blob = `${p.name} ${p.driver || ''} ${p.port || ''}`;
            if (/^USB\d+/i.test(String(p.port || ''))) return true;
            if (/COM Serial/i.test(String(p.driver || ''))) return true;
            return /pos|thermal|receipt|esc\s*pos|epson|tm-|xprinter|xp-|gprinter|gp-|rongta|rpp|goojprt|hakpost|hprt|star|sm-|bixolon|srp-|citizen|munbyn|tsc|zebra|gainscha|imin|sunmi|58mm|80mm|bluetooth printer|usb printing|generic/i.test(blob);
        };

        if (devices.suggested?.name && (isPos(devices.suggested) || isComName(devices.suggested.name))) {
            return devices.suggested;
        }

        const preferred = this.settings.extra?.com_port
            || this.settings.extra?.windows_printer
            || this.settings.printer_name;

        if (preferred && !/onenote|one note|microsoft print to pdf|microsoft xps|fax|adobe pdf|pdfcreator|pdf24/i.test(preferred)) {
            if (isComName(preferred) && comPorts.map((c) => String(c).toUpperCase()).includes(String(preferred).toUpperCase())) {
                return { name: String(preferred).toUpperCase(), port: String(preferred).toUpperCase(), driver: 'COM Serial' };
            }
            const hit = list.find((p) => String(p.name).toLowerCase() === String(preferred).toLowerCase());
            if (hit && !isVirtual(hit)) return hit;
            // Jangan anggap sukses jika nama tidak ada di Windows/COM
        }

        const thermal = list.find(isPos);
        if (thermal) return thermal;

        if (comPorts.length === 1) {
            return { name: String(comPorts[0]).toUpperCase(), port: String(comPorts[0]).toUpperCase(), driver: 'COM Serial' };
        }

        return null;
    }

    async ensureConnected() {
        if (this.isConnected()) return true;
        const type = this.settings.printer_type || 'bluetooth';
        if (type === 'none') {
            throw new Error('Printer dimatikan di Pengaturan.');
        }
        if (type === 'usb') {
            await this.connectWindowsUsb();
            this.type = 'windows';
            return true;
        }

        const ok = await this.reconnectBluetoothPersistent({ tries: 5, gapMs: 700 });
        if (ok) return true;

        throw new Error('Bluetooth terputus. Klik Sambungkan ulang di Kasir, lalu cetak lagi.');
    }

    async discoverWriteCharacteristic(server) {
        // Scan semua service dulu — dukung printer thermal dengan UUID custom
        try {
            const services = await server.getPrimaryServices();
            for (const service of services) {
                const found = await this.findWriteChar(service);
                if (found) return found;
            }
        } catch (_) {}

        for (const uuid of BLE_SERVICES) {
            try {
                const service = await server.getPrimaryService(uuid);
                const found = await this.findWriteChar(service);
                if (found) return found;
            } catch (_) {}
        }

        return null;
    }

    async findWriteChar(service) {
        for (const uuid of BLE_WRITE_CHARS) {
            try {
                const c = await service.getCharacteristic(uuid);
                if (c.properties.writeWithoutResponse || c.properties.write) return c;
            } catch (_) {}
        }

        try {
            const chars = await service.getCharacteristics();
            return chars.find((c) => c.properties.writeWithoutResponse)
                || chars.find((c) => c.properties.write)
                || null;
        } catch (_) {
            return null;
        }
    }

    bytesToBase64(bytes) {
        let binary = '';
        const chunk = 0x8000;
        for (let i = 0; i < bytes.length; i += chunk) {
            binary += String.fromCharCode(...bytes.subarray(i, i + chunk));
        }
        return btoa(binary);
    }

    async fetchWindowsDevices() {
        const url = window.POS_CONFIG?.routes?.printerDevices;
        if (!url) return { printers: [], com_ports: [], suggested: null, saved: {} };
        const res = await fetch(url, { headers: { Accept: 'application/json' } });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Gagal membaca printer Windows');
        return json;
    }

    async connectUsb(baudRate = null) {
        const extra = this.settings.extra || {};
        const mode = extra.printer_usb_mode || 'windows';

        if (mode === 'serial') {
            return this.connectSerial(baudRate);
        }
        if (mode === 'webusb') {
            return this.connectWebUsb();
        }

        return this.connectWindowsUsb();
    }

    async connectWindowsUsb() {
        const extra = this.settings.extra || {};
        let suggested = extra.windows_printer || this.settings.printer_name || this.windowsPrinter || null;
        let com = extra.com_port || this.comPort || null;
        let usb = extra.usb_port || this.usbPort || null;

        if (suggested && /^COM\d+$/i.test(suggested)) {
            com = suggested.toUpperCase();
            suggested = null;
        }

        try {
            const devices = await this.fetchWindowsDevices();
            if (!com) {
                com = devices.saved?.com_port || null;
            }
            if (!usb) {
                usb = devices.saved?.usb_port || null;
            }
            if (!suggested) {
                suggested = devices.saved?.windows_printer || null;
            }

            if (suggested && /^COM\d+$/i.test(suggested)) {
                com = suggested.toUpperCase();
                suggested = null;
            }

            if (!suggested && !com) {
                if (devices.suggested?.name) {
                    if (/^COM\d+$/i.test(devices.suggested.name)) {
                        com = String(devices.suggested.name).toUpperCase();
                    } else {
                        suggested = devices.suggested.name;
                        usb = usb || this.resolveUsbPortFromDevice(devices.suggested);
                    }
                } else if ((devices.pos_printers || []).length === 1) {
                    const only = devices.pos_printers[0];
                    if (/^COM\d+$/i.test(only.name)) com = String(only.name).toUpperCase();
                    else {
                        suggested = only.name;
                        usb = usb || this.resolveUsbPortFromDevice(only);
                    }
                } else if ((devices.com_ports || []).length === 1) {
                    com = String(devices.com_ports[0]).toUpperCase();
                }
            }

            if (!usb && suggested) {
                const hit = (devices.pos_printers || devices.printers || []).find(
                    (p) => String(p.name).toLowerCase() === String(suggested).toLowerCase(),
                );
                if (hit) usb = this.resolveUsbPortFromDevice(hit);
            }
        } catch (_) {}

        this.windowsPrinter = suggested || (com || null);
        this.comPort = com || null;
        this.usbPort = usb || null;
        this.type = 'windows';
        if (!this.windowsPrinter && !this.comPort) {
            this.windowsPrinter = this.settings.extra?.windows_printer || null;
            this.comPort = this.settings.extra?.com_port || this.comPort;
            this.usbPort = this.settings.extra?.usb_port || this.usbPort;
        }
        this.settings = {
            ...this.settings,
            printer_type: 'usb',
            printer_name: this.windowsPrinter || this.comPort || this.settings.printer_name || '',
            printer_setup_done: true,
            extra: {
                ...(this.settings.extra || {}),
                windows_printer: suggested || this.settings.extra?.windows_printer || null,
                com_port: this.comPort,
                usb_port: this.usbPort,
                printer_usb_mode: 'windows',
            },
        };
        this.emitStatus();

        return this.status();
    }

    async connectSerial(baudRate = null) {
        if (!('serial' in navigator)) {
            throw new Error('Web Serial tidak didukung. Untuk RPP02N, pakai mode USB Windows di Pengaturan.');
        }

        const baud = Number(baudRate || this.profile.baud || 9600);
        if (this.writer) {
            try { this.writer.releaseLock(); } catch (_) {}
            this.writer = null;
        }
        if (this.serialPort) {
            try { await this.serialPort.close(); } catch (_) {}
        }

        this.serialPort = await navigator.serial.requestPort();
        await this.serialPort.open({ baudRate: baud, bufferSize: 255 });
        this.writer = this.serialPort.writable.getWriter();
        this.type = 'usb';
        this.emitStatus();

        return this.status();
    }

    async connectWebUsb() {
        if (!navigator.usb) {
            throw new Error('WebUSB tidak didukung. Di Windows, gunakan Hubungkan USB (Serial).');
        }

        const device = await navigator.usb.requestDevice({
            filters: [],
        });

        await device.open();
        if (device.configuration === null) {
            await device.selectConfiguration(1);
        }

        let claimed = null;
        const interfaces = [...device.configuration.interfaces];
        const ranked = interfaces.sort((a, b) => {
            const classA = a.alternate?.interfaceClass ?? a.alternates?.[0]?.interfaceClass ?? 0;
            const classB = b.alternate?.interfaceClass ?? b.alternates?.[0]?.interfaceClass ?? 0;
            return (classB === 7 ? 1 : 0) - (classA === 7 ? 1 : 0);
        });

        for (const candidate of ranked) {
            try {
                await device.claimInterface(candidate.interfaceNumber);
            } catch (_) {
                continue;
            }
            const alternate = candidate.alternate || candidate.alternates[0];
            const endpoint = (alternate?.endpoints || []).find((ep) => ep.direction === 'out' && (ep.type === 'bulk' || !ep.type));
            if (endpoint) {
                claimed = { iface: candidate, endpoint };
                break;
            }
            try { await device.releaseInterface(candidate.interfaceNumber); } catch (_) {}
        }

        if (!claimed) {
            throw new Error('Tidak bisa klaim USB RPP02N dari browser (driver Windows mengunci perangkat). Gunakan mode USB Windows RAW di Pengaturan.');
        }

        this.usbDevice = device;
        this.usbEndpoint = claimed.endpoint.endpointNumber;
        this.usbPacketSize = claimed.endpoint.packetSize || 64;
        this.type = 'webusb';
        this.emitStatus();

        return this.status();
    }

    async ensureBluetooth() {
        if (this.btCharacteristic && this.btDevice?.gatt?.connected) return;
        if (this.btDevice) {
            try {
                await this.bindBluetoothDevice(this.btDevice);
                return;
            } catch (_) {}
        }
        const ok = await this.reconnectBluetoothPersistent({ tries: 6, gapMs: 400 });
        if (ok) return;
        throw new Error('Bluetooth belum siap. Pastikan printer menyala, atau klik Sambungkan ulang.');
    }

    resolveUsbPortFromDevice(device = {}) {
        const port = String(device.port || '').toUpperCase();
        if (/^USB\d+$/.test(port)) return port;
        return null;
    }

    rememberUsbTarget(device = {}) {
        const usbPort = this.resolveUsbPortFromDevice(device);
        if (usbPort) {
            this.usbPort = usbPort;
        }
        if (device.port && /^COM\d+$/i.test(device.port)) {
            this.comPort = String(device.port).toUpperCase();
        }
        this.settings = {
            ...this.settings,
            extra: {
                ...(this.settings.extra || {}),
                usb_port: usbPort || this.settings.extra?.usb_port || null,
                com_port: this.comPort || this.settings.extra?.com_port || null,
            },
        };
    }

    resolveUsbTargets() {
        const name = this.windowsPrinter || this.settings.extra?.windows_printer || this.settings.printer_name || null;
        const com = this.comPort || this.settings.extra?.com_port || null;
        const usb = this.usbPort || this.settings.extra?.usb_port || null;
        if (name && /^COM\d+$/i.test(name)) {
            return { printerName: null, comPort: String(name).toUpperCase(), usbPort: usb };
        }
        return {
            printerName: name,
            comPort: com ? String(com).toUpperCase() : null,
            usbPort: usb ? String(usb).toUpperCase() : null,
        };
    }

    async writeBytes(bytes, plainText = null) {
        const data = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes);
        const prefer = this.settings.printer_type || 'bluetooth';

        // USB: selalu lewat Windows (RAW + teks driver seperti Word)
        if (prefer === 'usb') {
            if (this.type !== 'windows') {
                await this.connectWindowsUsb();
            }
            const target = this.resolveUsbTargets();
            return this.sendWindowsRaw(data, target.printerName, target.comPort, target.usbPort, plainText);
        }

        if (this.type === 'windows') {
            const target = this.resolveUsbTargets();
            return this.sendWindowsRaw(data, target.printerName, target.comPort, target.usbPort, plainText);
        }

        if (this.type === 'bluetooth' || prefer === 'bluetooth') {
            await this.ensureBluetooth();
            this.profile = resolveProfile(this.settings, this.btDevice?.name);
            const chunkSize = btChunkSize(this.profile, this.btCharacteristic);
            const delay = this.profile.chunkDelay || 30;
            const canNoResp = this.btCharacteristic.properties.writeWithoutResponse;
            const canWrite = this.btCharacteristic.properties.write;

            for (let i = 0; i < data.length; i += chunkSize) {
                const chunk = data.slice(i, i + chunkSize);
                let sent = false;
                if (canNoResp) {
                    try {
                        await this.btCharacteristic.writeValueWithoutResponse(chunk);
                        sent = true;
                    } catch (_) {}
                }
                if (!sent && canWrite) {
                    try {
                        await this.btCharacteristic.writeValue(chunk);
                        sent = true;
                    } catch (_) {}
                }
                if (!sent) {
                    throw new Error('Gagal menulis ke printer Bluetooth.');
                }
                if (delay) await sleep(delay);
            }

            return true;
        }

        if (this.type === 'usb' && this.writer) {
            await this.writer.write(data);
            return true;
        }

        if (this.type === 'webusb' && this.usbDevice) {
            const chunkSize = this.usbPacketSize || 64;
            for (let i = 0; i < data.length; i += chunkSize) {
                await this.usbDevice.transferOut(this.usbEndpoint, data.slice(i, i + chunkSize));
            }
            return true;
        }

        throw new Error('Printer belum siap. Buka Pengaturan → Deteksi → Tes cetak.');
    }

    async sendWindowsRaw(bytes, printerName = null, comPort = null, usbPort = null, plainText = null) {
        const data = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes);
        const url = window.POS_CONFIG?.routes?.printerRaw;
        if (!url) {
            throw new Error('Endpoint cetak USB Windows belum tersedia. Refresh halaman.');
        }

        let name = printerName;
        let com = comPort;
        let usb = usbPort;
        if (name === undefined || name === null) {
            const t = this.resolveUsbTargets();
            name = t.printerName;
            if (com == null) com = t.comPort;
            if (usb == null) usb = t.usbPort;
        }
        if (com === undefined) {
            com = this.comPort || this.settings.extra?.com_port || null;
        }
        if (usb === undefined || usb === null) {
            usb = this.usbPort || this.settings.extra?.usb_port || null;
        }
        if (name && /^COM\d+$/i.test(name)) {
            com = String(name).toUpperCase();
            name = null;
        }
        if (name && /^USB\d+$/i.test(name)) {
            usb = String(name).toUpperCase();
            name = null;
        }

        const textPayload = plainText != null
            ? this.textToBase64(plainText)
            : null;

        const attempts = [
            { printer_name: name, com_port: com, usb_port: usb },
            { printer_name: name, com_port: com, usb_port: null },
            { printer_name: name, com_port: null, usb_port: usb },
            { printer_name: name, com_port: null, usb_port: null },
            { printer_name: null, com_port: com, usb_port: usb },
            { printer_name: null, com_port: com, usb_port: null },
            { printer_name: null, com_port: null, usb_port: usb },
            { printer_name: null, com_port: null, usb_port: null },
        ];

        const seen = new Set();
        let lastError = 'Gagal cetak USB. Pilih printer/COM di Pengaturan lalu Tes cetak.';

        for (const attempt of attempts) {
            const key = `${attempt.printer_name || ''}|${attempt.com_port || ''}|${attempt.usb_port || ''}`;
            if (seen.has(key)) continue;
            seen.add(key);

            const body = {
                bytes: this.bytesToBase64(data),
                printer_name: attempt.printer_name,
                com_port: attempt.com_port,
                usb_port: attempt.usb_port,
            };
            if (textPayload) body.text = textPayload;

            const res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': window.POS_CONFIG.csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(body),
            });
            const json = await res.json().catch(() => ({}));
            if (res.ok && json.success) {
                if (json.result?.target) {
                    if (/^COM\d+$/i.test(json.result.target)) {
                        this.comPort = json.result.target;
                    } else if (/^USB\d+$/i.test(json.result.target)) {
                        this.usbPort = json.result.target;
                    } else {
                        this.windowsPrinter = json.result.target;
                    }
                    this.type = 'windows';
                    this.emitStatus();
                }
                return true;
            }
            lastError = json.message || lastError;
        }

        throw new Error(lastError);
    }

    textToBase64(text) {
        const bytes = new TextEncoder().encode(String(text || ''));
        return this.bytesToBase64(bytes);
    }

    async openCashDrawer(options = {}) {
        const settings = this.settings || {};
        const extra = settings.extra || {};
        if (!options.force && extra.cash_drawer === false) {
            throw new Error('Buka laci dimatikan di pengaturan.');
        }
        // Default Pin 2 — paling umum (Hakpost, Rongta, Xprinter, dll)
        const pin = extra.cash_drawer_pin || '2';
        const kick = drawerKickBytes(pin);
        const comPort = options.comPort || extra.cash_drawer_com_port || null;
        let winName = options.printerName
            || extra.cash_drawer_windows_printer
            || null;
        const errors = [];

        // Satu kali saja ke printer yang sedang dipakai cetak
        if (this.isConnected() && (this.type === 'bluetooth' || this.type === 'usb' || this.type === 'webusb' || this.type === 'windows')) {
            try {
                if (this.type === 'windows' && winName) {
                    await this.sendWindowsRaw(kick, winName, comPort);
                } else {
                    await this.writeBytes(kick);
                }
                return true;
            } catch (err) {
                errors.push(err.message || String(err));
            }
        }

        if (comPort) {
            try {
                await this.sendWindowsRaw(kick, null, comPort);
                return true;
            } catch (err) {
                errors.push(`COM ${comPort}: ${err.message || err}`);
            }
        }

        if (winName) {
            try {
                await this.sendWindowsRaw(kick, winName);
                return true;
            } catch (err) {
                errors.push(`Windows ${winName}: ${err.message || err}`);
            }
        }

        // Fallback: printer Windows yang terpasang RJ11 (bukan scan semua COM berulang)
        try {
            const devices = await this.fetchWindowsDevices();
            const preferred = extra.windows_printer || this.windowsPrinter || this.settings.printer_name;
            const candidate = (devices.printers || []).find((p) => preferred && String(p.name).toLowerCase() === String(preferred).toLowerCase())
                || (devices.printers || []).find((p) => {
                    const name = String(p.name || '');
                    const port = String(p.port || '');
                    if (/pdf|onenote|fax|xps|microsoft/i.test(name)) return false;
                    return /usb|pos|thermal|receipt|epson|xprinter|gprinter|rongta|hakpost|hprt|star|bixolon/i.test(`${name} ${port}`)
                        || /^USB\d+/i.test(port);
                });
            if (candidate?.name) {
                await this.sendWindowsRaw(kick, candidate.name);
                return true;
            }
            if (comPort) {
                // sudah dicoba di atas
            } else if ((devices.com_ports || []).length === 1) {
                await this.sendWindowsRaw(kick, null, devices.com_ports[0]);
                return true;
            }
        } catch (err) {
            errors.push(err.message || String(err));
        }

        throw new Error(
            'Gagal membuka laci. Pastikan kabel RJ11 ke port DK printer, laci dapat daya, pin benar (coba Pin 2), lalu Tes buka laci. '
            + (errors[0] || ''),
        );
    }

    async printReceipt(transaction, settings = {}, options = {}) {
        if (settings && Object.keys(settings).length) this.setSettings(settings);
        await this.ensureConnected();

        const openDrawer = shouldOpenDrawer(transaction, this.settings, options);
        const logoImage = await loadLogoImage(this.settings.logo_url);
        const receiptText = buildReceiptText(transaction, this.settings, this.profile, options);
        const bytes = buildReceipt(transaction, this.settings, this.profile, {
            ...options,
            openDrawer: false,
            logoImage,
        });
        await this.writeBytes(bytes, receiptText);

        let drawerError = null;
        if (openDrawer) {
            try {
                // Satu kali setelah struk selesai dikirim
                await sleep(150);
                await this.openCashDrawer({ force: true });
            } catch (err) {
                drawerError = err.message || 'Gagal membuka laci';
                console.warn('Cash drawer:', drawerError);
            }
        }

        return { ok: true, drawerError };
    }

    async printTest(settings = {}) {
        if (settings && Object.keys(settings).length) this.setSettings(settings);
        await this.ensureConnected();

        return this.printReceipt({
            invoice_number: 'TEST-PRINT',
            sold_at: new Date().toISOString(),
            order_type: 'takeaway',
            payment_method: 'cash',
            subtotal: 1000,
            discount: 0,
            tax: 0,
            total: 1000,
            paid: 1000,
            change: 0,
            items: [{ product_name: 'Tes printer KasirFlow', qty: 1, price: 1000, subtotal: 1000 }],
        }, this.settings, { openDrawer: true });
    }
}

export const printer = new PosPrinter();
export default printer;
