<?php

namespace App\Services;

use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Symfony\Component\Process\Process;
use Throwable;

class WindowsPrinterService
{
    /** @return array<int,array{name:string,port:?string,driver:?string}> */
    public function listPrinters(): array
    {
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

        return $printers;
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

        return (bool) preg_match(
            '/pos|thermal|receipt|epson|tm-|xprinter|xp-|gprinter|gp-|rongta|rpp|goojprt|hakpost|hprt|hpc|star|sm-|bixolon|srp-|citizen|zebra|tsc|gainscha|munbyn|printer\s*58|printer\s*80|generic\s*\/\s*text|usb printing support/i',
            $blob
        );
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

        foreach ($printers as $printer) {
            if (preg_match('/^USB\d+/i', (string) $printer['port']) && ! $this->isVirtualPrinter($printer)) {
                return $printer;
            }
        }

        return null;
    }

    public function printRaw(string $bytes, ?string $printerName = null, ?string $comPort = null, int $baud = 9600): array
    {
        $errors = [];
        $explicit = $printerName ? trim($printerName) : '';
        $com = $comPort ? strtoupper(trim($comPort)) : null;

        // Nama "COMx" yang tersimpan sebagai printer_name
        if ($explicit !== '' && preg_match('/^COM\d+$/i', $explicit)) {
            $com = strtoupper($explicit);
            $explicit = '';
        }

        // 1) Utamakan Windows spooler (Generic/Text) jika ada nama printer nyata
        if ($explicit !== '' && ! $this->isVirtualPrinter(['name' => $explicit])) {
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
        }

        // 2) Port COM (banyak RPP/Hakpost tampil sebagai USB-Serial)
        if ($com) {
            try {
                $this->writeCom($com, $bytes, $baud);

                return ['ok' => true, 'via' => 'com', 'target' => $com];
            } catch (Throwable $e) {
                $errors[] = $com.': '.$e->getMessage();
                // Coba baud alternatif umum thermal
                foreach ([115200, 57600, 38400, 19200] as $altBaud) {
                    if ($altBaud === $baud) {
                        continue;
                    }
                    try {
                        $this->writeCom($com, $bytes, $altBaud);

                        return ['ok' => true, 'via' => 'com-baud-'.$altBaud, 'target' => $com];
                    } catch (Throwable $e2) {
                        $errors[] = $com.'@'.$altBaud.': '.$e2->getMessage();
                    }
                }
            }
        }

        $target = $this->guessRppPrinter($explicit !== '' ? $explicit : null);
        if ($target) {
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

            if (! empty($target['port']) && preg_match('/^(USB\d+|COM\d+)/i', (string) $target['port'], $m)) {
                try {
                    $this->writePort($m[1], $bytes, $baud);

                    return ['ok' => true, 'via' => 'port', 'target' => strtoupper($m[1])];
                } catch (Throwable $e) {
                    $errors[] = 'Port '.$m[1].': '.$e->getMessage();
                }
            }
        }

        // Hanya coba COM tersimpan / suggested — jangan scan semua COM (bisa hang)
        if (! $com) {
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

        foreach (range(1, 4) as $n) {
            $port = sprintf('USB%03d', $n);
            try {
                $this->writePort($port, $bytes, $baud);

                return ['ok' => true, 'via' => 'usb-port', 'target' => $port];
            } catch (Throwable $e) {
                $errors[] = $port.': '.$e->getMessage();
            }
        }

        $hint = 'Gagal cetak USB. Pasang printer, di Windows instal sebagai Generic/Text Only (Devices & Printers), '
            .'lalu di Pengaturan pilih printer itu ATAU port COM, Simpan, Tes cetak. ';
        if (stripos(implode(' ', $errors), 'access') !== false || stripos(implode(' ', $errors), 'denied') !== false) {
            $hint .= 'Port COM sedang dipakai driver lain — tutup aplikasi lain yang memakai printer, atau pakai nama printer Windows (bukan COM). ';
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
