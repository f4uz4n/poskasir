@extends('layouts.app')

@section('title', 'Pengaturan')
@section('heading', 'Pengaturan')
@section('subheading', 'Toko, printer, offline, dan fitur berbayar')

@section('content')
<div class="settings-page grid grid-cols-1 xl:grid-cols-2 gap-4 max-w-6xl">
    <div class="card p-4 sm:p-5 min-w-0">
        <h2 class="font-bold mb-4">Profil toko</h2>
        @if($user->isStoreOwner())
        <form method="POST" action="{{ route('settings.update') }}" class="space-y-3" enctype="multipart/form-data">
            @csrf @method('PUT')
            <div>
                <label class="text-sm font-medium">Nama toko</label>
                <input name="store_name" class="input mt-1" value="{{ old('store_name', $settings->store_name ?? $user->store_name) }}" required>
            </div>
            <div>
                <label class="text-sm font-medium">Logo toko</label>
                <div class="mt-1 flex flex-col xs:flex-row sm:flex-row items-start gap-3">
                    @if($settings->logo_url ?? null)
                        <img id="store-logo-preview" src="{{ $settings->logo_url }}" alt="Logo toko" class="h-16 w-16 rounded-xl object-cover border border-slate-200 bg-white shrink-0">
                    @else
                        <div class="h-16 w-16 rounded-xl bg-brand-600 text-white grid place-items-center font-bold text-xl shrink-0">{{ strtoupper(substr($settings->store_name ?? 'K', 0, 1)) }}</div>
                    @endif
                    <div class="flex-1 space-y-2 min-w-0 w-full">
                        <input type="file" name="store_logo" accept="image/png,image/jpeg,image/webp" class="input w-full">
                        @if($settings->store_logo ?? null)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="remove_store_logo" value="1">
                            <span>Hapus logo saat ini</span>
                        </label>
                        @endif
                        <p class="text-xs text-slate-500">PNG/JPG/WEBP, maks 2 MB. Tampil di menu dan struk kasir.</p>
                    </div>
                </div>
                @error('store_logo')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium">Telepon</label>
                <input name="store_phone" class="input mt-1" value="{{ old('store_phone', $settings->store_phone ?? $user->phone) }}">
            </div>
            <div>
                <label class="text-sm font-medium">Alamat</label>
                <textarea name="store_address" class="input mt-1" rows="2">{{ old('store_address', $settings->store_address ?? $user->store_address) }}</textarea>
            </div>
            <div>
                <label class="text-sm font-medium">Header struk</label>
                <input name="receipt_header" class="input mt-1" value="{{ old('receipt_header', $settings->receipt_header ?? '') }}" placeholder="Nama toko / slogan singkat">
            </div>
            <div>
                <label class="text-sm font-medium">Footer struk</label>
                <textarea name="receipt_footer" class="input mt-1" rows="4" placeholder="Contoh:&#10;Terima kasih&#10;Barang yang sudah dibeli tidak dapat dikembalikan&#10;IG: @tokoanda">{{ old('receipt_footer', $settings->receipt_footer ?? '') }}</textarea>
                <p class="text-xs text-slate-500 mt-1">Bebas diisi admin. Bisa lebih dari satu baris — teks ini muncul di bawah struk kasir.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="text-sm font-medium">Pajak (%)</label>
                    <input name="tax_percent" type="number" step="0.01" class="input mt-1" value="{{ old('tax_percent', $settings->tax_percent ?? 0) }}">
                </div>
                <div>
                    <label class="text-sm font-medium">Lebar kertas</label>
                    <select name="paper_width" class="input mt-1">
                        <option value="58" @selected(($settings->paper_width ?? 58) == 58)>58mm</option>
                        <option value="80" @selected(($settings->paper_width ?? 58) == 80)>80mm</option>
                    </select>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-3">
                <div>
                    <div class="font-semibold text-sm">Login perangkat</div>
                    <p class="text-xs text-slate-500 mt-1">
                        Atur berapa perangkat yang boleh login bersamaan untuk <strong>akun pemilik</strong> dan <strong>kasir</strong>.
                        Perangkat yang sama tetap masuk otomatis tanpa login ulang.
                    </p>
                </div>
                @php
                    $maxDevices = (int) old('max_login_devices', $settings->max_login_devices ?? 1);
                @endphp
                <div>
                    <label class="text-sm font-medium">Maksimal perangkat login</label>
                    <select name="max_login_devices" class="input mt-1" data-no-select2>
                        <option value="1" @selected($maxDevices === 1)>1 — login baru keluarkan perangkat lama</option>
                        <option value="2" @selected($maxDevices === 2)>2 perangkat</option>
                        <option value="3" @selected($maxDevices === 3)>3 perangkat</option>
                        <option value="5" @selected($maxDevices === 5)>5 perangkat</option>
                        <option value="10" @selected($maxDevices === 10)>10 perangkat</option>
                        <option value="0" @selected($maxDevices === 0)>Tanpa batas</option>
                    </select>
                    @error('max_login_devices')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                @if(!empty($activeLoginSessions))
                    <div class="text-xs text-slate-600 space-y-1">
                        <div class="font-medium">Perangkat aktif (akun Anda): {{ count($activeLoginSessions) }}</div>
                        <ul class="space-y-1 max-h-28 overflow-y-auto">
                            @foreach($activeLoginSessions as $sess)
                                <li class="rounded-lg bg-white border border-slate-200 px-2 py-1.5">
                                    <span class="font-medium">{{ $sess['ip'] ?: 'IP tidak diketahui' }}</span>
                                    · {{ \Illuminate\Support\Str::limit($sess['agent'] ?: 'Perangkat', 48) }}
                                    · {{ \Carbon\Carbon::createFromTimestamp($sess['last_activity'])->diffForHumans() }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-3">
                <div>
                    <div class="font-semibold text-sm">Printer & laci uang</div>
                    <p class="text-xs text-slate-500 mt-1">
                        Pilih <strong>Bluetooth</strong> atau <strong>USB</strong>, lalu klik <strong>Deteksi printer</strong>.
                        Setelah Simpan, Kasir memakai printer ini tanpa sambungkan ulang.
                    </p>
                </div>

                <div>
                    <label class="text-sm font-medium">Jenis koneksi</label>
                    @php
                        $ptype = old('printer_type', $settings->printer_type ?: 'bluetooth');
                        if ($ptype === 'auto') {
                            $ptype = !empty($settings->extra['windows_printer']) ? 'usb' : 'bluetooth';
                        }
                    @endphp
                    <select name="printer_type" id="printer-type-select" class="input mt-1" data-no-select2>
                        <option value="bluetooth" @selected($ptype === 'bluetooth')>Bluetooth</option>
                        <option value="usb" @selected($ptype === 'usb')>USB Windows</option>
                        <option value="none" @selected($ptype === 'none')>Tidak memakai printer</option>
                    </select>
                </div>

                <input type="hidden" name="printer_profile" value="{{ old('printer_profile', $settings->extra['printer_profile'] ?? 'auto') }}">
                <input type="hidden" name="printer_baud" value="{{ old('printer_baud', $settings->extra['printer_baud'] ?? 9600) }}">
                <input type="hidden" name="printer_usb_mode" value="{{ old('printer_usb_mode', $settings->extra['printer_usb_mode'] ?? 'windows') }}">
                <input type="hidden" name="com_port" id="com-port-input" value="{{ old('com_port', $settings->extra['com_port'] ?? '') }}">
                <input type="hidden" name="usb_port" id="usb-port-input" value="{{ old('usb_port', $settings->extra['usb_port'] ?? '') }}">

                <div id="usb-windows-fields" class="{{ $ptype === 'usb' ? '' : 'hidden' }} space-y-2">
                    <div>
                        <label class="text-sm font-medium">Printer USB / port COM</label>
                        <select name="windows_printer" id="windows-printer-select" class="input mt-1" data-no-select2>
                            @php
                                $winPrinter = old('windows_printer', $settings->extra['windows_printer'] ?? $settings->extra['com_port'] ?? $settings->printer_name ?? '');
                            @endphp
                            <option value="">— Deteksi otomatis —</option>
                            @if($winPrinter && !preg_match('/onenote|pdf|fax|xps|microsoft/i', $winPrinter))
                                <option value="{{ $winPrinter }}" selected>{{ preg_match('/^COM\d+$/i', $winPrinter) ? $winPrinter.' (USB/Serial)' : $winPrinter }}</option>
                            @endif
                        </select>
                        <p class="text-xs text-slate-500 mt-1">
                            Pilih printer Windows ATAU port COM. Jika <strong>Word bisa cetak</strong> tapi KasirFlow tidak:
                            pastikan nama printer sama persis, klik <strong>Deteksi</strong>, lalu <strong>Tes cetak</strong>.
                            KasirFlow otomatis memakai driver Windows (seperti Word) untuk printer Gprinter/GP-58MB.
                            Banyak thermal (RPP/Hakpost) hanya tampil sebagai COM — pilih COM-nya.
                        </p>
                    </div>
                </div>

                <div class="rounded-xl border border-emerald-100 bg-white p-3 space-y-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <div class="text-xs text-slate-500">Printer aktif</div>
                            <div id="detected-printer-name" class="font-semibold text-sm">
                                {{ $settings->printer_name && !preg_match('/onenote|pdf|fax|xps/i', $settings->printer_name) ? $settings->printer_name : 'Belum terdeteksi' }}
                            </div>
                            <div id="detected-printer-meta" class="text-xs text-slate-500 mt-0.5">
                                Mode: {{ $ptype === 'usb' ? 'USB Windows' : ($ptype === 'bluetooth' ? 'Bluetooth' : 'Nonaktif') }}
                            </div>
                        </div>
                        <button type="button" id="btn-pair-printer" class="btn btn-primary text-sm whitespace-nowrap">Deteksi printer</button>
                    </div>
                    <input type="hidden" name="printer_name" id="printer-name-input" value="{{ old('printer_name', $settings->printer_name ?? '') }}">
                    <p id="printer-setup-status" class="text-xs text-slate-500">Pilih Bluetooth atau USB, lalu klik Deteksi printer.</p>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="printer_auto_cut" value="1" @checked(old('printer_auto_cut', $settings->extra['printer_auto_cut'] ?? false))>
                    <span>Potong kertas otomatis</span>
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="cash_drawer" value="1" @checked(old('cash_drawer', $settings->extra['cash_drawer'] ?? true))>
                    <span>Buka laci uang saat cetak struk</span>
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm font-medium">Kapan buka laci</label>
                        <select name="cash_drawer_when" class="input mt-1">
                            @php $drawerWhen = old('cash_drawer_when', $settings->extra['cash_drawer_when'] ?? 'cash'); @endphp
                            <option value="cash" @selected($drawerWhen === 'cash')>Hanya tunai</option>
                            <option value="always" @selected($drawerWhen === 'always')>Semua pembayaran</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium">Pin laci (port DK)</label>
                        <select name="cash_drawer_pin" class="input mt-1">
                            @php $drawerPin = old('cash_drawer_pin', $settings->extra['cash_drawer_pin'] ?? '2'); @endphp
                            <option value="2" @selected($drawerPin === '2')>Pin 2 (Hakpost & umum)</option>
                            <option value="5" @selected($drawerPin === '5')>Pin 5</option>
                            <option value="both" @selected($drawerPin === 'both')>Pin 2 (cadangan)</option>
                        </select>
                    </div>
                </div>
                <p class="text-xs text-slate-500">
                    Laci dibuka <strong>satu kali</strong> lewat printer yang sama. Pastikan kabel RJ11 ke port
                    <strong>DK / Cash Drawer</strong>. Untuk Hakpost biasanya Pin 2.
                </p>
                <input type="hidden" name="cash_drawer_windows_printer" id="cash-drawer-windows-select" value="{{ old('cash_drawer_windows_printer', $settings->extra['cash_drawer_windows_printer'] ?? '') }}">
                <input type="hidden" name="cash_drawer_com_port" id="cash-drawer-com-input" value="{{ old('cash_drawer_com_port', $settings->extra['cash_drawer_com_port'] ?? '') }}">

                <div class="border-t border-slate-200 pt-3 space-y-2">
                    <div class="font-semibold text-sm">Scanner barcode</div>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="scanner_enabled" value="1" @checked(old('scanner_enabled', $settings->extra['scanner_enabled'] ?? true))>
                        <span>Aktifkan scanner (USB/Bluetooth keyboard wedge)</span>
                    </label>
                    <p class="text-xs text-slate-500">Scanner tidak perlu disambungkan ulang di Kasir.</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-2">
                    <button type="button" id="btn-test-print" class="btn btn-primary flex-1 text-sm whitespace-normal">Tes cetak</button>
                    <button type="button" id="btn-test-drawer" class="btn btn-secondary flex-1 text-sm whitespace-normal">Tes buka laci</button>
                </div>
            </div>
            @if($canLockStock)
            <label class="flex items-center gap-2 text-sm rounded-xl border border-amber-200 bg-amber-50 p-3">
                <input type="checkbox" name="stock_lock_enabled" value="1" @checked($settings->stock_lock_enabled ?? false)>
                <span><strong>Kunci stok global</strong> — cegah penjualan jika stok kurang</span>
            </label>
            @endif
            <button class="btn btn-primary">Simpan pengaturan</button>
        </form>
        @else
            <p class="text-sm text-slate-500">Pengaturan toko hanya dapat diubah oleh pemilik.</p>
        @endif
    </div>

    <div class="space-y-4 min-w-0">
        <div class="card p-4 sm:p-5">
            <h2 class="font-bold mb-2">Install aplikasi offline (PWA)</h2>
            <p class="text-sm text-slate-500 mb-4">
                Klik <strong>Install aplikasi</strong> untuk pasang ke HP/laptop sekaligus mengunduh semua menu, script, dan data ke perangkat.
                Setelah selesai, aplikasi bisa dibuka offline tanpa perlu buka setiap menu satu per satu.
            </p>
            <div class="rounded-xl border border-slate-200 p-4 mb-4 space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm font-medium">Status mode offline</span>
                    <span id="offline-mode-label" class="text-sm font-semibold {{ ($settings->offline_enabled ?? false) ? 'text-emerald-600' : 'text-slate-500' }}">
                        {{ ($settings->offline_enabled ?? false) ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </div>
                <p id="install-app-hint" class="text-xs text-slate-500">Pasang ke layar HP/laptop agar bisa dibuka seperti aplikasi.</p>
                <p id="offline-install-progress" class="hidden text-xs font-medium text-brand-700 rounded-lg bg-brand-50 border border-brand-100 px-3 py-2"></p>
            </div>
            <div class="flex flex-col sm:flex-row gap-2">
                <button type="button" id="btn-install-app" class="btn btn-primary flex-1 whitespace-normal">Install aplikasi</button>
                <button type="button" id="btn-enable-offline" class="btn btn-secondary flex-1 whitespace-normal">Perbarui cache offline</button>
            </div>
            <div class="mt-4 flex flex-col sm:flex-row gap-2">
                <button type="button" id="btn-sync-now" class="btn btn-ghost flex-1 whitespace-normal">Sinkron data</button>
                <button type="button" id="btn-disable-offline" class="btn btn-ghost flex-1 whitespace-normal">Nonaktifkan</button>
            </div>
        </div>

        @if($user->isStoreOwner())
        <div class="card p-4 sm:p-5 {{ $canRemote ? '' : 'opacity-90' }}">
            <h2 class="font-bold mb-2">Pantau laporan jarak jauh @if(!$canRemote)<span class="text-xs font-normal text-amber-600">(Berbayar)</span>@endif</h2>
            <p class="text-sm text-slate-500 mb-3">Buka link khusus di HP/laptop lain untuk memantau penjualan tanpa login penuh.</p>
            @if($canRemote)
                @if($remoteUrl)
                    <div class="rounded-xl bg-slate-50 border border-slate-200 p-3 text-xs break-all mb-3">{{ $remoteUrl }}</div>
                    <div class="flex flex-col sm:flex-row flex-wrap gap-2">
                        <a href="{{ $remoteUrl }}" target="_blank" class="btn btn-primary text-sm whitespace-normal">Buka pantauan</a>
                        <form method="POST" action="{{ route('remote.regenerate') }}">@csrf<button class="btn btn-ghost text-sm w-full sm:w-auto whitespace-normal">Regenerate link</button></form>
                        <form method="POST" action="{{ route('remote.disable') }}">@csrf<button class="btn btn-ghost text-sm w-full sm:w-auto whitespace-normal">Nonaktifkan</button></form>
                    </div>
                @else
                    <form method="POST" action="{{ route('remote.enable') }}">@csrf<button class="btn btn-primary w-full sm:w-auto whitespace-normal">Aktifkan pantauan jarak jauh</button></form>
                @endif
            @else
                <a href="{{ route('subscription.index') }}" class="btn btn-secondary text-sm whitespace-normal">Upgrade untuk membuka fitur</a>
            @endif
        </div>

        <div class="card p-4 sm:p-5 {{ $canApiSync ? '' : 'opacity-90' }}">
            <h2 class="font-bold mb-2">API Sinkron @if(!$canApiSync)<span class="text-xs font-normal text-amber-600">(Berbayar)</span>@endif</h2>
            <p class="text-sm text-slate-500 mb-3">Integrasikan aplikasi eksternal (mobile, ERP, dashboard) via REST API. Mencakup produk, penjualan, pembelian, piutang, hutang, stock opname, dan laporan.</p>
            @if($canApiSync)
                @if(session('api_token_plain'))
                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 mb-4">
                        <div class="text-xs font-semibold text-amber-800 mb-2">Token baru — salin sekarang!</div>
                        <code class="block text-xs break-all bg-white rounded-lg p-3 border border-amber-100 select-all">{{ session('api_token_plain') }}</code>
                    </div>
                @endif
                <div class="rounded-xl bg-slate-50 border border-slate-200 p-4 mb-4 text-sm space-y-2">
                    <div class="flex flex-col sm:flex-row sm:justify-between gap-1">
                        <span class="text-slate-500">Status token</span>
                        <span class="font-semibold {{ ($settings->api_token ?? null) ? 'text-emerald-600' : 'text-slate-500' }}">
                            {{ ($settings->api_token ?? null) ? 'Aktif' : 'Belum dibuat' }}
                        </span>
                    </div>
                    @if($settings->api_token_created_at ?? null)
                        <div class="flex flex-col sm:flex-row sm:justify-between gap-1">
                            <span class="text-slate-500">Dibuat</span>
                            <span>{{ $settings->api_token_created_at->format('d M Y H:i') }}</span>
                        </div>
                    @endif
                    <div class="text-xs text-slate-500 pt-2 border-t break-all">
                        Header: <code class="bg-white px-1 rounded">Authorization: Bearer {token}</code>
                    </div>
                </div>
                <details class="mb-4 text-xs">
                    <summary class="cursor-pointer font-medium text-brand-700">Daftar endpoint API</summary>
                    <ul class="mt-2 space-y-1 text-slate-600 pl-4 list-disc break-all">
                        <li><code>GET /api/v1/sync/pull?since=</code> — tarik semua data</li>
                        <li><code>POST /api/v1/sync/push</code> — kirim transaksi offline</li>
                        <li><code>GET /api/v1/products</code> — daftar produk</li>
                        <li><code>GET /api/v1/transactions</code> — penjualan</li>
                        <li><code>GET /api/v1/purchases</code> — pembelian</li>
                        <li><code>GET /api/v1/receivables</code> — piutang</li>
                        <li><code>GET /api/v1/payables</code> — hutang</li>
                        <li><code>GET /api/v1/stock-opnames</code> — stock opname</li>
                        <li><code>GET /api/v1/reports/sales</code> — laporan penjualan</li>
                        <li><code>GET /api/v1/reports/stock</code> — laporan stok</li>
                        <li><code>GET /api/v1/reports/profit-loss</code> — laba rugi</li>
                        <li><code>GET /api/v1/reports/roi?year=2026</code> — ROI bulanan per tahun</li>
                    </ul>
                </details>
                <div class="flex flex-col sm:flex-row flex-wrap gap-2">
                    <form method="POST" action="{{ route('settings.api-token.generate') }}">
                        @csrf
                        <button class="btn btn-primary text-sm w-full sm:w-auto whitespace-normal">{{ ($settings->api_token ?? null) ? 'Regenerate token' : 'Buat token API' }}</button>
                    </form>
                    @if($settings->api_token ?? null)
                        <form method="POST" action="{{ route('settings.api-token.revoke') }}">
                            @csrf @method('DELETE')
                            <button class="btn btn-cancel text-sm w-full sm:w-auto whitespace-normal" onclick="return confirm('Cabut token API? Integrasi eksternal akan berhenti.')">Cabut token</button>
                        </form>
                    @endif
                </div>
            @else
                <a href="{{ route('subscription.index') }}" class="btn btn-secondary text-sm whitespace-normal">Upgrade untuk API sinkron</a>
            @endif
        </div>

        <div class="card p-4 sm:p-5">
            <h2 class="font-bold mb-2">Backup & Restore</h2>
            <p class="text-sm text-slate-500 mb-3">Cadangkan data toko atau pulihkan dari file JSON.</p>
            <a href="{{ route('backup.index') }}" class="btn btn-secondary whitespace-normal">Kelola backup</a>
        </div>

        <div class="card p-4 sm:p-5 border border-red-200 bg-red-50/40">
            <h2 class="font-bold mb-2 text-red-700">Zona berbahaya — Format data</h2>
            <p class="text-sm text-slate-600 mb-3">
                Kosongkan data operasional toko. <strong>Akun, kasir, langganan, dan profil toko tidak dihapus.</strong>
                Disarankan buat backup dulu.
            </p>

            @if($dataSummary)
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mb-4 text-xs">
                <div class="rounded-lg bg-white border border-red-100 p-2"><div class="text-slate-500">Penjualan</div><div class="font-bold">{{ number_format($dataSummary['transactions']) }}</div></div>
                <div class="rounded-lg bg-white border border-red-100 p-2"><div class="text-slate-500">Pembelian</div><div class="font-bold">{{ number_format($dataSummary['purchases']) }}</div></div>
                <div class="rounded-lg bg-white border border-red-100 p-2"><div class="text-slate-500">Piutang</div><div class="font-bold">{{ number_format($dataSummary['receivables']) }}</div></div>
                <div class="rounded-lg bg-white border border-red-100 p-2"><div class="text-slate-500">Hutang</div><div class="font-bold">{{ number_format($dataSummary['payables']) }}</div></div>
                <div class="rounded-lg bg-white border border-red-100 p-2"><div class="text-slate-500">Produk</div><div class="font-bold">{{ number_format($dataSummary['products']) }}</div></div>
                <div class="rounded-lg bg-white border border-red-100 p-2"><div class="text-slate-500">Kategori</div><div class="font-bold">{{ number_format($dataSummary['categories']) }}</div></div>
            </div>
            @endif

            @error('confirm_text')
                <div class="mb-3 text-sm text-red-600">{{ $message }}</div>
            @enderror
            @error('password')
                <div class="mb-3 text-sm text-red-600">{{ $message }}</div>
            @enderror
            @error('mode')
                <div class="mb-3 text-sm text-red-600">{{ $message }}</div>
            @enderror

            <form method="POST" action="{{ route('settings.wipe') }}" id="wipe-data-form" class="space-y-3">
                @csrf
                <div id="wipe-data-anchor"></div>
                    <label class="text-sm font-medium">Mode pengosongan</label>
                    <select name="mode" id="wipe-mode" class="input mt-1" required>
                        <option value="transactions">Kosongkan transaksi saja (produk tetap)</option>
                        <option value="all" selected>Format semua data (produk + transaksi)</option>
                    </select>
                    <p id="wipe-mode-help" class="text-xs text-slate-500 mt-1">
                        Menghapus penjualan, pembelian, piutang, hutang, stock opname, produk, dan kategori.
                    </p>
                </div>
                <div>
                    <label class="text-sm font-medium">Ketik <span id="wipe-confirm-word" class="font-bold text-red-700">FORMAT</span> untuk konfirmasi</label>
                    <input name="confirm_text" id="wipe-confirm-text" class="input mt-1" autocomplete="off" placeholder="FORMAT" required>
                </div>
                <div>
                    <label class="text-sm font-medium">Password akun Anda</label>
                    <input type="password" name="password" class="input mt-1" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-danger w-full" id="btn-wipe-data">Format / kosongkan data</button>
            </form>
            <p class="text-xs text-slate-500 mt-3">
                Setelah format, data offline di browser juga akan dibersihkan otomatis.
            </p>
        </div>
        @endif

        <div class="card p-4 sm:p-5">
            <h2 class="font-bold mb-2">Printer & Scanner</h2>
            <p class="text-sm text-slate-500 mb-3">Setelah dipasangkan di form kiri, kasir akan otomatis memakai printer tanpa klik Hubungkan lagi.</p>
            <div class="flex flex-col sm:flex-row gap-2 mt-2">
                <button type="button" id="btn-test-print-side" class="btn btn-primary flex-1 text-sm whitespace-normal">Tes cetak</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script type="module">
document.getElementById('btn-enable-offline')?.addEventListener('click', () => window.PosApp?.enableOffline());
document.getElementById('btn-disable-offline')?.addEventListener('click', () => window.PosApp?.disableOffline());
document.getElementById('btn-sync-now')?.addEventListener('click', () => window.PosApp?.syncAll());
document.getElementById('btn-install-app')?.addEventListener('click', () => window.PosApp?.installPwa());
window.PosApp?.updateInstallUi?.();

(function setupWipeForm() {
    const modeEl = document.getElementById('wipe-mode');
    const wordEl = document.getElementById('wipe-confirm-word');
    const helpEl = document.getElementById('wipe-mode-help');
    const textEl = document.getElementById('wipe-confirm-text');
    const form = document.getElementById('wipe-data-form');

    function syncWipeUi() {
        const all = modeEl?.value !== 'transactions';
        const word = all ? 'FORMAT' : 'KOSONGKAN';
        if (wordEl) wordEl.textContent = word;
        if (textEl) textEl.placeholder = word;
        if (helpEl) {
            helpEl.textContent = all
                ? 'Menghapus penjualan, pembelian, piutang, hutang, stock opname, produk, dan kategori.'
                : 'Menghapus penjualan, pembelian, piutang, hutang, dan stock opname. Produk & kategori tetap.';
        }
    }

    modeEl?.addEventListener('change', syncWipeUi);
    if (window.jQuery && modeEl) {
        jQuery(modeEl).on('change', syncWipeUi);
    }
    syncWipeUi();

    form?.addEventListener('submit', (e) => {
        const all = modeEl?.value !== 'transactions';
        const word = all ? 'FORMAT' : 'KOSONGKAN';
        const typed = (textEl?.value || '').trim().toUpperCase();
        if (typed !== word) {
            e.preventDefault();
            window.PosApp?.toast('Ketik ' + word + ' untuk konfirmasi');
            return;
        }
        const msg = all
            ? 'Yakin FORMAT SEMUA data operasional? Produk dan transaksi akan hilang permanen.'
            : 'Yakin kosongkan semua transaksi? Produk tetap ada.';
        if (!confirm(msg)) {
            e.preventDefault();
        }
    });

    @if(session('wipe_offline'))
    window.OfflineStore?.clearBusinessData?.({ keepDeviceSettings: true })
        .then(() => window.PosApp?.toast?.('Data offline di perangkat sudah dibersihkan'))
        .catch(() => {});
    @endif
})();

const typeSelect = document.getElementById('printer-type-select');
const statusEl = document.getElementById('printer-setup-status');
const detectedNameEl = document.getElementById('detected-printer-name');
const detectedMetaEl = document.getElementById('detected-printer-meta');
const printerNameInput = document.getElementById('printer-name-input');
const windowsPrinterSelect = document.getElementById('windows-printer-select');
const usbFields = document.getElementById('usb-windows-fields');

function syncUsbFields() {
    const isUsb = typeSelect?.value === 'usb';
    if (usbFields) usbFields.classList.toggle('hidden', !isUsb);
    if (detectedMetaEl) {
        const v = typeSelect?.value || 'bluetooth';
        const label = v === 'usb' ? 'USB Windows' : (v === 'none' ? 'Nonaktif' : 'Bluetooth');
        detectedMetaEl.textContent = 'Mode: ' + label;
    }
}

function fillWindowsPrinterOptions(printers = [], selected = '') {
    if (!windowsPrinterSelect || windowsPrinterSelect.tagName !== 'SELECT') return;
    const current = selected || windowsPrinterSelect.value || '';
    const keep = new Map();
    keep.set('', '— Deteksi otomatis —');
    (printers || []).forEach((p) => {
        const name = typeof p === 'string' ? p : p?.name;
        if (!name) return;
        if (/onenote|one note|pdf|fax|xps|microsoft print/i.test(name)) return;
        const label = p?.label
            || (/^COM\d+$/i.test(name) ? `${name} (USB/Serial)` : `${name}${p?.port ? ` (${p.port})` : ''}`);
        keep.set(name, label);
        if (p?.port) keep.set(`__port__${name}`, p.port);
    });
    if (current && !keep.has(current) && !/onenote|pdf|fax|xps/i.test(current)) {
        keep.set(current, /^COM\d+$/i.test(current) ? `${current} (USB/Serial)` : current);
    }
    windowsPrinterSelect.innerHTML = '';
    keep.forEach((label, value) => {
        if (String(value).startsWith('__port__')) return;
        const opt = document.createElement('option');
        opt.value = value;
        opt.textContent = label;
        const port = keep.get(`__port__${value}`);
        if (port) opt.dataset.port = port;
        if (value === current) opt.selected = true;
        windowsPrinterSelect.appendChild(opt);
    });
}

function setStatus(msg, ok = false) {
    if (!statusEl) return;
    statusEl.textContent = msg;
    statusEl.className = ok ? 'text-xs text-emerald-600' : 'text-xs text-slate-500';
}

function setDetectedUi({ name, type, message, ok = false } = {}) {
    if (name && detectedNameEl) detectedNameEl.textContent = name;
    if (type && typeSelect) typeSelect.value = type;
    syncUsbFields();
    if (name && printerNameInput) printerNameInput.value = name;
    if (type === 'usb' && name && windowsPrinterSelect) {
        fillWindowsPrinterOptions(
            Array.from(windowsPrinterSelect.options).map((o) => ({ name: o.value })).filter((p) => p.name),
            name
        );
        windowsPrinterSelect.value = name;
    }
    if (message) setStatus(message, ok);
}

function collectDeviceSettings(bt = {}) {
    let prev = {};
    try {
        prev = JSON.parse(localStorage.getItem('kasirflow_device_settings') || '{}') || {};
    } catch (_) {
        prev = {};
    }
    const type = typeSelect?.value || 'bluetooth';
    const liveId = bt.bt_device_id || window.PosPrinter?.btDevice?.id || null;
    const liveName = bt.bt_device_name || window.PosPrinter?.btDevice?.name || null;
    const paired = Boolean(
        bt.bt_paired
        || window.PosPrinter?.btDevice
        || (type === 'bluetooth' && (liveId || prev.bt_device_id || prev.bt_paired))
    );

    return {
        printer_type: type,
        printer_name: printerNameInput?.value || prev.printer_name || '',
        paper_width: Number(document.querySelector('[name="paper_width"]')?.value || 58),
        receipt_header: document.querySelector('[name="receipt_header"]')?.value || '',
        receipt_footer: document.querySelector('[name="receipt_footer"]')?.value || '',
        store_name: document.querySelector('[name="store_name"]')?.value || '',
        store_address: document.querySelector('[name="store_address"]')?.value || '',
        store_phone: document.querySelector('[name="store_phone"]')?.value || '',
        logo_url: document.getElementById('store-logo-preview')?.src || '',
        scanner_enabled: Boolean(document.querySelector('[name="scanner_enabled"]')?.checked),
        bt_paired: type === 'bluetooth' ? paired : false,
        bt_device_id: type === 'bluetooth' ? (liveId || prev.bt_device_id || null) : null,
        bt_device_name: type === 'bluetooth' ? (liveName || prev.bt_device_name || printerNameInput?.value || null) : null,
        printer_setup_done: type !== 'none',
        extra: {
            printer_profile: document.querySelector('[name="printer_profile"]')?.value || 'auto',
            printer_baud: Number(document.querySelector('[name="printer_baud"]')?.value || 9600),
            printer_usb_mode: document.querySelector('[name="printer_usb_mode"]')?.value || 'windows',
            windows_printer: windowsPrinterSelect?.value || '',
            com_port: (() => {
                const v = windowsPrinterSelect?.value || document.getElementById('com-port-input')?.value || '';
                return /^COM\d+$/i.test(v) ? v.toUpperCase() : (document.getElementById('com-port-input')?.value || '');
            })(),
            usb_port: (document.getElementById('usb-port-input')?.value || '').trim().toUpperCase(),
            printer_auto_cut: Boolean(document.querySelector('[name="printer_auto_cut"]')?.checked),
            cash_drawer: Boolean(document.querySelector('[name="cash_drawer"]')?.checked),
            cash_drawer_when: document.querySelector('[name="cash_drawer_when"]')?.value || 'cash',
            cash_drawer_pin: document.querySelector('[name="cash_drawer_pin"]')?.value || '2',
            cash_drawer_windows_printer: document.getElementById('cash-drawer-windows-select')?.value || '',
            cash_drawer_com_port: (document.getElementById('cash-drawer-com-input')?.value || '').trim().toUpperCase(),
            scanner_enabled: Boolean(document.querySelector('[name="scanner_enabled"]')?.checked),
        },
    };
}

function applyFormSettings() {
    const data = collectDeviceSettings({
        bt_paired: Boolean(window.PosPrinter?.btDevice),
        bt_device_id: window.PosPrinter?.btDevice?.id || null,
        bt_device_name: window.PosPrinter?.btDevice?.name || null,
    });
    window.PosPrinter?.setSettings(data);
    window.OfflineStore?.saveDeviceSettings(data);
    return data;
}

async function persistPrinterToServer(data = null) {
    const payload = data || applyFormSettings();
    const url = window.POS_CONFIG?.routes?.settingsPrinter;
    if (!url) return false;
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': window.POS_CONFIG.csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                printer_type: payload.printer_type,
                printer_name: payload.printer_name,
                windows_printer: payload.extra?.windows_printer || payload.printer_name || '',
                com_port: payload.extra?.com_port || '',
                printer_usb_mode: payload.extra?.printer_usb_mode || 'windows',
                paper_width: payload.paper_width || 58,
                printer_auto_cut: Boolean(payload.extra?.printer_auto_cut),
                cash_drawer: payload.extra?.cash_drawer !== false,
                cash_drawer_when: payload.extra?.cash_drawer_when || 'cash',
                cash_drawer_pin: payload.extra?.cash_drawer_pin || '2',
            }),
        });
        const json = await res.json().catch(() => ({}));
        return Boolean(res.ok && json.success);
    } catch (_) {
        return false;
    }
}

