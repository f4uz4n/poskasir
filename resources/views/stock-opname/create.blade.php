@extends('layouts.app')

@section('title', 'Stock Opname Baru')
@section('heading', 'Stock Opname Baru')
@section('subheading', 'Isi stok fisik, lalu simpan draft atau selesaikan')

@section('content')
@if($products->isEmpty())
    <div class="card p-8 text-center">
        <p class="text-slate-500 mb-4">Tidak ada produk dengan pelacakan stok.</p>
        <a href="{{ route('products.index') }}" class="btn btn-primary">Kelola produk</a>
    </div>
@else
<form method="POST" action="{{ route('stock-opname.store') }}" id="opname-form">
    @csrf
    <div class="card p-4 sm:p-5 mb-4">
        <div class="grid sm:grid-cols-2 gap-3">
            <div>
                <label class="text-xs font-medium text-slate-600">Judul</label>
                <input name="title" class="input mt-1" placeholder="Stock Opname {{ now()->format('d/m/Y') }}" value="{{ old('title') }}">
            </div>
            <div>
                <label class="text-xs font-medium text-slate-600">Catatan</label>
                <input name="notes" class="input mt-1" placeholder="Opsional" value="{{ old('notes') }}">
            </div>
        </div>
        <div class="mt-3 flex flex-wrap gap-2 text-xs">
            <button type="button" id="btn-copy-system" class="btn btn-ghost text-xs py-1 px-2">Salin semua = stok sistem</button>
            <button type="button" id="btn-zero-diff" class="btn btn-ghost text-xs py-1 px-2">Reset fisik ke sistem</button>
        </div>
    </div>

    <div class="card p-4 sm:p-5 mb-4">
        <div class="overflow-x-auto -mx-4 sm:mx-0">
            <table class="w-full text-sm min-w-[720px]">
                <thead>
                    <tr class="text-left text-slate-500 border-b">
                        <th class="py-2 px-4 sm:px-2">Produk</th>
                        <th class="py-2 pr-2 text-right">Stok sistem</th>
                        <th class="py-2 pr-2 text-right w-36">Stok fisik</th>
                        <th class="py-2 pr-2 text-right">Selisih</th>
                        <th class="py-2 pr-4 sm:pr-2">Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $i => $p)
                        <tr class="border-b border-slate-100 opname-row">
                            <td class="py-3 px-4 sm:px-2">
                                <input type="hidden" name="items[{{ $i }}][product_id]" value="{{ $p->id }}">
                                <div class="font-semibold">{{ $p->name }}</div>
                                <div class="text-xs text-slate-500">{{ $p->category?->name ?? '-' }} · {{ $p->barcode ?: '-' }}</div>
                            </td>
                            <td class="py-3 pr-2 text-right">
                                <span class="system-stock font-medium" data-stock="{{ $p->stock }}">{{ $p->stock }}</span>
                            </td>
                            <td class="py-3 pr-2 text-right">
                                <input type="number" min="0" name="items[{{ $i }}][physical_stock]"
                                       class="input physical-stock text-right py-1.5"
                                       value="{{ $p->stock }}" required>
                            </td>
                            <td class="py-3 pr-2 text-right">
                                <span class="diff-value font-semibold">0</span>
                            </td>
                            <td class="py-3 pr-4 sm:pr-2">
                                <input type="text" name="items[{{ $i }}][notes]" class="input py-1.5" placeholder="-">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="flex flex-col-reverse sm:flex-row gap-2 sticky bottom-4">
        <a href="{{ route('stock-opname.index') }}" class="btn btn-cancel flex-1">Batal</a>
        <button type="submit" name="action" value="draft" class="btn btn-secondary flex-1">Simpan draft</button>
        <button type="submit" name="action" value="complete" class="btn btn-primary flex-1"
                onclick="return confirm('Selesaikan stock opname? Stok produk akan diganti sesuai stok fisik.')">
            Selesaikan & sesuaikan stok
        </button>
    </div>
</form>
@endif
@endsection

@push('scripts')
<script>
(function () {
    function recalc(row) {
        const system = Number(row.querySelector('.system-stock')?.dataset.stock || 0);
        const physical = Number(row.querySelector('.physical-stock')?.value || 0);
        const diff = physical - system;
        const el = row.querySelector('.diff-value');
        if (!el) return;
        el.textContent = (diff > 0 ? '+' : '') + diff;
        el.className = 'diff-value font-semibold ' + (diff > 0 ? 'text-emerald-600' : (diff < 0 ? 'text-red-600' : 'text-slate-500'));
    }

    document.querySelectorAll('.opname-row').forEach((row) => {
        row.querySelector('.physical-stock')?.addEventListener('input', () => recalc(row));
        recalc(row);
    });

    document.getElementById('btn-copy-system')?.addEventListener('click', () => {
        document.querySelectorAll('.opname-row').forEach((row) => {
            const system = row.querySelector('.system-stock')?.dataset.stock || 0;
            const input = row.querySelector('.physical-stock');
            if (input) input.value = system;
            recalc(row);
        });
    });

    document.getElementById('btn-zero-diff')?.addEventListener('click', () => {
        document.getElementById('btn-copy-system')?.click();
    });
})();
</script>
@endpush
