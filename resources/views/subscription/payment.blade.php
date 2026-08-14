@extends('layouts.app')

@section('title', 'Pembayaran Langganan')
@section('heading', 'Pembayaran')
@section('subheading', $payment->invoice_code)

@section('content')
<div class="max-w-2xl mx-auto grid gap-4">
    <div class="card p-6">
        <div class="flex items-start justify-between gap-3 mb-4">
            <div>
                <div class="text-sm text-slate-500">Paket</div>
                <div class="text-xl font-extrabold">{{ $payment->subscription->plan->name }}</div>
            </div>
            <span id="payment-status-badge" class="px-3 py-1 rounded-full text-xs font-semibold {{ $payment->status === 'paid' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                {{ strtoupper($payment->status) }}
            </span>
        </div>

        <div class="grid sm:grid-cols-2 gap-3 text-sm mb-4">
            <div class="rounded-xl bg-slate-50 p-3">
                <div class="text-slate-500">Harga paket</div>
                <div class="text-lg font-bold">Rp {{ number_format($payment->amount, 0, ',', '.') }}</div>
            </div>
            <div class="rounded-xl bg-slate-50 p-3">
                <div class="text-slate-500">Metode</div>
                <div class="text-lg font-bold uppercase">{{ $payment->method }}</div>
            </div>
        </div>

        @if($payment->status !== 'paid')
            @if($payment->method === 'transfer' && $payment->unit_code)
                <div class="rounded-xl border-2 border-brand-400 bg-brand-50 p-4 mb-4">
                    <div class="text-sm text-brand-800 font-semibold mb-2">Transfer ke rekening BSI</div>
                    <div class="font-mono text-sm space-y-1">
                        <div><span class="text-slate-500">Bank:</span> {{ $bank['name'] }}</div>
                        <div><span class="text-slate-500">No. rekening:</span> <strong>{{ $bank['account'] ?: '— (atur di .env)' }}</strong></div>
                        <div><span class="text-slate-500">Atas nama:</span> {{ $bank['holder'] }}</div>
                    </div>

                    <div class="mt-4 grid sm:grid-cols-2 gap-3">
                        <div class="rounded-lg bg-white border border-brand-200 p-3 text-center">
                            <div class="text-xs text-slate-500 uppercase tracking-wide">Kode unit Anda</div>
                            <div class="text-3xl font-extrabold text-brand-700 tracking-widest">{{ $payment->unit_code }}</div>
                        </div>
                        <div class="rounded-lg bg-white border border-brand-200 p-3 text-center">
                            <div class="text-xs text-slate-500 uppercase tracking-wide">Nominal transfer (tepat)</div>
                            <div class="text-2xl font-extrabold text-emerald-700">
                                Rp {{ number_format($payment->expected_amount, 0, ',', '.') }}
                            </div>
                        </div>
                    </div>

                    <p class="text-xs text-slate-600 mt-3">
                        Transfer <strong>tepat</strong> Rp {{ number_format($payment->expected_amount, 0, ',', '.') }}
                        (= harga paket Rp {{ number_format($payment->amount, 0, ',', '.') }} + kode unit {{ $payment->unit_code }}).
                        Setelah BSI mengirim notifikasi email, langganan akan aktif otomatis.
                    </p>

                    @if($payment->expires_at)
                        <p class="text-xs text-amber-700 mt-2">
                            Batas waktu: {{ $payment->expires_at->format('d M Y H:i') }} ({{ $payment->expires_at->diffForHumans() }})
                        </p>
                    @endif
                </div>

                <div id="verify-panel" class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 mb-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <div class="font-semibold text-sm">Validasi otomatis via email BSI</div>
                            <p id="verify-message" class="text-xs text-slate-600 mt-1">
                                @if($emailReady)
                                    Menunggu transfer… halaman akan memperbarui otomatis setelah tervalidasi.
                                @else
                                    IMAP belum siap. Aktifkan php_imap dan isi SUBSCRIPTION_IMAP_PASSWORD di .env.
                                @endif
                            </p>
                        </div>
                        <form method="POST" action="{{ route('subscription.verify', $payment) }}" id="verify-form">
                            @csrf
                            <button type="submit" class="btn btn-primary text-sm whitespace-nowrap" @disabled(! $emailReady)>
                                Cek sekarang
                            </button>
                        </form>
                    </div>
                    <div id="verify-spinner" class="hidden mt-3 text-xs text-brand-700 flex items-center gap-2">
                        <span class="inline-block w-4 h-4 border-2 border-brand-600 border-t-transparent rounded-full animate-spin"></span>
                        Memeriksa email BSI…
                    </div>
                </div>
            @else
                <div class="rounded-xl border border-dashed border-brand-300 bg-brand-50/40 p-4 mb-4 text-sm">
                    <div class="font-semibold mb-2">Instruksi pembayaran</div>
                    @if($payment->method === 'qris')
                        <p>Scan QRIS demo di aplikasi e-wallet Anda, lalu upload bukti.</p>
                    @elseif($payment->method === 'va')
                        <p>Virtual Account demo: <span class="font-mono">8808{{ substr($payment->invoice_code, -8) }}</span></p>
                    @else
                        <p>Selesaikan pembayaran melalui metode {{ strtoupper($payment->method) }}, lalu upload bukti.</p>
                    @endif
                </div>
            @endif

            <details class="mb-4">
                <summary class="text-sm font-medium cursor-pointer text-slate-600">Upload bukti transfer (opsional)</summary>
                <form method="POST" action="{{ route('subscription.proof', $payment) }}" enctype="multipart/form-data" class="space-y-3 mt-3">
                    @csrf
                    <div>
                        <label class="text-sm font-medium">Nama pengirim</label>
                        <input name="payer_name" class="input mt-1" value="{{ old('payer_name', $payment->payer_name) }}" required>
                    </div>
                    <div>
                        <label class="text-sm font-medium">Bank / e-wallet</label>
                        <input name="payer_bank" class="input mt-1" value="{{ old('payer_bank', $payment->payer_bank) }}">
                    </div>
                    <div>
                        <label class="text-sm font-medium">Bukti transfer</label>
                        <input type="file" name="proof_image" accept="image/*" class="input mt-1" required>
                    </div>
                    <div>
                        <label class="text-sm font-medium">Catatan</label>
                        <textarea name="notes" class="input mt-1" rows="2">{{ old('notes') }}</textarea>
                    </div>
                    <button class="btn btn-secondary w-full">Simpan bukti</button>
                </form>
            </details>

            @if($payment->method !== 'transfer')
                <form method="POST" action="{{ route('subscription.demo', $payment) }}" class="mt-3">
                    @csrf
                    <button class="btn btn-secondary w-full">Konfirmasi bayar demo (langsung aktif)</button>
                </form>
            @endif
        @else
            <div class="rounded-xl bg-emerald-50 text-emerald-800 p-4 text-sm space-y-1">
                <p>Pembayaran sudah lunas pada {{ optional($payment->paid_at)->format('d M Y H:i') }}.</p>
                @if($payment->email_verified_at)
                    <p class="text-xs">Tervalidasi otomatis dari email BSI
                        @if($payment->bank_transaction_ref)
                            · Ref: {{ $payment->bank_transaction_ref }}
                        @endif
                    </p>
                @endif
            </div>
            <a href="{{ route('subscription.index') }}" class="btn btn-primary w-full mt-4">Kembali ke langganan</a>
        @endif
    </div>
