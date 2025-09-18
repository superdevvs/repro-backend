# Mail Configuration Switcher
# This script helps switch between development and production mail configurations

param(
    [Parameter(Mandatory=$true)]
    [ValidateSet("development", "production", "gmail", "sendgrid")]
    [string]$Environment
)

Write-Host "=== Mail Configuration Switcher ===" -ForegroundColor Cyan
Write-Host "Switching to: $Environment" -ForegroundColor Yellow
Write-Host ""

# Backup current .env
if (Test-Path ".env") {
    Copy-Item ".env" ".env.backup.$(Get-Date -Format 'yyyyMMdd-HHmmss')"
    Write-Host "✓ Backed up current .env file" -ForegroundColor Green
}

# Read current .env content
$envContent = Get-Content ".env" -Raw

switch ($Environment) {
    "development" {
        Write-Host "Configuring for local development with MailHog..." -ForegroundColor Yellow
        
        $mailConfig = @"
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@reprophotos.com"
MAIL_FROM_NAME="R/E Pro Photos"
"@
        
        Write-Host "✓ Development configuration applied" -ForegroundColor Green
        Write-Host "  - Emails will be captured by MailHog" -ForegroundColor Gray
        Write-Host "  - View emails at: http://localhost:8025" -ForegroundColor Gray
    }
    
    "gmail" {
        Write-Host "Configuring for Gmail SMTP..." -ForegroundColor Yellow
        
        $email = Read-Host "Enter your Gmail address"
        $appPassword = Read-Host "Enter your Gmail App Password" -AsSecureString
        $appPasswordText = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($appPassword))
        
        $mailConfig = @"
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=$email
MAIL_PASSWORD=$appPasswordText
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@reprophotos.com"
MAIL_FROM_NAME="R/E Pro Photos"
"@
        
        Write-Host "✓ Gmail configuration applied" -ForegroundColor Green
        Write-Host "  - Make sure 2FA is enabled on your Gmail account" -ForegroundColor Yellow
        Write-Host "  - Use App Password, not your regular password" -ForegroundColor Yellow
    }
    
    "sendgrid" {
        Write-Host "Configuring for SendGrid..." -ForegroundColor Yellow
        
        $apiKey = Read-Host "Enter your SendGrid API Key" -AsSecureString
        $apiKeyText = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($apiKey))
        
        $mailConfig = @"
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=$apiKeyText
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@reprophotos.com"
MAIL_FROM_NAME="R/E Pro Photos"
"@
        
        Write-Host "✓ SendGrid configuration applied" -ForegroundColor Green
        Write-Host "  - Make sure your domain is verified in SendGrid" -ForegroundColor Yellow
        Write-Host "  - Username should always be 'apikey'" -ForegroundColor Yellow
    }
    
    "production" {
        Write-Host "Production configuration template..." -ForegroundColor Yellow
        
        $mailConfig = @"
# PRODUCTION MAIL CONFIGURATION
# Update these values with your production email service details

MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=your-smtp-host.com
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@reprophotos.com"
MAIL_FROM_NAME="R/E Pro Photos"

# For queue-based email sending (recommended for production)
QUEUE_CONNECTION=database
"@
        
        Write-Host "✓ Production template applied" -ForegroundColor Green
        Write-Host "  - Update the placeholder values with your actual credentials" -ForegroundColor Yellow
        Write-Host "  - Consider using queues for better performance" -ForegroundColor Yellow
    }
}

# Update .env file
$envContent = $envContent -replace "MAIL_MAILER=.*", ""
$envContent = $envContent -replace "MAIL_SCHEME=.*", ""
$envContent = $envContent -replace "MAIL_HOST=.*", ""
$envContent = $envContent -replace "MAIL_PORT=.*", ""
$envContent = $envContent -replace "MAIL_USERNAME=.*", ""
$envContent = $envContent -replace "MAIL_PASSWORD=.*", ""
$envContent = $envContent -replace "MAIL_ENCRYPTION=.*", ""
$envContent = $envContent -replace "MAIL_FROM_ADDRESS=.*", ""
$envContent = $envContent -replace "MAIL_FROM_NAME=.*", ""

# Remove empty lines
$envContent = $envContent -replace "(?m)^\s*$", ""

# Add new mail configuration
$envContent = $envContent.TrimEnd() + "`n`n# Mail Configuration`n" + $mailConfig

# Write updated content
$envContent | Set-Content ".env" -NoNewline

Write-Host ""
Write-Host "=== Configuration Updated ===" -ForegroundColor Green
Write-Host "Current mail settings:" -ForegroundColor Cyan

# Show current mail configuration
try {
    $config = Invoke-RestMethod -Uri "http://localhost:8000/api/test/mail/config" -Method GET -ErrorAction SilentlyContinue
    Write-Host "  Mailer: $($config.mailer)" -ForegroundColor White
    Write-Host "  Host: $($config.host):$($config.port)" -ForegroundColor White
    Write-Host "  From: $($config.from_name) <$($config.from_address)>" -ForegroundColor White
} catch {
    Write-Host "  Server not running - restart Laravel to apply changes" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "=== Next Steps ===" -ForegroundColor Cyan

switch ($Environment) {
    "development" {
        Write-Host "1. Make sure MailHog is running: .\setup-mailhog.ps1" -ForegroundColor White
        Write-Host "2. Test emails: .\test-mail.ps1" -ForegroundColor White
        Write-Host "3. View emails: http://localhost:8025" -ForegroundColor White
    }
    
    "gmail" {
        Write-Host "1. Test email sending: Invoke-RestMethod -Uri 'http://localhost:8000/api/test/mail/account-created'" -ForegroundColor White
        Write-Host "2. Check your Gmail inbox for test emails" -ForegroundColor White
        Write-Host "3. Monitor Laravel logs for any errors" -ForegroundColor White
    }
    
    "sendgrid" {
        Write-Host "1. Verify your domain in SendGrid dashboard" -ForegroundColor White
        Write-Host "2. Test email sending: Invoke-RestMethod -Uri 'http://localhost:8000/api/test/mail/account-created'" -ForegroundColor White
        Write-Host "3. Check SendGrid activity dashboard" -ForegroundColor White
    }
    
    "production" {
        Write-Host "1. Update placeholder values in .env with real credentials" -ForegroundColor White
        Write-Host "2. Set up domain authentication (SPF, DKIM, DMARC)" -ForegroundColor White
        Write-Host "3. Configure queue workers for better performance" -ForegroundColor White
        Write-Host "4. Remove test endpoints from production routes" -ForegroundColor White
    }
}

Write-Host ""
Write-Host "Configuration switch completed!" -ForegroundColor Green