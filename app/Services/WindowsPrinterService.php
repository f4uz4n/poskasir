<?php

namespace App\Services;

use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Symfony\Component\Process\Process;
use Throwable;

class WindowsPrinterService
{
    /** @var array<int,array{name:string,port:?string,driver:?string}>|null */
    protected static ?array $printerListCache = null;

    public function supportsServerSidePrint(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    /** @return array{server_side_usb:bool,os:string,recommended_usb_mode:string} */
    public function capabilities(): array
    {
        $serverSide = $this->supportsServerSidePrint();

        return [
            'server_side_usb' => $serverSide,
            'os' => PHP_OS_FAMILY,
            'recommended_usb_mode' => $serverSide ? 'windows' : 'serial',
        ];
    }

    /** @return array<int,array{name:string,port:?string,driver:?string}> */
    public function listPrinters(): array
    {
        if (self::$printerListCache !== null) {
            return self::$printerListCache;
        }

        $printers = [];
        $seen = [];

        $script = 'Get-Printer | Select-Object Name, PortName, DriverName | ConvertTo-Json -Compress';
        foreach ($this->decodeJson($this->powershell($script)) as $row) {
            $name = trim((string) ($row['Name'] ?? ''));
            if ($name === '' || isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;
            $printers[] = [
                'name' => $name,
                'port' => $row['PortName'] ?? null,
                'driver' => $row['DriverName'] ?? null,
            ];
        }

        $reg = 'Get-ChildItem -Path "HKLM:\\SYSTEM\\CurrentControlSet\\Control\\Print\\Printers" | ForEach-Object { Get-ItemProperty $_.PSPath | Select-Object @{n="Name";e={$_.PSChildName}}, Port } | ConvertTo-Json -Compress';
        foreach ($this->decodeJson($this->powershell($reg)) as $row) {
            $name = trim((string) ($row['Name'] ?? ''));
            if ($name === '' || isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;
            $printers[] = [
                'name' => $name,
                'port' => $row['Port'] ?? null,
                'driver' => null,
            ];
        }

        // WMI cadangan (jika Get-Printer kosong / lambat)
        $wmi = 'Get-CimInstance Win32_Printer | Select-Object Name, PortName, DriverName | ConvertTo-Json -Compress';
        foreach ($this->decodeJson($this->powershell($wmi)) as $row) {
            $name = trim((string) ($row['Name'] ?? ''));
            if ($name === '' || isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;
            $printers[] = [
                'name' => $name,
                'port' => $row['PortName'] ?? null,
                'driver' => $row['DriverName'] ?? null,
            ];
        }

        self::$printerListCache = $printers;

        return $printers;
    }

    public function clearPrinterCache(): void
    {
        self::$printerListCache = null;
    }

    /** Semua printer Windows yang bukan virtual (PDF, OneNote, dll). */
    public function listUsablePrinters(): array
    {
        return array_values(array_filter(
            $this->listPrinters(),
            fn (array $p) => ! $this->isVirtualPrinter($p)
        ));
    }

    /**
     * Perangkat USB / PnP terkait printer (mis. "USB Printing Support").
     *
     * @return array<int,array{name:string,device_id:?string,hint:string}>
     */
    public function listUsbDevices(): array
    {
        $devices = [];
        $seen = [];

        $script = 'Get-CimInstance Win32_PnPEntity | Where-Object { '
            .'$_.Name -match "Gprinter|Gainscha|Rongta|RPP|GP-|POS-?58|POS58|thermal|58mm|80mm|USB Printing Support|58Printer" '
            .'-and $_.Name -notmatch "PDF|OneNote|Fax|XPS|Root Print|Composite Bus|ACPI|Enumerator|No Printer" '
            .'} | Select-Object Name, DeviceID | ConvertTo-Json -Compress';

        foreach ($this->decodeJson($this->powershell($script)) as $row) {
            $name = trim((string) ($row['Name'] ?? ''));
            if ($name === '' || isset($seen[strtolower($name)])) {
                continue;
            }
            $seen[strtolower($name)] = true;
            $devices[] = [
                'name' => $name,
                'device_id' => $row['DeviceID'] ?? null,
                'hint' => $this->usbDeviceHint($name),
            ];
        }

        return $devices;
    }

    protected function usbDeviceHint(string $name): string
    {
        $n = strtolower($name);
        if (str_contains($n, 'usb printing support') || str_contains($n, 'dukungan pencetakan usb')) {
            return 'Driver printer belum terpasang — instal driver di Windows (Control Panel → Printer).';
        }

        return 'Perangkat USB terdeteksi.';
    }

    /** @return array<int,array{name:string,port:?string,driver:?string,label:string}> */
    public function listPrinterOptions(): array
    {
        $options = [];

        foreach ($this->listUsablePrinters() as $printer) {
            $port = trim((string) ($printer['port'] ?? ''));
            $driver = trim((string) ($printer['driver'] ?? ''));
            $label = $printer['name'];
            if ($port !== '') {
                $label .= ' — '.$port;
            } elseif ($driver !== '') {
                $label .= ' ('.$driver.')';
            }
            $options[] = [
                ...$printer,
                'label' => $label,
            ];
        }

        foreach ($this->listComPortOptions() as $com) {
            $options[] = [
                'name' => $com['name'],
                'port' => $com['port'],
                'driver' => 'COM Serial',
                'label' => $com['label'],
            ];
        }

        return $options;
    }

    public function isStandardPort(?string $port): bool
    {
        return (bool) preg_match('/^(COM\d+|USB\d+|LPT\d+)$/i', trim((string) $port));
    }

    public function hasCustomPort(?array $printer): bool
    {
        if (! $printer || empty($printer['port'])) {
            return false;
        }

        return ! $this->isStandardPort((string) $printer['port']);
    }

    /** @return array<int,string> */
    public function listComPorts(): array
    {
        $ports = [];

        $script = '[System.IO.Ports.SerialPort]::GetPortNames() | ForEach-Object { $_.ToUpper() }';
        foreach (preg_split('/\r\n|\n/', $this->powershell($script)) ?: [] as $line) {
            $line = strtoupper(trim($line));
            if (preg_match('/^COM\d+$/', $line)) {
                $ports[] = $line;
            }
        }

        $script2 = 'Get-CimInstance Win32_PnPEntity | Where-Object { $_.Name -match "COM\\d+" } | Select-Object -ExpandProperty Name';
        foreach (preg_split('/\r\n|\n/', $this->powershell($script2)) ?: [] as $line) {
            if (preg_match('/(COM\d+)/i', $line, $m)) {
                $ports[] = strtoupper($m[1]);
            }
        }

        sort($ports, SORT_NATURAL);

        return array_values(array_unique($ports));
    }

    /**
     * @return array<int,array{name:string,port:string,label:string}>
     */
    public function listComPortOptions(): array
    {
        $options = [];
        foreach ($this->listComPorts() as $port) {
            $options[] = [
                'name' => $port,
                'port' => $port,
                'label' => $port.' (USB/Serial)',
            ];
        }

        return $options;
    }

    public function isVirtualPrinter(array $printer): bool
    {
        $blob = strtolower(trim(($printer['name'] ?? '').' '.($printer['driver'] ?? '').' '.($printer['port'] ?? '')));

        return (bool) preg_match(
            '/onenote|one note|microsoft print to pdf|microsoft xps|fax|send to|adobe pdf|pdfcreator|pdf24|cutepdf|bullzip|dopdf|foxit pdf|nitro pdf|virtual|redirect|redirected|ts\d+|shr\d+|nul:|file:|portprompt|wpd|document writer/i',
            $blob
        );
    }

    public function isLikelyPosPrinter(array $printer): bool
    {
        if ($this->isVirtualPrinter($printer)) {
            return false;
        }

        $blob = strtolower(trim(($printer['name'] ?? '').' '.($printer['driver'] ?? '').' '.($printer['port'] ?? '')));

        if (preg_match('/^usb\d+/i', (string) ($printer['port'] ?? ''))) {
            return true;
        }

        if (preg_match('/rongtausb|usb\s*port/i', (string) ($printer['port'] ?? ''))) {
            return true;
        }

        return (bool) preg_match(
            '/pos|pos-?58|pos-?80|pos58|pos80|thermal|receipt|escpos|esc\s*pos|epson|tm-|xprinter|xp-|gprinter|gp-|gp58|58mb|gainscha|rongta|rpp|goojprt|hakpost|hprt|hpc|star|sm-|bixolon|srp-|citizen|zebra|tsc|gainscha|munbyn|imin|sunmi|bluetooth printer|printer\s*58|printer\s*80|58mm|80mm|generic\s*\/\s*text|usb printing support/i',
            $blob
        );
    }

    public function findPrinter(?string $name): ?array
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        $escaped = str_replace("'", "''", $name);
        $script = "Get-Printer -Name '$escaped' -ErrorAction SilentlyContinue | Select-Object Name, PortName, DriverName | ConvertTo-Json -Compress";
        foreach ($this->decodeJson($this->powershell($script)) as $row) {
            $found = trim((string) ($row['Name'] ?? ''));
            if ($found !== '') {
                return [
                    'name' => $found,
                    'port' => $row['PortName'] ?? null,
                    'driver' => $row['DriverName'] ?? null,
                ];
            }
        }

        foreach ($this->listPrinters() as $printer) {
            if (strcasecmp($printer['name'], $name) === 0) {
                return $printer;
            }
        }

        return null;
    }

    public function isGenericTextDriver(array $printer): bool
    {
        $blob = strtolower(trim(($printer['driver'] ?? '').' '.($printer['name'] ?? '')));

        return (bool) preg_match(
            '/generic\s*\/?\s*text|text\s*only|raw\s*only|esc\s*pos|pos\s*58|pos\s*80|receipt\s*only|standard\s*tcp/i',
            $blob
        );
    }

    /** Driver resmi Gprinter/Gainscha sering menerima job RAW tanpa mencetak ESC/POS — kirim langsung ke port USB/COM. */
    public function prefersDirectPort(array $printer): bool
    {
        if ($this->isVirtualPrinter($printer)) {
            return false;
        }

        $blob = strtolower(trim(($printer['name'] ?? '').' '.($printer['driver'] ?? '').' '.($printer['port'] ?? '')));

        if ($this->isGenericTextDriver($printer)) {
            return false;
        }

        return (bool) preg_match(
            '/gprinter|gainscha|gp-|gp58|gp80|gs-|pos58|pos80|58mb|80mm|receipt\s*printer|thermal\s*receipt|escpos|esc\s*pos|rongta|rpp|rpp02|rongtausb/i',
            $blob
        );
    }

    /** Port OEM (RongtaUSB, dll.) — hanya bisa cetak lewat nama printer + driver Windows. */
    public function requiresDriverByName(?array $printer): bool
    {
        if (! $printer || $this->isVirtualPrinter($printer)) {
            return false;
        }

        if ($this->hasCustomPort($printer)) {
            return true;
        }

        return $this->prefersDriverRender($printer);
    }

    /** @return array<int,string> */
    protected function collectPortTargets(?array $printer, ?string $comPort = null, ?string $usbPort = null): array
    {
        $ports = [];

        foreach ([$comPort, $usbPort] as $candidate) {
            $candidate = strtoupper(trim((string) $candidate));
            if ($candidate !== '' && preg_match('/^(COM\d+|USB\d+|LPT\d+)$/i', $candidate)) {
                $ports[] = $candidate;
            }
        }

        if ($printer && ! empty($printer['port'])) {
            $port = strtoupper(trim((string) $printer['port']));
            if ($this->isStandardPort($port)) {
                $ports[] = $port;
            }
        }

        return array_values(array_unique($ports));
    }

    /** @return array{ok:bool,via:string,target:string}|null */
    protected function tryPortTargets(array $ports, string $bytes, int $baud, array &$errors): ?array
    {
        foreach ($ports as $port) {
            try {
                $this->writePort($port, $bytes, $baud);

                return ['ok' => true, 'via' => 'port-direct', 'target' => strtoupper($port)];
            } catch (Throwable $e) {
                $errors[] = $port.': '.$e->getMessage();
            }

            if (preg_match('/^COM\d+$/i', $port)) {
                foreach ([115200, 57600, 38400, 19200] as $altBaud) {
                    if ($altBaud === $baud) {
                        continue;
                    }
                    try {
                        $this->writeCom($port, $bytes, $altBaud);

                        return ['ok' => true, 'via' => 'com-baud-'.$altBaud, 'target' => strtoupper($port)];
                    } catch (Throwable $e2) {
                        $errors[] = $port.'@'.$altBaud.': '.$e2->getMessage();
                    }
                }
            }
        }

        return null;
    }

    public function guessRppPrinter(?string $preferred = null): ?array
    {
        $printers = $this->listPrinters();
        if ($preferred) {
            foreach ($printers as $printer) {
                if (strcasecmp($printer['name'], $preferred) === 0 && ! $this->isVirtualPrinter($printer)) {
                    return $printer;
                }
            }
        }

        foreach ($printers as $printer) {
            if ($this->isLikelyPosPrinter($printer)) {
                return $printer;
            }
        }

        // Prioritas nama umum 58mm
        foreach ($printers as $printer) {
            if ($this->isVirtualPrinter($printer)) {
                continue;
            }
            if (preg_match('/^POS-?58$/i', (string) ($printer['name'] ?? ''))) {
                return $printer;
            }
        }

        foreach ($printers as $printer) {
            if (preg_match('/^USB\d+/i', (string) $printer['port']) && ! $this->isVirtualPrinter($printer)) {
                return $printer;
            }
        }

        return null;
    }

    /** Driver OEM (POS-58, Gprinter, dll.) — Word bisa cetak, RAW ESC/POS sering tidak keluar. */
    public function prefersDriverRender(array $printer): bool
    {
        if ($this->isVirtualPrinter($printer)) {
            return false;
        }

        if ($this->isGenericTextDriver($printer)) {
            return false;
        }

        // Printer thermal terpasang di Windows dengan driver resmi → cetak lewat driver (seperti Word)
        if ($this->isLikelyPosPrinter($printer)) {
            return true;
        }

        return $this->prefersDirectPort($printer) || $this->hasCustomPort($printer);
    }

    /** RAW winspool sering "sukses" tanpa kertas keluar pada driver OEM — lewati RAW. */
    protected function shouldSkipRawSpool(?array $printer): bool
    {
        if ($printer === null) {
            return false;
        }

        if ($this->isGenericTextDriver($printer)) {
            return false;
        }

        return $this->isLikelyPosPrinter($printer) || $this->requiresDriverByName($printer);
    }

    protected function resolveRenderMode(?array $extra = null): string
    {
        $mode = strtolower(trim((string) ($extra['printer_usb_render'] ?? 'auto')));

        return in_array($mode, ['auto', 'raw', 'driver'], true) ? $mode : 'auto';
    }

    protected function shouldTryDriverFirst(?array $meta, string $renderMode, ?string $plainText): bool
    {
        if ($plainText === null || trim($plainText) === '') {
            return false;
        }
        if ($renderMode === 'driver') {
            return true;
        }
        if ($renderMode === 'raw') {
            return false;
        }

        if ($meta !== null && $this->requiresDriverByName($meta)) {
            return true;
        }

        return $meta !== null && $this->prefersDriverRender($meta);
    }

    protected function writeDriverText(string $printerName, string $text, int $paperWidth = 58): void
    {
        $printerName = trim($printerName);
        if ($printerName === '') {
            throw new \RuntimeException('Nama printer kosong.');
        }

        $paperWidth = $paperWidth === 80 ? 80 : 58;

        $tmp = tempnam(sys_get_temp_dir(), 'kftxt');
        if ($tmp === false) {
            throw new \RuntimeException('Tidak bisa membuat file sementara untuk cetak teks.');
        }
        $txt = $tmp.'.txt';
        file_put_contents($txt, $text);
        @unlink($tmp);

        $script = base_path('scripts/windows-text-print.ps1');
        $process = new Process([
            'powershell',
            '-NoProfile',
            '-ExecutionPolicy',
            'Bypass',
            '-File',
            $script,
            '-PrinterName',
            $printerName,
            '-FilePath',
            $txt,
            '-PaperWidth',
            (string) $paperWidth,
        ]);
        $process->setTimeout(15);

        if (PHP_OS_FAMILY === 'Windows') {
            $this->writeDriverTextDetached($process, $txt);

            return;
        }

        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput().' '.$process->getOutput()) ?: 'Cetak driver gagal');
        }
    }

