import printer from './printer';

export function formatMoneyId(n) {
    return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
}

export function paymentMethodLabel(method = '') {
    const m = String(method || '').toLowerCase();
    if (m === 'cash') return 'Tunai';
    if (m === 'qris') return 'QRIS';
    if (m === 'transfer') return 'Transfer';
    if (m === 'card') return 'Kartu';
    if (m === 'credit') return 'Piutang';
    if (m === 'other') return 'Lainnya';
    return method || '-';
}

export function historyPayload(trx) {
    return {
        ...trx,
        items: (trx.items || []).map((item) => ({
            ...item,
            subtotal: item.subtotal ?? (Number(item.qty) * Number(item.price)),
        })),
    };
}

export function buildWhatsAppText(trx, settings = {}) {
    const store = settings.store_name || settings.receipt_header || 'Toko';
    const fmt = formatMoneyId;
    const lines = [];
    lines.push(`*${store}*`);
    lines.push(`No: ${trx.invoice_number || trx.local_id || '-'}`);
    lines.push(`Tgl: ${trx.sold_at ? new Date(trx.sold_at).toLocaleString('id-ID') : '-'}`);
    lines.push(`Tipe: ${trx.order_type === 'takeaway' ? 'Take Away' : 'Dine In'}`);
    if (trx.customer_name) lines.push(`Pelanggan: ${trx.customer_name}`);
    if (trx.table_number) lines.push(`Meja: ${trx.table_number}`);
    lines.push('');
    (trx.items || []).forEach((item) => {
        const sub = item.subtotal ?? (Number(item.qty) * Number(item.price));
        lines.push(`${item.product_name || '-'} — ${item.qty}x ${fmt(item.price)} = ${fmt(sub)}`);
    });
    lines.push('');
    if (Number(trx.discount) > 0) lines.push(`Diskon: ${fmt(trx.discount)}`);
    if (Number(trx.tax) > 0) lines.push(`Pajak: ${fmt(trx.tax)}`);
    lines.push(`*Total: ${fmt(trx.total)}*`);
    lines.push(`Bayar (${paymentMethodLabel(trx.payment_method)}): ${fmt(trx.paid)}`);
    lines.push(`Kembali: ${fmt(trx.change)}`);
    if (settings.receipt_footer) {
        lines.push('');
        lines.push(String(settings.receipt_footer).split(/\r\n|\n|\r/).filter(Boolean).join('\n'));
    }
    return lines.join('\n');
}

export function shareWhatsApp(trx, settings = {}) {
    const text = encodeURIComponent(buildWhatsAppText(trx, settings));
    window.open(`https://wa.me/?text=${text}`, '_blank', 'noopener,noreferrer');
}

