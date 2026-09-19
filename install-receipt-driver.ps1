# Install the built-in receipt printer driver
$infPath = "C:\Windows\System32\DriverStore\FileRepository\c_receiptprinter.inf_amd64_7952e4baaee88d58\c_receiptprinter.inf"
Write-Host "Installing receipt printer driver..."
pnputil /add-driver $infPath /install 2>&1

Write-Host "`nListing printer drivers with 'receipt' or 'POS'..."
Get-PrinterDriver | Where-Object { $_.Name -match "receipt|pos|thermal|Generic" } | ForEach-Object {
    Write-Host "  Driver: $($_.Name)"
}

# Check what models are available in the receipt printer driver
Write-Host "`nChecking available models in c_receiptprinter.inf..."
Get-Content $infPath | Select-String -Pattern "DeviceDesc|Compatible" -CaseSensitive:$false | ForEach-Object { Write-Host "  $_" } | Select-Object -First 20