    protected function writeDriverTextDetached(Process $process, string $txtPath): void
    {
        $cmdLine = $process->getCommandLine();
        $logPath = storage_path('logs/print-jobs.log');
        $wrapper = 'cmd /c start "" /B '.$cmdLine.' >> '.escapeshellarg($logPath).' 2>&1';
        $handle = @popen($wrapper, 'r');
        if ($handle === false) {
            $process->run();
            if (! $process->isSuccessful()) {
                throw new \RuntimeException(trim($process->getErrorOutput().' '.$process->getOutput()) ?: 'Cetak driver gagal');
            }

            return;
        }
        pclose($handle);
        // File temp dibiarkan — windows-text-print.ps1 yang baca lalu hapus.
        // Menghapus dari PHP terlalu cepat = PowerShell "File struk tidak ditemukan".
    }

    /** @return array{ok:bool,via:string,target:string}|null */
    protected function tryDriverTextTargets(array $names, string $plainText, array &$errors, int $paperWidth = 58): ?array
    {
        foreach (array_values(array_unique(array_filter(array_map('trim', $names)))) as $name) {
            if ($name === '' || $this->isVirtualPrinter(['name' => $name])) {
                continue;
            }
            try {
                $this->writeDriverText($name, $plainText, $paperWidth);

                return ['ok' => true, 'via' => 'windows-driver', 'target' => $name];
            } catch (Throwable $e) {
                $errors[] = 'Driver '.$name.': '.$e->getMessage();
            }
        }

        return null;
    }

