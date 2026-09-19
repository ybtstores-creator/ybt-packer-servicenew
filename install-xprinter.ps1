# Remove existing printer
Remove-Printer -Name 'Xprinter XP-365B' -ErrorAction SilentlyContinue

# Remove existing driver
Remove-PrinterDriver -Name 'Generic / Text Only' -ErrorAction SilentlyContinue

# Download the XPrinter XP-365B driver from official Xprinter source
$driverUrl = "https://www.xprintertech.com/uploads/files/20240115054722351204.zip"
$driverZip = "$env:TEMP\xprinter-driver.zip"
$driverDir = "$env:TEMP\xprinter-driver"

Write-Host "Downloading XPrinter XP-365B driver..."
try {
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    Invoke-WebRequest -Uri $driverUrl -OutFile $driverZip -TimeoutSec 60 -ErrorAction Stop
    Write-Host "Download complete."
} catch {
    Write-Host "Download from official source failed: $($_.Exception.Message)"
    Write-Host "Trying alternative source..."
    # Try the Seagull Scientific driver as alternative
    $driverUrl2 = "https://support.seagullscientific.com/entry.aspx?keepThis=true&id=380"
    try {
        Invoke-WebRequest -Uri $driverUrl2 -OutFile $driverZip -TimeoutSec 60 -ErrorAction Stop
        Write-Host "Alternative download complete."
    } catch {
        Write-Host "Alternative download also failed: $($_.Exception.Message)"
        Write-Host ""
        Write-Host "Falling back to built-in receipt printer driver..."
        # Use the Windows built-in TM-T88V or similar POS driver
        # Try adding with the OPOS driver
        $infFiles = Get-ChildItem "C:\Windows\System32\DriverStore\FileRepository" -Filter "*.inf" -Recurse -ErrorAction SilentlyContinue |
            Where-Object { $_.Name -match "receipt|pos|thermal|epson|tm-|star|citizen" } |
            Select-Object -First 5
        if ($infFiles) {
            Write-Host "Found compatible driver INF files:"
            $infFiles | ForEach-Object { Write-Host "  $($_.FullName)" }
        } else {
            Write-Host "No compatible receipt printer drivers found locally."
        }
        exit 1
    }
}

# Extract the driver
Write-Host "Extracting driver..."
if (Test-Path $driverDir) { Remove-Item $driverDir -Recurse -Force }
Expand-Archive -Path $driverZip -DestinationPath $driverDir -Force

# Find the INF file
$infFiles = Get-ChildItem $driverDir -Filter "*.inf" -Recurse -ErrorAction SilentlyContinue
if (-not $infFiles) {
    Write-Host "No INF files found in extracted driver!"
    # Try nested zip
    $nestedZips = Get-ChildItem $driverDir -Filter "*.zip" -Recurse
    foreach ($nz in $nestedZips) {
        Write-Host "Found nested zip: $($nz.FullName), extracting..."
        Expand-Archive -Path $nz.FullName -DestinationPath $driverDir -Force
    }
    $infFiles = Get-ChildItem $driverDir -Filter "*.inf" -Recurse
}

if ($infFiles) {
    Write-Host "Found driver INF files:"
    $infFiles | ForEach-Object { Write-Host "  $($_.FullName)" }
    
    # Install the driver
    foreach ($inf in $infFiles) {
        Write-Host "Installing driver from: $($inf.Name)"
        try {
            pnputil /add-driver $inf.FullName /install 2>&1
            Write-Host "Driver installed successfully!"
        } catch {
            Write-Host "Driver installation failed: $($_.Exception.Message)"
        }
    }
    
    # Now find and add the printer with the new driver
    # List available drivers after installation
    Get-PrinterDriver | Where-Object { $_.Name -match "xprint|xp-365|365" } | ForEach-Object {
        Write-Host "Found driver: $($_.Name)"
        try {
            Add-Printer -Name "Xprinter XP-365B" -DriverName $_.Name -PortName "USB001"
            Write-Host "Printer added with driver: $($_.Name)"
        } catch {
            Write-Host "Failed to add printer with this driver: $($_.Exception.Message)"
        }
    }
} else {
    Write-Host "ERROR: No INF files found in driver package!"
    exit 1
}

# Verify
Write-Host "`nFinal printer status:"
Get-Printer | Select-Object Name, DriverName, PortName, PrinterStatus | Format-Table -AutoSize
