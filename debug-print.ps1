Add-Type @"
using System;
using System.Runtime.InteropServices;
public class RawPrint {
    [DllImport("winspool.drv", CharSet = CharSet.Ansi, SetLastError = true)]
    public static extern bool OpenPrinter(string n, out IntPtr h, IntPtr d);
    [DllImport("winspool.drv", SetLastError = true)]
    public static extern bool ClosePrinter(IntPtr h);
    [DllImport("winspool.drv", CharSet = CharSet.Ansi, SetLastError = true)]
    public static extern bool StartDocPrinter(IntPtr h, int l, [In] ref DOCINFO di);
    [DllImport("winspool.drv", SetLastError = true)]
    public static extern bool EndDocPrinter(IntPtr h);
    [DllImport("winspool.drv", SetLastError = true)]
    public static extern bool StartPagePrinter(IntPtr h);
    [DllImport("winspool.drv", SetLastError = true)]
    public static extern bool EndPagePrinter(IntPtr h);
    [DllImport("winspool.drv", SetLastError = true)]
    public static extern bool WritePrinter(IntPtr h, IntPtr b, int c, out int w);
    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Ansi)]
    public struct DOCINFO {
        [MarshalAs(UnmanagedType.LPStr)] public string pDocName;
        [MarshalAs(UnmanagedType.LPStr)] public string pOutputFile;
        [MarshalAs(UnmanagedType.LPStr)] public string pDatatype;
    }
}
"@

$h = [IntPtr]::Zero
[RawPrint]::OpenPrinter("Xprinter XP-365B", [ref]$h, [IntPtr]::Zero) | Out-Null
$di = New-Object RawPrint+DOCINFO
$di.pDocName = "Debug Test"
$di.pDatatype = "RAW"
[RawPrint]::StartDocPrinter($h, 1, [ref]$di) | Out-Null
[RawPrint]::StartPagePrinter($h) | Out-Null

$bytes = [System.Text.Encoding]::ASCII.GetBytes("HELLO WORLD TEST 12345`r`nLine 2 test`r`n")
$buf = [System.Runtime.InteropServices.Marshal]::AllocHGlobal($bytes.Length)
[System.Runtime.InteropServices.Marshal]::Copy($bytes, 0, $buf, $bytes.Length)
$w = 0
$ok = [RawPrint]::WritePrinter($h, $buf, $bytes.Length, [ref]$w)
Write-Host "WritePrinter returned: $ok, bytes: $w"

[System.Runtime.InteropServices.Marshal]::FreeHGlobal($buf)
[RawPrint]::EndPagePrinter($h) | Out-Null
[RawPrint]::EndDocPrinter($h) | Out-Null
[RawPrint]::ClosePrinter($h) | Out-Null

Start-Sleep -Milliseconds 500

# Check spooler folder
Write-Host "=== Spooler files ==="
Get-ChildItem 'C:\Windows\System32\spool\PRINTERS' -ErrorAction SilentlyContinue | Select-Object Name, Length, LastWriteTime

# Check print queue
Write-Host "=== Print jobs in queue ==="
Get-PrintJob -PrinterName 'Xprinter XP-365B' -ErrorAction SilentlyContinue | Select-Object JobId, JobStatus, DocumentName, TotalPages

# Check print processor log
Write-Host "=== Recent System Events (Print) ==="
Get-WinEvent -FilterHashtable @{LogName='System'; ProviderName='Microsoft-Windows-Spooler'} -MaxEvents 5 -ErrorAction SilentlyContinue | Select-Object TimeCreated, Message | Format-List
