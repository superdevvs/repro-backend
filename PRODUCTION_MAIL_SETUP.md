# Production Mail Configuration Guide

## Current Setup (Development Only)
Your current setup uses MailHog for local testing:
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

## Production Setup Options

### Option 1: Gmail SMTP (Easiest for small scale)

Update your production `.env` file:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-gmail@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@reprophotos.com"
MAIL_FROM_NAME="R/E Pro Photos"
```

**Setup Steps:**
1. Enable 2-factor authentication on your Gmail account
2. Generate an App Password: https://myaccount.google.com/apppasswords
3. Use the App Password (not your regular password) in `MAIL_PASSWORD`

### Option 2: SendGrid (Recommended for production)

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

**Setup Steps:**
1. Create account at https://sendgrid.com
2. Verify your domain or sender identity
3. Create an API key in SendGrid dashboard
4. Use "apikey" as username and your API key as password

### Option 3: Mailgun (Good for high volume)

```env
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=your-domain.mailgun.org
MAILGUN_SECRET=your-mailgun-secret
MAILGUN_ENDPOINT=api.mailgun.net
MAIL_FROM_ADDRESS="noreply@reprophotos.com"
MAIL_FROM_NAME="R/E Pro Photos"
```

**Setup Steps:**
1. Create account at https://mailgun.com
2. Add and verify your domain
3. Get your API key from the dashboard
4. Update the Mailgun configuration in `config/services.php`

### Option 4: Amazon SES (AWS)

```env
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=us-east-1
AWS_SES_REGION=us-east-1
MAIL_FROM_ADDRESS="noreply@reprophotos.com"
MAIL_FROM_NAME="R/E Pro Photos"
```

## Domain Setup (Important!)

For production, you should:

1. **Set up SPF record** in your DNS:
   ```
   v=spf1 include:_spf.google.com ~all  (for Gmail)
   v=spf1 include:sendgrid.net ~all      (for SendGrid)
   ```

2. **Set up DKIM** (provided by your email service)

3. **Set up DMARC record**:
   ```
   v=DMARC1; p=none; rua=mailto:dmarc@reprophotos.com
   ```

## Environment-Specific Configuration

Create different `.env` files for different environments:

### `.env.local` (Development)
```env
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_FROM_ADDRESS="noreply@reprophotos.com"
MAIL_FROM_NAME="R/E Pro Photos"
```

### `.env.production` (Production)
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your-production-api-key
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@reprophotos.com"
MAIL_FROM_NAME="R/E Pro Photos"
```

## Queue Configuration (Recommended for Production)

For production, configure email queues to prevent blocking:

```env
QUEUE_CONNECTION=database
```

Then run queue workers on your server:
```bash
php artisan queue:work --daemon
```

## Testing Production Setup

1. **Test with Artisan command:**
   ```bash
   php artisan tinker
   Mail::raw('Test email', function($msg) { $msg->to('test@example.com')->subject('Test'); });
   ```

2. **Use our test endpoints:**
   ```bash
   curl https://your-domain.com/api/test/mail/account-created
   ```

3. **Monitor logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

## Security Considerations

1. **Never commit credentials** to version control
2. **Use environment variables** for all sensitive data
3. **Implement rate limiting** for email sending
4. **Validate email addresses** before sending
5. **Use HTTPS** for all email-related endpoints

## Deployment Checklist

- [ ] Choose email service provider
- [ ] Set up domain authentication (SPF, DKIM, DMARC)
- [ ] Update production `.env` file
- [ ] Test email sending in staging environment
- [ ] Configure queue workers
- [ ] Set up monitoring and logging
- [ ] Remove test email endpoints from production routes

## Monitoring and Troubleshooting

1. **Check email delivery rates** in your provider dashboard
2. **Monitor bounce rates** and spam complaints
3. **Set up alerts** for failed email deliveries
4. **Log all email activities** for debugging

## Cost Considerations

- **Gmail**: Free for low volume, limited to 500 emails/day
- **SendGrid**: Free tier: 100 emails/day, paid plans start at $14.95/month
- **Mailgun**: Free tier: 5,000 emails/month, then $0.80/1,000 emails
- **Amazon SES**: $0.10 per 1,000 emails sent

## Recommended Production Setup

For R/E Pro Photos, I recommend **SendGrid** because:
- Reliable delivery rates
- Good free tier for testing
- Excellent documentation
- Built-in analytics
- Easy domain authentication
- Scales well with business growth