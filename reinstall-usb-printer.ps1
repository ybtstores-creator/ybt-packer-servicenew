# Full USB printer reinstall
Write-Host "=== Removing USB printer device ==="
pnputil /remove-device "USBPRINT\XPRINTERXP-365B\6&27C498C4&0&USB001" 2>&1

Start-Sleep -Seconds 3

Write-Host "=== Scanning for devices ==="
pnputil /scan-devices 2>&1

Start-Sleep -Seconds 8

Write-Host "=== USB Monitor values ==="
$key = [Microsoft.Win32.Registry]::LocalMachine.OpenSubKey('SYSTEM\CurrentControlSet\Control\Print\Monitors\USB Monitor')
foreach ($n in $key.GetValueNames()) { 
    $v = $key.GetValue($n)
    Write-Host "  $n = $v" 
}
$key.Close()

Write-Host "=== Printer list ==="
Get-Printer | Select-Object Name, PortName, DriverName, PrinterStatus | Format-Table -AutoSize

Write-Host "=== USB Print Devices ==="
Get-CimInstance Win32_PnPEntity | Where-Object { $_.DeviceID -like '*USBPRINT*' } | Select-Object Name, DeviceID, Status | Format-List

Write-Host "=== Done ==="
