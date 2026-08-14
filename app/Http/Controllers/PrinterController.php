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
        $guess = $printers->guessRppPrinter($extra['windows_printer'] ?? $settings?->printer_name);

        return response()->json([
            'success' => true,
            'printers' => $list,
            'com_ports' => $coms,
            'suggested' => $guess,
            'saved' => [
                'windows_printer' => $extra['windows_printer'] ?? $settings?->printer_name,
                'cash_drawer_windows_printer' => $extra['cash_drawer_windows_printer'] ?? null,
                'com_port' => $extra['com_port'] ?? null,
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
        $com = $data['com_port'] ?: ($extra['cash_drawer_com_port'] ?? $extra['com_port'] ?? null);
        $baud = (int) ($extra['printer_baud'] ?? 9600);

        try {
            $result = $printers->printRaw($raw, $name, $com, $baud);

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
