param(
    [Parameter(Mandatory = $true)]
    [string]$PrinterName,
    [Parameter(Mandatory = $true)]
    [string]$FilePath,
    [int]$PaperWidth = 58
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $FilePath)) {
    throw "File struk tidak ditemukan: $FilePath"
}

$printer = $PrinterName.Trim()
if ($printer -eq '') {
    throw "Nama printer kosong."
}

$content = Get-Content -LiteralPath $FilePath -Raw -Encoding UTF8
Remove-Item -LiteralPath $FilePath -Force -ErrorAction SilentlyContinue
if ($null -eq $content -or $content.Trim().Length -eq 0) {
    throw "File struk kosong."
}

if ($PaperWidth -ne 80) {
    $PaperWidth = 58
}

$dll = Join-Path $PSScriptRoot 'KasirFlowThermalPrint.dll'
if (-not (Test-Path -LiteralPath $dll)) {
    $csc = @(
        "${env:WINDIR}\Microsoft.NET\Framework64\v4.0.30319\csc.exe",
        "${env:WINDIR}\Microsoft.NET\Framework\v4.0.30319\csc.exe"
    ) | Where-Object { Test-Path $_ } | Select-Object -First 1
    if (-not $csc) {
        throw 'Compiler C# tidak ditemukan. Jalankan: scripts/build-print-dll.bat'
    }
    $src = Join-Path $PSScriptRoot 'KasirFlowThermalPrint.cs'
    & $csc /nologo /target:library "/out:$dll" $src /r:System.Drawing.dll
    if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $dll)) {
        throw 'Gagal compile KasirFlowThermalPrint.dll'
    }
}

Add-Type -Path $dll

$job = New-Object KasirFlowThermalPrint
$job.PrinterName = $printer
$job.Content = $content
$job.PaperWidthMm = $PaperWidth
$job.Run()

Write-Output 'OK'
