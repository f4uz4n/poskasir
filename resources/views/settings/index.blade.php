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
                    <div class="font-semibold text-sm">Printer kasir</div>
                    <p class="text-xs text-slate-500 mt-1">Atur sekali di sini. Di halaman Kasir printer akan terhubung otomatis — tidak perlu klik Hubungkan lagi.</p>
                </div>
                <div>
                    <label class="text-sm font-medium">Cara cetak</label>
                    <select name="printer_type" id="printer-type-select" class="input mt-1">
                        <option value="bluetooth" @selected(($settings->printer_type ?? '') === 'bluetooth')>Bluetooth (RPP02N)</option>
                        <option value="usb" @selected(($settings->printer_type ?? '') === 'usb')>USB Windows</option>
                        <option value="none" @selected(($settings->printer_type ?? '') === 'none')>Tidak memakai printer</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium">Nama printer</label>
                    <input name="printer_name" class="input mt-1" value="{{ old('printer_name', $settings->printer_name ?? '') }}" placeholder="RPP02N">
                </div>
                <input type="hidden" name="printer_profile" value="{{ old('printer_profile', $settings->extra['printer_profile'] ?? 'rpp02n') }}">
                <input type="hidden" name="printer_baud" value="{{ old('printer_baud', $settings->extra['printer_baud'] ?? 9600) }}">
                <input type="hidden" name="printer_usb_mode" value="{{ old('printer_usb_mode', $settings->extra['printer_usb_mode'] ?? 'windows') }}">
                <div id="usb-windows-fields" class="{{ ($settings->printer_type ?? '') === 'usb' ? '' : 'hidden' }} space-y-3">
                    <div>
                        <label class="text-sm font-medium">Printer Windows</label>
                        <select name="windows_printer" id="windows-printer-select" class="input mt-1">
                            @php $winPrinter = old('windows_printer', $settings->extra['windows_printer'] ?? $settings->printer_name ?? ''); @endphp
                            <option value="">Otomatis deteksi</option>
                            @if($winPrinter)
                                <option value="{{ $winPrinter }}" selected>{{ $winPrinter }}</option>
                            @endif
                        </select>
                    </div>
                    <input type="hidden" name="com_port" id="com-port-input" value="{{ old('com_port', $settings->extra['com_port'] ?? '') }}">
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
                            @php $drawerPin = old('cash_drawer_pin', $settings->extra['cash_drawer_pin'] ?? 'both'); @endphp
                            <option value="both" @selected($drawerPin === 'both')>Pin 2 dan 5</option>
                            <option value="2" @selected($drawerPin === '2')>Pin 2 (umum)</option>
                            <option value="5" @selected($drawerPin === '5')>Pin 5</option>
                        </select>
                    </div>
                </div>
                <p class="text-xs text-slate-500">
                    Perintah buka laci dikirim ke printer yang sama saat cetak struk (termasuk Bluetooth).
                    Pastikan kabel RJ11 masuk ke port <strong>DK / Cash Drawer</strong> di printer (bukan port charge/USB),
                    dan laci mendapat daya (biasanya dari printer).
                </p>
                <div>
                    <label class="text-sm font-medium">Printer Windows untuk buka laci</label>
                    <select name="cash_drawer_windows_printer" id="cash-drawer-windows-select" class="input mt-1">
                        @php $drawerWin = old('cash_drawer_windows_printer', $settings->extra['cash_drawer_windows_printer'] ?? ''); @endphp
                        <option value="">— Pilih printer USB yang kabel RJ11 laci terpasang —</option>
                        @if($drawerWin)
                            <option value="{{ $drawerWin }}" selected>{{ $drawerWin }}</option>
                        @endif
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium">Atau Port COM trigger laci</label>
                    <input name="cash_drawer_com_port" id="cash-drawer-com-input" class="input mt-1" placeholder="COM3" value="{{ old('cash_drawer_com_port', $settings->extra['cash_drawer_com_port'] ?? '') }}">
                    <p class="text-xs text-slate-500 mt-1">Isi jika laci/adapter muncul sebagai COM di Device Manager. Contoh: COM3</p>
                </div>
                <p class="text-xs text-amber-700">
                    Setelah ubah pin/port, klik <strong>Tes buka laci</strong> dulu. Jika belum terbuka, ganti Pin ke
                    <strong>Pin 2</strong> lalu tes lagi. Opsi printer Windows / COM di bawah hanya bila laci menempel di perangkat lain.
                </p>

                <div class="border-t border-slate-200 pt-3 space-y-2">
                    <div class="font-semibold text-sm">Scanner barcode</div>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="scanner_enabled" value="1" @checked(old('scanner_enabled', $settings->extra['scanner_enabled'] ?? true))>
                        <span>Aktifkan scanner (USB/Bluetooth keyboard wedge)</span>
                    </label>
                    <p class="text-xs text-slate-500">Scanner tidak perlu disambungkan ulang di Kasir. Pengaturan disimpan offline di browser ini.</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-2">
                    <button type="button" id="btn-pair-printer" class="btn btn-secondary flex-1 text-sm whitespace-normal">Pasangkan printer</button>
                    <button type="button" id="btn-test-print" class="btn btn-primary flex-1 text-sm whitespace-normal">Tes cetak</button>
                    <button type="button" id="btn-test-drawer" class="btn btn-secondary flex-1 text-sm whitespace-normal">Tes buka laci</button>
                </div>
                <p id="printer-setup-status" class="text-xs text-slate-500">Status: belum dipasangkan di browser ini.</p>
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
const usbFields = document.getElementById('usb-windows-fields');
const statusEl = document.getElementById('printer-setup-status');

