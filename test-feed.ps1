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

$ok = [RawPrinter]::OpenPrinter($printerName, [ref]$hPrinter, [IntPtr]::Zero)
Write-Host "OpenPrinter: $ok (handle: $hPrinter)"

if (-not $ok -or $hPrinter -eq [IntPtr]::Zero) {
    $err = [System.Runtime.InteropServices.Marshal]::GetLastWin32Error()
    Write-Host "ERROR: Cannot open printer. Win32 error: $err"
    exit 1
}

$di = New-Object RawPrinter+DOCINFOA
$di.pDocName = "Feed Test"
$di.pDatatype = "RAW"

$ok = [RawPrinter]::StartDocPrinter($hPrinter, 1, [ref]$di)
Write-Host "StartDocPrinter: $ok"

$ok = [RawPrinter]::StartPagePrinter($hPrinter)
Write-Host "StartPagePrinter: $ok"

# ESC @ = initialize printer
# GS V 0 = full cut (if cutter exists)
# Feed a few lines
$esc = [byte]0x1B  # ESC
$gs = [byte]0x1D  # GS
$feed = [byte]0x0A # LF

$commands = New-Object System.Collections.Generic.List[byte]
$commands.AddRange([byte[]]@($esc, 0x40))  # ESC @ - initialize
$commands.AddRange([byte[]]@([byte]'H','E','L','L','O'))
$commands.AddRange([byte[]]@($feed, $feed, $feed))  # 3 line feeds
$commands.AddRange([byte[]]@($esc, 0x64, 3))  # ESC d 3 = feed 3 lines
$commands.AddRange([byte[]]@($feed))  # extra LF

$bytes = $commands.ToArray()
$buf = [System.Runtime.InteropServices.Marshal]::AllocHGlobal($bytes.Length)
[System.Runtime.InteropServices.Marshal]::Copy($bytes, 0, $buf, $bytes.Length)

$written = 0
$ok = [RawPrinter]::WritePrinter($hPrinter, $buf, $bytes.Length, [ref]$written)
Write-Host "WritePrinter: $ok, bytes written: $written / $($bytes.Length)"

if (-not $ok) {
    $err = [System.Runtime.InteropServices.Marshal]::GetLastWin32Error()
    Write-Host "ERROR: WritePrinter failed. Win32 error: $err"
}

[System.Runtime.InteropServices.Marshal]::FreeHGlobal($buf)
[RawPrinter]::EndPagePrinter($hPrinter) | Out-Null
[RawPrinter]::EndDocPrinter($hPrinter) | Out-Null
[RawPrinter]::ClosePrinter($hPrinter) | Out-Null
Write-Host "Done."
