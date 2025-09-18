# Production Deployment Checklist for Mail Functionality

## ✅ Current Status
- [x] Mail functionality implemented and working locally
- [x] MailHog setup for development testing
- [x] Email templates created and tested
- [x] Mail service integrated with user registration and workflows

## 🚀 Production Deployment Steps

### 1. Choose Email Service Provider
**Recommended: SendGrid** (reliable, good free tier, scales well)

Alternative options:
- Gmail SMTP (for small scale)
- Mailgun (for high volume)
- Amazon SES (if using AWS)

### 2. Update Environment Configuration

**Current (Development):**
```env
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
```

**Production (Example with SendGrid):**
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

### 3. Domain Authentication Setup
Set up these DNS records for better deliverability:

**SPF Record:**
```
v=spf1 include:sendgrid.net ~all
```

**DKIM Record:**
(Provided by SendGrid after domain verification)

**DMARC Record:**
```
v=DMARC1; p=none; rua=mailto:dmarc@reprophotos.com
```

### 4. Remove Development-Only Code

**Remove these routes from production:**
```php
// Remove from routes/api.php in production
Route::prefix('test/mail')->group(function () {
    Route::get('config', [App\Http\Controllers\TestMailController::class, 'getMailConfig']);
    Route::get('account-created', [App\Http\Controllers\TestMailController::class, 'testAccountCreated']);
    Route::get('shoot-scheduled', [App\Http\Controllers\TestMailController::class, 'testShootScheduled']);
    Route::get('shoot-ready', [App\Http\Controllers\TestMailController::class, 'testShootReady']);
    Route::get('payment-confirmation', [App\Http\Controllers\TestMailController::class, 'testPaymentConfirmation']);
    Route::get('all', [App\Http\Controllers\TestMailController::class, 'testAllEmails']);
});
```

### 5. Configure Queue Workers (Recommended)

**Update .env:**
```env
QUEUE_CONNECTION=database
```

**Run migrations:**
```bash
php artisan queue:table
php artisan migrate
```

**Start queue workers on server:**
```bash
php artisan queue:work --daemon
```

### 6. Update Mail Service for Production

**Make emails queueable by updating Mail classes:**
```php
// In app/Mail/AccountCreatedMail.php
class AccountCreatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    // ... rest of the class
}
```

### 7. Testing Production Setup

**Test with a staging environment first:**
```bash
# Test email sending
curl https://your-staging-domain.com/api/test/mail/account-created

# Check logs
tail -f storage/logs/laravel.log
```

### 8. Monitoring and Alerts

Set up monitoring for:
- Email delivery rates
- Bounce rates
- Queue processing
- Failed jobs

## 🛠️ Quick Setup Commands

**Switch to production config:**
```powershell
.\switch-mail-config.ps1 -Environment production
```

**Test current setup:**
```powershell
.\test-mail.ps1
```

**For SendGrid setup:**
```powershell
.\switch-mail-config.ps1 -Environment sendgrid
```

## 📋 Pre-Deployment Verification

- [ ] Email service account created and configured
- [ ] Domain authentication (SPF, DKIM, DMARC) set up
- [ ] Production .env file updated with real credentials
- [ ] Test endpoints removed from production routes
- [ ] Queue workers configured (if using queues)
- [ ] Staging environment tested
- [ ] Monitoring and logging set up

## 🚨 Important Notes

1. **Never commit real credentials** to version control
2. **Test thoroughly** in staging before production
3. **Monitor email deliverability** after deployment
4. **Have a rollback plan** ready
5. **Set up proper error handling** and logging

## 💰 Cost Estimates

**SendGrid:**
- Free: 100 emails/day
- Essentials: $14.95/month (40,000 emails)
- Pro: $89.95/month (100,000 emails)

**Mailgun:**
- Free: 5,000 emails/month
- Pay-as-you-go: $0.80/1,000 emails

**Gmail:**
- Free: 500 emails/day (limited)
- Google Workspace: $6/user/month

## 🎯 Recommended Production Setup

1. **Use SendGrid** for email delivery
2. **Enable queues** for better performance
3. **Set up proper monitoring**
4. **Configure domain authentication**
5. **Remove test endpoints**
6. **Use environment-specific configurations**

Your mail functionality is production-ready! You just need to switch the configuration and set up your email service provider.