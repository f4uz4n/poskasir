param(
    [Parameter(Mandatory = $true)]
    [string]$PrinterName,
    [Parameter(Mandatory = $true)]
    [string]$FilePath
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $FilePath)) {
    throw "File struk tidak ditemukan: $FilePath"
}

$printer = $PrinterName.Trim()
if ($printer -eq '') {
    throw "Nama printer kosong."
}

$errors = @()

# Metode 1: perintah print Windows (sama seperti Notepad/Word lewat driver)
try {
    $quotedPrinter = '"' + $printer.Replace('"', '""') + '"'
    $quotedFile = '"' + $FilePath.Replace('"', '""') + '"'
    $p = Start-Process -FilePath "cmd.exe" -ArgumentList @("/c", "print /D:$quotedPrinter $quotedFile") -Wait -PassThru -WindowStyle Hidden
    if ($p.ExitCode -eq 0) {
        Write-Output "OK"
        exit 0
    }
    $errors += "print exit $($p.ExitCode)"
} catch {
    $errors += $_.Exception.Message
}

# Metode 2: Out-Printer (PowerShell, lewat spooler + driver)
try {
    $content = Get-Content -LiteralPath $FilePath -Raw -Encoding UTF8
    if ($null -ne $content -and $content.Length -gt 0) {
        $content | Out-Printer -Name $printer
        Write-Output "OK"
        exit 0
    }
    $errors += "file kosong"
} catch {
    $errors += $_.Exception.Message
}

throw "Gagal cetak lewat driver Windows '$printer': $($errors -join ' | ')"
