@extends('layouts.app')

@section('title', 'Pembelian Baru')
@section('heading', 'Pembelian / Restock')
@section('subheading', 'Tambah stok dari pembelian supplier')

@section('content')
@if($products->isEmpty())
<div class="card p-8 text-center">
    <p class="text-slate-500 mb-4">Tidak ada produk ber-stok. Aktifkan track stok pada produk dulu.</p>
    <a href="{{ route('products.index') }}" class="btn btn-primary">Kelola produk</a>
</div>
@else
<form method="POST" action="{{ route('purchases.store') }}" id="purchase-form">
    @csrf
    <div class="card p-4 sm:p-5 mb-4 grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div>
            <label class="text-xs text-slate-500">Supplier</label>
            <input name="supplier_name" class="input mt-1" placeholder="Nama supplier" value="{{ old('supplier_name') }}">
        </div>
        <div>
            <label class="text-xs text-slate-500">Tanggal beli</label>
            <input type="date" name="purchased_at" class="input mt-1" value="{{ old('purchased_at', now()->toDateString()) }}" required>
        </div>
        <div>
            <label class="text-xs text-slate-500">Metode bayar</label>
            <select name="payment_method" id="payment_method" class="input mt-1" required data-placeholder="Pilih metode">
                <option value="cash">Tunai</option>
                <option value="transfer">Transfer</option>
                <option value="qris">QRIS</option>
                <option value="credit">Kredit / Hutang</option>
                <option value="other">Lainnya</option>
            </select>
        </div>
        <div>
            <label class="text-xs text-slate-500">Jumlah dibayar</label>
            <input type="number" min="0" step="1" name="paid" id="paid" class="input mt-1" value="{{ old('paid', 0) }}">
            <p class="text-xs text-slate-400 mt-1">Kosongkan / 0 + metode kredit = hutang penuh</p>
        </div>
        <div>
            <label class="text-xs text-slate-500">Diskon</label>
            <input type="number" min="0" step="1" name="discount" id="discount" class="input mt-1" value="{{ old('discount', 0) }}">
        </div>
        <div>
            <label class="text-xs text-slate-500">Pajak</label>
            <input type="number" min="0" step="1" name="tax" id="tax" class="input mt-1" value="{{ old('tax', 0) }}">
        </div>
        <div class="sm:col-span-2">
            <label class="text-xs text-slate-500">Catatan</label>
            <input name="notes" class="input mt-1" value="{{ old('notes') }}" placeholder="Opsional">
        </div>
        <label class="flex items-center gap-2 text-sm sm:col-span-2">
            <input type="checkbox" name="update_product_cost" value="1" checked>
            Update HPP produk sesuai harga beli
        </label>
    </div>

    <div class="card p-4 sm:p-5 mb-4">
        <div class="flex items-center justify-between mb-3">
            <h2 class="font-bold">Item pembelian</h2>
            <button type="button" id="btn-add-row" class="btn btn-secondary text-sm">+ Item</button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[700px]">
                <thead>
                    <tr class="text-left text-slate-500 border-b">
                        <th class="py-2 pr-2">Produk</th>
                        <th class="py-2 pr-2 w-28">Qty</th>
                        <th class="py-2 pr-2 w-36">HPP beli</th>
                        <th class="py-2 pr-2 text-right">Subtotal</th>
                        <th class="py-2 w-16"></th>
                    </tr>
                </thead>
                <tbody id="items-body"></tbody>
            </table>
        </div>
        <div class="mt-4 flex justify-end text-sm">
            <div class="space-y-1 text-right">
                <div>Subtotal: <strong id="sum-subtotal">Rp 0</strong></div>
                <div>Total: <strong id="sum-total" class="text-lg">Rp 0</strong></div>
            </div>
        </div>
    </div>

    <div class="flex flex-col-reverse sm:flex-row gap-2">
        <a href="{{ route('purchases.index') }}" class="btn btn-cancel flex-1">Batal</a>
        <button class="btn btn-primary flex-1">Simpan & restock</button>
    </div>
</form>
@endif
@endsection

