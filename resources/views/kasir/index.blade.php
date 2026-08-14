@extends('layouts.app')

@section('title', 'Akun Kasir')
@section('heading', 'Akun Kasir')
@section('subheading', 'Kelola akun kasir toko (paket berbayar)')

@section('content')
<div class="grid lg:grid-cols-3 gap-4">
    <div class="card p-5 lg:col-span-2">
        <h2 class="font-bold mb-4">Daftar kasir</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b">
                        <th class="py-2">Nama</th>
                        <th class="py-2">Email</th>
                        <th class="py-2">Status</th>
                        <th class="py-2">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cashiers as $kasir)
                        <tr class="border-b border-slate-100 align-top">
                            <td class="py-3 font-medium">{{ $kasir->name }}</td>
                            <td class="py-3">{{ $kasir->email }}</td>
                            <td class="py-3">
                                <span class="px-2 py-1 rounded-full text-xs {{ $kasir->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $kasir->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="py-3">
                                <details>
                                    <summary class="cursor-pointer text-brand-700 font-medium">Edit</summary>
                                    <form method="POST" action="{{ route('kasir.update', $kasir) }}" class="mt-3 space-y-2 min-w-[220px]">
                                        @csrf @method('PUT')
                                        <input name="name" value="{{ $kasir->name }}" class="input" required>
                                        <input name="phone" value="{{ $kasir->phone }}" class="input" placeholder="No. HP">
                                        <input type="password" name="password" class="input" placeholder="Password baru (opsional)">
                                        <input type="password" name="password_confirmation" class="input" placeholder="Konfirmasi password">
                                        <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="is_active" value="1" @checked($kasir->is_active)> Aktif</label>
                                        <button class="btn btn-primary w-full text-xs">Simpan</button>
                                    </form>
                                    <form method="POST" action="{{ route('kasir.destroy', $kasir) }}" class="mt-2" onsubmit="return confirm('Hapus akun kasir?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-danger w-full text-xs">Hapus</button>
                                    </form>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-8 text-center text-slate-500">Belum ada akun kasir.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card p-5">
        <h2 class="font-bold mb-3">Daftarkan kasir baru</h2>
        <form method="POST" action="{{ route('kasir.store') }}" class="space-y-3">
            @csrf
            <input name="name" class="input" placeholder="Nama kasir" required>
            <input type="email" name="email" class="input" placeholder="Email login" required>
            <input name="phone" class="input" placeholder="No. HP">
            <input type="password" name="password" class="input" placeholder="Password" required>
            <input type="password" name="password_confirmation" class="input" placeholder="Konfirmasi password" required>
            <button class="btn btn-primary w-full">Daftarkan</button>
        </form>
        <p class="text-xs text-slate-500 mt-3">Kasir dapat login dan mengoperasikan POS toko Anda.</p>
    </div>
</div>
@endsection
