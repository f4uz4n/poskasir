<?php

namespace App\Http\Controllers;

use App\Services\WindowsPrinterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PrinterController extends Controller
{
    public function capabilities(WindowsPrinterService $printers)
    {
        return response()->json([
            'success' => true,
            ...$printers->capabilities(),
        ]);
    }

    public function devices(WindowsPrinterService $printers)
    {
        $settings = Auth::user()->storeOwner()->storeSetting;
        $extra = is_array($settings?->extra) ? $settings->extra : [];
        $caps = $printers->capabilities();

        if (! $caps['server_side_usb']) {
            return response()->json([
                'success' => true,
                'printers' => [],
                'usable_printers' => [],
                'printer_options' => [],
                'pos_printers' => [],
                'usb_devices' => [],
                'com_ports' => [],
                'com_options' => [],
                'suggested' => null,
                'saved' => [
                    'windows_printer' => $extra['windows_printer'] ?? $settings?->printer_name,
                    'cash_drawer_windows_printer' => $extra['cash_drawer_windows_printer'] ?? null,
                    'com_port' => $extra['com_port'] ?? null,
                    'usb_port' => $extra['usb_port'] ?? null,
                    'usb_mode' => $extra['printer_usb_mode'] ?? 'serial',
                    'baud' => $extra['printer_baud'] ?? 9600,
                ],
                ...$caps,
            ]);
        }

        $list = $printers->listPrinters();
        $usable = $printers->listUsablePrinters();
        $options = $printers->listPrinterOptions();
        $coms = $printers->listComPorts();
        $usbDevices = $printers->listUsbDevices();
        $guess = $printers->guessRppPrinter($extra['windows_printer'] ?? $settings?->printer_name);

        $posPrinters = array_values(array_filter($usable, fn ($p) => $printers->isLikelyPosPrinter($p)));
        if (count($posPrinters) === 0) {
            $posPrinters = $usable;
        }

        return response()->json([
            'success' => true,
            'printers' => $list,
            'usable_printers' => $usable,
            'printer_options' => $options,
            'pos_printers' => $posPrinters,
            'usb_devices' => $usbDevices,
            'com_ports' => $coms,
            'com_options' => $printers->listComPortOptions(),
            'suggested' => $guess,
            'saved' => [
                'windows_printer' => $extra['windows_printer'] ?? $settings?->printer_name,
                'cash_drawer_windows_printer' => $extra['cash_drawer_windows_printer'] ?? null,
                'com_port' => $extra['com_port'] ?? null,
                'usb_port' => $extra['usb_port'] ?? null,
                'usb_mode' => $extra['printer_usb_mode'] ?? 'windows',
                'baud' => $extra['printer_baud'] ?? 9600,
            ],
            ...$caps,
        ]);
    }

    public function printRaw(Request $request, WindowsPrinterService $printers)
    {
        $data = $request->validate([
            'bytes' => ['required', 'string'],
            'text' => ['nullable', 'string'],
            'printer_name' => ['nullable', 'string', 'max:255'],
            'com_port' => ['nullable', 'string', 'max:20'],
            'usb_port' => ['nullable', 'string', 'max:20'],
        ]);

        $settings = Auth::user()->storeOwner()->storeSetting;
        $extra = is_array($settings?->extra) ? $settings->extra : [];
        $raw = base64_decode($data['bytes'], true);
        if (($raw === false || $raw === '') && empty($data['text'])) {
            return response()->json(['success' => false, 'message' => 'Data struk tidak valid.'], 422);
        }

        $name = array_key_exists('printer_name', $data) && $data['printer_name'] !== null && $data['printer_name'] !== ''
            ? $data['printer_name']
            : (($data['com_port'] ?? null) ? null : ($extra['windows_printer'] ?? $settings?->printer_name));
        $com = $data['com_port'] ?: ($extra['com_port'] ?? null);

        if (! $com && $name && preg_match('/^COM\d+$/i', $name)) {
            $com = strtoupper($name);
            $name = null;
        }

        $usb = $data['usb_port'] ?: ($extra['usb_port'] ?? null);
        $baud = (int) ($extra['printer_baud'] ?? 9600);
        $plainText = null;
        if (! empty($data['text'])) {
            $decoded = base64_decode($data['text'], true);
            if ($decoded !== false && $decoded !== '') {
                $plainText = $decoded;
            }
        }

        if (($raw === false || $raw === '') && ($plainText === null || trim($plainText) === '')) {
            return response()->json(['success' => false, 'message' => 'Data struk tidak valid.'], 422);
        }
        if ($raw === false || $raw === '') {
            $raw = "\x1b\x40";
        }

        if (! $name && ! $com && empty($extra['windows_printer']) && empty($settings?->printer_name)) {
            return response()->json([
                'success' => false,
                'message' => 'Printer USB belum dipilih. Buka Pengaturan → pilih nama printer di daftar (sama seperti di Windows) → Simpan → Tes cetak.',
            ], 422);
        }

        try {
            $extra['paper_width'] = (int) ($settings?->paper_width ?? 58);
            $result = $printers->printRaw($raw, $name, $com, $baud, $usb, $plainText, $extra);

            return response()->json([
                'success' => true,
                'message' => 'Struk terkirim ke '.$result['target'].' ('.$result['via'].')',
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