export function renderTransactionDetail(trx, settings = {}) {
    const body = document.getElementById('history-detail-body');
    const title = document.getElementById('history-detail-title');
    const subtitle = document.getElementById('history-detail-subtitle');
    if (!body || !trx) return;

    const fmt = formatMoneyId;
    const when = trx.sold_at ? new Date(trx.sold_at).toLocaleString('id-ID') : '-';
    const typeLabel = trx.order_type === 'takeaway' ? 'Take Away' : 'Dine In';
    const isVoid = trx.status === 'void';

    if (title) title.textContent = trx.invoice_number || trx.local_id || 'Struk';
    if (subtitle) subtitle.textContent = `${when} · ${typeLabel}${isVoid ? ' · VOID' : ''}`;

    const itemsHtml = (trx.items || []).map((item) => {
        const sub = item.subtotal ?? (Number(item.qty) * Number(item.price));
        return `
            <tr>
                <td>${item.product_name || '-'}</td>
                <td class="text-center whitespace-nowrap">${item.qty}</td>
                <td class="text-right whitespace-nowrap">${fmt(item.price)}</td>
                <td class="text-right whitespace-nowrap font-medium">${fmt(sub)}</td>
            </tr>
        `;
    }).join('');

    body.innerHTML = `
        <div class="space-y-3 ${isVoid ? 'opacity-60' : ''}">
            ${trx.customer_name ? `<div><span class="text-slate-500">Pelanggan:</span> <span class="font-medium">${trx.customer_name}</span></div>` : ''}
            ${trx.table_number ? `<div><span class="text-slate-500">Meja:</span> <span class="font-medium">${trx.table_number}</span></div>` : ''}
            <div><span class="text-slate-500">Pembayaran:</span> <span class="font-medium">${paymentMethodLabel(trx.payment_method)}</span></div>
            ${isVoid && trx.void_reason ? `<div class="text-red-700 bg-red-50 border border-red-100 rounded-lg p-2 text-xs"><span class="font-medium">Alasan void:</span> ${trx.void_reason}</div>` : ''}
            <table class="history-detail-items">
                <thead>
                    <tr>
                        <th class="text-left">Produk</th>
                        <th>Qty</th>
                        <th class="text-right">Harga</th>
                        <th class="text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>${itemsHtml || '<tr><td colspan="4" class="text-slate-400 py-4 text-center">Tidak ada item</td></tr>'}</tbody>
            </table>
            <div class="history-detail-summary">
                <div class="history-detail-summary-row"><span>Subtotal</span><span>${fmt(trx.subtotal)}</span></div>
                ${Number(trx.discount) > 0 ? `<div class="history-detail-summary-row"><span>Diskon</span><span>${fmt(trx.discount)}</span></div>` : ''}
                ${Number(trx.tax) > 0 ? `<div class="history-detail-summary-row"><span>Pajak</span><span>${fmt(trx.tax)}</span></div>` : ''}
                <div class="history-detail-summary-row is-total"><span>Total</span><span>${fmt(trx.total)}</span></div>
                <div class="history-detail-summary-row"><span>Bayar</span><span>${fmt(trx.paid)}</span></div>
                <div class="history-detail-summary-row"><span>Kembali</span><span>${fmt(trx.change)}</span></div>
            </div>
        </div>
    `;

    const printBtn = document.getElementById('btn-history-detail-print');
    const waBtn = document.getElementById('btn-history-detail-wa');
    const voidBtn = document.getElementById('btn-history-detail-void');
    const voidPanel = document.getElementById('history-detail-void-panel');
    const canVoid = window.POS_CONFIG?.canVoid === true;

    if (printBtn) {
        printBtn.disabled = isVoid;
        printBtn.classList.toggle('opacity-50', isVoid);
    }
    if (waBtn) waBtn.disabled = false;
    if (voidBtn) voidBtn.classList.toggle('hidden', !canVoid || isVoid);
    voidPanel?.classList.add('hidden');
    document.getElementById('history-detail-void-reason') && (document.getElementById('history-detail-void-reason').value = '');
    document.getElementById('history-detail-void-password') && (document.getElementById('history-detail-void-password').value = '');
}

function hideVoidPanel() {
    document.getElementById('history-detail-void-panel')?.classList.add('hidden');
}

