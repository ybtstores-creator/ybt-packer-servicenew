Add-Type @"
using System;
using System.Runtime.InteropServices;

public class RawPrinterHelper {
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

$result = [RawPrinterHelper]::OpenPrinter($printerName, [ref]$hPrinter, [IntPtr]::Zero)
if (-not $result) {
    $err = [System.Runtime.InteropServices.Marshal]::GetLastWin32Error()
    Write-Host "OpenPrinter failed. Error: $err"
    exit 1
}
Write-Host "Printer opened successfully."

$docInfo = New-Object RawPrinterHelper+DOCINFOA
$docInfo.pDocName = "Test Receipt"
$docInfo.pOutputFile = $null
$docInfo.pDatatype = "RAW"

$result = [RawPrinterHelper]::StartDocPrinter($hPrinter, 1, [ref]$docInfo)
if (-not $result) {
    $err = [System.Runtime.InteropServices.Marshal]::GetLastWin32Error()
    Write-Host "StartDocPrinter failed. Error: $err"
    [RawPrinterHelper]::ClosePrinter($hPrinter)
    exit 1
}

[RawPrinterHelper]::StartPagePrinter($hPrinter) | Out-Null

# Build receipt text
$receiptText = @"
==============================
  TEST RECEIPT
  Xprinter XP-365B
  $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')
==============================

Item 1 .............. $10.00
Item 2 .............. $25.00
Item 3 .............. $15.00
------------------------------
TOTAL ............... $50.00

Thank you for your visit!
==============================
"@

# Convert to bytes
$bytes = [System.Text.Encoding]::ASCII.GetBytes($receiptText)
$buf = [System.Runtime.InteropServices.Marshal]::AllocHGlobal($bytes.Length)
[System.Runtime.InteropServices.Marshal]::Copy($bytes, 0, $buf, $bytes.Length)

$written = 0
$result = [RawPrinterHelper]::WritePrinter($hPrinter, $buf, $bytes.Length, [ref]$written)
if ($result) {
    Write-Host "Data sent successfully! Bytes written: $written"
} else {
    $err = [System.Runtime.InteropServices.Marshal]::GetLastWin32Error()
    Write-Host "WritePrinter failed. Error: $err"
}

[System.Runtime.InteropServices.Marshal]::FreeHGlobal($buf)

[RawPrinterHelper]::EndPagePrinter($hPrinter) | Out-Null
[RawPrinterHelper]::EndDocPrinter($hPrinter) | Out-Null
[RawPrinterHelper]::ClosePrinter($hPrinter) | Out-Null

Write-Host "Print job completed. Check the printer!"