async function pairPrinter() {
    const preferType = typeSelect?.value || 'bluetooth';
    if (preferType === 'none') {
        setStatus('Printer dimatikan. Pilih Bluetooth atau USB untuk mendeteksi.');
        return;
    }
    applyFormSettings();
    setStatus(preferType === 'usb' ? 'Mendeteksi printer USB…' : 'Mendeteksi printer Bluetooth…');
    try {
        const result = await window.PosPrinter.detectAndConnect({ allowPrompt: true, preferType });
        if (!result?.ok) {
            if (preferType === 'usb' && result?.printers) {
                fillWindowsPrinterOptions(result.printers, windowsPrinterSelect?.value || '');
            }
            setStatus(result?.message || 'Printer belum terdeteksi');
            window.PosApp?.toast(result?.message || 'Printer belum terdeteksi');
            return;
        }
        if (result.type === 'usb' && result.printers) {
            fillWindowsPrinterOptions(result.printers, result.name);
        }
        setDetectedUi({
            name: result.name,
            type: result.type,
            message: result.message + '. Menyimpan ke toko…',
            ok: true,
        });
        const saved = applyFormSettings();
        const okServer = await persistPrinterToServer(saved);
        setDetectedUi({
            name: result.name,
            type: result.type,
            message: okServer
                ? (result.message + '. Tersimpan — Kasir siap memakai printer ini.')
                : (result.message + '. Tersimpan di perangkat. Klik Simpan pengaturan juga.'),
            ok: true,
        });
        window.PosApp?.toast(okServer ? 'Printer tersimpan untuk Kasir' : (result.message || 'Printer terdeteksi'));
    } catch (e) {
        setStatus(e.message || 'Gagal mendeteksi printer');
        window.PosApp?.toast(e.message || 'Gagal mendeteksi printer');
    }
}

