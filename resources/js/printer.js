import ReceiptPrinterEncoder from '@point-of-sale/receipt-printer-encoder';
import OfflineStore from './offline-store';

const STORAGE_BT = 'kasirflow_bt_printer';

/** UUID BLE yang dipakai printer kasir 58/80mm, termasuk Rongta RPP02N. */
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
    '49535343-fe7d-4ae5-8fa9-9fafd205e455', // ISSC / Epson TM-P
    '6e400001-b5a3-f393-e0a9-e50e24dcca9e', // Nordic UART
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
];

const PROFILES = {
    rpp02n: { chunkSize: 20, chunkDelay: 40, autoCut: false, paper: 58, baud: 9600, mapping: 'youku' },
    generic58: { chunkSize: 20, chunkDelay: 30, autoCut: false, paper: 58, baud: 9600, mapping: 'pos-5890' },
    generic80: { chunkSize: 20, chunkDelay: 20, autoCut: true, paper: 80, baud: 115200, mapping: 'xprinter' },
    xprinter: { chunkSize: 20, chunkDelay: 20, autoCut: true, paper: 80, baud: 9600, mapping: 'xprinter' },
    epson: { chunkSize: 20, chunkDelay: 15, autoCut: true, paper: 80, baud: 38400, mapping: 'epson' },
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

function resolveProfile(settings = {}, deviceName = '') {
    const extra = settings.extra || {};
    let key = extra.printer_profile || settings.printer_profile || '';
    const name = String(deviceName || settings.printer_name || '');

    if (!key) {
        if (/rpp|rongta|goojprt/i.test(name)) key = 'rpp02n';
        else if (Number(settings.paper_width) === 80) key = 'generic80';
        else key = 'rpp02n';
    }

    const base = { ...(PROFILES[key] || PROFILES.rpp02n) };
    if (Number(settings.paper_width) === 80 || Number(settings.paper_width) === 58) {
        base.paper = Number(settings.paper_width);
    }
    if (extra.printer_baud) base.baud = Number(extra.printer_baud);
    if (typeof extra.printer_auto_cut === 'boolean') base.autoCut = extra.printer_auto_cut;
    if (key === 'rpp02n') base.autoCut = extra.printer_auto_cut === true;

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

/** Kick cash drawer: beberapa variasi ESC p + DLE DC4 agar kompatibel lintas printer. */
export function drawerKickBytes(pin = 'both') {
    const pulses = [
        [0x32, 0xFA], // 50ms on / 250ms off (umum)
        [0x40, 0xF0], // lebih panjang
        [0x19, 0xFF], // pendek
        [0x60, 0xFF], // kuat
    ];
    const pins = (pin === '5' || pin === 1 || pin === '1')
        ? [1]
        : (pin === '2' || pin === 0 || pin === '0')
            ? [0]
            : [0, 1];

    const cmds = [];
    // Initialize dulu biar printer siap menerima perintah laci
    cmds.push(0x1b, 0x40);
    pins.forEach((m) => {
        pulses.forEach(([t1, t2]) => {
            cmds.push(0x1b, 0x70, m, t1, t2); // ESC p
            cmds.push(0x10, 0x14, 0x01, m, 0x01); // DLE DC4
        });
        // Ulangi pulse utama
        cmds.push(0x1b, 0x70, m, 0x32, 0xFA);
        cmds.push(0x1b, 0x70, m, 0x32, 0xFA);
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
        if (shouldOpenDrawer(transaction, settings, printOptions)) {
            const pin = (settings.extra || {}).cash_drawer_pin || 'both';
            bytes = concatBytes(drawerKickBytes(pin), bytes, drawerKickBytes(pin));
        }
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
        let bytes = new TextEncoder().encode(blob);
        if (shouldOpenDrawer(transaction, settings, printOptions)) {
            const pin = (settings.extra || {}).cash_drawer_pin || 'both';
            bytes = concatBytes(drawerKickBytes(pin), bytes, drawerKickBytes(pin));
        }
        return bytes;
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

    async waitForAdvertisement(device, timeoutMs = 10000) {
        if (!device?.watchAdvertisements) return false;
        try {
            await Promise.race([
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
            return true;
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

        const device = this.pickBluetoothDevice(devices, saved);
        if (!device) return false;

        const attempts = [0, 700, 1600];
        for (let i = 0; i < attempts.length; i += 1) {
            if (attempts[i]) await sleep(attempts[i]);
            try {
                if (i > 0) await this.waitForAdvertisement(device, 6000);
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

    async bindBluetoothDevice(device) {
        this.btDevice = device;
        if (!device._kasirflowDisconnectBound) {
            device._kasirflowDisconnectBound = true;
            device.addEventListener('gattserverdisconnected', () => {
                this.btCharacteristic = null;
                this.emitStatus();
                if ((this.settings.printer_type || 'bluetooth') === 'bluetooth') {
                    setTimeout(() => {
                        this.reconnectBluetooth().catch(() => {});
                    }, 1200);
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
            throw new Error('Karakteristik tulis tidak ditemukan. Pastikan printer RPP02N dalam mode BLE (bukan Classic SPP saja).');
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
            await this.connectWindowsUsb();
            return true;
        }

        if (this.isConnected() && this.type === 'bluetooth') return true;
        return this.reconnectBluetooth();
    }

    async ensureConnected() {
        if (this.isConnected()) return true;
        const type = this.settings.printer_type || 'bluetooth';
        if (type === 'none') {
            throw new Error('Printer dimatikan di Pengaturan.');
        }
        if (type === 'usb') {
            await this.connectWindowsUsb();
            return true;
        }

        const ok = await this.reconnectBluetooth();
        if (ok) return true;

        if (this.isPairedLocally()) {
            throw new Error('Printer siap — ketuk Sambungkan ulang sekali lalu cetak.');
        }

        throw new Error('Printer belum dipasangkan. Buka Pengaturan → Pasangkan printer.');
    }

    async discoverWriteCharacteristic(server) {
        for (const uuid of BLE_SERVICES) {
            try {
                const service = await server.getPrimaryService(uuid);
                const found = await this.findWriteChar(service);
                if (found) return found;
            } catch (_) {}
        }

        const services = await server.getPrimaryServices();
        for (const service of services) {
            const found = await this.findWriteChar(service);
            if (found) return found;
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

        const chars = await service.getCharacteristics();
        return chars.find((c) => c.properties.writeWithoutResponse)
            || chars.find((c) => c.properties.write)
            || null;
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
        let suggested = extra.windows_printer || this.settings.printer_name || null;
        let com = extra.com_port || null;

        try {
            const devices = await this.fetchWindowsDevices();
            suggested = extra.windows_printer
                || devices.saved?.windows_printer
                || devices.suggested?.name
                || this.settings.printer_name
                || suggested;
            com = extra.com_port || devices.saved?.com_port || com;
            if (!suggested && devices.printers?.length === 1) {
                suggested = devices.printers[0].name;
            }
            if (!com && devices.com_ports?.length === 1) {
                com = devices.com_ports[0];
            }
        } catch (_) {}

        this.windowsPrinter = suggested || null;
        this.comPort = com || null;
        this.type = 'windows';
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
            await this.bindBluetoothDevice(this.btDevice);
            return;
        }
        throw new Error('Printer Bluetooth belum terhubung. Klik ikon Bluetooth dulu.');
    }

    async writeBytes(bytes) {
        const data = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes);

        if (this.type === 'bluetooth') {
            await this.ensureBluetooth();
            const chunkSize = this.profile.chunkSize || 20;
            const delay = this.profile.chunkDelay || 40;
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
                    await this.btCharacteristic.writeValue(chunk);
                    sent = true;
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

        if (this.type === 'windows') {
            return this.sendWindowsRaw(data, this.windowsPrinter, this.comPort);
        }

        throw new Error('Printer belum terhubung. Hubungkan Bluetooth atau USB terlebih dahulu.');
    }

    async sendWindowsRaw(bytes, printerName = null, comPort = null) {
        const data = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes);
        const url = window.POS_CONFIG?.routes?.printerRaw;
        if (!url) {
            throw new Error('Endpoint cetak USB Windows belum tersedia. Refresh halaman.');
        }
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': window.POS_CONFIG.csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                bytes: this.bytesToBase64(data),
                printer_name: printerName === undefined ? this.windowsPrinter : printerName,
                com_port: comPort === undefined ? this.comPort : comPort,
            }),
        });
        const json = await res.json().catch(() => ({}));
        if (!res.ok || !json.success) {
            throw new Error(json.message || 'Gagal kirim ke printer Windows. Pastikan printer USB terpasang di Devices and Printers.');
        }
        if (json.result?.target) {
            this.windowsPrinter = json.result.target;
            this.emitStatus();
        }
        return true;
    }

    async openCashDrawer(options = {}) {
        const settings = this.settings || {};
        const extra = settings.extra || {};
        if (!options.force && extra.cash_drawer === false) {
            throw new Error('Buka laci dimatikan di pengaturan.');
        }
        const pin = extra.cash_drawer_pin || 'both';
        const kick = drawerKickBytes(pin);
        const comPort = options.comPort || extra.cash_drawer_com_port || null;
        let winName = options.printerName
            || extra.cash_drawer_windows_printer
            || null;
        const errors = [];

        // Jalur utama: kirim ESC p ke printer yang sedang dipakai cetak (termasuk Bluetooth)
        // Karena RJ11 biasanya menempel di printer yang sama dengan yang mencetak struk.
        if (this.isConnected() && (this.type === 'bluetooth' || this.type === 'usb' || this.type === 'webusb' || this.type === 'windows')) {
            try {
                if (this.type === 'windows' && winName) {
                    await this.sendWindowsRaw(kick, winName, comPort);
                } else {
                    await this.writeBytes(kick);
                    // jeda lalu kick kedua (beberapa laci butuh 2 pulse)
                    await sleep(300);
                    await this.writeBytes(kick);
                }
                return true;
            } catch (err) {
                errors.push(err.message || String(err));
            }
        }

        // Jalur COM eksplisit (adapter / Bluetooth SPP)
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

        // Coba semua port COM Bluetooth yang terdeteksi
        try {
            const devices = await this.fetchWindowsDevices();
            for (const com of (devices.com_ports || [])) {
                try {
                    await this.sendWindowsRaw(kick, null, com);
                    return true;
                } catch (err) {
                    errors.push(`${com}: ${err.message || err}`);
                }
            }
            const candidate = (devices.printers || []).find((p) => {
                const name = String(p.name || '');
                const port = String(p.port || '');
                if (/pdf|onenote|fax|xps|microsoft/i.test(name)) return false;
                return /usb|pos|thermal|receipt|epson|xprinter|gprinter|rongta|star|bixolon/i.test(`${name} ${port}`)
                    || /^USB\d+/i.test(port);
            });
            if (candidate?.name) {
                await this.sendWindowsRaw(kick, candidate.name);
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
        const bytes = buildReceipt(transaction, this.settings, this.profile, {
            ...options,
            openDrawer,
            logoImage,
        });
        await this.writeBytes(bytes);

        let drawerError = null;
        if (openDrawer) {
            try {
                // Kick ekstra setelah struk (beberapa printer hanya bereaksi di luar job panjang)
                await sleep(200);
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