    protected function escPosToPlainText(string $bytes): string
    {
        $text = preg_replace('/\x1b[@-Z\\\\-_]|\x1b\[[0-?]*[ -\/]*[@-~]/', '', $bytes) ?? '';
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text) ?? '';

        return trim($text);
    }

    protected function resolvePlainTextForDriver(
        ?string $plainText,
        string $bytes,
        ?array $meta,
        ?array $settingsExtra,
    ): ?string {
        if ($plainText !== null && trim($plainText) !== '') {
            return $plainText;
        }

        if ($meta !== null && ($this->requiresDriverByName($meta) || $this->prefersDriverRender($meta))) {
            $stripped = $this->escPosToPlainText($bytes);
            if ($stripped !== '') {
                return $stripped;
            }
        }

        $renderMode = $this->resolveRenderMode($settingsExtra);
        if ($renderMode === 'driver') {
            return 'Struk KasirFlow';
        }

        return $plainText;
    }

    public function printRaw(
        string $bytes,
        ?string $printerName = null,
        ?string $comPort = null,
        int $baud = 9600,
        ?string $usbPort = null,
        ?string $plainText = null,
        ?array $settingsExtra = null,
    ): array {
        $errors = [];
        $renderMode = $this->resolveRenderMode($settingsExtra);
        $paperWidth = (int) ($settingsExtra['paper_width'] ?? 58);
        $paperWidth = $paperWidth === 80 ? 80 : 58;
        $explicit = $printerName ? trim($printerName) : '';
        $com = $comPort ? strtoupper(trim($comPort)) : null;
        $usb = $usbPort ? strtoupper(trim($usbPort)) : null;

        // Nama "COMx" / "USBxxx" yang tersimpan sebagai printer_name
        if ($explicit !== '' && preg_match('/^COM\d+$/i', $explicit)) {
            $com = strtoupper($explicit);
            $explicit = '';
        }
        if ($explicit !== '' && preg_match('/^USB\d+$/i', $explicit)) {
            $usb = strtoupper($explicit);
            $explicit = '';
        }

        $driverNames = array_values(array_filter(array_unique([
            $explicit,
            trim((string) ($settingsExtra['windows_printer'] ?? '')),
            trim((string) ($settingsExtra['printer_name'] ?? '')),
        ])));

        if ($plainText !== null && trim($plainText) !== '' && $driverNames !== []) {
            $tryDriver = $renderMode === 'driver';
            foreach ($driverNames as $candidate) {
                if (preg_match('/^POS-?58$/i', (string) $candidate)) {
                    $tryDriver = true;
                    break;
                }
            }
            if ($tryDriver) {
                $driver = $this->tryDriverTextTargets($driverNames, $plainText, $errors, $paperWidth);
                if ($driver) {
                    return $driver;
                }
            }
        }

        $meta = $explicit !== '' ? $this->findPrinter($explicit) : null;
        $plainText = $this->resolvePlainTextForDriver($plainText, $bytes, $meta, $settingsExtra);
        $portTargets = $this->collectPortTargets($meta, $com, $usb);
        if ($explicit === '' && $driverNames !== []) {
            $explicit = $driverNames[0];
            $meta = $this->findPrinter($explicit) ?: $meta;
        }
        $driverNames = array_values(array_filter(array_unique([
            $explicit,
            $meta['name'] ?? null,
            trim((string) ($settingsExtra['windows_printer'] ?? '')),
            trim((string) ($settingsExtra['printer_name'] ?? '')),
        ])));
        if ($meta && preg_match('/^POS-?58$/i', (string) ($meta['name'] ?? ''))) {
            $text = $plainText ?? $this->escPosToPlainText($bytes) ?: "Struk KasirFlow\r\n\r\n";
            $driver = $this->tryDriverTextTargets($driverNames, $text, $errors, $paperWidth);
            if ($driver) {
                return $driver;
            }
        }

        // 0) Mode driver — sama seperti cetak dari Word (driver Windows yang sudah terpasang)
        if ($this->shouldTryDriverFirst($meta, $renderMode, $plainText)) {
            $driver = $this->tryDriverTextTargets($driverNames, $plainText, $errors, $paperWidth);
            if ($driver) {
                return $driver;
            }
        }

        // 1) Driver Gprinter/Gainscha — kirim langsung ke port USB/COM (RAW)
        if ($meta && $this->prefersDirectPort($meta) && $portTargets !== [] && $renderMode !== 'driver') {
            $direct = $this->tryPortTargets($portTargets, $bytes, $baud, $errors);
            if ($direct) {
                return $direct;
            }
        }

        // 2) Port COM/USB eksplisit
        if ($portTargets !== [] && $renderMode !== 'driver') {
            $direct = $this->tryPortTargets($portTargets, $bytes, $baud, $errors);
            if ($direct) {
                return $direct;
            }
        }

        // 3) Windows spooler RAW — lewati driver OEM (POS-58, Gprinter, dll.)
        $skipRaw = $this->shouldSkipRawSpool($meta);
        if ($explicit !== '' && ! $this->isVirtualPrinter(['name' => $explicit]) && $renderMode !== 'driver' && ! $skipRaw) {
            try {
                $this->writeWinspool($explicit, $bytes);

                return ['ok' => true, 'via' => 'winspool', 'target' => $explicit];
            } catch (Throwable $e) {
                $errors[] = 'Winspool '.$explicit.': '.$e->getMessage();
            }

            try {
                $this->writeWindowsConnector($explicit, $bytes);

                return ['ok' => true, 'via' => 'windows-connector', 'target' => $explicit];
            } catch (Throwable $e) {
                $errors[] = 'WindowsPrintConnector '.$explicit.': '.$e->getMessage();
            }

            if ($meta && ! empty($meta['port']) && preg_match('/^(USB\d+|COM\d+)/i', (string) $meta['port'], $m)) {
                try {
                    $port = strtoupper($m[0]);
                    $this->writePort($port, $bytes, $baud);

                    return ['ok' => true, 'via' => 'port-fallback', 'target' => $port];
                } catch (Throwable $e) {
                    $errors[] = 'Port '.$m[0].': '.$e->getMessage();
                }
            }
        }

        $target = $this->guessRppPrinter($explicit !== '' ? $explicit : null);
        if ($target) {
            $guessPorts = $this->collectPortTargets($target, $com, $usb);
            $driverNames[] = $target['name'];

            if ($this->shouldTryDriverFirst($target, $renderMode, $plainText)) {
                $driver = $this->tryDriverTextTargets([$target['name']], $plainText, $errors, $paperWidth);
                if ($driver) {
                    return $driver;
                }
            }

            if ($this->prefersDirectPort($target) && $guessPorts !== [] && $renderMode !== 'driver') {
                $direct = $this->tryPortTargets($guessPorts, $bytes, $baud, $errors);
                if ($direct) {
                    return $direct;
                }
            }

            if ($renderMode !== 'driver' && ! $this->shouldSkipRawSpool($target)) {
                try {
                    $this->writeWinspool($target['name'], $bytes);

                    return ['ok' => true, 'via' => 'winspool', 'target' => $target['name']];
                } catch (Throwable $e) {
                    $errors[] = 'Winspool: '.$e->getMessage();
                }

                try {
                    $this->writeWindowsConnector($target['name'], $bytes);

                    return ['ok' => true, 'via' => 'windows-connector', 'target' => $target['name']];
                } catch (Throwable $e) {
                    $errors[] = 'WindowsPrintConnector: '.$e->getMessage();
                }

                if ($guessPorts !== []) {
                    $direct = $this->tryPortTargets($guessPorts, $bytes, $baud, $errors);
                    if ($direct) {
                        return $direct;
                    }
                }
            }
        }

        if ($com === null && $usb === null && $renderMode !== 'driver') {
            $available = $this->listComPorts();
            if (count($available) === 1) {
                try {
                    $this->writeCom($available[0], $bytes, $baud);

                    return ['ok' => true, 'via' => 'com-auto', 'target' => $available[0]];
                } catch (Throwable $e) {
                    $errors[] = $available[0].': '.$e->getMessage();
                }
            }
        }

        if ($renderMode !== 'driver') {
            foreach (range(1, 8) as $n) {
                $port = sprintf('USB%03d', $n);
                if (in_array($port, $portTargets, true)) {
                    continue;
                }
                try {
                    $this->writePort($port, $bytes, $baud);

                    return ['ok' => true, 'via' => 'usb-port', 'target' => $port];
                } catch (Throwable $e) {
                    $errors[] = $port.': '.$e->getMessage();
                }
            }
        }

        // Fallback terakhir: cetak teks lewat driver (seperti Word)
        if ($plainText !== null && trim($plainText) !== '') {
            $driver = $this->tryDriverTextTargets($driverNames, $plainText, $errors, $paperWidth);
            if ($driver) {
                return $driver;
            }
        }

        $hint = 'Gagal cetak USB. Jika Word bisa cetak: pastikan nama printer di Pengaturan sama persis dengan Windows, '
            .'lalu klik Deteksi → Tes cetak. Tutup aplikasi driver printer (Gainscha/Gprinter Utility). ';
        if (stripos(implode(' ', $errors), 'access') !== false || stripos(implode(' ', $errors), 'denied') !== false) {
            $hint .= 'Akses ditolak — jalankan XAMPP/Apache sebagai user Windows yang sama, atau gunakan `php artisan serve`. ';
        }

        throw new \RuntimeException($hint.implode(' | ', array_slice($errors, 0, 3)));
    }

    protected function writeWinspool(string $printerName, string $bytes): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'kfprn');
        if ($tmp === false) {
            throw new \RuntimeException('Tidak bisa membuat file sementara untuk cetak.');
        }
        $bin = $tmp.'.bin';
        file_put_contents($bin, $bytes);
        @unlink($tmp);

        $script = base_path('scripts/windows-raw-print.ps1');
        $process = new Process([
            'powershell',
            '-NoProfile',
            '-ExecutionPolicy',
            'Bypass',
            '-File',
            $script,
            '-PrinterName',
            $printerName,
            '-FilePath',
            $bin,
        ]);
        $process->setTimeout(15);
        $process->run();
        @unlink($bin);

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput().' '.$process->getOutput()) ?: 'Winspool gagal');
        }
    }

    protected function writeWindowsConnector(string $printerName, string $bytes): void
    {
        $connector = new WindowsPrintConnector($printerName);
        $connector->write($bytes);
        $connector->finalize();
    }

    protected function writePort(string $port, string $bytes, int $baud): void
    {
        if (preg_match('/^COM\d+/i', $port)) {
            $this->writeCom($port, $bytes, $baud);

            return;
        }

        $this->writeViaPowershellPort($port, $bytes, $baud);
    }

    protected function writeCom(string $port, string $bytes, int $baud): void
    {
        $port = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $port) ?? '');
        if (! preg_match('/^COM\d+$/', $port)) {
            throw new \RuntimeException('Port COM tidak valid');
        }

        // SerialPort .NET lebih andal + timeout (fopen sering hang di XAMPP)
        $this->writeViaPowershellPort($port, $bytes, $baud);
    }

    protected function writeViaPowershellPort(string $port, string $bytes, int $baud): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'kfcom');
        if ($tmp === false) {
            throw new \RuntimeException('Tidak bisa membuat file sementara COM.');
        }
        $bin = $tmp.'.bin';
        file_put_contents($bin, $bytes);
        @unlink($tmp);

        $script = base_path('scripts/windows-com-print.ps1');
        $process = new Process([
            'powershell',
            '-NoProfile',
            '-ExecutionPolicy',
            'Bypass',
            '-File',
            $script,
            '-PortName',
            strtoupper($port),
            '-FilePath',
            $bin,
            '-BaudRate',
            (string) $baud,
        ]);
        $process->setTimeout(12);
        $process->run();
        @unlink($bin);

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput().' '.$process->getOutput()) ?: 'Gagal tulis '.$port);
        }
    }

    protected function powershell(string $command): string
    {
        $process = new Process([
            'powershell',
            '-NoProfile',
            '-ExecutionPolicy',
            'Bypass',
            '-Command',
            $command,
        ]);
        $process->setTimeout(15);
        $process->run();

        return trim($process->getOutput());
    }

    /** @return array<int,array<string,mixed>> */
    protected function decodeJson(string $json): array
    {
        if ($json === '') {
            return [];
        }
        $data = json_decode($json, true);
        if (! is_array($data)) {
            return [];
        }
        if (isset($data['Name'])) {
            return [$data];
        }

        return array_values($data);
    }
}
