@extends('layouts.app')

@section('title', 'Batalkan Transaksi')
@section('heading', 'Batalkan Transaksi')
@section('subheading', 'Batalkan pembelian berdasarkan nomor struk — wajib validasi password pimpinan toko')

@section('content')
<div class="grid lg:grid-cols-5 gap-4">
    <div class="card p-5 lg:col-span-3">
        <form method="POST" action="{{ route('transactions.void.store') }}" class="space-y-4" id="void-form">
            @csrf
            <div>
                <label class="text-sm font-medium text-slate-700">Nomor struk / invoice</label>
                <input type="text" name="invoice_number" id="invoice_number" value="{{ old('invoice_number', $invoice) }}"
                    class="input mt-1 font-mono" placeholder="INV-20260902123456-ABCD" required autofocus>
                <p class="text-xs text-slate-500 mt-1">Salin nomor struk dari riwayat transaksi atau struk fisik.</p>
            </div>

            @if($preview)
                <div class="rounded-xl border p-4 {{ $preview->status === 'void' ? 'border-red-200 bg-red-50' : 'border-emerald-200 bg-emerald-50' }}">
                    <div class="font-semibold">{{ $preview->invoice_number }}</div>
                    <div class="text-sm mt-1">
                        {{ optional($preview->sold_at)->format('d/m/Y H:i') }}
                        · Rp {{ number_format($preview->total, 0, ',', '.') }}
                        · {{ $preview->items->sum('qty') }} item
                    </div>
                    @if($preview->cashier)
                        <div class="text-xs text-slate-600 mt-1">Kasir: {{ $preview->cashier->name }}</div>
                    @endif
                    <div class="mt-2">
                        <span class="px-2 py-1 rounded-full text-xs {{ $preview->status === 'completed' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                            {{ $preview->status === 'completed' ? 'Siap dibatalkan' : 'Sudah dibatalkan' }}
                        </span>
                    </div>
                </div>
            @endif

            <div>
                <label class="text-sm font-medium text-slate-700">Alasan pembatalan</label>
                <textarea name="reason" rows="3" class="input mt-1" placeholder="Contoh: salah input item, pelanggan batal, dll." required>{{ old('reason') }}</textarea>
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Password pimpinan toko</label>
                <input type="password" name="owner_password" class="input mt-1" placeholder="Password akun pimpinan toko" required autocomplete="current-password">
                <p class="text-xs text-slate-500 mt-1">Hanya pimpinan toko yang dapat menyetujui pembatalan transaksi.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="submit" class="btn btn-danger" onclick="return confirm('Yakin batalkan transaksi ini? Stok akan dikembalikan.')">
                    Batalkan transaksi
                </button>
                <a href="{{ route('transactions.index') }}" class="btn btn-secondary">Kembali ke riwayat</a>
            </div>
        </form>
    </div>

    <div class="card p-5 lg:col-span-2">
        <h2 class="font-bold mb-3">Cara kerja</h2>
        <ol class="text-sm text-slate-600 space-y-2 list-decimal list-inside">
            <li>Masukkan nomor struk yang akan dibatalkan.</li>
            <li>Periksa ringkasan transaksi yang muncul.</li>
            <li>Tulis alasan pembatalan.</li>
            <li>Minta pimpinan toko memasukkan password-nya.</li>
            <li>Setelah disetujui, stok produk dikembalikan otomatis.</li>
        </ol>
        <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3 mt-4">
            Transaksi yang sudah dibatalkan tidak dapat dipulihkan. Piutang terkait akan ditutup otomatis.
        </p>
    </div>
</div>
@endsection