async function ensurePrinterReady() {
    applyFormSettings();
    if (window.PosPrinter.isConnected()) return true;
    const preferType = typeSelect?.value || 'bluetooth';
    const result = await window.PosPrinter.detectAndConnect({ allowPrompt: true, preferType });
    if (result?.ok) {
        if (result.type === 'usb' && result.printers) {
            fillWindowsPrinterOptions(result.printers, result.name);
        }
        setDetectedUi({ name: result.name, type: result.type, message: result.message, ok: true });
        applyFormSettings();
        return true;
    }
    throw new Error(result?.message || 'Printer belum siap');
}

async function testPrint() {
    try {
        await ensurePrinterReady();
        await persistPrinterToServer();
        await window.PosPrinter.printTest();
        window.PosApp?.toast('Struk tes dikirim ke printer');
    } catch (e) {
        window.PosApp?.toast(e.message || 'Gagal tes cetak');
    }
}

async function testDrawer() {
    try {
        await ensurePrinterReady();
        await persistPrinterToServer();
        await window.PosPrinter.openCashDrawer({ force: true });
        window.PosApp?.toast('Perintah buka laci dikirim. Jika tidak terbuka: cek kabel RJ11 dan coba Pin 5.');
    } catch (e) {
        window.PosApp?.toast(e.message || 'Gagal buka laci');
    }
}