function syncUsbFields() {
    if (!usbFields || !typeSelect) return;
    usbFields.classList.toggle('hidden', typeSelect.value !== 'usb');
    if (typeSelect.value === 'usb') {
        usbFields.querySelectorAll('select').forEach((el) => window.reinitSelect2?.(el));
    }
}
if (window.jQuery && typeSelect) {
    jQuery(typeSelect).on('change', syncUsbFields);
} else {
    typeSelect?.addEventListener('change', syncUsbFields);
}
syncUsbFields();

function setStatus(msg, ok = false) {
    if (!statusEl) return;
    statusEl.textContent = msg;
    statusEl.className = ok ? 'text-xs text-emerald-600' : 'text-xs text-slate-500';
}

function collectDeviceSettings(bt = {}) {
    return {
        printer_type: typeSelect?.value || 'bluetooth',
        printer_name: document.querySelector('[name="printer_name"]')?.value || '',
        paper_width: Number(document.querySelector('[name="paper_width"]')?.value || 58),
        receipt_header: document.querySelector('[name="receipt_header"]')?.value || '',
        receipt_footer: document.querySelector('[name="receipt_footer"]')?.value || '',
        store_name: document.querySelector('[name="store_name"]')?.value || '',
        store_address: document.querySelector('[name="store_address"]')?.value || '',
        store_phone: document.querySelector('[name="store_phone"]')?.value || '',
        logo_url: document.getElementById('store-logo-preview')?.src || '',
        scanner_enabled: Boolean(document.querySelector('[name="scanner_enabled"]')?.checked),
        bt_paired: Boolean(bt.bt_paired),
        bt_device_id: bt.bt_device_id || null,
        bt_device_name: bt.bt_device_name || null,
        extra: {
            printer_profile: document.querySelector('[name="printer_profile"]')?.value || 'rpp02n',
            printer_baud: Number(document.querySelector('[name="printer_baud"]')?.value || 9600),
            printer_usb_mode: document.querySelector('[name="printer_usb_mode"]')?.value || 'windows',
            windows_printer: document.getElementById('windows-printer-select')?.value || '',
            com_port: document.getElementById('com-port-input')?.value || '',
            printer_auto_cut: Boolean(document.querySelector('[name="printer_auto_cut"]')?.checked),
            cash_drawer: Boolean(document.querySelector('[name="cash_drawer"]')?.checked),
            cash_drawer_when: document.querySelector('[name="cash_drawer_when"]')?.value || 'cash',
            cash_drawer_pin: document.querySelector('[name="cash_drawer_pin"]')?.value || 'both',
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

async function pairPrinter() {
    applyFormSettings();
    const type = typeSelect?.value || 'bluetooth';
    try {
        if (type === 'bluetooth') {
            await window.PosPrinter.connectBluetooth();
            const name = window.PosPrinter.btDevice?.name || '';
            const nameInput = document.querySelector('[name="printer_name"]');
            if (name && nameInput && !nameInput.value.trim()) nameInput.value = name;
            applyFormSettings();
            window.OfflineStore?.saveDeviceSettings(collectDeviceSettings({
                bt_paired: true,
                bt_device_id: window.PosPrinter.btDevice?.id || null,
                bt_device_name: name || null,
            }));
            setStatus('Bluetooth terpasang: ' + (name || 'OK') + '. Pengaturan disimpan offline di browser. Klik Simpan, lalu buka Kasir — tidak perlu sambungkan ulang.', true);
            window.PosApp?.toast('Printer Bluetooth dipasangkan (offline OK)');
        } else if (type === 'usb') {
            await window.PosPrinter.connectUsb();
            applyFormSettings();
            setStatus('USB Windows siap: ' + (window.PosPrinter.windowsPrinter || 'otomatis') + '. Simpan pengaturan.', true);
            window.PosApp?.toast('Printer USB siap');
        } else {
            setStatus('Printer dimatikan.');
        }
    } catch (e) {
        setStatus(e.message || 'Gagal memasangkan printer');
        window.PosApp?.toast(e.message || 'Gagal memasangkan printer');
    }
}

async function testPrint() {
    applyFormSettings();
    try {
        const type = typeSelect?.value || 'bluetooth';
        if (!window.PosPrinter.isConnected()) {
            if (type === 'bluetooth') {
                const ok = await window.PosPrinter.reconnectBluetooth();
                if (!ok) await window.PosPrinter.connectBluetooth();
            } else if (type === 'usb') {
                await window.PosPrinter.connectUsb();
            }
        }
        await window.PosPrinter.printTest();
        window.PosApp?.toast('Struk tes dikirim ke printer');
    } catch (e) {
        window.PosApp?.toast(e.message || 'Gagal tes cetak');
    }
}

async function testDrawer() {
    applyFormSettings();
    try {
        const type = typeSelect?.value || 'bluetooth';
        if (!window.PosPrinter.isConnected()) {
            if (type === 'bluetooth') {
                const ok = await window.PosPrinter.reconnectBluetooth();
                if (!ok) await window.PosPrinter.connectBluetooth();
            } else if (type === 'usb') {
                await window.PosPrinter.connectUsb();
            }
        }
        await window.PosPrinter.openCashDrawer({ force: true });
        window.PosApp?.toast('Perintah buka laci dikirim ke printer. Jika tidak terbuka: cek kabel RJ11, daya laci, dan coba Pin 2.');
    } catch (e) {
        window.PosApp?.toast(e.message || 'Gagal buka laci');
    }
}

document.getElementById('btn-pair-printer')?.addEventListener('click', pairPrinter);
document.getElementById('btn-test-print')?.addEventListener('click', testPrint);
document.getElementById('btn-test-drawer')?.addEventListener('click', testDrawer);
document.getElementById('btn-test-print-side')?.addEventListener('click', testPrint);

(async function bootPrinterStatus() {
    try {
        applyFormSettings();
        const ok = await window.PosPrinter.reconnectBluetooth();
        if (ok) {
            setStatus('Bluetooth tersimpan offline di browser: ' + (window.PosPrinter.btDevice?.name || 'OK') + '. Kasir memakai ini tanpa sambungkan ulang.', true);
            return;
        }
        if (window.OfflineStore) {
            const local = await window.OfflineStore.getDeviceSettings();
            if (local?.bt_paired) {
                setStatus('Printer sudah dipasangkan offline (' + (local.bt_device_name || local.printer_name || 'BT') + '). Siap dipakai di Kasir.', true);
                return;
            }
        }
    } catch (_) {}
    setStatus('Belum ada printer Bluetooth tersimpan. Klik Pasangkan printer sekali, Simpan, lalu buka Kasir dengan URL yang sama.');
})();

// Saat form disimpan (submit), cache dulu ke offline sebelum request server
document.querySelector('form')?.addEventListener('submit', () => {
    applyFormSettings();
});

(async function loadWindowsPrinters() {
    const selects = [
        document.getElementById('windows-printer-select'),
        document.getElementById('cash-drawer-windows-select'),
    ].filter(Boolean);
    if (!selects.length || !window.POS_CONFIG?.routes?.printerDevices) return;
    try {
        const res = await fetch(window.POS_CONFIG.routes.printerDevices, { headers: { Accept: 'application/json' } });
        const json = await res.json();
        if (!json.success) return;
        selects.forEach((select) => {
            const current = select.value;
            const names = new Set();
            const isDrawer = select.id === 'cash-drawer-windows-select';
            select.innerHTML = isDrawer
                ? '<option value="">— Pilih printer USB yang kabel RJ11 laci terpasang —</option>'
                : '<option value="">Otomatis deteksi</option>';
            const rows = (json.printers || []).filter((p) => {
                if (!isDrawer) return true;
                const name = String(p.name || '');
                return !/pdf|onenote|fax|xps|microsoft/i.test(name);
            });
            if (isDrawer && !rows.length) {
                const opt = document.createElement('option');
                opt.disabled = true;
                opt.textContent = 'Belum ada printer USB di Windows — colok printer meja + install driver';
                select.appendChild(opt);
            }
            rows.forEach((p) => {
                if (!p.name || names.has(p.name)) return;
                names.add(p.name);
                const opt = document.createElement('option');
                opt.value = p.name;
                opt.textContent = p.port ? `${p.name} (${p.port})` : p.name;
                const saved = isDrawer
                    ? json.saved?.cash_drawer_windows_printer
                    : json.saved?.windows_printer;
                if (p.name === current || p.name === saved) opt.selected = true;
                select.appendChild(opt);
            });
            window.reinitSelect2?.(select);
        });
    } catch (_) {}
})();
</script>
@endpush
