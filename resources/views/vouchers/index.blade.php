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

        <form method="GET" action="{{ route('vouchers.print') }}" id="print-selected-form">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <h2 class="font-bold">Daftar voucher</h2>
                <button type="submit" class="btn btn-secondary text-sm">Cetak terpilih</button>
            </div>
            <input type="hidden" name="ids" id="print-ids" value="">

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
                            <td class="py-2.5 text-right whitespace-nowrap">
                                <a href="{{ route('vouchers.print', ['ids' => $v->id]) }}" class="text-brand-700 font-medium text-sm">Cetak</a>
                                @if($v->status === 'active')
                                    <form method="POST" action="{{ route('vouchers.cancel', $v) }}" class="inline" onsubmit="return confirm('Batalkan voucher ini?')">
                                        @csrf
                                        <button class="text-rose-600 font-medium text-sm ml-2">Batal</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-10 text-center text-slate-500">Belum ada voucher.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </form>

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
