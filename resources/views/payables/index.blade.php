@extends('layouts.app')

@section('title', 'Hutang')
@section('heading', 'Hutang')
@section('subheading', 'Kewajiban ke supplier / pihak lain')

@section('content')
<div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="card p-5">
        <div class="text-sm text-slate-500">Total hutang</div>
        <div class="text-2xl font-extrabold mt-1">Rp {{ number_format($summary['total'], 0, ',', '.') }}</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Sudah dibayar</div>
        <div class="text-2xl font-extrabold mt-1 text-emerald-700">Rp {{ number_format($summary['paid'], 0, ',', '.') }}</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Outstanding</div>
        <div class="text-2xl font-extrabold mt-1 text-red-600">Rp {{ number_format($summary['outstanding'], 0, ',', '.') }}</div>
    </div>
    <div class="card p-5">
        <div class="text-sm text-slate-500">Belum lunas</div>
        <div class="text-2xl font-extrabold mt-1">{{ $summary['count_open'] }}</div>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-4 mb-6">
    <form method="POST" action="{{ route('payables.store') }}" class="card p-5 space-y-3 lg:col-span-1">
        @csrf
        <h2 class="font-bold">Tambah hutang manual</h2>
        <input name="party_name" class="input" placeholder="Nama supplier / pihak" required>
        <input type="number" min="1" name="amount" class="input" placeholder="Jumlah" required>
        <input type="date" name="due_date" class="input">
        <input name="notes" class="input" placeholder="Catatan">
        <button class="btn btn-primary w-full">Simpan</button>
    </form>

    <div class="card p-4 sm:p-5 lg:col-span-2">
        <form method="GET" class="flex flex-wrap gap-2 mb-4">
            <input type="text" name="q" value="{{ request('q') }}" class="input" placeholder="Cari kode / supplier">
            <select name="status" class="input">
                <option value="">Semua status</option>
                <option value="unpaid" @selected($status==='unpaid')>Belum bayar</option>
                <option value="partial" @selected($status==='partial')>Sebagian</option>
                <option value="paid" @selected($status==='paid')>Lunas</option>
            </select>
            <button class="btn btn-secondary">Filter</button>
        </form>
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[700px]">
                <thead>
                    <tr class="text-left text-slate-500 border-b">
                        <th class="py-2">Kode</th>
                        <th class="py-2">Pihak</th>
                        <th class="py-2 text-right">Sisa</th>
                        <th class="py-2">Status</th>
                        <th class="py-2">Bayar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr class="border-b border-slate-100 align-top">
                            <td class="py-3 font-mono text-xs">{{ $item->code }}</td>
                            <td class="py-3">
                                <div class="font-medium">{{ $item->party_name }}</div>
                                <div class="text-xs text-slate-400">
                                    {{ $item->source }}
                                    @if($item->purchase) · {{ $item->purchase->code }} @endif
                                    @if($item->due_date) · JT {{ $item->due_date->format('d/m/Y') }} @endif
                                </div>
                            </td>
                            <td class="py-3 text-right font-semibold">Rp {{ number_format($item->remaining(), 0, ',', '.') }}</td>
                            <td class="py-3">
                                <span class="px-2 py-1 rounded-full text-xs
                                    {{ $item->status === 'paid' ? 'bg-emerald-100 text-emerald-700' : ($item->status === 'partial' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">
                                    {{ $item->status }}
                                </span>
                            </td>
                            <td class="py-3">
                                @if($item->status !== 'paid')
                                <form method="POST" action="{{ route('payables.pay', $item) }}" class="flex flex-wrap gap-1 items-center">
                                    @csrf
                                    <input type="number" min="1" step="1" name="amount" class="input py-1 text-xs w-28" value="{{ (int) $item->remaining() }}" required>
                                    <div class="select2-compact w-28">
                                        <select name="method" class="input py-1 text-xs">
                                            <option value="cash">Tunai</option>
                                            <option value="transfer">Transfer</option>
                                            <option value="qris">QRIS</option>
                                            <option value="other">Lain</option>
                                        </select>
                                    </div>
                                    <button class="btn btn-secondary text-xs px-2">Bayar</button>
                                </form>
                                @else
                                    <span class="text-xs text-emerald-600">Lunas</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-10 text-center text-slate-500">Belum ada hutang.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $items->links() }}</div>
    </div>
</div>
@endsection
