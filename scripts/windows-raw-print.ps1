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

Add-Type -TypeDefinition @"
using System;
using System.Runtime.InteropServices;

public class KasirFlowRawPrinter {
    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Ansi)]
    public class DOCINFOA {
        [MarshalAs(UnmanagedType.LPStr)] public string pDocName;
        [MarshalAs(UnmanagedType.LPStr)] public string pOutputFile;
        [MarshalAs(UnmanagedType.LPStr)] public string pDataType;
    }

    [DllImport("winspool.Drv", EntryPoint = "OpenPrinterA", SetLastError = true, CharSet = CharSet.Ansi, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool OpenPrinter([MarshalAs(UnmanagedType.LPStr)] string szPrinter, out IntPtr hPrinter, IntPtr pd);

    [DllImport("winspool.Drv", EntryPoint = "ClosePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool ClosePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "StartDocPrinterA", SetLastError = true, CharSet = CharSet.Ansi, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern int StartDocPrinter(IntPtr hPrinter, int level, [In, MarshalAs(UnmanagedType.LPStruct)] DOCINFOA di);

    [DllImport("winspool.Drv", EntryPoint = "EndDocPrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool EndDocPrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "StartPagePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool StartPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "EndPagePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool EndPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "WritePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool WritePrinter(IntPtr hPrinter, IntPtr pBytes, int dwCount, out int dwWritten);

    public static bool SendBytes(string printerName, byte[] bytes, string dataType = "RAW") {
        IntPtr hPrinter = IntPtr.Zero;
        IntPtr pUnmanagedBytes = IntPtr.Zero;
        bool success = false;
        try {
            DOCINFOA di = new DOCINFOA();
            di.pDocName = "KasirFlow RAW";
            di.pDataType = dataType ?? "RAW";
            int written = 0;
            pUnmanagedBytes = Marshal.AllocCoTaskMem(bytes.Length);
            Marshal.Copy(bytes, 0, pUnmanagedBytes, bytes.Length);
            if (!OpenPrinter(printerName, out hPrinter, IntPtr.Zero)) {
                return false;
            }
            if (StartDocPrinter(hPrinter, 1, di) == 0) {
                return false;
            }
            if (!StartPagePrinter(hPrinter)) {
                EndDocPrinter(hPrinter);
                return false;
            }
            success = WritePrinter(hPrinter, pUnmanagedBytes, bytes.Length, out written);
            EndPagePrinter(hPrinter);
            EndDocPrinter(hPrinter);
            return success && written > 0;
        } finally {
            if (hPrinter != IntPtr.Zero) {
                ClosePrinter(hPrinter);
            }
            if (pUnmanagedBytes != IntPtr.Zero) {
                Marshal.FreeCoTaskMem(pUnmanagedBytes);
            }
        }
    }
}
"@

$bytes = [System.IO.File]::ReadAllBytes($FilePath)
$dataTypes = @('RAW', 'TEXT', 'XPS_PASS')

foreach ($dt in $dataTypes) {
    $ok = [KasirFlowRawPrinter]::SendBytes($PrinterName, $bytes, $dt)
    if ($ok) {
        Write-Output "OK"
        exit 0
    }
}

throw "Windows menolak kirim data ke printer '$PrinterName'. Coba mode driver di Pengaturan atau pasang Generic/Text Only."
