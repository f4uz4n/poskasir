<?php

namespace App\Http\Controllers;

use App\Services\OfflinePrecacheService;
use App\Services\StoreDataWipeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SettingController extends Controller
{
    public function index(StoreDataWipeService $wipe)
    {
        $user = Auth::user();
        $owner = $user->storeOwner();
        $settings = $owner->storeSetting;
        $canRemote = $user->hasFeature('remote_laporan');
        $canLockStock = $user->hasFeature('kunci_stok');
        $canApiSync = $user->hasFeature('api_sync');
        $remoteUrl = ($settings?->remote_monitor_enabled && $settings?->remote_monitor_token)
            ? url('/monitor/'.$settings->remote_monitor_token)
            : null;
        $dataSummary = $user->isStoreOwner()
            ? $wipe->summary((int) $owner->id)
            : null;

        return view('settings.index', compact(
            'settings',
            'user',
            'owner',
            'canRemote',
            'canLockStock',
            'canApiSync',
            'remoteUrl',
            'dataSummary',
        ));
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->isStoreOwner(), 403);

        $data = $request->validate([
            'store_name' => ['required', 'string', 'max:255'],
            'store_phone' => ['nullable', 'string', 'max:30'],
            'store_address' => ['nullable', 'string', 'max:500'],
            'store_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_store_logo' => ['nullable', 'boolean'],
            'receipt_header' => ['nullable', 'string', 'max:500'],
            'receipt_footer' => ['nullable', 'string', 'max:1000'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'printer_type' => ['nullable', 'in:bluetooth,usb,none,auto'],
            'printer_name' => ['nullable', 'string', 'max:255'],
            'paper_width' => ['nullable', 'in:58,80'],
            'printer_profile' => ['nullable', 'in:auto,rpp02n,hakpost,generic58,generic80,xprinter,gprinter,epson,star,bixolon'],
            'printer_baud' => ['nullable', 'in:9600,19200,38400,57600,115200'],
            'printer_auto_cut' => ['nullable', 'boolean'],
            'printer_usb_mode' => ['nullable', 'in:windows,serial,webusb'],
            'windows_printer' => ['nullable', 'string', 'max:255'],
            'com_port' => ['nullable', 'string', 'max:20'],
            'cash_drawer' => ['nullable', 'boolean'],
            'cash_drawer_when' => ['nullable', 'in:cash,always'],
            'cash_drawer_pin' => ['nullable', 'in:both,2,5'],
            'cash_drawer_windows_printer' => ['nullable', 'string', 'max:255'],
            'cash_drawer_com_port' => ['nullable', 'string', 'max:20'],
            'scanner_enabled' => ['nullable', 'boolean'],
            'stock_lock_enabled' => ['nullable', 'boolean'],
        ]);

        $extra = is_array($user->storeSetting?->extra) ? $user->storeSetting->extra : [];
        $extra['printer_profile'] = $data['printer_profile'] ?? 'auto';
        $extra['printer_baud'] = (int) ($data['printer_baud'] ?? 9600);
        $extra['printer_auto_cut'] = $request->boolean('printer_auto_cut');
        $extra['printer_usb_mode'] = $data['printer_usb_mode'] ?? 'windows';
        $extra['windows_printer'] = $data['windows_printer'] ?? null;
        $extra['com_port'] = $data['com_port'] ?? null;
        $extra['cash_drawer'] = $request->boolean('cash_drawer');
        $extra['cash_drawer_when'] = $data['cash_drawer_when'] ?? 'cash';
        $extra['cash_drawer_pin'] = $data['cash_drawer_pin'] ?? '2';
        $extra['cash_drawer_windows_printer'] = $data['cash_drawer_windows_printer'] ?: null;
        $extra['cash_drawer_com_port'] = $data['cash_drawer_com_port'] ? strtoupper(trim($data['cash_drawer_com_port'])) : null;
        $extra['scanner_enabled'] = $request->boolean('scanner_enabled');
        $extra['printer_setup_done'] = ($data['printer_type'] ?? 'bluetooth') !== 'none';

        // Sinkronkan nama printer USB agar Kasir bisa cetak tanpa pairing ulang
        if (($data['printer_type'] ?? '') === 'usb') {
            $usbName = trim((string) ($data['windows_printer'] ?: ($data['printer_name'] ?? '')));
            if ($usbName !== '' && preg_match('/^COM\d+$/i', $usbName)) {
                $extra['com_port'] = strtoupper($usbName);
                $extra['windows_printer'] = null;
                $data['printer_name'] = strtoupper($usbName);
                $data['com_port'] = strtoupper($usbName);
            } elseif ($usbName !== '') {
                $extra['windows_printer'] = $usbName;
                $data['printer_name'] = $usbName;
                if (empty($extra['cash_drawer_windows_printer'])) {
                    $extra['cash_drawer_windows_printer'] = $usbName;
                }
            }
            if (! empty($data['com_port'])) {
                $extra['com_port'] = strtoupper(trim((string) $data['com_port']));
            }
            $extra['printer_usb_mode'] = $extra['printer_usb_mode'] ?: 'windows';
        }

        unset(
            $data['printer_profile'],
            $data['printer_baud'],
            $data['printer_auto_cut'],
            $data['printer_usb_mode'],
            $data['windows_printer'],
            $data['com_port'],
            $data['cash_drawer'],
            $data['cash_drawer_when'],
            $data['cash_drawer_pin'],
            $data['cash_drawer_windows_printer'],
            $data['cash_drawer_com_port'],
            $data['scanner_enabled'],
            $data['store_logo'],
            $data['remove_store_logo'],
        );
        $data['extra'] = $extra;

        if (($data['printer_type'] ?? null) === 'auto') {
            $data['printer_type'] = ! empty($extra['windows_printer']) ? 'usb' : 'bluetooth';
        }

        if ($user->hasFeature('kunci_stok')) {
            $data['stock_lock_enabled'] = $request->boolean('stock_lock_enabled');
        } else {
            unset($data['stock_lock_enabled']);
        }

        $settings = $user->storeSetting;

        if (! $settings) {
            $settings = $user->storeSetting()->create(array_merge($data, [
                'user_id' => $user->id,
            ]));
        }

        if ($request->boolean('remove_store_logo') && $settings->store_logo) {
            Storage::disk('public')->delete($settings->store_logo);
            $data['store_logo'] = null;
        }

        if ($request->hasFile('store_logo')) {
            if ($settings->store_logo) {
                Storage::disk('public')->delete($settings->store_logo);
            }
            $data['store_logo'] = $request->file('store_logo')->store('store-logos', 'public');
        }

        $settings->update($data);

        $user->update([
            'store_name' => $data['store_name'],
            'store_address' => $data['store_address'] ?? $user->store_address,
            'phone' => $data['store_phone'] ?? $user->phone,
        ]);

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }

    /**
     * Simpan khusus pengaturan printer (dipanggil otomatis setelah Deteksi/Tes cetak).
     */
    public function updatePrinter(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->isStoreOwner(), 403);

        $owner = $user->storeOwner();
        abort_unless($owner, 403);

        $data = $request->validate([
            'printer_type' => ['required', 'in:bluetooth,usb,none'],
            'printer_name' => ['nullable', 'string', 'max:255'],
            'windows_printer' => ['nullable', 'string', 'max:255'],
            'com_port' => ['nullable', 'string', 'max:20'],
            'printer_usb_mode' => ['nullable', 'in:windows,serial,webusb'],
            'paper_width' => ['nullable', 'in:58,80'],
            'printer_auto_cut' => ['nullable', 'boolean'],
            'cash_drawer' => ['nullable', 'boolean'],
            'cash_drawer_when' => ['nullable', 'in:cash,always'],
            'cash_drawer_pin' => ['nullable', 'in:both,2,5'],
        ]);

        $settings = $owner->storeSetting;
        if (! $settings) {
            $settings = $owner->storeSetting()->create([
                'user_id' => $owner->id,
                'store_name' => $owner->store_name ?: $owner->name,
            ]);
        }

        $extra = is_array($settings->extra) ? $settings->extra : [];
        $type = $data['printer_type'];
        $name = trim((string) ($data['printer_name'] ?? ''));
        $windows = trim((string) ($data['windows_printer'] ?? ''));
        $com = strtoupper(trim((string) ($data['com_port'] ?? '')));

        if ($type === 'usb') {
            $target = $windows ?: $name;
            if ($target !== '' && preg_match('/^COM\d+$/i', $target)) {
                $com = strtoupper($target);
                $extra['com_port'] = $com;
                $extra['windows_printer'] = null;
                $name = $com;
            } elseif ($target !== '') {
                $extra['windows_printer'] = $target;
                $name = $target;
                if ($com !== '') {
                    $extra['com_port'] = $com;
                }
            }
            $extra['printer_usb_mode'] = $data['printer_usb_mode'] ?? 'windows';
            if (empty($extra['cash_drawer_windows_printer']) && ! empty($extra['windows_printer'])) {
                $extra['cash_drawer_windows_printer'] = $extra['windows_printer'];
            }
        } else {
            if ($com !== '') {
                $extra['com_port'] = $com;
            }
        }

        $extra['printer_setup_done'] = $type !== 'none';
        if (array_key_exists('printer_auto_cut', $data) || $request->has('printer_auto_cut')) {
            $extra['printer_auto_cut'] = $request->boolean('printer_auto_cut');
        }
        if (array_key_exists('cash_drawer', $data) || $request->has('cash_drawer')) {
            $extra['cash_drawer'] = $request->boolean('cash_drawer');
        }
        if (! empty($data['cash_drawer_when'])) {
            $extra['cash_drawer_when'] = $data['cash_drawer_when'];
        }
        if (! empty($data['cash_drawer_pin'])) {
            $extra['cash_drawer_pin'] = $data['cash_drawer_pin'];
        }

        $settings->update([
            'printer_type' => $type,
            'printer_name' => $name !== '' ? $name : $settings->printer_name,
            'paper_width' => $data['paper_width'] ?? $settings->paper_width ?? 58,
            'extra' => $extra,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan printer disimpan.',
            'printer' => [
                'printer_type' => $settings->printer_type,
                'printer_name' => $settings->printer_name,
                'extra' => $settings->extra,
            ],
        ]);
    }

    public function precacheManifest(OfflinePrecacheService $precache)
    {
        $urls = $precache->urlsFor(Auth::user());

        return response()->json([
            'success' => true,
            'count' => count($urls),
            'urls' => $urls,
        ]);
    }

    public function enableOffline(Request $request)
    {
        $user = Auth::user()->storeOwner();
        $settings = $user->storeSetting;

        if (! $settings) {
            $settings = $user->storeSetting()->create([
                'user_id' => $user->id,
                'store_name' => $user->store_name,
            ]);
        }

        $settings->update([
            'offline_enabled' => true,
            'offline_installed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mode offline diaktifkan. Data akan disimpan di perangkat.',
            'offline_enabled' => true,
            'offline_installed_at' => $settings->offline_installed_at,
        ]);
    }

    public function disableOffline()
    {
        $settings = Auth::user()->storeOwner()->storeSetting;
        if ($settings) {
            $settings->update(['offline_enabled' => false]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Mode offline dinonaktifkan.',
        ]);
    }

    public function wipe(Request $request, StoreDataWipeService $wipe)
    {
        $user = Auth::user();
        abort_unless($user->isStoreOwner(), 403);

        $data = $request->validate([
            'mode' => ['required', 'in:transactions,all'],
            'confirm_text' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $expected = $data['mode'] === 'transactions' ? 'KOSONGKAN' : 'FORMAT';
        if (mb_strtoupper(trim($data['confirm_text'])) !== $expected) {
            throw ValidationException::withMessages([
                'confirm_text' => 'Ketik '.$expected.' dengan huruf kapital untuk konfirmasi.',
            ]);
        }

        if (! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Password salah.',
            ]);
        }

        $result = $wipe->wipe((int) $user->id, $data['mode']);
        $deleted = $result['deleted'];
        $parts = [];
        foreach ([
            'transactions' => 'penjualan',
            'purchases' => 'pembelian',
            'receivables' => 'piutang',
            'payables' => 'hutang',
            'stock_opnames' => 'stock opname',
            'products' => 'produk',
            'categories' => 'kategori',
        ] as $key => $label) {
            $n = (int) ($deleted[$key] ?? 0);
            if ($n > 0) {
                $parts[] = $label.' '.$n;
            }
        }

        $message = $result['mode'] === 'transactions'
            ? 'Data transaksi berhasil dikosongkan.'
            : 'Semua data operasional berhasil diformat.';
        if ($parts) {
            $message .= ' Dihapus: '.implode(', ', $parts).'.';
        }
        $message .= ' Akun, kasir, dan langganan tetap aman.';

        return back()
            ->with('success', $message)
            ->with('wipe_offline', true);
    }
}