typeSelect?.addEventListener('change', () => {
    syncUsbFields();
    applyFormSettings();
    const v = typeSelect.value;
    if (v === 'usb') {
        setStatus('Pilih printer USB di daftar (jika ada), lalu klik Deteksi printer.');
        loadPosPrinters();
    } else if (v === 'bluetooth') {
        setStatus('Nyalakan printer Bluetooth, lalu klik Deteksi printer.');
    } else {
        setStatus('Printer dimatikan.');
    }
});

windowsPrinterSelect?.addEventListener('change', () => {
    if (windowsPrinterSelect.value && printerNameInput) {
        printerNameInput.value = windowsPrinterSelect.value;
        if (detectedNameEl) detectedNameEl.textContent = windowsPrinterSelect.value;
    }
    const selected = windowsPrinterSelect?.selectedOptions?.[0];
    const port = selected?.dataset?.port || '';
    if (/^USB\d+$/i.test(port)) {
        const usbInput = document.getElementById('usb-port-input');
        if (usbInput) usbInput.value = port.toUpperCase();
    }
    applyFormSettings();
});

document.getElementById('btn-pair-printer')?.addEventListener('click', pairPrinter);
document.getElementById('btn-test-print')?.addEventListener('click', testPrint);
document.getElementById('btn-test-drawer')?.addEventListener('click', testDrawer);
document.getElementById('btn-test-print-side')?.addEventListener('click', testPrint);

