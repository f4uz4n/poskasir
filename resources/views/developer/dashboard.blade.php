@extends('layouts.app')

@section('title', 'Developer')
@section('heading', 'Panel Developer')
@section('subheading', 'Monitoring akun berlangganan & tidak berlangganan')

@section('content')
<div class="grid sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-6">
    <div class="card p-5">
        <div class="text-sm text-slate-500">Total toko</div>
        <div class="text-2xl font-extrabold mt-1">{{ $summary['owners'] }}</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Berbayar aktif</div>
        <div class="text-2xl font-extrabold mt-1 text-emerald-700">{{ $summary['paid'] }}</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Paket gratis</div>
        <div class="text-2xl font-extrabold mt-1 text-slate-700">{{ $summary['free'] }}</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Tanpa langganan</div>
        <div class="text-2xl font-extrabold mt-1 text-amber-600">{{ $summary['none'] }}</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Omzet pembayaran</div>
        <div class="text-xl font-extrabold mt-1">Rp {{ number_format($summary['revenue_paid'], 0, ',', '.') }}</div>
        <div class="text-xs text-slate-400 mt-1">{{ $summary['pending_payments'] }} pending</div>
    </div>
</div>

@if(($summary['awaiting_proof'] ?? 0) > 0)
<div class="card p-4 mb-4 border border-amber-200 bg-amber-50 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div class="text-sm text-amber-900">
        <strong>{{ $summary['awaiting_proof'] }}</strong> bukti transfer menunggu verifikasi manual.
    </div>
    <a href="{{ route('developer.payments.index', ['filter' => 'awaiting']) }}" class="btn btn-primary text-sm whitespace-nowrap">Review pembayaran</a>
</div>
@endif

<div class="card p-4 sm:p-5 mb-4">
    <form method="GET" class="grid sm:grid-cols-3 gap-3">
        <input type="text" name="q" value="{{ $q }}" class="input" placeholder="Cari toko / email / nama">
        <select name="filter" class="input">
            <option value="all" @selected($filter === 'all')>Semua</option>
            <option value="paid" @selected($filter === 'paid')>Berlangganan berbayar</option>
            <option value="free" @selected($filter === 'free')>Paket gratis</option>
            <option value="none" @selected($filter === 'none')>Tidak berlangganan</option>
        </select>
        <button class="btn btn-primary">Filter</button>
    </form>
</div>

@php $plans = \App\Models\SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get(); @endphp

<div class="card p-4 sm:p-5 mb-6">
    <h2 class="font-bold mb-4">Daftar akun toko</h2>
    <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[900px]">
            <thead>
                <tr class="text-left text-slate-500 border-b">
                    <th class="py-2 pr-2">Toko</th>
                    <th class="py-2 pr-2">Pemilik</th>
                    <th class="py-2 pr-2">Status paket</th>
                    <th class="py-2 pr-2">Berlaku</th>
                    <th class="py-2 pr-2">Kasir</th>
                    <th class="py-2 pr-2">Akun</th>
                    <th class="py-2">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($owners as $row)
                    @php $u = $row['user']; @endphp
                    <tr class="border-b border-slate-100 align-top">
                        <td class="py-3 pr-2">
                            <div class="font-semibold">{{ $u->store_name ?: '-' }}</div>
                            <div class="text-xs text-slate-400">#{{ $u->id }} · {{ $u->created_at->format('d/m/Y') }}</div>
                        </td>
                        <td class="py-3 pr-2">
                            <div>{{ $u->name }}</div>
                            <div class="text-xs text-slate-500">{{ $u->email }}</div>
                        </td>
                        <td class="py-3 pr-2">
                            @if($row['status'] === 'paid')
                                <span class="px-2 py-1 rounded-full text-xs bg-emerald-100 text-emerald-700">Berbayar</span>
                                <div class="text-xs mt-1">{{ $row['plan']?->name }}</div>
                            @elseif($row['status'] === 'free')
                                <span class="px-2 py-1 rounded-full text-xs bg-slate-100 text-slate-700">Gratis</span>
                                <div class="text-xs mt-1">{{ $row['plan']?->name }}</div>
                            @else
                                <span class="px-2 py-1 rounded-full text-xs bg-amber-100 text-amber-700">Tidak aktif</span>
                            @endif
                        </td>
                        <td class="py-3 pr-2 text-xs">
                            {{ optional($row['subscription']?->ends_at)->format('d/m/Y') ?: ($row['plan'] ? 'Selamanya' : '-') }}
                        </td>
                        <td class="py-3 pr-2">{{ $row['cashiers'] }}</td>
                        <td class="py-3 pr-2">
                            <span class="text-xs {{ $u->is_active ? 'text-emerald-600' : 'text-red-600' }}">
                                {{ $u->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="py-3 space-y-2 min-w-[220px]">
                            <form method="POST" action="{{ route('developer.users.plan', $u) }}" class="flex gap-1">
                                @csrf
                                <select name="plan_id" class="input text-xs py-1 flex-1" required>
                                    @foreach($plans as $plan)
                                        <option value="{{ $plan->id }}" @selected($row['plan']?->id == $plan->id)>
                                            {{ $plan->name }} — Rp {{ number_format($plan->price, 0, ',', '.') }}
                                        </option>
                                    @endforeach
                                </select>
                                <button class="btn btn-secondary text-xs px-2">Set</button>
                            </form>
                            <form method="POST" action="{{ route('developer.users.toggle', $u) }}">
                                @csrf
                                <button class="btn btn-ghost text-xs px-2 py-1">
                                    {{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-10 text-center text-slate-500">Tidak ada akun.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card p-4 sm:p-5">
    <h2 class="font-bold mb-4">Pembayaran terbaru</h2>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b">
                    <th class="py-2">Invoice</th>
                    <th class="py-2">Toko</th>
                    <th class="py-2">Paket</th>
                    <th class="py-2">Status</th>
                    <th class="py-2 text-right">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentPayments as $pay)
                    <tr class="border-b border-slate-100">
                        <td class="py-3 font-mono text-xs">{{ $pay->invoice_code }}</td>
                        <td class="py-3">{{ $pay->user?->store_name ?? $pay->user?->name }}</td>
                        <td class="py-3">{{ $pay->subscription?->plan?->name ?? '-' }}</td>
                        <td class="py-3">{{ $pay->status }}</td>
                        <td class="py-3 text-right">Rp {{ number_format($pay->amount, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-slate-500">Belum ada pembayaran.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
