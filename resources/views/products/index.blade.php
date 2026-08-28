@extends('layouts.app')

@section('title', 'Produk')
@section('heading', 'Produk')
@section('subheading', 'Kelola produk, HPP, harga jual & foto')

@push('head')
<style>
    .product-modal-grid {
        display: grid;
        gap: 0.85rem;
    }
    @media (min-width: 640px) {
        .product-modal-grid.two-col {
            grid-template-columns: 1fr 1fr;
        }
    }
</style>
@endpush

@section('content')
<div class="space-y-4">
    <div class="card p-4 sm:p-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between mb-4">
            <div>
                <h2 class="font-bold text-lg">Daftar produk</h2>
                <p class="text-xs text-slate-500">{{ $products->total() }} produk terdaftar</p>
            </div>
            <div class="flex flex-col sm:flex-row gap-2 w-full lg:w-auto">
                <form method="GET" class="flex gap-2 flex-1 lg:flex-initial">
                    <input type="text" name="q" value="{{ $q }}" class="input min-w-0 flex-1 sm:w-56" placeholder="Cari produk / barcode">
                    <button class="btn btn-secondary shrink-0">Cari</button>
                </form>
                <div class="flex gap-2">
                    <button type="button" id="btn-open-category-modal" class="btn btn-ghost flex-1 sm:flex-initial whitespace-nowrap">+ Kategori</button>
                    <button type="button" id="btn-open-product-modal" class="btn btn-primary flex-1 sm:flex-initial whitespace-nowrap">+ Produk</button>
                </div>
            </div>
        </div>

        @if($categories->isNotEmpty())
            <div class="flex flex-wrap gap-2 mb-4">
                @foreach($categories as $cat)
                    <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 text-slate-700 text-xs px-3 py-1.5">
                        {{ $cat->name }}
                        @if(auth()->user()->isStoreOwner())
                            <form method="POST" action="{{ route('categories.destroy', $cat) }}" class="inline" onsubmit="return confirm('Hapus kategori {{ $cat->name }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-700 font-bold" title="Hapus">×</button>
                            </form>
                        @endif
                    </span>
                @endforeach
            </div>
        @endif

        <div class="overflow-x-auto -mx-4 sm:mx-0">
            <table class="w-full text-sm min-w-[640px]">
                <thead>
                    <tr class="text-left text-slate-500 border-b">
                        <th class="py-2 px-4 sm:px-2">Produk</th>
                        <th class="py-2 pr-2 hidden md:table-cell">HPP</th>
                        <th class="py-2 pr-2">Harga jual</th>
                        <th class="py-2 pr-2">Stok / Exp</th>
                        <th class="py-2 pr-4 sm:pr-2">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        <tr class="border-b border-slate-100 align-top">
                            <td class="py-3 px-4 sm:px-2">
                                <div class="flex items-center gap-3">
                                    @if($product->image_url)
                                        <img src="{{ $product->image_url }}" alt="" class="h-12 w-12 rounded-lg object-cover border border-slate-200 shrink-0">
                                    @else
                                        <div class="h-12 w-12 rounded-lg bg-slate-100 grid place-items-center text-slate-400 text-[10px] shrink-0">No foto</div>
                                    @endif
                                    <div class="min-w-0">
                                        <div class="font-semibold truncate">{{ $product->name }}
                                            @if($product->stock_locked)
                                                <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-700">🔒</span>
                                            @endif
                                        </div>
                                        <div class="text-xs text-slate-500 truncate">{{ $product->category?->name ?? 'Tanpa kategori' }} · {{ $product->barcode ?: $product->sku ?: '-' }}</div>
                                        <div class="text-xs text-slate-400 md:hidden mt-0.5">HPP Rp {{ number_format($product->cost, 0, ',', '.') }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 pr-2 hidden md:table-cell">Rp {{ number_format($product->cost, 0, ',', '.') }}</td>
                            <td class="py-3 pr-2 font-semibold whitespace-nowrap">Rp {{ number_format($product->price, 0, ',', '.') }}</td>
                            <td class="py-3 pr-2 text-xs">
                                @if($product->track_stock)
                                    <div>Stok: <strong>{{ $product->stock }}</strong></div>
                                @else
                                    <div class="text-slate-400">Tanpa stok</div>
                                @endif
                                @if($product->has_expiry && $product->expired_at)
                                    <div class="{{ $product->expired_at->isPast() ? 'text-red-600' : 'text-slate-600' }}">
                                        Exp: {{ $product->expired_at->format('d/m/Y') }}
                                    </div>
                                @endif
                            </td>
                            <td class="py-3 pr-4 sm:pr-2">
                                <button type="button"
                                    class="btn-edit-product text-brand-700 font-medium text-sm"
                                    data-id="{{ $product->id }}"
                                    data-name="{{ $product->name }}"
                                    data-category="{{ $product->category_id }}"
                                    data-barcode="{{ $product->barcode }}"
                                    data-sku="{{ $product->sku }}"
                                    data-cost="{{ (int) $product->cost }}"
                                    data-price="{{ (int) $product->price }}"
                                    data-track-stock="{{ $product->track_stock ? 1 : 0 }}"
                                    data-stock="{{ $product->stock }}"
                                    data-has-expiry="{{ $product->has_expiry ? 1 : 0 }}"
                                    data-expired-at="{{ optional($product->expired_at)->format('Y-m-d') }}"
                                    data-is-active="{{ $product->is_active ? 1 : 0 }}"
                                    data-stock-locked="{{ $product->stock_locked ? 1 : 0 }}"
                                    data-image="{{ $product->image_url }}"
                                    data-action="{{ route('products.update', $product) }}"
                                    data-lock-action="{{ route('products.toggle-lock', $product) }}"
                                    data-delete-action="{{ route('products.destroy', $product) }}"
                                >Edit</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center text-slate-500 px-4">
                                Belum ada produk. Klik <strong>+ Produk</strong> untuk menambah.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4 px-1">{{ $products->withQueryString()->links() }}</div>
    </div>
</div>

{{-- Modal Tambah / Edit Produk --}}
<div id="product-modal" class="modal-backdrop {{ $errors->any() && in_array(old('_form'), ['create', 'update'], true) ? 'open' : '' }}">
    <div class="modal-panel p-4 sm:p-6 w-full max-w-2xl">
        <div class="flex items-start justify-between gap-3 mb-4">
            <div>
                <h3 id="product-modal-title" class="text-lg font-bold">Tambah produk</h3>
                <p class="text-xs text-slate-500">Isi HPP, harga jual, stok, expired, dan foto</p>
            </div>
            <button type="button" class="btn-close-product-modal btn btn-ghost px-3 py-1 text-sm shrink-0">✕</button>
        </div>

        @if($errors->any() && in_array(old('_form'), ['create', 'update'], true))
            <div class="mb-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm px-3 py-2">{{ $errors->first() }}</div>
        @endif

        <form id="product-form" method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data" class="product-form space-y-3">
            @csrf
            <div id="product-method-field"></div>
            <input type="hidden" name="_form" id="product-form-type" value="create">

            <div class="product-modal-grid two-col">
                <div class="sm:col-span-2">
                    <label class="text-xs font-medium text-slate-600">Barcode <span class="text-red-500">*</span></label>
                    <div class="flex gap-2 mt-1">
                        <input name="barcode" id="product-barcode" class="input flex-1 min-w-0" placeholder="Scan barcode produk" required maxlength="100" inputmode="none" autocomplete="off" data-no-keyboard readonly>
                        <button type="button" id="btn-scan-barcode-camera" class="btn btn-secondary shrink-0 whitespace-nowrap px-3" title="Scan dengan kamera">
                            <span class="hidden sm:inline">Scan kamera</span>
                            <span class="sm:hidden">📷</span>
                        </button>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Wajib diisi. Pakai scanner USB/Bluetooth atau tombol <strong>Scan kamera</strong>.</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="text-xs font-medium text-slate-600">Nama produk</label>
                    <input name="name" id="product-name" type="text" class="input mt-1" placeholder="Contoh: Teh Botol" required inputmode="text">
                </div>
                <div class="sm:col-span-2">
                    <label class="text-xs font-medium text-slate-600">Kategori</label>
                    <select name="category_id" id="product-category" class="mt-1"
                            data-placeholder="Pilih atau cari kategori"
                            data-allow-clear="true"
                            data-search="true"
                            data-dropdown-parent="#product-modal .modal-panel">
                        <option value=""></option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="text-xs font-medium text-slate-600">SKU</label>
                    <input name="sku" id="product-sku" type="text" class="input mt-1" placeholder="Opsional" inputmode="text">
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-600">HPP</label>
                    <input name="cost" id="product-cost" type="number" min="0" step="1" class="input mt-1" placeholder="0" value="0">
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-600">Harga jual</label>
                    <input name="price" id="product-price" type="number" min="0" step="1" class="input mt-1" placeholder="0" required>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 p-3 space-y-3">
                <label class="flex items-center gap-2 text-sm font-medium">
                    <input type="checkbox" name="track_stock" id="product-track-stock" value="1" class="toggle-stock" checked>
                    Ada stok
                </label>
                <div class="stock-field">
                    <input name="stock" id="product-stock" type="number" min="0" class="input" placeholder="Jumlah stok" value="0">
                </div>

                <label class="flex items-center gap-2 text-sm font-medium">
                    <input type="checkbox" name="has_expiry" id="product-has-expiry" value="1" class="toggle-expiry">
                    Ada expired
                </label>
                <div class="expiry-field hidden">
                    <input name="expired_at" id="product-expired-at" type="date" class="input">
                </div>
            </div>

            <div>
                <label class="text-xs font-medium text-slate-600">Foto produk</label>
                <input type="file" name="image" id="product-image" accept="image/*" class="input mt-1">
                <div id="product-current-image" class="hidden mt-2 text-xs text-slate-500">
                    <img id="product-image-preview" src="" alt="" class="h-16 w-16 rounded-lg object-cover border mb-1">
                    <label class="flex items-center gap-2"><input type="checkbox" name="remove_image" id="product-remove-image" value="1"> Hapus foto saat ini</label>
                </div>
            </div>

            <div id="product-extra-flags" class="hidden space-y-2">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" id="product-is-active" value="1" checked> Aktif</label>
                @if($canLockStock && auth()->user()->isStoreOwner())
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="stock_locked" id="product-stock-locked" value="1"> Kunci stok produk</label>
                @endif
            </div>

            <div class="flex flex-col-reverse sm:flex-row gap-2 pt-2">
                <button type="button" class="btn-close-product-modal btn btn-cancel flex-1">Batal</button>
                <div id="product-edit-actions" class="hidden flex-1 gap-2">
                    @if(auth()->user()->isStoreOwner())
                        <button type="button" id="btn-delete-product" class="btn btn-danger flex-1">Hapus</button>
                    @endif
                </div>
                <button type="submit" id="product-submit-btn" class="btn btn-primary flex-1">Simpan produk</button>
            </div>
        </form>

        <form id="product-delete-form" method="POST" class="hidden">@csrf @method('DELETE')</form>
    </div>
</div>

{{-- Modal scan barcode kamera --}}
<div id="camera-barcode-modal" class="modal-backdrop" aria-hidden="true">
    <div class="modal-panel p-4 sm:p-5 w-full max-w-md">
        <div class="flex items-start justify-between gap-3 mb-3">
            <div>
                <h3 class="text-lg font-bold">Scan barcode</h3>
                <p class="text-xs text-slate-500">Arahkan barcode produk ke dalam kotak putih</p>
            </div>
            <button type="button" id="btn-close-camera-barcode" class="btn btn-ghost px-3 py-1 text-sm shrink-0">✕</button>
        </div>
        <div class="rounded-xl overflow-hidden bg-black aspect-[4/3] relative">
            <video id="camera-barcode-video" class="w-full h-full object-cover" playsinline muted autoplay webkit-playsinline></video>
            <div class="pointer-events-none absolute inset-x-4 top-1/2 -translate-y-1/2 h-[38%] border-2 border-white/80 rounded-lg shadow-[0_0_0_9999px_rgba(0,0,0,0.35)]"></div>
        </div>
        <p id="camera-barcode-status" class="text-xs text-slate-500 mt-3 text-center min-h-[1.25rem]">Menyiapkan kamera…</p>
        <p class="text-[11px] text-slate-400 text-center mt-1">Tips: dekatkan barcode, hindari silau, tahan stabil 1–2 detik</p>
        <button type="button" id="btn-close-camera-barcode-bottom" class="btn btn-cancel w-full mt-3">Batal</button>
    </div>
</div>

{{-- Modal Tambah Kategori --}}
<div id="category-modal" class="modal-backdrop {{ $errors->any() && old('_form') === 'category' ? 'open' : '' }}">
    <div class="modal-panel p-4 sm:p-6 w-full max-w-md">
        <div class="flex items-start justify-between gap-3 mb-4">
            <div>
                <h3 class="text-lg font-bold">Tambah kategori</h3>
                <p class="text-xs text-slate-500">Kelompokkan produk agar mudah dicari</p>
            </div>
            <button type="button" class="btn-close-category-modal btn btn-ghost px-3 py-1 text-sm">✕</button>
        </div>

        @if($errors->any() && old('_form') === 'category')
            <div class="mb-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm px-3 py-2">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('categories.store') }}" class="space-y-3">
            @csrf
            <input type="hidden" name="_form" value="category">
            <div>
                <label class="text-xs font-medium text-slate-600">Nama kategori</label>
                <input name="name" class="input mt-1" value="{{ old('_form') === 'category' ? old('name') : '' }}" placeholder="Contoh: Minuman" required autofocus>
            </div>
            <div>
                <label class="text-xs font-medium text-slate-600">Deskripsi (opsional)</label>
                <textarea name="description" class="input mt-1" rows="2" placeholder="Catatan singkat">{{ old('_form') === 'category' ? old('description') : '' }}</textarea>
            </div>
            <div class="flex flex-col-reverse sm:flex-row gap-2 pt-1">
                <button type="button" class="btn-close-category-modal btn btn-cancel flex-1">Batal</button>
                <button class="btn btn-primary flex-1">Simpan kategori</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const productModal = document.getElementById('product-modal');
    const categoryModal = document.getElementById('category-modal');
    const form = document.getElementById('product-form');
    const methodField = document.getElementById('product-method-field');
    const formType = document.getElementById('product-form-type');
    const title = document.getElementById('product-modal-title');
    const submitBtn = document.getElementById('product-submit-btn');
    const extraFlags = document.getElementById('product-extra-flags');
    const editActions = document.getElementById('product-edit-actions');
    const currentImageWrap = document.getElementById('product-current-image');
    const imagePreview = document.getElementById('product-image-preview');
    const deleteForm = document.getElementById('product-delete-form');

    function initCategorySelect() {
        window.reinitSelect2?.(document.getElementById('product-category'));
    }

    function bindToggles(root) {
        const stockToggle = root.querySelector('.toggle-stock');
        const expiryToggle = root.querySelector('.toggle-expiry');
        const stockField = root.querySelector('.stock-field');
        const expiryField = root.querySelector('.expiry-field');
        stockToggle?.addEventListener('change', () => stockField?.classList.toggle('hidden', !stockToggle.checked));
        expiryToggle?.addEventListener('change', () => expiryField?.classList.toggle('hidden', !expiryToggle.checked));
        stockField?.classList.toggle('hidden', !stockToggle?.checked);
        expiryField?.classList.toggle('hidden', !expiryToggle?.checked);
    }

    function openModal(el) {
        el.classList.add('open');
        el.setAttribute('aria-hidden', 'false');
    }

    function closeModal(el) {
        el.classList.remove('open');
        el.setAttribute('aria-hidden', 'true');
    }

    function resetProductFormCreate() {
        form.action = @json(route('products.store'));
        methodField.innerHTML = '';
        formType.value = 'create';
        title.textContent = 'Tambah produk';
        submitBtn.textContent = 'Simpan produk';
        extraFlags.classList.add('hidden');
        editActions.classList.add('hidden');
        editActions.classList.remove('flex');
        currentImageWrap.classList.add('hidden');
        form.reset();
        document.getElementById('product-track-stock').checked = true;
        document.getElementById('product-has-expiry').checked = false;
        document.getElementById('product-cost').value = 0;
        document.getElementById('product-stock').value = 0;
        document.getElementById('product-remove-image').checked = false;
        jQuery('#product-category').val(null).trigger('change');
        bindToggles(form);
    }

    function openCreateProduct() {
        resetProductFormCreate();
        openModal(productModal);
        initCategorySelect();
        window.PosApp?.initNoKeyboardFields?.(productModal);
        setTimeout(() => document.getElementById('product-barcode')?.focus(), 50);
    }

    function openEditProduct(btn) {
        form.action = btn.dataset.action;
        methodField.innerHTML = '<input type="hidden" name="_method" value="PUT">';
        formType.value = 'update';
        title.textContent = 'Edit produk';
        submitBtn.textContent = 'Update produk';
        extraFlags.classList.remove('hidden');
        editActions.classList.remove('hidden');
        editActions.classList.add('flex');

        document.getElementById('product-name').value = btn.dataset.name || '';
        document.getElementById('product-barcode').value = btn.dataset.barcode || '';
        document.getElementById('product-sku').value = btn.dataset.sku || '';
        document.getElementById('product-cost').value = btn.dataset.cost || 0;
        document.getElementById('product-price').value = btn.dataset.price || 0;
        document.getElementById('product-stock').value = btn.dataset.stock || 0;
        document.getElementById('product-expired-at').value = btn.dataset.expiredAt || '';
        document.getElementById('product-track-stock').checked = btn.dataset.trackStock === '1';
        document.getElementById('product-has-expiry').checked = btn.dataset.hasExpiry === '1';
        document.getElementById('product-is-active').checked = btn.dataset.isActive === '1';
        const locked = document.getElementById('product-stock-locked');
        if (locked) locked.checked = btn.dataset.stockLocked === '1';

        if (btn.dataset.image) {
            currentImageWrap.classList.remove('hidden');
            imagePreview.src = btn.dataset.image;
            document.getElementById('product-remove-image').checked = false;
        } else {
            currentImageWrap.classList.add('hidden');
        }

        deleteForm.action = btn.dataset.deleteAction;
        openModal(productModal);
        initCategorySelect();
        jQuery('#product-category').val(btn.dataset.category || '').trigger('change');
        bindToggles(form);
    }

    document.getElementById('btn-open-product-modal')?.addEventListener('click', openCreateProduct);
    document.getElementById('btn-open-category-modal')?.addEventListener('click', () => {
        openModal(categoryModal);
        categoryModal.querySelector('input[name="name"]')?.focus();
    });

    document.querySelectorAll('.btn-close-product-modal').forEach((b) => b.addEventListener('click', () => closeModal(productModal)));
    document.querySelectorAll('.btn-close-category-modal').forEach((b) => b.addEventListener('click', () => closeModal(categoryModal)));

    productModal?.addEventListener('click', (e) => { if (e.target === productModal) closeModal(productModal); });
    categoryModal?.addEventListener('click', (e) => { if (e.target === categoryModal) closeModal(categoryModal); });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeModal(productModal);
            closeModal(categoryModal);
        }
    });

    document.querySelectorAll('.btn-edit-product').forEach((btn) => {
        btn.addEventListener('click', () => openEditProduct(btn));
    });

    document.getElementById('btn-delete-product')?.addEventListener('click', () => {
        if (confirm('Hapus produk ini?')) deleteForm.submit();
    });

    function fillProductBarcode(code) {
        const input = document.getElementById('product-barcode');
        if (!input || !code) return;
        input.value = String(code).trim();
        input.dispatchEvent(new Event('input', { bubbles: true }));
        document.getElementById('product-name')?.focus();
    }

    async function scanBarcodeWithCamera() {
        if (!window.PosApp?.openCameraBarcodeScanner) {
            alert('Fitur scan kamera belum dimuat. Refresh halaman (Ctrl+F5).');
            return;
        }
        try {
            await window.PosApp.openCameraBarcodeScanner({
                onScan: (code) => {
                    fillProductBarcode(code);
                    window.PosApp?.toast?.('Barcode: ' + code);
                },
                onError: (msg) => window.PosApp?.toast?.(msg),
            });
        } catch (_) {}
    }

    document.getElementById('btn-scan-barcode-camera')?.addEventListener('click', scanBarcodeWithCamera);

    bindToggles(form);
    if (productModal?.classList.contains('open')) {
        initCategorySelect();
    }
})();
</script>
@endpush
