<?php

namespace App\Http\Controllers;

use App\Services\WindowsPrinterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PrinterController extends Controller
{
    public function devices(WindowsPrinterService $printers)
    {
        $settings = Auth::user()->storeOwner()->storeSetting;
        $extra = is_array($settings?->extra) ? $settings->extra : [];

        $list = $printers->listPrinters();
        $coms = $printers->listComPorts();
        $comOptions = $printers->listComPortOptions();
        $guess = $printers->guessRppPrinter($extra['windows_printer'] ?? $settings?->printer_name);
        $posPrinters = array_values(array_filter($list, fn ($p) => $printers->isLikelyPosPrinter($p)));

        // Jika tidak ada printer spooler POS, tampilkan COM sebagai opsi USB
        if (count($posPrinters) === 0 && count($comOptions) > 0) {
            foreach ($comOptions as $com) {
                $posPrinters[] = [
                    'name' => $com['name'],
                    'port' => $com['port'],
                    'driver' => 'COM Serial',
                    'label' => $com['label'],
                ];
            }
            if (! $guess && count($comOptions) === 1) {
                $guess = [
                    'name' => $comOptions[0]['name'],
                    'port' => $comOptions[0]['port'],
                    'driver' => 'COM Serial',
                ];
            }
        }

        return response()->json([
            'success' => true,
            'printers' => $list,
            'pos_printers' => $posPrinters,
            'com_ports' => $coms,
            'com_options' => $comOptions,
            'suggested' => $guess,
            'saved' => [
                'windows_printer' => $extra['windows_printer'] ?? $settings?->printer_name,
                'cash_drawer_windows_printer' => $extra['cash_drawer_windows_printer'] ?? null,
                'com_port' => $extra['com_port'] ?? null,
                'usb_port' => $extra['usb_port'] ?? null,
                'usb_mode' => $extra['printer_usb_mode'] ?? 'windows',
                'baud' => $extra['printer_baud'] ?? 9600,
            ],
        ]);
    }

    public function printRaw(Request $request, WindowsPrinterService $printers)
    {
        $data = $request->validate([
            'bytes' => ['required', 'string'],
            'printer_name' => ['nullable', 'string', 'max:255'],
            'com_port' => ['nullable', 'string', 'max:20'],
            'usb_port' => ['nullable', 'string', 'max:20'],
        ]);

        $settings = Auth::user()->storeOwner()->storeSetting;
        $extra = is_array($settings?->extra) ? $settings->extra : [];
        $raw = base64_decode($data['bytes'], true);
        if ($raw === false || $raw === '') {
            return response()->json(['success' => false, 'message' => 'Data struk tidak valid.'], 422);
        }

        $name = array_key_exists('printer_name', $data) && $data['printer_name'] !== null && $data['printer_name'] !== ''
            ? $data['printer_name']
            : (($data['com_port'] ?? null) ? null : ($extra['windows_printer'] ?? $settings?->printer_name));
        $com = $data['com_port'] ?: ($extra['com_port'] ?? null);

        // Jika yang tersimpan adalah COMx
        if (! $com && $name && preg_match('/^COM\d+$/i', $name)) {
            $com = strtoupper($name);
            $name = null;
        }

        $usb = $data['usb_port'] ?: ($extra['usb_port'] ?? null);
        $baud = (int) ($extra['printer_baud'] ?? 9600);

        try {
            $result = $printers->printRaw($raw, $name, $com, $baud, $usb);

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