export function bindTransactionDetailModal({ settings = {}, onReprint, onVoidSuccess, toast, showBackButton = false } = {}) {
    const modal = document.getElementById('history-detail-modal');
    let currentTrx = null;

    function open(trx) {
        if (!modal || !trx) return;
        currentTrx = trx;
        renderTransactionDetail(trx, settings);
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function close() {
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        hideVoidPanel();
        currentTrx = null;
    }

    const notify = toast || ((msg) => window.PosApp?.toast?.(msg));

    document.getElementById('btn-close-history-detail')?.addEventListener('click', close);
    if (showBackButton) {
        document.getElementById('btn-history-detail-back')?.addEventListener('click', close);
    }
    modal?.addEventListener('click', (e) => {
        if (e.target === modal) close();
    });

    document.getElementById('btn-history-detail-print')?.addEventListener('click', async () => {
        if (!currentTrx || currentTrx.status === 'void' || !onReprint) return;
        try {
            await onReprint(historyPayload(currentTrx));
            notify?.('Struk dikirim ke printer');
        } catch (err) {
            notify?.(err.message || 'Gagal cetak ulang');
        }
    });

    document.getElementById('btn-history-detail-wa')?.addEventListener('click', () => {
        if (!currentTrx) return;
        shareWhatsApp(currentTrx, settings);
    });

    document.getElementById('btn-history-detail-void')?.addEventListener('click', () => {
        if (!currentTrx || currentTrx.status === 'void') return;
        document.getElementById('history-detail-void-panel')?.classList.remove('hidden');
        document.getElementById('history-detail-void-reason')?.focus();
    });

    document.getElementById('btn-history-detail-void-cancel')?.addEventListener('click', hideVoidPanel);

    document.getElementById('btn-history-detail-void-submit')?.addEventListener('click', async () => {
        if (!currentTrx || currentTrx.status === 'void') return;

        const url = window.POS_CONFIG?.routes?.transactionsVoidStore;
        if (!url) {
            notify?.('Fitur batalkan tidak tersedia');
            return;
        }

        const reason = document.getElementById('history-detail-void-reason')?.value?.trim() || '';
        const ownerPassword = document.getElementById('history-detail-void-password')?.value || '';

        if (!reason) {
            notify?.('Alasan pembatalan wajib diisi');
            return;
        }
        if (!ownerPassword) {
            notify?.('Password pimpinan toko wajib diisi');
            return;
        }

        const submitBtn = document.getElementById('btn-history-detail-void-submit');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Memproses...';
        }

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': window.POS_CONFIG?.csrf || '',
                },
                body: JSON.stringify({
                    invoice_number: currentTrx.invoice_number,
                    reason,
                    owner_password: ownerPassword,
                }),
            });

            const json = await res.json().catch(() => ({}));
            if (!res.ok || !json.success) {
                throw new Error(json.message || 'Gagal membatalkan transaksi');
            }

            const updated = json.transaction || { ...currentTrx, status: 'void', void_reason: reason };
            currentTrx = updated;
            renderTransactionDetail(updated, settings);
            hideVoidPanel();
            notify?.(json.message || 'Transaksi dibatalkan');
            onVoidSuccess?.(updated);
        } catch (err) {
            notify?.(err.message || 'Gagal membatalkan transaksi');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Konfirmasi batalkan';
            }
        }
    });

    return { open, close, getCurrent: () => currentTrx };
}

function mergePrinterSettings(base = {}) {
    const cfg = window.POS_CONFIG?.printer || {};
    return {
        ...base,
        printer_type: cfg.printer_type || base.printer_type,
        printer_name: cfg.printer_name || base.printer_name,
        paper_width: cfg.paper_width || base.paper_width,
        extra: { ...(base.extra || {}), ...(cfg.extra || {}) },
    };
}

export function initTransactionsPage() {
    const root = document.getElementById('transactions-app');
    if (!root) return;

    let settings = {};
    try {
        settings = JSON.parse(root.dataset.settings || '{}') || {};
    } catch (_) {
        settings = {};
    }
    settings = mergePrinterSettings(settings);

    const detail = bindTransactionDetailModal({
        settings,
        showBackButton: false,
        toast: (msg) => window.PosApp?.toast?.(msg),
        onReprint: async (trx) => {
            printer.setSettings(settings);
            await printer.printReceipt(trx, settings, { openDrawer: false });
        },
    });

    root.querySelectorAll('[data-transaction-show]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const url = btn.dataset.transactionShow;
            if (!url) return;
            btn.disabled = true;
            try {
                const res = await fetch(url, {
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': window.POS_CONFIG?.csrf || '',
                    },
                });
                if (!res.ok) throw new Error('Gagal memuat detail transaksi');
                const trx = await res.json();
                detail.open(trx);
            } catch (err) {
                window.PosApp?.toast?.(err.message || 'Gagal memuat detail');
            } finally {
                btn.disabled = false;
            }
        });
    });
}
