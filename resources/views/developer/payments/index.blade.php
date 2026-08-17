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
    <div class="space-y-4">
        @foreach($payments as $pay)
            <article class="card overflow-hidden">
                <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-mono text-xs text-brand-700">{{ $pay->invoice_code }}</p>
                        <h3 class="text-lg font-bold truncate">{{ $pay->user?->store_name ?? $pay->user?->name }}</h3>
                        <p class="text-xs text-slate-500 truncate">{{ $pay->user?->email }}</p>
                    </div>
                    <span class="shrink-0 px-2.5 py-1 rounded-full text-xs font-semibold
                        {{ $pay->status === 'paid' ? 'bg-emerald-100 text-emerald-700' : ($pay->status === 'failed' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">
                        {{ strtoupper($pay->status) }}
                    </span>
                </div>

                <div class="p-4 sm:p-5 grid sm:grid-cols-2 xl:grid-cols-4 gap-3 text-sm">
                    <div class="rounded-xl bg-slate-50 p-3">
                        <div class="text-xs text-slate-500 mb-1">Paket</div>
                        <div class="font-semibold">{{ $pay->subscription?->plan?->name ?? '—' }}</div>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <div class="text-xs text-slate-500 mb-1">Nominal transfer</div>
                        <div class="font-semibold text-emerald-700">
                            Rp {{ number_format($pay->expected_amount ?? $pay->amount, 0, ',', '.') }}
                        </div>
                        @if($pay->unit_code)
                            <div class="text-xs text-slate-500 mt-0.5">Kode unit: {{ $pay->unit_code }}</div>
                        @endif
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <div class="text-xs text-slate-500 mb-1">Pengirim</div>
                        <div class="font-semibold">{{ $pay->payer_name ?: '—' }}</div>
                        @if($pay->payer_bank)
                            <div class="text-xs text-slate-500 mt-0.5">{{ $pay->payer_bank }}</div>
                        @endif
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <div class="text-xs text-slate-500 mb-1">Upload bukti</div>
                        <div class="font-semibold">{{ $pay->proof_image ? $pay->updated_at->format('d M Y H:i') : 'Belum ada' }}</div>
                    </div>
                </div>

                @if($pay->notes)
                    <div class="px-4 sm:px-5 pb-4">
                        <div class="text-sm rounded-xl bg-slate-50 p-3">
                            <span class="text-slate-500">Catatan toko:</span> {{ $pay->notes }}
                        </div>
                    </div>
                @endif

                @if($pay->admin_notes)
                    <div class="px-4 sm:px-5 pb-4">
                        <div class="text-sm rounded-xl border border-slate-200 p-3 {{ $pay->status === 'failed' ? 'bg-red-50 text-red-800' : 'bg-emerald-50 text-emerald-800' }}">
                            <span class="font-medium">Catatan developer:</span> {{ $pay->admin_notes }}
                            @if($pay->manual_verified_at)
                                <div class="text-xs mt-1 opacity-80">{{ $pay->manual_verified_at->format('d M Y H:i') }}</div>
                            @endif
                        </div>
                    </div>
                @endif

                <div class="px-4 sm:px-5 py-4 bg-slate-50/70 border-t border-slate-100 space-y-3">
                    @if($pay->proof_image)
                        <div class="flex flex-wrap gap-2">
                            <button type="button"
                                    class="btn btn-secondary text-sm btn-view-proof"
                                    data-proof-url="{{ $pay->proofUrl() }}"
                                    data-proof-title="Bukti transfer — {{ $pay->user?->store_name ?? $pay->invoice_code }}">
                                Lihat bukti transfer
                            </button>
                            <a href="{{ $pay->proofUrl() }}" target="_blank" rel="noopener" class="btn btn-ghost text-sm">
                                Buka tab baru
                            </a>
                        </div>
                    @endif

                    @if($pay->status === 'pending' && $pay->proof_image)
                        <div class="grid lg:grid-cols-2 gap-3 pt-1">
                            <form method="POST" action="{{ route('developer.payments.approve', $pay) }}"
                                  onsubmit="return confirm('Setujui pembayaran ini dan aktifkan langganan toko?')">
                                @csrf
                                <button type="submit" class="btn btn-primary w-full">Setujui & aktifkan langganan</button>
                            </form>
                            <form method="POST" action="{{ route('developer.payments.reject', $pay) }}" class="space-y-2">
                                @csrf
                                <input name="admin_notes" class="input text-sm" placeholder="Alasan penolakan (wajib)" required maxlength="500">
                                <button type="submit" class="btn btn-cancel w-full" onclick="return confirm('Tolak pembayaran ini?')">Tolak pembayaran</button>
                            </form>
                        </div>
                    @elseif($pay->status === 'pending' && ! $pay->proof_image)
                        <p class="text-xs text-slate-500">Menunggu toko mengupload bukti transfer.</p>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

    <div class="mt-4">{{ $payments->links() }}</div>
@endif

<div id="proof-modal" class="modal-backdrop" aria-hidden="true">
    <div class="modal-panel w-full max-w-3xl p-0 overflow-hidden" role="dialog" aria-modal="true" aria-labelledby="proof-modal-title">
        <div class="flex items-center justify-between gap-3 px-4 sm:px-5 py-4 border-b border-slate-100">
            <h3 id="proof-modal-title" class="text-base sm:text-lg font-bold truncate">Bukti transfer</h3>
            <button type="button" id="proof-modal-close" class="btn btn-ghost px-3 py-1 text-sm shrink-0" aria-label="Tutup">✕</button>
        </div>
        <div class="p-4 sm:p-5 bg-slate-50 flex items-center justify-center min-h-[12rem] max-h-[70vh] overflow-auto">
            <img id="proof-modal-img" src="" alt="Bukti transfer" class="max-w-full max-h-[65vh] w-auto h-auto object-contain rounded-lg shadow-sm bg-white">
        </div>
        <div class="px-4 sm:px-5 py-4 border-t border-slate-100 flex flex-wrap gap-2">
            <a id="proof-modal-open-tab" href="#" target="_blank" rel="noopener" class="btn btn-secondary text-sm">Buka tab baru</a>
            <button type="button" id="proof-modal-close-bottom" class="btn btn-cancel text-sm">Tutup</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const modal = document.getElementById('proof-modal');
    const img = document.getElementById('proof-modal-img');
    const title = document.getElementById('proof-modal-title');
    const openTab = document.getElementById('proof-modal-open-tab');
    const closeButtons = [
        document.getElementById('proof-modal-close'),
        document.getElementById('proof-modal-close-bottom'),
    ];

    if (!modal || !img) return;

    const openModal = (url, label) => {
        title.textContent = label || 'Bukti transfer';
        img.src = url;
        openTab.href = url;
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    };

    const closeModal = () => {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        img.removeAttribute('src');
        document.body.style.overflow = '';
    };

    document.querySelectorAll('.btn-view-proof').forEach((btn) => {
        btn.addEventListener('click', () => {
            openModal(btn.dataset.proofUrl, btn.dataset.proofTitle);
        });
    });

    closeButtons.forEach((btn) => btn?.addEventListener('click', closeModal));

    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('open')) closeModal();
    });
})();
</script>
@endpush
