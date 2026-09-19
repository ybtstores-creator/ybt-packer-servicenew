# Fix USB printer port registration
$ErrorActionPreference = "SilentlyContinue"

Write-Host "=== Step 1: Remove broken printer ==="
Remove-Printer -Name 'Xprinter XP-365B'

Write-Host "=== Step 2: Clean up old registry ==="
Remove-Item 'HKLM:\SYSTEM\CurrentControlSet\Control\Print\Printers\Xprinter XP-365B' -Recurse -Force

Write-Host "=== Step 3: Register USB001 port in USB Monitor ==="
# The USB Monitor registry needs Ports (REG_SZ) and UsbPortList (REG_MULTI_SZ) values
$monitorPath = 'HKLM:\SYSTEM\CurrentControlSet\Control\Print\Monitors\USB Monitor'

# Set Ports value to USB001
Set-ItemProperty -Path $monitorPath -Name 'Ports' -Value 'USB001' -Type String -Force
Write-Host "Set Ports = USB001"

# Set UsbPortList value
Set-ItemProperty -Path $monitorPath -Name 'UsbPortList' -Value @('USB001') -Type MultiString -Force
Write-Host "Set UsbPortList = USB001"

# Create the USB001 port subkey with device details
$portPath = "$monitorPath\USB001"
if (-not (Test-Path $portPath)) {
    New-Item -Path $monitorPath -Name 'USB001' -Force | Out-Null
}
Set-ItemProperty -Path $portPath -Name '(default)' -Value 'Xprinter XP-365B' -Force

Write-Host "=== Step 4: Restart Print Spooler ==="
Stop-Service -Name Spooler -Force
Start-Sleep -Seconds 2
Start-Service -Name Spooler
Start-Sleep -Seconds 3
Write-Host "Spooler restarted"

Write-Host "=== Step 5: Verify USB Monitor ports ==="
$key = [Microsoft.Win32.Registry]::LocalMachine.OpenSubKey('SYSTEM\CurrentControlSet\Control\Print\Monitors\USB Monitor')
foreach ($n in $key.GetValueNames()) {
    $v = $key.GetValue($n)
    Write-Host "  $n = $v"
}
$key.Close()

Write-Host "=== Step 6: Re-add printer ==="
Add-Printer -Name 'Xprinter XP-365B' -DriverName 'Generic / Text Only' -PortName 'USB001'
Write-Host "Printer re-added"

Write-Host "=== Done ==="
Get-Printer -Name 'Xprinter XP-365B' | Select-Object Name, DriverName, PortName, PrinterStatus | Format-List
