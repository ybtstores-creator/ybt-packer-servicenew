# Create a simple test file
$testFile = "$env:TEMP\printer-test.txt"
"HELLO FROM THE PC" | Out-File $testFile -Encoding ASCII
"This is a test print" | Out-File $testFile -Encoding ASCII -Append
"Xprinter XP-365B" | Out-File $testFile -Encoding ASCII -Append
(Get-Date).ToString("yyyy-MM-dd HH:mm:ss") | Out-File $testFile -Encoding ASCII -Append

Write-Host "Test file created at: $testFile"
Write-Host "Content:"
Get-Content $testFile
Write-Host ""

# Print using shell execute (like right-click > Print)
Write-Host "Printing via shell..."
Start-Process -FilePath "notepad.exe" -ArgumentList "/p `"$testFile`"" -Wait -WindowStyle Hidden
Write-Host "Print command sent."
