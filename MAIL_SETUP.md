# Mail Functionality Setup and Testing

This document explains how to set up and test the mail functionality in the R/E Pro Photos application.

## Overview

The application includes comprehensive email functionality for:
- Account creation notifications
- Shoot scheduling confirmations
- Shoot updates and cancellations
- Payment confirmations
- Shoot completion notifications
- Terms acceptance confirmations

## Local Development Setup

### 1. Install and Run MailHog

MailHog is a local SMTP server that captures emails for testing without sending them to real recipients.

#### Option A: Using the PowerShell Script (Recommended)
```powershell
# Run from the repro-backend directory
.\setup-mailhog.ps1
```

#### Option B: Manual Installation
1. Download MailHog from: https://github.com/mailhog/MailHog/releases
2. Extract `MailHog_windows_amd64.exe` to `tools/mailhog.exe`
3. Run: `.\tools\mailhog.exe`

### 2. Configure Environment

The `.env` file is already configured for MailHog:
```env
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@reprophotos.com"
MAIL_FROM_NAME="R/E Pro Photos"
```

### 3. Access MailHog Web Interface

Once MailHog is running, access the web interface at:
- **Web Interface**: http://localhost:8025
- **SMTP Server**: localhost:1025

## Testing Mail Functionality

### API Test Endpoints

The application includes several test endpoints for email functionality:

#### Get Mail Configuration
```http
GET /api/test/mail/config
```

#### Test Individual Email Types
```http
GET /api/test/mail/account-created
GET /api/test/mail/shoot-scheduled
GET /api/test/mail/shoot-ready
GET /api/test/mail/payment-confirmation
```

#### Test All Emails at Once
```http
GET /api/test/mail/all
```

### Using PowerShell to Test

```powershell
# Test mail configuration
Invoke-RestMethod -Uri "http://localhost:8000/api/test/mail/config" -Method GET

# Test account created email
Invoke-RestMethod -Uri "http://localhost:8000/api/test/mail/account-created" -Method GET

# Test all emails
Invoke-RestMethod -Uri "http://localhost:8000/api/test/mail/all" -Method GET
```

### Using curl

```bash
# Test mail configuration
curl http://localhost:8000/api/test/mail/config

# Test account created email
curl http://localhost:8000/api/test/mail/account-created

# Test all emails
curl http://localhost:8000/api/test/mail/all
```

## Email Templates

Email templates are located in `resources/views/emails/`:

- `account_created.blade.php` - New account creation
- `shoot_scheduled.blade.php` - Shoot booking confirmation
- `shoot_updated.blade.php` - Shoot modifications
- `shoot_removed.blade.php` - Shoot cancellation
- `shoot_ready.blade.php` - Photos ready for download
- `payment_confirmation.blade.php` - Payment received
- `terms_accepted.blade.php` - Terms acceptance

## Mail Service Integration

### Automatic Email Triggers

Emails are automatically sent when:

1. **New Shoot Created** → `ShootScheduledMail` to client
2. **Payment Completed** → `PaymentConfirmationMail` to client
3. **All Files Verified** → `ShootReadyMail` to client

### Manual Email Sending

Use the `MailService` class to send emails programmatically:

```php
use App\Services\MailService;

// Inject the service
public function __construct(MailService $mailService)
{
    $this->mailService = $mailService;
}

// Send emails
$this->mailService->sendShootScheduledEmail($user, $shoot, $paymentLink);
$this->mailService->sendPaymentConfirmationEmail($user, $shoot, $payment);
$this->mailService->sendShootReadyEmail($user, $shoot);
```

## Production Configuration

For production, update the `.env` file with your SMTP provider:

### Using Gmail SMTP
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@reprophotos.com"
MAIL_FROM_NAME="R/E Pro Photos"
```

### Using SendGrid
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your-sendgrid-api-key
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@reprophotos.com"
MAIL_FROM_NAME="R/E Pro Photos"
```

### Using Mailgun
```env
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=your-domain.mailgun.org
MAILGUN_SECRET=your-mailgun-secret
MAIL_FROM_ADDRESS="noreply@reprophotos.com"
MAIL_FROM_NAME="R/E Pro Photos"
```

## Troubleshooting

### Common Issues

1. **Emails not appearing in MailHog**
   - Check that MailHog is running on port 1025
   - Verify MAIL_HOST and MAIL_PORT in .env
   - Check Laravel logs for errors

2. **Connection refused errors**
   - Ensure MailHog is running
   - Check Windows Firewall settings
   - Verify port 1025 is not blocked

3. **Template errors**
   - Check that all email templates exist
   - Verify template syntax
   - Check for missing variables

### Debugging

Enable mail logging by adding to `.env`:
```env
LOG_LEVEL=debug
```

Check logs in `storage/logs/laravel.log` for mail-related errors.

## Security Considerations

- Never commit real SMTP credentials to version control
- Use environment variables for all sensitive configuration
- Implement rate limiting for email sending in production
- Validate email addresses before sending
- Use queue workers for email sending in production to avoid blocking requests

## Queue Configuration (Production)

For production, configure email queues:

```env
QUEUE_CONNECTION=database
```

Then run queue workers:
```bash
php artisan queue:work
```

This ensures email sending doesn't block web requests.