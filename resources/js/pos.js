import OfflineStore from './offline-store';
import printer from './printer';
import { createBarcodeScanner } from './scanner';

function formatMoney(n) {
    return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
}

function parseRupiah(value) {
    if (typeof value === 'number') return Math.max(0, Math.floor(value));
    const digits = String(value || '').replace(/[^\d]/g, '');
    return digits ? parseInt(digits, 10) : 0;
}

function formatRupiahInput(value) {
    const num = parseRupiah(value);
    return num ? num.toLocaleString('id-ID') : '';
}

function bindRupiahInput(el, onChange) {
    if (!el) return;
    el.addEventListener('input', () => {
        const raw = parseRupiah(el.value);
        const pos = el.selectionStart;
        const beforeLen = el.value.length;
        el.value = formatRupiahInput(raw);
        const afterLen = el.value.length;
        const nextPos = Math.max(0, (pos || 0) + (afterLen - beforeLen));
        try { el.setSelectionRange(nextPos, nextPos); } catch (_) {}
        onChange?.();
    });
    el.addEventListener('blur', () => {
        el.value = formatRupiahInput(parseRupiah(el.value));
        onChange?.();
    });
}

function uid() {
    return 'loc_' + Date.now() + '_' + Math.random().toString(36).slice(2, 9);
}

