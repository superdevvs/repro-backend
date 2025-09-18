# MailHog Setup Script for Windows
# This script downloads and runs MailHog for local email testing

Write-Host "Setting up MailHog for local email testing..." -ForegroundColor Green

# Create a tools directory if it doesn't exist
$toolsDir = ".\tools"
if (!(Test-Path $toolsDir)) {
    New-Item -ItemType Directory -Path $toolsDir
    Write-Host "Created tools directory" -ForegroundColor Yellow
}

# MailHog executable path
$mailhogPath = "$toolsDir\mailhog.exe"

# Download MailHog if it doesn't exist
if (!(Test-Path $mailhogPath)) {
    Write-Host "Downloading MailHog..." -ForegroundColor Yellow
    $downloadUrl = "https://github.com/mailhog/MailHog/releases/download/v1.0.1/MailHog_windows_amd64.exe"
    
    try {
        Invoke-WebRequest -Uri $downloadUrl -OutFile $mailhogPath
        Write-Host "MailHog downloaded successfully!" -ForegroundColor Green
    } catch {
        Write-Host "Failed to download MailHog: $($_.Exception.Message)" -ForegroundColor Red
        exit 1
    }
} else {
    Write-Host "MailHog already exists" -ForegroundColor Yellow
}

# Start MailHog
Write-Host "Starting MailHog..." -ForegroundColor Green
Write-Host "MailHog will be available at:" -ForegroundColor Cyan
Write-Host "  Web Interface: http://localhost:8025" -ForegroundColor Cyan
Write-Host "  SMTP Server: localhost:1025" -ForegroundColor Cyan
Write-Host ""
Write-Host "Press Ctrl+C to stop MailHog" -ForegroundColor Yellow
Write-Host ""

# Run MailHog
& $mailhogPath