@extends('layouts.app')

@section('title', 'Voucher')
@section('heading', 'Voucher Belanja')
@section('subheading', 'Buat, cetak barcode, dan kelola voucher yang bisa di-scan di kasir')

@section('content')
@if(session('success'))
    <div class="mb-4 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800">{{ session('success') }}</div>
@endif

<div class="grid sm:grid-cols-3 gap-4 mb-6">
    <div class="card p-5">
        <div class="text-sm text-slate-500">Voucher aktif</div>
        <div class="text-2xl font-extrabold mt-1 text-brand-700">{{ number_format($summary['active'], 0, ',', '.') }}</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Nilai aktif</div>
        <div class="text-2xl font-extrabold mt-1">Rp {{ number_format($summary['active_value'], 0, ',', '.') }}</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Sudah dipakai</div>
        <div class="text-2xl font-extrabold mt-1 text-slate-600">{{ number_format($summary['used'], 0, ',', '.') }}</div>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-4 mb-6">
    <div class="card p-5 lg:col-span-1">
        <h2 class="font-bold mb-3">Buat voucher</h2>
        <form method="POST" action="{{ route('vouchers.store') }}" class="space-y-3">
            @csrf
            <div>
                <label class="text-xs text-slate-500">Judul</label>
                <input type="text" name="title" value="{{ old('title', 'Voucher Belanja') }}" class="input mt-1" maxlength="120">
            </div>
            <div>
                <label class="text-xs text-slate-500">Nilai voucher (Rp)</label>
                <input type="number" name="amount" value="{{ old('amount', 50000) }}" min="1" step="1" class="input mt-1" required>
            </div>
            <div>
                <label class="text-xs text-slate-500">Jumlah lembar</label>
                <input type="number" name="qty" value="{{ old('qty', 1) }}" min="1" max="200" class="input mt-1" required>
            </div>
            <div>
                <label class="text-xs text-slate-500">Kadaluarsa (opsional)</label>
                <input type="datetime-local" name="expires_at" value="{{ old('expires_at') }}" class="input mt-1">
            </div>
            <div>
                <label class="text-xs text-slate-500">Catatan</label>
                <textarea name="notes" class="input mt-1" rows="2">{{ old('notes') }}</textarea>
            </div>
            <button class="btn btn-primary w-full">Buat & cetak barcode</button>
        </form>
        <p class="text-xs text-slate-500 mt-3">Setiap voucher punya kode unik + barcode. Di kasir, pilih metode <strong>Voucher</strong> lalu scan kodenya.</p>
    </div>

    <div class="card p-5 lg:col-span-2 overflow-x-auto">
        <form method="GET" class="flex flex-wrap gap-2 mb-4">
            <input type="text" name="q" value="{{ $q }}" class="input flex-1 min-w-[12rem]" placeholder="Cari kode / judul">
            <select name="status" class="input w-40">
                <option value="">Semua status</option>
                <option value="active" @selected($status === 'active')>Aktif</option>
                <option value="used" @selected($status === 'used')>Terpakai</option>
                <option value="cancelled" @selected($status === 'cancelled')>Dibatalkan</option>
                <option value="expired" @selected($status === 'expired')>Kadaluarsa</option>
            </select>
            <button class="btn btn-primary">Filter</button>
        </form>

        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <h2 class="font-bold">Daftar voucher</h2>
            <form method="GET" action="{{ route('vouchers.print') }}" id="print-selected-form" class="inline">
                <input type="hidden" name="ids" id="print-ids" value="">
                <button type="submit" class="btn btn-secondary text-sm">Cetak terpilih</button>
            </form>
        </div>

        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b">
                    <th class="py-2 pr-2 w-8"><input type="checkbox" id="check-all-vouchers"></th>
                    <th class="py-2 pr-2">Kode</th>
                    <th class="py-2 pr-2 text-right">Nilai</th>
                    <th class="py-2 pr-2">Status</th>
                    <th class="py-2 pr-2">Kadaluarsa</th>
                    <th class="py-2 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($vouchers as $v)
                    @php
                        $badge = match($v->status) {
                            'active' => 'bg-emerald-100 text-emerald-700',
                            'used' => 'bg-slate-100 text-slate-600',
                            'cancelled' => 'bg-rose-100 text-rose-700',
                            default => 'bg-amber-100 text-amber-700',
                        };
                    @endphp
                    <tr class="border-b border-slate-100">
                        <td class="py-2.5 pr-2">
                            <input type="checkbox" class="voucher-check" value="{{ $v->id }}">
                        </td>
                        <td class="py-2.5 pr-2">
                            <div class="font-mono font-semibold">{{ $v->code }}</div>
                            <div class="text-xs text-slate-400">{{ $v->title ?: 'Voucher' }}</div>
                        </td>
                        <td class="py-2.5 pr-2 text-right font-semibold">Rp {{ number_format($v->amount, 0, ',', '.') }}</td>
                        <td class="py-2.5 pr-2">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $badge }}">{{ $v->statusLabel() }}</span>
                            @if($v->usedOnTransaction)
                                <div class="text-[11px] text-slate-400 mt-0.5">{{ $v->usedOnTransaction->invoice_number }}</div>
                            @endif
                        </td>
                        <td class="py-2.5 pr-2 text-xs text-slate-500">
                            {{ $v->expires_at ? $v->expires_at->format('d/m/Y H:i') : '—' }}
                        </td>
                        <td class="py-2.5 text-right">
                            <div class="inline-flex items-center justify-end gap-1.5">
                                <a href="{{ route('vouchers.print', ['ids' => $v->id]) }}"
                                   class="btn-icon"
                                   title="Cetak"
                                   aria-label="Cetak voucher">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                                        <rect x="6" y="14" width="12" height="8"></rect>
                                    </svg>
                                </a>
                                @if($v->status === 'active')
                                    <form method="POST" action="{{ route('vouchers.destroy', $v) }}" class="inline m-0" onsubmit="return confirm('Hapus voucher {{ $v->code }} secara permanen?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="btn-icon text-rose-600 hover:text-rose-700 hover:border-rose-200"
                                                title="Hapus"
                                                aria-label="Hapus voucher">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                <line x1="10" y1="11" x2="10" y2="17"></line>
                                                <line x1="14" y1="11" x2="14" y2="17"></line>
                                            </svg>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-10 text-center text-slate-500">Belum ada voucher.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="mt-4">{{ $vouchers->links() }}</div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('print-selected-form');
    const idsInput = document.getElementById('print-ids');
    const checks = () => Array.from(document.querySelectorAll('.voucher-check'));

    document.getElementById('check-all-vouchers')?.addEventListener('change', (e) => {
        checks().forEach((c) => { c.checked = e.target.checked; });
    });

    form?.addEventListener('submit', (e) => {
        const ids = checks().filter((c) => c.checked).map((c) => c.value);
        if (!ids.length) {
            e.preventDefault();
            alert('Pilih minimal satu voucher untuk dicetak.');
            return;
        }
        idsInput.value = ids.join(',');
    });
})();
</script>
@endpush