@push('scripts')
<script>
(function () {
    const products = @json($products);
    const body = document.getElementById('items-body');
    if (!body) return;
    let idx = 0;

    function money(n) {
        return 'Rp ' + Math.round(n || 0).toLocaleString('id-ID');
    }

    function productOptions(selected) {
        return products.map(p =>
            `<option value="${p.id}" data-cost="${p.cost}" data-stock="${p.stock}" ${String(p.id)===String(selected)?'selected':''}>${p.name} (stok ${p.stock})</option>`
        ).join('');
    }

    function recalc() {
        let sub = 0;
        body.querySelectorAll('tr').forEach(tr => {
            const qty = Number(tr.querySelector('.qty')?.value || 0);
            const cost = Number(tr.querySelector('.cost')?.value || 0);
            const line = qty * cost;
            sub += line;
            const el = tr.querySelector('.line-sub');
            if (el) el.textContent = money(line);
        });
        const discount = Number(document.getElementById('discount')?.value || 0);
        const tax = Number(document.getElementById('tax')?.value || 0);
        const total = Math.max(0, sub - discount + tax);
        document.getElementById('sum-subtotal').textContent = money(sub);
        document.getElementById('sum-total').textContent = money(total);

        const methodEl = document.getElementById('payment_method');
        const method = window.jQuery && methodEl ? jQuery(methodEl).val() : methodEl?.value;
        const paid = document.getElementById('paid');
        if (paid && method !== 'credit' && (!paid.dataset.touched || paid.value === '' || paid.value === '0')) {
            paid.value = Math.round(total);
        }
    }

    function addRow(productId) {
        const i = idx++;
        const tr = document.createElement('tr');
        tr.className = 'border-b border-slate-100';
        tr.innerHTML = `
            <td class="py-2 pr-2">
                <select name="items[${i}][product_id]" class="input product-select py-1.5" data-search="true" data-placeholder="Pilih produk" required>${productOptions(productId || products[0]?.id)}</select>
            </td>
            <td class="py-2 pr-2"><input type="number" min="1" name="items[${i}][qty]" class="input qty py-1.5" value="1" required></td>
            <td class="py-2 pr-2"><input type="number" min="0" step="1" name="items[${i}][cost]" class="input cost py-1.5" value="0" required></td>
            <td class="py-2 pr-2 text-right line-sub font-semibold">Rp 0</td>
            <td class="py-2"><button type="button" class="btn btn-ghost text-xs btn-remove">Hapus</button></td>
        `;
        body.appendChild(tr);
        const select = tr.querySelector('.product-select');
        const costInput = tr.querySelector('.cost');
        const syncCost = () => {
            const opt = select.options[select.selectedIndex];
            if (opt) costInput.value = Math.round(Number(opt.dataset.cost || 0));
            recalc();
        };
        if (window.reinitSelect2) {
            window.reinitSelect2(select);
            jQuery(select).on('change', syncCost);
        } else {
            select.addEventListener('change', syncCost);
        }
        tr.querySelector('.qty').addEventListener('input', recalc);
        costInput.addEventListener('input', recalc);
        tr.querySelector('.btn-remove').addEventListener('click', () => {
            if (window.jQuery && jQuery(select).hasClass('select2-hidden-accessible')) {
                jQuery(select).select2('destroy');
            }
            tr.remove();
            recalc();
        });
        syncCost();
    }

    document.getElementById('btn-add-row')?.addEventListener('click', () => addRow());
    document.getElementById('discount')?.addEventListener('input', recalc);
    document.getElementById('tax')?.addEventListener('input', recalc);
    document.getElementById('paid')?.addEventListener('input', function () { this.dataset.touched = '1'; });
    const paymentMethod = document.getElementById('payment_method');
    const onPaymentChange = function () {
        const paid = document.getElementById('paid');
        const val = window.jQuery && paymentMethod ? jQuery(paymentMethod).val() : paymentMethod?.value;
        if (val === 'credit' && paid) {
            paid.value = 0;
            paid.dataset.touched = '1';
        } else if (paid) {
            paid.dataset.touched = '';
        }
        recalc();
    };
    if (window.jQuery && paymentMethod) {
        jQuery(paymentMethod).on('change', onPaymentChange);
    } else {
        paymentMethod?.addEventListener('change', onPaymentChange);
    }

    addRow();
})();
</script>
@endpush
