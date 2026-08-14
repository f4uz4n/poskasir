@extends('layouts.app')

@section('title', 'Harga Langganan')
@section('heading', 'Harga & Paket Langganan')
@section('subheading', 'Tentukan harga dan durasi paket')

@section('content')
<div class="grid lg:grid-cols-3 gap-4 mb-6">
    @foreach($plans as $plan)
        <form method="POST" action="{{ route('developer.plans.update', $plan) }}" class="card p-5 space-y-3">
            @csrf
            @method('PUT')
            <div>
                <label class="text-xs text-slate-500">Nama paket</label>
                <input name="name" class="input mt-1" value="{{ $plan->name }}" required>
            </div>
            <div>
                <label class="text-xs text-slate-500">Deskripsi</label>
                <textarea name="description" class="input mt-1" rows="2">{{ $plan->description }}</textarea>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="text-xs text-slate-500">Harga (Rp)</label>
                    <input type="number" min="0" step="1" name="price" class="input mt-1" value="{{ (int) $plan->price }}" required>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Durasi (hari)</label>
                    <input type="number" min="0" name="duration_days" class="input mt-1" value="{{ $plan->duration_days }}" required>
                </div>
            </div>
            <div>
                <label class="text-xs text-slate-500">Fitur (satu baris = satu fitur)</label>
                <textarea name="features_text" class="input mt-1" rows="5">{{ implode("\n", $plan->features ?? []) }}</textarea>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="text-xs text-slate-500">Urutan</label>
                    <input type="number" min="0" name="sort_order" class="input mt-1" value="{{ $plan->sort_order }}">
                </div>
                <div class="flex flex-col justify-end gap-1 text-sm">
                    <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked($plan->is_active)> Aktif</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="is_free" value="1" @checked($plan->is_free)> Gratis</label>
                </div>
            </div>
            <div class="text-xs text-slate-400">Slug: {{ $plan->slug }}</div>
            <button class="btn btn-primary w-full">Simpan harga</button>
        </form>
    @endforeach
</div>

<div class="card p-5 max-w-xl">
    <h2 class="font-bold mb-3">Tambah paket baru</h2>
    <form method="POST" action="{{ route('developer.plans.store') }}" class="space-y-3">
        @csrf
        <input name="name" class="input" placeholder="Nama paket" required>
        <input name="slug" class="input" placeholder="Slug (opsional)">
        <textarea name="description" class="input" rows="2" placeholder="Deskripsi"></textarea>
        <div class="grid grid-cols-2 gap-2">
            <input type="number" min="0" name="price" class="input" placeholder="Harga Rp" required>
            <input type="number" min="0" name="duration_days" class="input" placeholder="Durasi hari" required>
        </div>
        <textarea name="features_text" class="input" rows="4" placeholder="Fitur, satu baris satu item"></textarea>
        <button class="btn btn-secondary w-full">Tambah paket</button>
    </form>
</div>
@endsection
