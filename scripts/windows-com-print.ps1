param(
    [Parameter(Mandatory = $true)]
    [string]$PortName,
    [Parameter(Mandatory = $true)]
    [string]$FilePath,
    [int]$BaudRate = 9600
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $FilePath)) {
    throw "File tidak ditemukan: $FilePath"
}

$PortName = $PortName.ToUpper()
$bytes = [System.IO.File]::ReadAllBytes($FilePath)

function Write-ViaCopy {
    param([string]$Port, [string]$Path)
    $p = Start-Process -FilePath "cmd.exe" -ArgumentList @("/c", "copy /b `"$Path`" \\.\$Port") -Wait -PassThru -WindowStyle Hidden
    if ($p.ExitCode -ne 0) {
        throw "copy /b gagal (exit $($p.ExitCode))"
    }
}

# USB00x / LPT
if ($PortName -match '^(USB|LPT)\d+') {
    try {
        Write-ViaCopy -Port $PortName -Path $FilePath
        Write-Output "OK"
        exit 0
    } catch {
        throw "Gagal tulis $PortName : $($_.Exception.Message)"
    }
}

if ($PortName -notmatch '^COM\d+$') {
    throw "Port tidak didukung: $PortName"
}

try {
    cmd /c "mode ${PortName}: BAUD=$BaudRate PARITY=N DATA=8 STOP=1 XON=OFF" | Out-Null
} catch {}

# Coba copy /b dulu (sering berhasil saat SerialPort "Access Denied")
try {
    Write-ViaCopy -Port $PortName -Path $FilePath
    Write-Output "OK"
    exit 0
} catch {}

# SerialPort dijalankan di job agar bisa di-timeout (hindari hang XAMPP)
$job = Start-Job -ScriptBlock {
    param($Port, $Baud, $Data)
    $sp = $null
    try {
        $sp = New-Object System.IO.Ports.SerialPort $Port, $Baud, ([System.IO.Ports.Parity]::None), 8, ([System.IO.Ports.StopBits]::One)
        $sp.Handshake = [System.IO.Ports.Handshake]::None
        $sp.WriteTimeout = 1500
        $sp.ReadTimeout = 800
        $sp.DtrEnable = $true
        $sp.RtsEnable = $true
        $sp.Open()
        $sp.Write($Data, 0, $Data.Length)
        Start-Sleep -Milliseconds 50
        return "OK"
    } finally {
        if ($sp) {
            if ($sp.IsOpen) { $sp.Close() }
            $sp.Dispose()
        }
    }
} -ArgumentList $PortName, $BaudRate, $bytes

$finished = Wait-Job $job -Timeout 3
if (-not $finished) {
    Stop-Job $job -ErrorAction SilentlyContinue
    Remove-Job $job -Force -ErrorAction SilentlyContinue
    throw "Timeout membuka $PortName (port sibuk/tidak ada printer)."
}

try {
    $result = Receive-Job $job -ErrorAction Stop
    Remove-Job $job -Force -ErrorAction SilentlyContinue
    if ($result -ne "OK") {
        throw "SerialPort gagal"
    }
    Write-Output "OK"
    exit 0
} catch {
    Remove-Job $job -Force -ErrorAction SilentlyContinue
    throw "Gagal tulis $PortName : $($_.Exception.Message)"
}
