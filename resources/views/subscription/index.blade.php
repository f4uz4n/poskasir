@extends('layouts.app')

@section('title', 'Langganan')
@section('heading', 'Langganan')
@section('subheading', 'Gratis full 1 akun · Berbayar: remote, kunci stok, multi kasir')

@section('content')
@if($current && $current->status === 'active' && (is_null($current->ends_at) || $current->ends_at->isFuture()))
<div class="card p-5 mb-6 border border-emerald-200 bg-emerald-50/50">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <div class="text-sm text-emerald-700 font-medium">Paket aktif</div>
            <div class="text-xl font-extrabold">{{ $current->plan->name }}</div>
            <div class="text-sm text-slate-600">
                @if($current->ends_at)
                    Berlaku sampai {{ $current->ends_at->format('d M Y') }}
                @else
                    Berlaku selamanya (Gratis)
                @endif
            </div>
        </div>
        @if($current->plan->is_free)
            <div class="text-sm text-slate-500">Upgrade untuk multi kasir & pantau jarak jauh</div>
        @elseif($current->ends_at)
            <div class="text-sm text-slate-500">Sisa {{ $current->ends_at->diffForHumans(null, true) }}</div>
        @endif
    </div>
</div>
@endif

<div class="card p-4 mb-6 text-sm text-slate-600">
    <strong>Perbedaan paket:</strong>
    <ul class="list-disc pl-5 mt-2 space-y-1">
        <li><strong>Gratis</strong> — operasi POS penuh, laporan lokal, backup/restore, hanya 1 akun pemilik.</li>
        <li><strong>Berbayar</strong> — pantau laporan jarak jauh, kunci stok, multi kasir, dan API sinkron lengkap.</li>
    </ul>
    @if($emailAutoVerify ?? false)
        <p class="mt-3 text-brand-700">
            <strong>Transfer BSI:</strong> setelah klik Berlangganan Anda mendapat <em>kode unit</em> unik.
            Transfer nominal = harga paket + kode unit (mis. Rp 99.000 + 127 = Rp 99.127).
            Sistem memvalidasi otomatis dari notifikasi email BSI.
        </p>
    @endif
</div>

<div class="grid md:grid-cols-3 gap-4 mb-8">
    @foreach($plans as $plan)
        <div class="card p-5 flex flex-col {{ ! $plan->is_free ? 'ring-2 ring-brand-500' : '' }}">
            <div class="text-sm font-semibold text-brand-700 mb-1">{{ $plan->name }}</div>
            <div class="text-3xl font-extrabold mb-1">
                @if($plan->price == 0)
                    Gratis
                @else
                    <span class="text-lg">Rp</span> {{ number_format($plan->price, 0, ',', '.') }}
                @endif
            </div>
            <div class="text-xs text-slate-500 mb-3">
                @if($plan->is_free)
                    Selamanya · {{ $plan->description }}
                @else
                    {{ $plan->duration_days }} hari · {{ $plan->description }}
                @endif
            </div>
            <ul class="text-sm space-y-1 mb-4 flex-1">
                @foreach($plan->features ?? [] as $feature)
                    <li class="flex gap-2"><span class="text-brand-600">✓</span> {{ $feature }}</li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('subscription.subscribe') }}" class="space-y-2">
                @csrf
                <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                @if($plan->is_free || $plan->price == 0)
                    <input type="hidden" name="method" value="demo">
                    <button class="btn btn-secondary w-full">Pakai paket Gratis</button>
                @else
                    <input type="hidden" name="method" value="transfer">
                    <p class="text-xs text-slate-500">Pembayaran: Transfer bank BSI (validasi otomatis via email)</p>
                    <input name="payer_name" class="input text-sm" placeholder="Nama pengirim" value="{{ auth()->user()->name }}">
                    <x-recaptcha />
                    <button class="btn btn-primary w-full">Berlangganan</button>
                @endif
            </form>
        </div>
    @endforeach
</div>

<div class="card p-5">
    <h2 class="font-bold mb-4">Riwayat pembayaran</h2>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b">
                    <th class="py-2">Invoice</th>
                    <th class="py-2">Paket</th>
                    <th class="py-2">Metode</th>
                    <th class="py-2">Status</th>
                    <th class="py-2 text-right">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $pay)
                    <tr class="border-b border-slate-100">
                        <td class="py-3">
                            <a href="{{ route('subscription.payment', $pay) }}" class="text-brand-700 font-medium">{{ $pay->invoice_code }}</a>
                        </td>
                        <td class="py-3">{{ $pay->subscription?->plan?->name }}</td>
                        <td class="py-3">{{ $pay->method === 'transfer' ? 'Transfer bank' : ($pay->method === 'demo' ? 'Gratis' : strtoupper($pay->method)) }}</td>
                        <td class="py-3">
                            <span class="px-2 py-1 rounded-full text-xs {{ $pay->status === 'paid' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                {{ $pay->status }}
                            </span>
                        </td>
                        <td class="py-3 text-right font-semibold">Rp {{ number_format($pay->amount, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-center text-slate-500">Belum ada pembayaran.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
