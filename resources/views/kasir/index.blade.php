@extends('layouts.app')

@section('title', 'Akun Staff')
@section('heading', 'Akun Staff')
@section('subheading', 'Kelola akun kasir, keuangan, dan administrator')

@section('content')
@php
    $roleLabels = [
        'kasir' => 'Kasir',
        'keuangan' => 'Keuangan',
        'administrator' => 'Administrator',
    ];
@endphp

<div class="grid lg:grid-cols-3 gap-4">
    <div class="card p-5 lg:col-span-2">
        <h2 class="font-bold mb-4">Daftar akun staff</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b">
                        <th class="py-2">Nama</th>
                        <th class="py-2">Email</th>
                        <th class="py-2">Peran</th>
                        <th class="py-2">Status</th>
                        <th class="py-2">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($staff as $member)
                        <tr class="border-b border-slate-100 align-top">
                            <td class="py-3 font-medium">{{ $member->name }}</td>
                            <td class="py-3">{{ $member->email }}</td>
                            <td class="py-3">
                                <span class="px-2 py-1 rounded-full text-xs bg-sky-100 text-sky-700">
                                    {{ $roleLabels[$member->role] ?? $member->roleLabel() }}
                                </span>
                            </td>
                            <td class="py-3">
                                <span class="px-2 py-1 rounded-full text-xs {{ $member->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $member->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="py-3">
                                <details>
                                    <summary class="cursor-pointer text-brand-700 font-medium">Edit</summary>
                                    <form method="POST" action="{{ route('kasir.update', $member) }}" class="mt-3 space-y-2 min-w-[240px]">
                                        @csrf @method('PUT')
                                        <input name="name" value="{{ $member->name }}" class="input" required>
                                        <input name="phone" value="{{ $member->phone }}" class="input" placeholder="No. HP">
                                        <select name="role" class="input" required>
                                            @foreach($staffRoles as $role)
                                                <option value="{{ $role }}" @selected($member->role === $role)>{{ $roleLabels[$role] ?? $role }}</option>
                                            @endforeach
                                        </select>
                                        <input type="password" name="password" class="input" placeholder="Password baru (opsional)">
                                        <input type="password" name="password_confirmation" class="input" placeholder="Konfirmasi password">
                                        <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="is_active" value="1" @checked($member->is_active)> Aktif</label>
                                        <button class="btn btn-primary w-full text-xs">Simpan</button>
                                    </form>
                                    <form method="POST" action="{{ route('kasir.destroy', $member) }}" class="mt-2" onsubmit="return confirm('Hapus akun staff ini?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-danger w-full text-xs">Hapus</button>
                                    </form>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-slate-500">Belum ada akun staff.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card p-5">
        <h2 class="font-bold mb-3">Daftarkan staff baru</h2>
        <form method="POST" action="{{ route('kasir.store') }}" class="space-y-3">
            @csrf
            <input name="name" class="input" placeholder="Nama lengkap" required>
            <input type="email" name="email" class="input" placeholder="Email login" required>
            <input name="phone" class="input" placeholder="No. HP">
            <select name="role" class="input" required>
                <option value="">Pilih peran</option>
                @foreach($staffRoles as $role)
                    <option value="{{ $role }}" @selected(old('role') === $role)>{{ $roleLabels[$role] ?? $role }}</option>
                @endforeach
            </select>
            <input type="password" name="password" class="input" placeholder="Password" required>
            <input type="password" name="password_confirmation" class="input" placeholder="Konfirmasi password" required>
            <button class="btn btn-primary w-full">Daftarkan</button>
        </form>
        <div class="text-xs text-slate-500 mt-3 space-y-1">
            <p><strong>Kasir</strong> — akses POS dan pembatalan transaksi (butuh password pimpinan).</p>
            <p><strong>Keuangan</strong> — riwayat transaksi, piutang/hutang, laporan, pembatalan.</p>
            <p><strong>Administrator</strong> — inventori, keuangan, laporan, pengaturan, kelola akun staff.</p>
        </div>
    </div>
</div>
@endsection
