# Comprehensive Mail Testing Script
# This script tests all email functionality and provides detailed results

Write-Host "=== R/E Pro Photos Mail Testing ===" -ForegroundColor Cyan
Write-Host ""

# Check if server is running
try {
    $config = Invoke-RestMethod -Uri "http://localhost:8000/api/test/mail/config" -Method GET
    Write-Host "✓ Laravel server is running" -ForegroundColor Green
    Write-Host "  Mail Driver: $($config.mailer)" -ForegroundColor Gray
    Write-Host "  SMTP Host: $($config.host):$($config.port)" -ForegroundColor Gray
    Write-Host "  From: $($config.from_name) <$($config.from_address)>" -ForegroundColor Gray
} catch {
    Write-Host "✗ Laravel server is not running or not accessible" -ForegroundColor Red
    Write-Host "  Please start the server with: php artisan serve" -ForegroundColor Yellow
    exit 1
}

Write-Host ""

# Check if MailHog is running
try {
    $mailhogResponse = Invoke-WebRequest -Uri "http://localhost:8025" -Method GET -TimeoutSec 5
    Write-Host "✓ MailHog is running at http://localhost:8025" -ForegroundColor Green
} catch {
    Write-Host "⚠ MailHog is not running" -ForegroundColor Yellow
    Write-Host "  Starting MailHog..." -ForegroundColor Gray
    
    if (Test-Path "tools\mailhog.exe") {
        Start-Process -FilePath "tools\mailhog.exe" -WindowStyle Minimized
        Start-Sleep -Seconds 3
        Write-Host "✓ MailHog started" -ForegroundColor Green
    } else {
        Write-Host "✗ MailHog executable not found" -ForegroundColor Red
        Write-Host "  Please run: .\setup-mailhog.ps1" -ForegroundColor Yellow
    }
}

Write-Host ""
Write-Host "=== Testing Individual Email Types ===" -ForegroundColor Cyan

# Test each email type
$emailTypes = @(
    @{ name = "Account Created"; endpoint = "account-created" },
    @{ name = "Shoot Scheduled"; endpoint = "shoot-scheduled" },
    @{ name = "Shoot Ready"; endpoint = "shoot-ready" },
    @{ name = "Payment Confirmation"; endpoint = "payment-confirmation" }
)

$results = @{}

foreach ($emailType in $emailTypes) {
    Write-Host "Testing $($emailType.name)..." -NoNewline
    
    try {
        $response = Invoke-RestMethod -Uri "http://localhost:8000/api/test/mail/$($emailType.endpoint)" -Method GET
        
        if ($response.success -eq $true) {
            Write-Host " ✓ SUCCESS" -ForegroundColor Green
            $results[$emailType.name] = @{ success = $true; message = $response.message }
        } else {
            Write-Host " ✗ FAILED" -ForegroundColor Red
            $results[$emailType.name] = @{ success = $false; message = $response.message }
        }
    } catch {
        Write-Host " ✗ ERROR" -ForegroundColor Red
        $results[$emailType.name] = @{ success = $false; message = $_.Exception.Message }
    }
}

Write-Host ""
Write-Host "=== Testing All Emails at Once ===" -ForegroundColor Cyan

try {
    $allResults = Invoke-RestMethod -Uri "http://localhost:8000/api/test/mail/all" -Method GET
    Write-Host "✓ Bulk test completed" -ForegroundColor Green
} catch {
    Write-Host "✗ Bulk test failed: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""
Write-Host "=== Summary ===" -ForegroundColor Cyan

$successCount = 0
$totalCount = $results.Count

foreach ($result in $results.GetEnumerator()) {
    $status = if ($result.Value.success) { "✓" } else { "✗" }
    $color = if ($result.Value.success) { "Green" } else { "Red" }
    
    Write-Host "$status $($result.Key)" -ForegroundColor $color
    
    if ($result.Value.success) {
        $successCount++
    } else {
        Write-Host "  Error: $($result.Value.message)" -ForegroundColor Gray
    }
}

Write-Host ""
Write-Host "Results: $successCount/$totalCount tests passed" -ForegroundColor $(if ($successCount -eq $totalCount) { "Green" } else { "Yellow" })

if ($successCount -eq $totalCount) {
    Write-Host ""
    Write-Host "🎉 All mail functionality is working correctly!" -ForegroundColor Green
    Write-Host "   Check MailHog at http://localhost:8025 to see the sent emails" -ForegroundColor Cyan
} else {
    Write-Host ""
    Write-Host "⚠ Some tests failed. Check the Laravel logs for more details:" -ForegroundColor Yellow
    Write-Host "   tail -f storage/logs/laravel.log" -ForegroundColor Gray
}

Write-Host ""
Write-Host "=== Next Steps ===" -ForegroundColor Cyan
Write-Host "1. Open MailHog web interface: http://localhost:8025" -ForegroundColor White
Write-Host "2. Check sent emails in MailHog inbox" -ForegroundColor White
Write-Host "3. Review email templates in resources/views/emails/" -ForegroundColor White
Write-Host "4. Configure production SMTP settings in .env when ready" -ForegroundColor White