async function loadPosPrinters() {
    if (!window.POS_CONFIG?.routes?.printerDevices) return;
    try {
        const res = await fetch(window.POS_CONFIG.routes.printerDevices, { headers: { Accept: 'application/json' } });
        const json = await res.json();
        if (!json.success) return;
        const list = [
            ...(json.pos_printers || []),
            ...((json.com_ports || []).map((c) => ({ name: c, port: c, driver: 'COM Serial', label: `${c} (USB/Serial)` }))),
        ];
        // unique by name
        const uniq = [];
        const seen = new Set();
        list.forEach((p) => {
            const n = String(p.name || '').toUpperCase();
            if (!n || seen.has(n)) return;
            seen.add(n);
            uniq.push(p);
        });
        fillWindowsPrinterOptions(uniq, windowsPrinterSelect?.value || json.suggested?.name || json.saved?.com_port || '');
        if (json.suggested?.port && /^USB\d+$/i.test(json.suggested.port)) {
            const usbInput = document.getElementById('usb-port-input');
            if (usbInput) usbInput.value = String(json.suggested.port).toUpperCase();
        }
        return json;
    } catch (_) {
        return null;
    }
}

syncUsbFields();

(async function bootPrinterStatus() {
    try {
        applyFormSettings();
        const preferType = typeSelect?.value || 'bluetooth';
        if (preferType === 'usb') {
            await loadPosPrinters();
        }
        if (preferType === 'none') {
            setStatus('Printer dimatikan di pengaturan.');
            return;
        }
        // Silent detect sesuai pilihan (tanpa dialog BT jika belum pernah dipasangkan)
        const result = await window.PosPrinter.detectAndConnect({ allowPrompt: false, preferType });
        if (result?.ok) {
            if (result.type === 'usb' && result.printers) {
                fillWindowsPrinterOptions(result.printers, result.name);
            }
            setDetectedUi({
                name: result.name,
                type: result.type,
                message: result.message + '. Siap dipakai di Kasir setelah Simpan.',
                ok: true,
            });
            applyFormSettings();
            return;
        }
        const local = await window.OfflineStore?.getDeviceSettings?.();
        if (local?.printer_name || local?.bt_device_name) {
            const t = local.printer_type === 'usb' || local.printer_type === 'bluetooth' || local.printer_type === 'none'
                ? local.printer_type
                : (local.bt_paired ? 'bluetooth' : preferType);
            setDetectedUi({
                name: local.printer_name || local.bt_device_name,
                type: t,
                message: 'Printer tersimpan di perangkat ini. Klik Deteksi printer jika ingin refresh.',
                ok: true,
            });
            return;
        }
        if (preferType === 'usb') {
            setStatus(result?.message || 'Printer USB kasir belum ditemukan. Tancapkan printer lalu klik Deteksi.');
        } else {
            setStatus('Nyalakan printer Bluetooth, lalu klik Deteksi printer.');
        }
    } catch (_) {
        setStatus('Pilih Bluetooth atau USB, lalu klik Deteksi printer.');
    }
})();

document.querySelector('form')?.addEventListener('submit', () => {
    // Pastikan nama USB/COM ikut tersimpan ke printer_name sebelum POST
    if (typeSelect?.value === 'usb' && windowsPrinterSelect?.value) {
        if (printerNameInput) printerNameInput.value = windowsPrinterSelect.value;
        if (detectedNameEl) detectedNameEl.textContent = windowsPrinterSelect.value;
        const comInput = document.getElementById('com-port-input');
        if (comInput) {
            comInput.value = /^COM\d+$/i.test(windowsPrinterSelect.value)
                ? windowsPrinterSelect.value.toUpperCase()
                : '';
        }
    }
    applyFormSettings();
});
</script>
@endpush