</div>

@if($payment->status !== 'paid' && $payment->method === 'transfer' && $emailReady)
@push('scripts')
<script>
(function () {
    const statusUrl = @json(route('subscription.payment.status', $payment));
    const verifyUrl = @json(route('subscription.verify', $payment));
    const csrf = @json(csrf_token());
    const msgEl = document.getElementById('verify-message');
    const spinner = document.getElementById('verify-spinner');
    const badge = document.getElementById('payment-status-badge');

    function showSpinner(show) {
        if (spinner) spinner.classList.toggle('hidden', !show);
    }

    function onVerified(data) {
        if (msgEl) msgEl.textContent = 'Pembayaran tervalidasi! Mengalihkan…';
        if (badge) {
            badge.textContent = 'PAID';
            badge.className = 'px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700';
        }
        setTimeout(function () { window.location.reload(); }, 1500);
    }

    async function pollStatus() {
        try {
            const res = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (data.verified) {
                onVerified(data);
                return true;
            }
        } catch (e) {}
        return false;
    }

    async function triggerVerify() {
        showSpinner(true);
        try {
            const res = await fetch(verifyUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await res.json();
            if (data.verified) {
                onVerified(data);
            } else if (msgEl && data.message) {
                msgEl.textContent = data.message;
            }
        } catch (e) {
            if (msgEl) msgEl.textContent = 'Gagal memeriksa email. Coba lagi.';
        } finally {
            showSpinner(false);
        }
    }

    document.getElementById('verify-form')?.addEventListener('submit', function (e) {
        e.preventDefault();
        triggerVerify();
    });

    let interval = setInterval(async function () {
        const done = await pollStatus();
        if (done) clearInterval(interval);
    }, 15000);

    setTimeout(triggerVerify, 2000);
})();
</script>
@endpush
@endif
@endsection
