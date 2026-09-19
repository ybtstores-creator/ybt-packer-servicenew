Add-Type @"
using System;
using System.Runtime.InteropServices;

public class RawPrinter {
    [DllImport("winspool.drv", CharSet = CharSet.Ansi, SetLastError = true)]
    public static extern bool OpenPrinter(string pPrinterName, out IntPtr hPrinter, IntPtr pDefault);
    [DllImport("winspool.drv", SetLastError = true)]
    public static extern bool ClosePrinter(IntPtr hPrinter);
    [DllImport("winspool.drv", CharSet = CharSet.Ansi, SetLastError = true)]
    public static extern bool StartDocPrinter(IntPtr hPrinter, int level, [In] ref DOCINFOA di);
    [DllImport("winspool.drv", SetLastError = true)]
    public static extern bool EndDocPrinter(IntPtr hPrinter);
    [DllImport("winspool.drv", SetLastError = true)]
    public static extern bool StartPagePrinter(IntPtr hPrinter);
    [DllImport("winspool.drv", SetLastError = true)]
    public static extern bool EndPagePrinter(IntPtr hPrinter);
    [DllImport("winspool.drv", SetLastError = true)]
    public static extern bool WritePrinter(IntPtr hPrinter, IntPtr pBuf, int cbBuf, out int pcWritten);
    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Ansi)]
    public struct DOCINFOA {
        [MarshalAs(UnmanagedType.LPStr)] public string pDocName;
        [MarshalAs(UnmanagedType.LPStr)] public string pOutputFile;
        [MarshalAs(UnmanagedType.LPStr)] public string pDatatype;
    }
}
"@

$printerName = "Xprinter XP-365B"
$hPrinter = [IntPtr]::Zero
[RawPrinter]::OpenPrinter($printerName, [ref]$hPrinter, [IntPtr]::Zero) | Out-Null

$di = New-Object RawPrinter+DOCINFOA
$di.pDocName = "Test"
$di.pDatatype = "RAW"
[RawPrinter]::StartDocPrinter($hPrinter, 1, [ref]$di) | Out-Null
[RawPrinter]::StartPagePrinter($hPrinter) | Out-Null

# Simple ESC/POS: init printer, print line, feed paper, cut
# ESC @ = initialize printer
# ESC a 1 = center align
# LF = line feed
# GS V m = cut paper
$text = "HELLO`r`nTHIS IS A TEST`r`nFROM YOUR PC`r`n`r`n"
$bytes = [System.Text.Encoding]::ASCII.GetBytes($text)
$buf = [System.Runtime.InteropServices.Marshal]::AllocHGlobal($bytes.Length)
[System.Runtime.InteropServices.Marshal]::Copy($bytes, 0, $buf, $bytes.Length)
$written = 0
[RawPrinter]::WritePrinter($hPrinter, $buf, $bytes.Length, [ref]$written) | Out-Null
[System.Runtime.InteropServices.Marshal]::FreeHGlobal($buf)

Write-Host "Sent $written bytes to printer"

[RawPrinter]::EndPagePrinter($hPrinter) | Out-Null
[RawPrinter]::EndDocPrinter($hPrinter) | Out-Null
[RawPrinter]::ClosePrinter($hPrinter) | Out-Null
