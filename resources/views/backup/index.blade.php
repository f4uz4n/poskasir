@extends('layouts.app')

@section('title', 'Backup & Restore')
@section('heading', 'Backup & Restore')
@section('subheading', 'Cadangkan dan pulihkan data toko')

@section('content')
<div class="grid lg:grid-cols-2 gap-4">
    <div class="card p-5">
        <h2 class="font-bold mb-2">Export backup</h2>
        <p class="text-sm text-slate-500 mb-4">Unduh seluruh data toko (produk, kategori, transaksi, pengaturan) dalam file JSON.</p>
        <form method="POST" action="{{ route('backup.export') }}">
            @csrf
            <button class="btn btn-primary">Download backup sekarang</button>
        </form>
    </div>

    <div class="card p-5">
        <h2 class="font-bold mb-2">Restore dari file</h2>
        <p class="text-sm text-slate-500 mb-4">Unggah file backup JSON. Mode <strong>Gabung</strong> menambah/update produk. Mode <strong>Ganti</strong> menghapus data lama lalu memulihkan penuh.</p>
        <form method="POST" action="{{ route('backup.restore') }}" enctype="multipart/form-data" class="space-y-3" onsubmit="return confirm('Yakin restore data?')">
            @csrf
            <input type="file" name="backup_file" accept=".json,application/json" class="input" required>
            <select name="mode" class="input" required>
                <option value="merge">Gabung (produk & kategori)</option>
                <option value="replace">Ganti semua (termasuk transaksi)</option>
            </select>
            <button class="btn btn-secondary w-full">Restore</button>
        </form>
    </div>
</div>

<div class="card p-5 mt-4">
    <h2 class="font-bold mb-4">Riwayat backup di server</h2>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b">
                    <th class="py-2">File</th>
                    <th class="py-2">Ukuran</th>
                    <th class="py-2">Waktu</th>
                    <th class="py-2">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($backups as $b)
                    <tr class="border-b border-slate-100">
                        <td class="py-3 font-medium">{{ $b['name'] }}</td>
                        <td class="py-3">{{ number_format($b['size'] / 1024, 1) }} KB</td>
                        <td class="py-3">{{ date('d/m/Y H:i', $b['modified']) }}</td>
                        <td class="py-3">
                            <a href="{{ route('backup.download', $b['name']) }}" class="text-brand-700 font-medium">Unduh</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-6 text-center text-slate-500">Belum ada backup tersimpan di server.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="card p-5 mt-4 border border-red-200 bg-red-50/40">
    <h2 class="font-bold mb-2 text-red-700">Format / kosongkan data</h2>
    <p class="text-sm text-slate-600 mb-3">
        Hapus data operasional toko dari server. Disarankan export backup dulu.
        Pengaturan lengkap ada di halaman Pengaturan.
    </p>
    <a href="{{ route('settings.index') }}#wipe-data-form" class="btn btn-danger">Buka zona format data</a>
</div>
@endsection
