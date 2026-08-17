@extends('layouts.app')

@section('title', 'Supplier')
@section('heading', 'Data Supplier')
@section('subheading', 'Kelola supplier untuk pembelian & restock')

@section('content')
<div class="space-y-4">
    <div class="card p-4 sm:p-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between mb-4">
            <div>
                <h2 class="font-bold text-lg">Daftar supplier</h2>
                <p class="text-xs text-slate-500">{{ $suppliers->total() }} supplier terdaftar</p>
            </div>
            <div class="flex flex-col sm:flex-row gap-2 w-full lg:w-auto">
                <form method="GET" class="flex gap-2 flex-1 lg:flex-initial">
                    <input type="text" name="q" value="{{ $q }}" class="input min-w-0 flex-1 sm:w-56" placeholder="Cari nama / telepon">
                    <button class="btn btn-secondary shrink-0">Cari</button>
                </form>
                <button type="button" id="btn-open-supplier-modal" class="btn btn-primary whitespace-nowrap">+ Supplier</button>
            </div>
        </div>

        <div class="overflow-x-auto -mx-4 sm:mx-0">
            <table class="w-full text-sm min-w-[640px]">
                <thead>
                    <tr class="text-left text-slate-500 border-b">
                        <th class="py-2 px-4 sm:px-2">Nama</th>
                        <th class="py-2 pr-2">Kontak</th>
                        <th class="py-2 pr-2 hidden md:table-cell">Alamat</th>
                        <th class="py-2 pr-2">Status</th>
                        <th class="py-2 pr-4 sm:pr-2">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($suppliers as $supplier)
                        <tr class="border-b border-slate-100 align-top">
                            <td class="py-3 px-4 sm:px-2">
                                <div class="font-semibold">{{ $supplier->name }}</div>
                                @if($supplier->notes)
                                    <div class="text-xs text-slate-500 mt-0.5 line-clamp-2">{{ $supplier->notes }}</div>
                                @endif
                            </td>
                            <td class="py-3 pr-2 text-xs">
                                <div>{{ $supplier->phone ?: '—' }}</div>
                                <div class="text-slate-500">{{ $supplier->email ?: '' }}</div>
                            </td>
                            <td class="py-3 pr-2 text-xs hidden md:table-cell">{{ $supplier->address ?: '—' }}</td>
                            <td class="py-3 pr-2">
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $supplier->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $supplier->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="py-3 pr-4 sm:pr-2">
                                <button type="button"
                                    class="btn-edit-supplier text-brand-700 font-medium text-sm"
                                    data-id="{{ $supplier->id }}"
                                    data-name="{{ $supplier->name }}"
                                    data-phone="{{ $supplier->phone }}"
                                    data-email="{{ $supplier->email }}"
                                    data-address="{{ $supplier->address }}"
                                    data-notes="{{ $supplier->notes }}"
                                    data-active="{{ $supplier->is_active ? 1 : 0 }}"
                                    data-action="{{ route('suppliers.update', $supplier) }}">
                                    Edit
                                </button>
                                @if(auth()->user()->isStoreOwner())
                                    <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}" class="inline" onsubmit="return confirm('Hapus supplier {{ $supplier->name }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-600 text-sm ml-2">Hapus</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-slate-500">Belum ada supplier.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $suppliers->links() }}</div>
    </div>
</div>

<div id="supplier-modal" class="modal-backdrop">
    <div class="modal-panel p-4 sm:p-6 w-full max-w-lg">
        <div class="flex items-center justify-between mb-4">
            <h3 id="supplier-modal-title" class="text-lg font-bold">Tambah supplier</h3>
            <button type="button" class="btn-close-supplier btn btn-ghost px-3 py-1 text-sm">✕</button>
        </div>
        <form method="POST" id="supplier-form" action="{{ route('suppliers.store') }}" class="space-y-3">
            @csrf
            <input type="hidden" name="_method" id="supplier-method" value="POST" disabled>
            <div>
                <label class="text-sm font-medium">Nama supplier</label>
                <input name="name" id="supplier-name" class="input mt-1" required maxlength="255">
            </div>
            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <label class="text-sm font-medium">Telepon</label>
                    <input name="phone" id="supplier-phone" class="input mt-1" maxlength="30">
                </div>
                <div>
                    <label class="text-sm font-medium">Email</label>
                    <input type="email" name="email" id="supplier-email" class="input mt-1" maxlength="255">
                </div>
            </div>
            <div>
                <label class="text-sm font-medium">Alamat</label>
                <input name="address" id="supplier-address" class="input mt-1" maxlength="500">
            </div>
            <div>
                <label class="text-sm font-medium">Catatan</label>
                <textarea name="notes" id="supplier-notes" class="input mt-1" rows="2" maxlength="1000"></textarea>
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_active" id="supplier-active" value="1" checked>
                Aktif
            </label>
            <div class="flex gap-2 pt-2">
                <button type="button" class="btn-close-supplier btn btn-cancel flex-1">Batal</button>
                <button type="submit" class="btn btn-primary flex-1">Simpan</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const modal = document.getElementById('supplier-modal');
    const form = document.getElementById('supplier-form');
    const title = document.getElementById('supplier-modal-title');
    const methodInput = document.getElementById('supplier-method');

    function openModal(mode = 'create', data = {}) {
        modal?.classList.add('open');
        if (mode === 'edit') {
            title.textContent = 'Edit supplier';
            form.action = data.action;
            methodInput.disabled = false;
            methodInput.value = 'PUT';
            document.getElementById('supplier-name').value = data.name || '';
            document.getElementById('supplier-phone').value = data.phone || '';
            document.getElementById('supplier-email').value = data.email || '';
            document.getElementById('supplier-address').value = data.address || '';
            document.getElementById('supplier-notes').value = data.notes || '';
            document.getElementById('supplier-active').checked = data.active === '1';
        } else {
            title.textContent = 'Tambah supplier';
            form.action = @json(route('suppliers.store'));
            methodInput.disabled = true;
            form.reset();
            document.getElementById('supplier-active').checked = true;
        }
    }

    function closeModal() {
        modal?.classList.remove('open');
    }

    document.getElementById('btn-open-supplier-modal')?.addEventListener('click', () => openModal('create'));
    document.querySelectorAll('.btn-close-supplier').forEach((btn) => btn.addEventListener('click', closeModal));
    modal?.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

    document.querySelectorAll('.btn-edit-supplier').forEach((btn) => {
        btn.addEventListener('click', () => openModal('edit', btn.dataset));
    });
})();
</script>
@endpush
