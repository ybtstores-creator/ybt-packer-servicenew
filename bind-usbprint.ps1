# Fix: Bind usbprint service to the XPrinter device
$ErrorActionPreference = "SilentlyContinue"

Write-Host "=== Step 1: Set usbprint service on device ==="
$devPath = 'HKLM:\SYSTEM\CurrentControlSet\Enum\USBPRINT\XPRINTERXP-365B\6&27C498C4&0&USB001'

# Set the service
Set-ItemProperty -Path $devPath -Name 'Service' -Value 'usbprint' -Force
Write-Host "Set Service = usbprint"

# Verify
$val = (Get-ItemProperty $devPath).Service
Write-Host "Verified Service: $val"

Write-Host "=== Step 2: Restart the device ==="
pnputil /restart-device "USBPRINT\XPRINTERXP-365B\6&27C498C4&0&USB001" 2>&1

Start-Sleep -Seconds 5

Write-Host "=== Step 3: Check results ==="
$dev = Get-CimInstance Win32_PnPEntity | Where-Object { $_.DeviceID -like '*XPRINT*' }
Write-Host "Service: $($dev.Service)"
Write-Host "Status: $($dev.Status)"
Write-Host "ConfigManagerErrorCode: $($dev.ConfigManagerErrorCode)"

Write-Host "=== USB Monitor ==="
$key = [Microsoft.Win32.Registry]::LocalMachine.OpenSubKey('SYSTEM\CurrentControlSet\Control\Print\Monitors\USB Monitor')
foreach ($n in $key.GetValueNames()) { 
    $v = $key.GetValue($n)
    Write-Host "  $n = $v" 
}
$key.Close()

Write-Host "=== Printers ==="
Get-Printer | Select-Object Name, PortName, DriverName | Format-Table -AutoSize
