<?php

namespace App\Services;

use Mike42\Escpos\PrintConnectors\FilePrintConnector;
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
        $script = 'Get-CimInstance Win32_PnPEntity | Where-Object { $_.Name -match "COM\d+" } | Select-Object -ExpandProperty Name';
        $output = $this->powershell($script);
        $ports = [];
        foreach (preg_split('/\r\n|\n/', $output) ?: [] as $line) {
            if (preg_match('/(COM\d+)/i', $line, $m)) {
                $ports[] = strtoupper($m[1]);
            }
        }

        return array_values(array_unique($ports));
    }

    public function guessRppPrinter(?string $preferred = null): ?array
    {
        $printers = $this->listPrinters();
        if ($preferred) {
            foreach ($printers as $printer) {
                if (strcasecmp($printer['name'], $preferred) === 0) {
                    return $printer;
                }
            }
        }

        foreach ($printers as $printer) {
            if (preg_match('/rpp|rongta|pos58|thermal|receipt|usb printing/i', $printer['name'].' '.($printer['driver'] ?? ''))) {
                return $printer;
            }
        }

        foreach ($printers as $printer) {
            if (preg_match('/^USB\d+/i', (string) $printer['port'])) {
                return $printer;
            }
        }

        return $printers[0] ?? null;
    }

    public function printRaw(string $bytes, ?string $printerName = null, ?string $comPort = null, int $baud = 9600): array
    {
        $errors = [];
        $target = $this->guessRppPrinter($printerName);

        if ($comPort) {
            try {
                $this->writeCom($comPort, $bytes, $baud);

                return ['ok' => true, 'via' => 'com', 'target' => strtoupper($comPort)];
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

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

            if ($target['port'] && preg_match('/^(USB\d+|COM\d+)/i', $target['port'], $m)) {
                try {
                    $this->writePort($m[1], $bytes, $baud);

                    return ['ok' => true, 'via' => 'port', 'target' => $m[1]];
                } catch (Throwable $e) {
                    $errors[] = 'Port '.$m[1].': '.$e->getMessage();
                }
            }
        }

        foreach (range(1, 8) as $n) {
            $port = sprintf('USB%03d', $n);
            try {
                $this->writePort($port, $bytes, $baud);

                return ['ok' => true, 'via' => 'usb-port', 'target' => $port];
            } catch (Throwable $e) {
                $errors[] = $port.': '.$e->getMessage();
            }
        }

        foreach ($this->listComPorts() as $port) {
            try {
                $this->writeCom($port, $bytes, $baud);

                return ['ok' => true, 'via' => 'com-auto', 'target' => $port];
            } catch (Throwable $e) {
                $errors[] = $port.': '.$e->getMessage();
            }
        }

        throw new \RuntimeException(
            'Gagal cetak USB RPP02N. Pasang printer di Windows (Devices & Printers), pilih namanya di Pengaturan, lalu tes lagi. '
            .implode(' | ', array_slice($errors, 0, 4))
        );
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
        $process->setTimeout(20);
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

        $path = '\\\\.\\'.$port;
        $fp = @fopen($path, 'wb');
        if (! $fp) {
            throw new \RuntimeException('Tidak bisa membuka '.$port);
        }
        $written = fwrite($fp, $bytes);
        fflush($fp);
        fclose($fp);
        if ($written === false || $written < 1) {
            throw new \RuntimeException('Tidak ada data terkirim ke '.$port);
        }
    }

    protected function writeCom(string $port, string $bytes, int $baud): void
    {
        $port = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $port) ?? '');
        if (! preg_match('/^COM\d+$/', $port)) {
            throw new \RuntimeException('Port COM tidak valid');
        }

        $mode = new Process(['cmd', '/c', sprintf('mode %s: BAUD=%d PARITY=N DATA=8 STOP=1 XON=OFF', $port, $baud)]);
        $mode->setTimeout(8);
        $mode->run();

        $connector = new FilePrintConnector('\\\\.\\'.$port);
        $connector->write($bytes);
        $connector->finalize();
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
