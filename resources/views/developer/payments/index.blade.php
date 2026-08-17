@extends('layouts.app')

@section('title', 'Verifikasi Pembayaran')
@section('heading', 'Verifikasi Pembayaran')
@section('subheading', 'Setujui bukti transfer langganan toko')

@section('content')
<div class="flex flex-wrap gap-2 mb-4">
    <a href="{{ route('developer.payments.index', ['filter' => 'awaiting']) }}"
       class="btn {{ $filter === 'awaiting' ? 'btn-primary' : 'btn-secondary' }} text-sm">
        Menunggu review
        @if($counts['awaiting'] > 0)
            <span class="ml-1 px-1.5 py-0.5 rounded-full bg-white/20 text-xs">{{ $counts['awaiting'] }}</span>
        @endif
    </a>
    <a href="{{ route('developer.payments.index', ['filter' => 'pending']) }}" class="btn {{ $filter === 'pending' ? 'btn-primary' : 'btn-secondary' }} text-sm">Semua pending</a>
    <a href="{{ route('developer.payments.index', ['filter' => 'paid']) }}" class="btn {{ $filter === 'paid' ? 'btn-primary' : 'btn-secondary' }} text-sm">Lunas</a>
    <a href="{{ route('developer.payments.index', ['filter' => 'failed']) }}" class="btn {{ $filter === 'failed' ? 'btn-primary' : 'btn-secondary' }} text-sm">Ditolak</a>
    <a href="{{ route('developer.payments.index', ['filter' => 'all']) }}" class="btn {{ $filter === 'all' ? 'btn-primary' : 'btn-secondary' }} text-sm">Semua</a>
</div>

@if($payments->isEmpty())
    <div class="card p-8 text-center text-slate-500">
        Tidak ada pembayaran untuk filter ini.
    </div>
@else
    <div class="grid gap-4">
        @foreach($payments as $pay)
            <div class="card p-4 sm:p-5">
                <div class="flex flex-col lg:flex-row gap-4">
                    <div class="flex-1 min-w-0 space-y-3">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <div class="font-mono text-xs text-brand-700">{{ $pay->invoice_code }}</div>
                                <div class="text-lg font-bold">{{ $pay->user?->store_name ?? $pay->user?->name }}</div>
                                <div class="text-xs text-slate-500">{{ $pay->user?->email }}</div>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold
                                {{ $pay->status === 'paid' ? 'bg-emerald-100 text-emerald-700' : ($pay->status === 'failed' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">
                                {{ strtoupper($pay->status) }}
                            </span>
                        </div>

                        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-2 text-sm">
                            <div class="rounded-lg bg-slate-50 p-3">
                                <div class="text-xs text-slate-500">Paket</div>
                                <div class="font-semibold">{{ $pay->subscription?->plan?->name ?? '—' }}</div>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-3">
                                <div class="text-xs text-slate-500">Nominal transfer</div>
                                <div class="font-semibold text-emerald-700">
                                    Rp {{ number_format($pay->expected_amount ?? $pay->amount, 0, ',', '.') }}
                                </div>
                                @if($pay->unit_code)
                                    <div class="text-xs text-slate-500">Kode unit: {{ $pay->unit_code }}</div>
                                @endif
                            </div>
                            <div class="rounded-lg bg-slate-50 p-3">
                                <div class="text-xs text-slate-500">Pengirim</div>
                                <div class="font-semibold">{{ $pay->payer_name ?: '—' }}</div>
                                <div class="text-xs text-slate-500">{{ $pay->payer_bank ?: '' }}</div>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-3">
                                <div class="text-xs text-slate-500">Upload bukti</div>
                                <div class="font-semibold">{{ $pay->proof_image ? $pay->updated_at->format('d M Y H:i') : 'Belum ada' }}</div>
                            </div>
                        </div>

                        @if($pay->notes)
                            <div class="text-sm rounded-lg bg-slate-50 p-3">
                                <span class="text-slate-500">Catatan toko:</span> {{ $pay->notes }}
                            </div>
                        @endif

                        @if($pay->admin_notes)
                            <div class="text-sm rounded-lg border border-slate-200 p-3 {{ $pay->status === 'failed' ? 'bg-red-50 text-red-800' : 'bg-emerald-50 text-emerald-800' }}">
                                <span class="font-medium">Catatan developer:</span> {{ $pay->admin_notes }}
                                @if($pay->manual_verified_at)
                                    <div class="text-xs mt-1 opacity-80">{{ $pay->manual_verified_at->format('d M Y H:i') }}</div>
                                @endif
                            </div>
                        @endif
                    </div>

                    @if($pay->proof_image)
                        <div class="lg:w-56 shrink-0">
                            <a href="{{ $pay->proofUrl() }}" target="_blank" class="block rounded-xl border border-slate-200 overflow-hidden bg-slate-50">
                                <img src="{{ $pay->proofUrl() }}" alt="Bukti transfer" class="w-full h-48 object-contain">
                            </a>
                            <a href="{{ $pay->proofUrl() }}" target="_blank" class="btn btn-ghost w-full text-xs mt-2">Buka gambar penuh</a>
                        </div>
                    @endif
                </div>

                @if($pay->status === 'pending' && $pay->proof_image)
                    <div class="flex flex-col sm:flex-row gap-2 mt-4 pt-4 border-t border-slate-100">
                        <form method="POST" action="{{ route('developer.payments.approve', $pay) }}" class="flex-1"
                              onsubmit="return confirm('Setujui pembayaran ini dan aktifkan langganan toko?')">
                            @csrf
                            <button type="submit" class="btn btn-primary w-full">Setujui & aktifkan langganan</button>
                        </form>
                        <form method="POST" action="{{ route('developer.payments.reject', $pay) }}" class="flex-1 space-y-2">
                            @csrf
                            <input name="admin_notes" class="input text-sm" placeholder="Alasan penolakan (wajib)" required maxlength="500">
                            <button type="submit" class="btn btn-cancel w-full" onclick="return confirm('Tolak pembayaran ini?')">Tolak</button>
                        </form>
                    </div>
                @elseif($pay->status === 'pending' && ! $pay->proof_image)
                    <p class="text-xs text-slate-500 mt-4 pt-4 border-t border-slate-100">Menunggu toko mengupload bukti transfer.</p>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-4">{{ $payments->links() }}</div>
@endif
@endsection
