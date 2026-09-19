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
Write-Host "OpenPrinter: $ok"

if (-not $ok) {
    $err = [System.Runtime.InteropServices.Marshal]::GetLastWin32Error()
    Write-Host "ERROR: Cannot open printer. Win32 error: $err"
    exit 1
}

$di = New-Object RawPrinter+DOCINFOA
$di.pDocName = "Feed Test"
$di.pDatatype = "RAW"

[RawPrinter]::StartDocPrinter($hPrinter, 1, [ref]$di) | Out-Null
[RawPrinter]::StartPagePrinter($hPrinter) | Out-Null

# Build ESC/POS command bytes
$esc = 0x1B
$gs = 0x1D
$lf = 0x0A

$hello = [System.Text.Encoding]::ASCII.GetBytes("HELLO FROM PC")

$commands = [byte[]]@(
    $esc, 0x40,           # ESC @ = initialize printer
    $hello[0], $hello[1], $hello[2], $hello[3], $hello[4], $hello[5], $hello[6], $hello[7], $hello[8], $hello[9], $hello[10], $hello[11], $hello[12],
    $lf, $lf, $lf,        # 3 line feeds
    $esc, 0x64, 3,        # ESC d 3 = feed 3 lines
    $lf                    # extra LF
)

$buf = [System.Runtime.InteropServices.Marshal]::AllocHGlobal($commands.Length)
[System.Runtime.InteropServices.Marshal]::Copy($commands, 0, $buf, $commands.Length)

$written = 0
$ok = [RawPrinter]::WritePrinter($hPrinter, $buf, $commands.Length, [ref]$written)
Write-Host "WritePrinter: $ok, bytes written: $written / $($commands.Length)"

if (-not $ok) {
    $err = [System.Runtime.InteropServices.Marshal]::GetLastWin32Error()
    Write-Host "ERROR: WritePrinter failed. Win32 error: $err"
}

[System.Runtime.InteropServices.Marshal]::FreeHGlobal($buf)
[RawPrinter]::EndPagePrinter($hPrinter) | Out-Null
[RawPrinter]::EndDocPrinter($hPrinter) | Out-Null
[RawPrinter]::ClosePrinter($hPrinter) | Out-Null
Write-Host "Done. Check the printer!"