export function initPos() {
    const root = document.getElementById('pos-app');
    if (!root) return;

    let products = JSON.parse(root.dataset.products || '[]');
    let categories = JSON.parse(root.dataset.categories || '[]');

    const serverSettings = JSON.parse(root.dataset.settings || 'null') || {};
    const configPrinter = window.POS_CONFIG?.printer || {};
    let localDevice = null;
    try {
        localDevice = JSON.parse(localStorage.getItem('kasirflow_device_settings') || 'null');
    } catch (_) {
        localDevice = null;
    }

    // Gabungkan: server + POS_CONFIG.printer + localStorage (local menang untuk koneksi)
    let settings = OfflineStore.mergeSettings({
        ...serverSettings,
        printer_type: configPrinter.printer_type || serverSettings.printer_type,
        printer_name: configPrinter.printer_name || serverSettings.printer_name,
        paper_width: configPrinter.paper_width || serverSettings.paper_width,
        extra: {
            ...(serverSettings.extra || {}),
            ...(configPrinter.extra || {}),
        },
    }, localDevice || {});

    let cart = [];
    let lastTransaction = null;
    let orderType = 'dine_in';
    let printerReady = false;
    let productViewMode = localStorage.getItem('poskasir_product_view') === 'list' ? 'list' : 'grid';

    const els = {
        grid: document.getElementById('product-grid'),
        btnViewGrid: document.getElementById('btn-pos-view-grid'),
        btnViewList: document.getElementById('btn-pos-view-list'),
        cartList: document.getElementById('cart-list'),
        subtotal: document.getElementById('cart-subtotal'),
        tax: document.getElementById('cart-tax'),
        total: document.getElementById('cart-total'),
        discount: document.getElementById('cart-discount'),
        taxLabel: document.getElementById('tax-label'),
        paid: document.getElementById('paid-amount'),
        change: document.getElementById('change-amount'),
        search: document.getElementById('product-search'),
        category: document.getElementById('category-filter'),
        barcode: document.getElementById('barcode-input'),
        customer: document.getElementById('customer-name'),
        method: document.getElementById('payment-method'),
        tableNumber: document.getElementById('table-number'),
        tableWrap: document.getElementById('table-number-wrap'),
        printerStatus: document.getElementById('printer-status'),
        scannerStatus: document.getElementById('scanner-status'),
        offlineBadge: document.getElementById('offline-badge'),
        modal: document.getElementById('checkout-modal'),
        modalMsg: document.getElementById('checkout-message'),
    };

    els.taxLabel.textContent = settings.tax_percent || 0;
    if (els.discount) els.discount.value = '0';
    printer.setSettings(settings);

    function syncPrinterSettings(partial = {}) {
        settings = {
            ...settings,
            ...partial,
            extra: {
                ...(settings.extra || {}),
                ...(partial.extra || {}),
            },
        };
        printer.setSettings(settings);
        OfflineStore.saveDeviceSettings({
            printer_type: settings.printer_type,
            printer_name: settings.printer_name,
            paper_width: settings.paper_width,
            printer_setup_done: true,
            bt_paired: settings.bt_paired,
            bt_device_id: settings.bt_device_id,
            bt_device_name: settings.bt_device_name,
            extra: settings.extra || {},
        });
        return settings;
    }

    // Cache awal
    syncPrinterSettings({
        printer_setup_done: Boolean(settings.printer_type && settings.printer_type !== 'none'),
    });

    function isPrinterConfigured() {
        const type = settings.printer_type;
        if (!type || type === 'none') return false;
        return type === 'usb' || type === 'bluetooth';
    }

    function isPrinterPaired() {
        return printerReady || isPrinterConfigured();
    }

    function hideReconnectBtn() {
        document.getElementById('btn-reconnect-printer')?.classList.add('hidden');
    }

    function showReconnectBtn() {
        const btn = document.getElementById('btn-reconnect-printer');
        if (!btn) return;
        if (!isPrinterConfigured()) {
            btn.classList.add('hidden');
            return;
        }
        btn.classList.remove('hidden');
        btn.removeAttribute('hidden');
        btn.style.display = '';
    }

    function markPrinterReady(detail = '', { showButton = false } = {}) {
        printerReady = true;
        const name = detail
            || printer.btDevice?.name
            || settings.bt_device_name
            || settings.printer_name
            || (settings.printer_type === 'usb' ? 'USB' : 'Bluetooth');

        if (printer.isConnected()) {
            setDeviceBadge(els.printerStatus, 'Printer', true, name);
            hideReconnectBtn();
            return;
        }

        // Tersimpan — anggap siap; reconnect diam-diam (jangan paksa klik tiap pindah menu)
        if (els.printerStatus) {
            els.printerStatus.textContent = `Printer: siap (${name})`;
            els.printerStatus.className = 'device-badge inline-flex items-center px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 font-medium';
        }
        if (showButton) showReconnectBtn();
        else hideReconnectBtn();
    }

    function markPrinterLive(detail = '') {
        printerReady = true;
        const name = detail
            || printer.btDevice?.name
            || printer.windowsPrinter
            || settings.bt_device_name
            || settings.printer_name
            || (settings.printer_type === 'usb' ? 'USB' : 'Bluetooth');
        setDeviceBadge(els.printerStatus, 'Printer', true, name);
        hideReconnectBtn();
    }

    async function refreshPrinterBeforePrint() {
        try {
            const local = JSON.parse(localStorage.getItem('kasirflow_device_settings') || 'null');
            if (local) {
                settings = OfflineStore.mergeSettings(settings, local);
            }
        } catch (_) {}

        const cfg = window.POS_CONFIG?.printer;
        if (cfg?.printer_type && cfg.printer_type !== 'none') {
            // Server (hasil Deteksi/Tes di Pengaturan) jadi acuan tipe koneksi
            settings.printer_type = cfg.printer_type;
            settings.printer_name = cfg.printer_name || settings.printer_name;
            settings.extra = {
                ...(settings.extra || {}),
                ...(cfg.extra || {}),
            };
        }

        // Token BT tetap dari local
        try {
            const local = JSON.parse(localStorage.getItem('kasirflow_device_settings') || 'null');
            if (local?.bt_device_id) {
                settings.bt_paired = true;
                settings.bt_device_id = local.bt_device_id;
                settings.bt_device_name = local.bt_device_name || settings.bt_device_name;
            }
        } catch (_) {}

        printer.setSettings(settings);
        await printer.autoConnect();
        return settings;
    }

    function focusBarcodeInput() {
        if (settings.scanner_enabled === false || !els.barcode) return;
        const active = document.activeElement;
        if (active && active !== els.barcode && active.matches?.('input:not([data-no-keyboard]), textarea, select')) {
            return;
        }
        window.requestAnimationFrame(() => {
            els.barcode.setAttribute('readonly', 'readonly');
            els.barcode.setAttribute('inputmode', 'none');
            try {
                els.barcode.focus({ preventScroll: true });
            } catch (_) {
                els.barcode.focus();
            }
            requestAnimationFrame(() => els.barcode.removeAttribute('readonly'));
        });
    }

    printer.onStatusChange = (label, connected, type) => {
        if (connected) {
            let detail = '';
            if (type === 'bluetooth') detail = printer.btDevice?.name || settings.printer_name || 'Bluetooth';
            else if (type === 'windows') detail = printer.windowsPrinter || printer.comPort || 'USB';
            else if (type === 'webusb') detail = 'USB Printer';
            else detail = 'USB';
            markPrinterLive(detail);
            return;
        }
        // Putus sementara (pindah menu) — jangan minta sambungkan ulang
        if (isPrinterConfigured()) {
            markPrinterReady(settings.bt_device_name || settings.printer_name || '', { showButton: false });
            return;
        }
        setDeviceBadge(els.printerStatus, 'Printer', false);
        hideReconnectBtn();
    };

    function toast(msg) {
        window.PosApp?.toast(msg);
    }

    function setDeviceBadge(el, label, connected, detail = '') {
        if (!el) return;
        if (connected) {
            el.textContent = detail ? `${label}: ${detail}` : `${label}: terkoneksi`;
            el.className = 'device-badge inline-flex items-center px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 font-medium';
        } else {
            el.textContent = `${label}: belum terkoneksi`;
            el.className = 'device-badge inline-flex items-center px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 font-medium';
        }
    }

    setDeviceBadge(els.printerStatus, 'Printer', false);
    if (isPrinterConfigured()) {
        markPrinterReady(settings.bt_device_name || settings.printer_name || '');
    }
    if (settings.scanner_enabled !== false) {
        setDeviceBadge(els.scannerStatus, 'Scanner', true, 'siap');
    } else {
        setDeviceBadge(els.scannerStatus, 'Scanner', false);
        els.scannerStatus.textContent = 'Scanner: nonaktif';
    }

    function setOrderType(type) {
        orderType = type;
        document.querySelectorAll('.order-type-btn').forEach((btn) => {
            const active = btn.dataset.type === type;
            btn.classList.toggle('btn-primary', active);
            btn.classList.toggle('btn-ghost', !active);
        });
        if (els.tableWrap) {
            els.tableWrap.classList.toggle('hidden', type !== 'dine_in');
        }
        if (type !== 'dine_in' && els.tableNumber) {
            els.tableNumber.value = '';
        }
    }

    function totals() {
        const subtotal = cart.reduce((s, i) => s + i.price * i.qty, 0);
        const discount = parseRupiah(els.discount?.value);
        const taxPercent = Number(settings.tax_percent || 0);
        const taxable = Math.max(0, subtotal - discount);
        const tax = Math.round(taxable * (taxPercent / 100));
        const total = taxable + tax;
        const paid = parseRupiah(els.paid?.value);
        const change = Math.max(0, paid - total);
        return { subtotal, discount, tax, total, paid, change };
    }

    function renderCart() {
        if (!cart.length) {
            els.cartList.innerHTML = '<p class="text-sm text-slate-400 py-8 text-center">Keranjang kosong</p>';
        } else {
            els.cartList.innerHTML = cart.map((item, idx) => `
                <div class="cart-item">
                    <div class="cart-item-row">
                        <div class="cart-item-info min-w-0">
                            <div class="font-semibold text-sm leading-snug truncate">${item.product_name}</div>
                            <div class="text-xs text-slate-500">${formatMoney(item.price)}</div>
                        </div>
                        <div class="cart-qty shrink-0">
                            <button type="button" data-dec="${idx}" class="cart-qty-btn" aria-label="Kurangi">−</button>
                            <span class="cart-qty-val">${item.qty}</span>
                            <button type="button" data-inc="${idx}" class="cart-qty-btn" aria-label="Tambah">+</button>
                        </div>
                        <div class="cart-item-total font-bold text-sm whitespace-nowrap shrink-0">${formatMoney(item.price * item.qty)}</div>
                        <button type="button" data-del="${idx}" class="cart-del-icon" title="Hapus" aria-label="Hapus">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                <line x1="10" y1="11" x2="10" y2="17"></line>
                                <line x1="14" y1="11" x2="14" y2="17"></line>
                            </svg>
                        </button>
                    </div>
                </div>
            `).join('');
        }

        const t = totals();
        els.subtotal.textContent = formatMoney(t.subtotal);
        els.tax.textContent = formatMoney(t.tax);
        els.total.textContent = formatMoney(t.total);
        els.change.textContent = formatMoney(t.change);
    }

    function setProductViewMode(mode) {
        productViewMode = mode === 'list' ? 'list' : 'grid';
        localStorage.setItem('poskasir_product_view', productViewMode);

        if (els.grid) {
            els.grid.classList.toggle('pos-view-list', productViewMode === 'list');
            els.grid.classList.toggle('pos-view-grid', productViewMode === 'grid');
            if (productViewMode === 'list') {
                els.grid.classList.remove('grid', 'grid-cols-2', 'md:grid-cols-3', 'xl:grid-cols-4', 'gap-3');
            } else {
                els.grid.classList.add('grid', 'grid-cols-2', 'md:grid-cols-3', 'xl:grid-cols-4', 'gap-3');
            }
        }

        els.btnViewGrid?.classList.toggle('active', productViewMode === 'grid');
        els.btnViewList?.classList.toggle('active', productViewMode === 'list');
        renderProducts();
    }

    function renderProductMeta(p) {
        const meta = [];
        if (p.track_stock !== false) meta.push(`Stok ${p.stock}`);
        if (p.has_expiry && p.expired_at) {
            const exp = new Date(p.expired_at);
            meta.push(`Exp ${exp.toLocaleDateString('id-ID')}`);
        }
        return meta.join(' · ') || 'Tanpa stok';
    }

    function renderProductImage(p, { list = false } = {}) {
        const listCls = list ? 'h-12 w-12 shrink-0 rounded-lg' : 'w-full h-24 rounded-lg mb-2';
        if (p.image_url) {
            return `<img src="${p.image_url}" alt="" class="${listCls} object-cover bg-slate-100">`;
        }
        if (list) {
            return `<div class="${listCls} bg-slate-100 grid place-items-center text-slate-400 text-[10px]">No foto</div>`;
        }
        return `<div class="w-full h-24 rounded-lg mb-2 bg-slate-100 grid place-items-center text-slate-400 text-xs">Tanpa foto</div>`;
    }

    function renderProducts() {
        const q = (els.search.value || '').toLowerCase();
        const cat = window.jQuery && els.category ? jQuery(els.category).val() : els.category?.value;
        const filtered = products.filter((p) => {
            const matchQ = !q || p.name.toLowerCase().includes(q) || (p.barcode || '').includes(q);
            const matchC = !cat || String(p.category_id) === String(cat);
            return matchQ && matchC && p.is_active !== false;
        });

        if (productViewMode === 'list') {
            els.grid.innerHTML = filtered.map((p) => `
                <button type="button" class="product-tile product-tile-list card p-2.5 text-left w-full flex items-center gap-3" data-id="${p.id}">
                    ${renderProductImage(p, { list: true })}
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold text-sm leading-snug truncate">${p.name}</div>
                        <div class="text-xs text-slate-500 truncate">${p.category?.name || '-'} · ${renderProductMeta(p)}</div>
                    </div>
                    <div class="text-brand-700 font-bold text-sm whitespace-nowrap shrink-0">${formatMoney(p.price)}</div>
                </button>
            `).join('') || '<p class="text-slate-400 text-center py-10">Produk tidak ditemukan</p>';
            return;
        }

        els.grid.innerHTML = filtered.map((p) => `
            <button type="button" class="product-tile card p-3 text-left" data-id="${p.id}">
                ${renderProductImage(p)}
                <div class="font-semibold text-sm leading-snug">${p.name}</div>
                <div class="text-xs text-slate-500 mt-1">${p.category?.name || '-'} · ${renderProductMeta(p)}</div>
                <div class="text-brand-700 font-bold mt-2">${formatMoney(p.price)}</div>
            </button>
        `).join('') || '<p class="text-slate-400 col-span-full text-center py-10">Produk tidak ditemukan</p>';
    }

    function addProduct(product) {
        const existing = cart.find((c) => c.product_id === product.id);
        if (existing) {
            existing.qty += 1;
        } else {
            cart.push({
                product_id: product.id,
                product_name: product.name,
                product_sku: product.sku,
                price: Number(product.price),
                cost: Number(product.cost || 0),
                qty: 1,
                discount: 0,
            });
        }
        renderCart();
        focusBarcodeInput();
    }

    function findByBarcode(code) {
        return products.find((p) => String(p.barcode) === String(code) || String(p.sku) === String(code));
    }

    function setPaid(amount) {
        if (!els.paid) return;
        els.paid.value = formatRupiahInput(amount);
        renderCart();
    }

    async function checkout() {
        if (!cart.length) {
            toast('Keranjang masih kosong');
            return;
        }

        const t = totals();
        const method = (window.jQuery && els.method ? jQuery(els.method).val() : els.method?.value) || 'cash';
        if (method === 'credit') {
            if (!els.customer?.value?.trim()) {
                toast('Isi nama pelanggan untuk penjualan piutang');
                return;
            }
        } else if (t.paid < t.total) {
            toast('Jumlah bayar kurang');
            return;
        }

        const payload = {
            local_id: uid(),
            customer_name: els.customer.value || null,
            order_type: orderType,
            table_number: orderType === 'dine_in' ? (els.tableNumber?.value || null) : null,
            subtotal: t.subtotal,
            discount: t.discount,
            tax: t.tax,
            total: t.total,
            paid: method === 'credit' ? Math.min(t.paid, t.total) : t.paid,
            change: method === 'credit' ? 0 : t.change,
            payment_method: method,
            sold_at: new Date().toISOString(),
            items: cart.map((c) => ({
                ...c,
                subtotal: c.price * c.qty,
            })),
        };

        const online = navigator.onLine;
        let resultInvoice = payload.local_id;

        try {
            if (online) {
                const res = await fetch(window.POS_CONFIG.routes.transactionsStore, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': window.POS_CONFIG.csrf,
                    },
                    body: JSON.stringify(payload),
                });
                const json = await res.json();
                if (!res.ok || !json.success) throw new Error(json.message || 'Gagal menyimpan');
                resultInvoice = json.transaction.invoice_number;
                payload.invoice_number = resultInvoice;
                payload.synced = true;
            } else {
                if (!OfflineStore.isOfflineEnabled()) {
                    throw new Error('Offline. Aktifkan mode offline di Pengaturan terlebih dahulu.');
                }
                payload.invoice_number = 'OFF-' + Date.now();
                payload.synced = false;
                await OfflineStore.saveTransaction(payload);
                resultInvoice = payload.invoice_number;
                toast('Disimpan offline — akan disinkron saat online');
            }
        } catch (err) {
            if (OfflineStore.isOfflineEnabled()) {
                payload.invoice_number = 'OFF-' + Date.now();
                payload.synced = false;
                await OfflineStore.saveTransaction(payload);
                resultInvoice = payload.invoice_number;
                toast('Server gagal — transaksi disimpan offline');
            } else {
                toast(err.message || 'Gagal checkout');
                return;
            }
        }

        lastTransaction = payload;
        try {
            await refreshPrinterBeforePrint();
            const printResult = await printer.printReceipt(payload, settings);
            if (printResult?.drawerError) {
                toast(printResult.drawerError);
            }
        } catch (printErr) {
            toast(printErr.message || 'Transaksi tersimpan, tetapi cetak struk gagal.');
        }

        cart = [];
        setPaid(0);
        els.customer.value = '';
        if (els.discount) els.discount.value = '0';
        if (els.tableNumber) els.tableNumber.value = '';
        renderCart();

        const typeLabel = payload.order_type === 'takeaway' ? 'Take Away' : 'Dine In';
        els.modalMsg.textContent = `Invoice ${resultInvoice} · ${typeLabel} · Total ${formatMoney(payload.total)}`;
        els.modal.classList.remove('hidden');
        els.modal.classList.add('flex');
        focusBarcodeInput();
    }

    els.grid.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-id]');
        if (!btn) return;
        const product = products.find((p) => String(p.id) === String(btn.dataset.id));
        if (product) addProduct(product);
    });

    els.cartList.addEventListener('click', (e) => {
        const inc = e.target.closest('[data-inc]');
        const dec = e.target.closest('[data-dec]');
        const del = e.target.closest('[data-del]');
        if (inc) cart[inc.dataset.inc].qty += 1;
        if (dec) {
            const i = dec.dataset.dec;
            cart[i].qty -= 1;
            if (cart[i].qty <= 0) cart.splice(i, 1);
        }
        if (del) cart.splice(del.dataset.del, 1);
        renderCart();
    });

    document.querySelectorAll('.order-type-btn').forEach((btn) => {
        btn.addEventListener('click', () => setOrderType(btn.dataset.type));
    });

    bindRupiahInput(els.discount, renderCart);
    bindRupiahInput(els.paid, renderCart);

    if (window.jQuery && els.method) {
        jQuery(els.method).on('change', onPaymentMethodChange);
    } else {
        els.method?.addEventListener('change', onPaymentMethodChange);
    }

    function onPaymentMethodChange() {
        const val = window.jQuery ? jQuery(els.method).val() : els.method?.value;
        if (val === 'credit') {
            setPaid(0);
            if (els.customer) els.customer.placeholder = 'Nama pelanggan (wajib untuk piutang)';
        } else if (els.customer) {
            els.customer.placeholder = 'Nama pelanggan (opsional)';
        }
        renderCart();
    }

    document.getElementById('btn-pay-exact')?.addEventListener('click', () => {
        setPaid(totals().total);
    });

    document.querySelectorAll('.btn-quick-pay').forEach((btn) => {
        btn.addEventListener('click', () => setPaid(Number(btn.dataset.amount)));
    });

    els.search.addEventListener('input', renderProducts);
    if (window.jQuery && els.category) {
        jQuery(els.category).on('change', renderProducts);
    } else {
        els.category?.addEventListener('change', renderProducts);
    }
    document.getElementById('btn-clear-cart')?.addEventListener('click', () => { cart = []; renderCart(); });
    document.getElementById('btn-checkout')?.addEventListener('click', checkout);
    document.getElementById('btn-close-modal')?.addEventListener('click', () => {
        els.modal.classList.add('hidden');
        els.modal.classList.remove('flex');
        focusBarcodeInput();
    });
    document.getElementById('btn-reprint')?.addEventListener('click', async () => {
        if (!lastTransaction) return;
        try {
            await refreshPrinterBeforePrint();
            await printer.printReceipt(lastTransaction, settings, { openDrawer: false });
        } catch (err) {
            toast(err.message || 'Gagal cetak ulang');
        }
    });

    document.getElementById('btn-reconnect-printer')?.addEventListener('click', async () => {
        const btn = document.getElementById('btn-reconnect-printer');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Menyambungkan…';
        }
        try {
            await refreshPrinterBeforePrint();
            const type = settings.printer_type || 'bluetooth';

            if (type === 'usb') {
                await printer.connectWindowsUsb();
                // Verifikasi cetak ringan tidak wajib; pastikan target ada
                const target = printer.resolveUsbTargets?.() || {};
                if (!target.printerName && !target.comPort && !printer.windowsPrinter && !printer.comPort) {
                    throw new Error('Printer USB/COM belum dipilih. Ulangi Deteksi di Pengaturan.');
                }
                markPrinterLive(printer.windowsPrinter || printer.comPort || settings.printer_name || 'USB');
                toast('Printer USB siap');
            } else {
                let ok = await printer.reconnectBluetoothPersistent({ tries: 3, gapMs: 500 });
                if (!ok) {
                    await printer.connectBluetooth();
                    ok = printer.isConnected();
                }
                if (!ok) {
                    throw new Error('Gagal sambungkan Bluetooth. Pastikan printer menyala.');
                }
                OfflineStore.saveDeviceSettings({
                    printer_type: 'bluetooth',
                    printer_name: printer.btDevice?.name || settings.printer_name,
                    printer_setup_done: true,
                    bt_paired: true,
                    bt_device_id: printer.btDevice?.id || settings.bt_device_id || null,
                    bt_device_name: printer.btDevice?.name || settings.bt_device_name || settings.printer_name,
                    extra: settings.extra || {},
                });
                settings.bt_paired = true;
                settings.bt_device_id = printer.btDevice?.id || settings.bt_device_id;
                settings.bt_device_name = printer.btDevice?.name || settings.bt_device_name;
                markPrinterLive(printer.btDevice?.name || settings.printer_name || 'Bluetooth');
                toast('Bluetooth terhubung');
            }
        } catch (err) {
            markPrinterReady(settings.bt_device_name || settings.printer_name || '', { showButton: true });
            showReconnectBtn();
            toast(err.message || 'Gagal menyambungkan printer');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Sambungkan ulang';
            }
        }
    });

    const historyModal = document.getElementById('history-modal');
    const historyList = document.getElementById('history-list');

    function openHistory() {
        if (!historyModal) return;
        historyModal.classList.remove('hidden');
        historyModal.classList.add('flex');
        loadHistory();
    }

    function closeHistory() {
        if (!historyModal) return;
        historyModal.classList.add('hidden');
        historyModal.classList.remove('flex');
    }

    function historyPayload(trx) {
        return {
            ...trx,
            items: (trx.items || []).map((item) => ({
                ...item,
                subtotal: item.subtotal ?? (Number(item.qty) * Number(item.price)),
            })),
        };
    }

    function isTodayTransaction(trx) {
        if (!trx?.sold_at) return false;
        const d = new Date(trx.sold_at);
        const now = new Date();
        return d.getFullYear() === now.getFullYear()
            && d.getMonth() === now.getMonth()
            && d.getDate() === now.getDate();
    }

    async function loadHistory() {
        if (!historyList) return;
        historyList.innerHTML = '<p class="text-slate-400 text-center py-8">Memuat...</p>';
        let rows = [];

        try {
            if (navigator.onLine && window.POS_CONFIG?.routes?.transactionsRecent) {
                const res = await fetch(window.POS_CONFIG.routes.transactionsRecent, {
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': window.POS_CONFIG.csrf },
                });
                const json = await res.json();
                if (json.success) rows = json.transactions || [];
            }
        } catch (_) {}

        if (!rows.length && OfflineStore.isOfflineEnabled()) {
            try { rows = await OfflineStore.getAllTransactions(); } catch (_) {}
        }

        rows = rows.filter(isTodayTransaction);

        if (!rows.length) {
            historyList.innerHTML = '<p class="text-slate-400 text-center py-8">Belum ada transaksi hari ini.</p>';
            return;
        }

        historyList.innerHTML = rows.map((trx, idx) => {
            const when = trx.sold_at ? new Date(trx.sold_at).toLocaleString('id-ID') : '-';
            const isVoid = trx.status === 'void';
            const typeLabel = trx.order_type === 'takeaway' ? 'Take Away' : 'Dine In';
            return `
                <div class="history-row ${isVoid ? 'is-void' : ''}">
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold truncate">${trx.invoice_number || trx.local_id || '-'}</div>
                        <div class="text-xs text-slate-500">${when} · ${typeLabel}${trx.customer_name ? ' · ' + trx.customer_name : ''}</div>
                        <div class="text-xs text-slate-400">${(trx.items || []).length} item · ${(trx.payment_method || '').toUpperCase()}${isVoid ? ' · VOID' : ''}</div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="font-bold text-sm">${formatMoney(trx.total)}</div>
                        ${isVoid ? '' : `<button type="button" class="btn-icon mt-1" data-reprint="${idx}" title="Cetak ulang" aria-label="Cetak ulang">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                                <rect x="6" y="14" width="12" height="8"></rect>
                            </svg>
                        </button>`}
                    </div>
                </div>
            `;
        }).join('');

        historyList.querySelectorAll('[data-reprint]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const trx = rows[Number(btn.dataset.reprint)];
                if (!trx) return;
                try {
                    await printer.printReceipt(historyPayload(trx), settings, { openDrawer: false });
                    toast('Struk dikirim ke printer');
                } catch (err) {
                    toast(err.message || 'Gagal cetak ulang');
                }
            });
        });
    }

    document.getElementById('btn-pos-history')?.addEventListener('click', openHistory);
    document.getElementById('btn-close-history')?.addEventListener('click', closeHistory);
    historyModal?.addEventListener('click', (e) => {
        if (e.target === historyModal) closeHistory();
    });

    createBarcodeScanner({
        onScan: (code) => {
            if (settings.scanner_enabled === false) return;
            setDeviceBadge(els.scannerStatus, 'Scanner', true, 'aktif');
            const product = findByBarcode(code);
            if (product) {
                addProduct(product);
                toast(`Ditambahkan: ${product.name}`);
            } else {
                toast(`Barcode ${code} tidak ditemukan`);
            }
            if (els.barcode) els.barcode.value = '';
            focusBarcodeInput();
        },
    });

    els.barcode?.addEventListener('focus', () => {
        if (settings.scanner_enabled === false) return;
        setDeviceBadge(els.scannerStatus, 'Scanner', true, 'siap');
    });
    els.barcode?.addEventListener('blur', () => {
        if (settings.scanner_enabled === false) return;
        const active = document.activeElement;
        if (active?.matches?.('input:not([data-no-keyboard]), textarea, select')) return;
        if (active?.closest?.('#checkout-modal, #history-modal, button, a, select')) return;
        setTimeout(() => focusBarcodeInput(), 80);
    });

    // Selalu cache katalog/settings perangkat (printer+scanner) ke lokal
    OfflineStore.saveCatalog({ products, categories, settings }).catch(() => {});
    OfflineStore.getDeviceSettings().then((local) => {
        if (!local) return;
        settings = OfflineStore.mergeSettings(settings, local);
        printer.setSettings(settings);
        if (settings.scanner_enabled !== false) {
            setDeviceBadge(els.scannerStatus, 'Scanner', true, 'siap');
        }
    }).catch(() => {});

    if (!navigator.onLine) {
        OfflineStore.getProducts().then((p) => {
            if (p?.length) {
                products = p;
                renderProducts();
            }
        });
        OfflineStore.getSettings().then((s) => {
            if (s) {
                settings = OfflineStore.mergeSettings(s);
                printer.setSettings(settings);
                els.taxLabel.textContent = settings.tax_percent || 0;
            }
        });
        els.offlineBadge?.classList.remove('hidden');
    } else if (!OfflineStore.isOfflineEnabled()) {
        // tetap sembunyikan badge offline mode penuh
    } else {
        els.offlineBadge?.classList.remove('hidden');
    }

    setOrderType('dine_in');
    setProductViewMode(productViewMode);
    els.btnViewGrid?.addEventListener('click', () => setProductViewMode('grid'));
    els.btnViewList?.addEventListener('click', () => setProductViewMode('list'));
    renderCart();

    (async function autoConnectPrinter() {
        let type = settings.printer_type || window.POS_CONFIG?.printer?.printer_type || null;
        if (type === 'auto') {
            type = settings.extra?.windows_printer || settings.extra?.com_port
                ? 'usb'
                : 'bluetooth';
            syncPrinterSettings({ printer_type: type });
        }

        if (!type || type === 'none') {
            setDeviceBadge(els.printerStatus, 'Printer', false);
            if (els.printerStatus) {
                els.printerStatus.textContent = type === 'none' ? 'Printer: nonaktif' : 'Printer: belum disetel';
            }
            hideReconnectBtn();
            return;
        }

        // Langsung tampilkan siap (tersimpan) — jangan minta sambung ulang
        markPrinterReady(settings.bt_device_name || settings.printer_name || '', { showButton: false });

        if (els.printerStatus && type === 'bluetooth') {
            els.printerStatus.textContent = `Printer: menyambungkan… (${settings.bt_device_name || settings.printer_name || 'Bluetooth'})`;
            els.printerStatus.className = 'device-badge inline-flex items-center px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 font-medium';
        }
        hideReconnectBtn();

        try {
            await refreshPrinterBeforePrint();
            if (type === 'usb') {
                await printer.connectWindowsUsb();
                markPrinterLive(printer.windowsPrinter || printer.comPort || settings.printer_name || 'USB');
            } else {
                const ok = await printer.reconnectBluetoothPersistent({ tries: 8, gapMs: 500 });
                if (ok && printer.isConnected()) {
                    markPrinterLive(printer.btDevice?.name || settings.printer_name || 'Bluetooth');
                } else {
                    // Tetap "siap" — tombol hanya jika user butuh fallback manual
                    markPrinterReady(settings.bt_device_name || settings.printer_name || 'Bluetooth', { showButton: true });
                }
            }
        } catch (_) {
            markPrinterReady(settings.bt_device_name || settings.printer_name || '', { showButton: true });
        }

        if (type === 'bluetooth') {
            setInterval(() => {
                if (printer.isConnected()) {
                    markPrinterLive();
                    return;
                }
                printer.reconnectBluetoothPersistent({ tries: 3, gapMs: 400 }).then((ok) => {
                    if (ok) markPrinterLive();
                    // jangan show button tiap interval gagal — biarkan status "siap"
                    else markPrinterReady(settings.bt_device_name || settings.printer_name || '', { showButton: false });
                }).catch(() => {});
            }, 8000);
        }
    })();

    focusBarcodeInput();

    async function onPosVisible() {
        if (!isPrinterConfigured()) return;
        if (printer.isConnected()) {
            markPrinterLive();
            return;
        }

        // Saat kembali ke Kasir: reconnect diam-diam, jangan paksa klik
        markPrinterReady(settings.bt_device_name || settings.printer_name || '', { showButton: false });
        if (els.printerStatus && settings.printer_type === 'bluetooth') {
            els.printerStatus.textContent = `Printer: menyambungkan… (${settings.bt_device_name || settings.printer_name || 'Bluetooth'})`;
            els.printerStatus.className = 'device-badge inline-flex items-center px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 font-medium';
        }

        try {
            await refreshPrinterBeforePrint();
            if (settings.printer_type === 'usb') {
                await printer.connectWindowsUsb();
                markPrinterLive(printer.windowsPrinter || printer.comPort || settings.printer_name || 'USB');
            } else {
                const ok = await printer.reconnectBluetoothPersistent({ tries: 8, gapMs: 450 });
                if (ok) markPrinterLive();
                else {
                    markPrinterReady(settings.bt_device_name || settings.printer_name || '', { showButton: true });
                }
            }
        } catch (_) {
            markPrinterReady(settings.bt_device_name || settings.printer_name || '', { showButton: true });
        }
    }

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') onPosVisible();
    });
    window.addEventListener('pageshow', () => onPosVisible());
}

export default initPos;
