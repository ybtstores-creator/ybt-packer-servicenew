# Fix the broken child device
$ErrorActionPreference = "SilentlyContinue"

Write-Host "=== Step 1: Remove incorrect Service binding ==="
$devPath = 'HKLM:\SYSTEM\CurrentControlSet\Enum\USBPRINT\XPRINTERXP-365B\6&27C498C4&0&USB001'
Remove-ItemProperty -Path $devPath -Name 'Service' -Force -ErrorAction SilentlyContinue
Write-Host "Removed Service property from child device"

Write-Host "=== Step 2: Restart the device ==="
pnputil /restart-device "USBPRINT\XPRINTERXP-365B\6&27C498C4&0&USB001" 2>&1
Start-Sleep -Seconds 5

Write-Host "=== Step 3: Check parent USB device ==="
$parent = Get-CimInstance Win32_PnPEntity | Where-Object { $_.DeviceID -like '*1FC9*2016*' }
Write-Host "Parent Name: $($parent.Name)"
Write-Host "Parent Status: $($parent.Status)"
Write-Host "Parent Service: $($parent.Service)"
Write-Host "Parent Error: $($parent.ConfigManagerErrorCode)"

Write-Host ""
Write-Host "=== Step 4: Check child device ==="
$child = Get-CimInstance Win32_PnPEntity | Where-Object { $_.DeviceID -like '*XPRINT*' }
Write-Host "Child Name: $($child.Name)"
Write-Host "Child Status: $($child.Status)"
Write-Host "Child Service: $($child.Service)"
Write-Host "Child Error: $($child.ConfigManagerErrorCode)"

Write-Host ""
Write-Host "=== Step 5: USB Monitor ==="
$key = [Microsoft.Win32.Registry]::LocalMachine.OpenSubKey('SYSTEM\CurrentControlSet\Control\Print\Monitors\USB Monitor')
foreach ($n in $key.GetValueNames()) { 
    $v = $key.GetValue($n)
    Write-Host "  $n = $v" 
}
$key.Close()

Write-Host ""
Write-Host "=== Step 6: Check spoolsv loaded modules ==="
try {
    $spool = Get-Process spoolsv -ErrorAction Stop
    Write-Host "spoolsv PID: $($spool.Id)"
    Write-Host "spoolsv path: $($spool.Path)"
} catch {
    Write-Host "spoolsv not running!"
}

Write-Host ""
Write-Host "=== Step 7: Check usbmon.dll exists ==="
Test-Path 'C:\Windows\System32\usbmon.dll'
Get-Item 'C:\Windows\System32\usbmon.dll' | Select-Object Name, Length, LastWriteTime
