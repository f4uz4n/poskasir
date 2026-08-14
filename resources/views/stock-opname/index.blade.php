@extends('layouts.app')

@section('title', 'Stock Opname')
@section('heading', 'Stock Opname')
@section('subheading', 'Penyesuaian stok fisik dengan sistem')

@section('content')
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
    <p class="text-sm text-slate-500">Hitung stok fisik, bandingkan dengan sistem, lalu sesuaikan.</p>
    <a href="{{ route('stock-opname.create') }}" class="btn btn-primary">+ Stock Opname Baru</a>
</div>

<div class="card p-4 sm:p-5">
    <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[640px]">
            <thead>
                <tr class="text-left text-slate-500 border-b">
                    <th class="py-2 pr-2">Kode</th>
                    <th class="py-2 pr-2">Judul</th>
                    <th class="py-2 pr-2">Item</th>
                    <th class="py-2 pr-2">Status</th>
                    <th class="py-2 pr-2">Oleh</th>
                    <th class="py-2">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($opnames as $op)
                    <tr class="border-b border-slate-100">
                        <td class="py-3 pr-2 font-mono text-xs">{{ $op->code }}</td>
                        <td class="py-3 pr-2">
                            <div class="font-medium">{{ $op->title }}</div>
                            <div class="text-xs text-slate-400">{{ $op->created_at->format('d/m/Y H:i') }}</div>
                        </td>
                        <td class="py-3 pr-2">{{ $op->items_count }}</td>
                        <td class="py-3 pr-2">
                            <span class="px-2 py-1 rounded-full text-xs
                                {{ $op->status === 'completed' ? 'bg-emerald-100 text-emerald-700' : ($op->status === 'draft' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600') }}">
                                {{ $op->status }}
                            </span>
                        </td>
                        <td class="py-3 pr-2 text-xs">{{ $op->creator?->name ?? '-' }}</td>
                        <td class="py-3">
                            <a href="{{ route('stock-opname.show', $op) }}" class="text-brand-700 font-medium">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-10 text-center text-slate-500">Belum ada stock opname.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $opnames->links() }}</div>
</div>
@endsection
