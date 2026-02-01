# Framework Email

Core email functionality for the Vireo Framework. Send emails via SMTP with support for queuing, templates, attachments, and tracking.

## Quick Start

### Simple Send

```php
use Framework\Email\Email;

// Fluent API
Email::to('user@example.com')
    ->subject('Welcome!')
    ->html('<h1>Hello!</h1><p>Welcome to our platform.</p>')
    ->send();

// Helper function
email('user@example.com', 'Test Subject', '<p>Email body</p>');
```

### Queue for Later

```php
// Fluent API
Email::to('user@example.com')
    ->subject('Welcome!')
    ->html('<h1>Hello!</h1>')
    ->queue();

// With delay (seconds)
Email::to('user@example.com')
    ->subject('Reminder')
    ->html('<p>Don\'t forget!</p>')
    ->queue(3600); // Send in 1 hour

// Helper function
email_queue('user@example.com', 'Subject', '<p>Body</p>', 300); // 5 min delay
```

### Using Templates

```php
// Blade template
Email::to('user@example.com')
    ->subject('Welcome!')
    ->view('emails.welcome', ['name' => 'John'])
    ->send();

// Helper function
email_view('user@example.com', 'Welcome', 'emails.welcome', ['name' => 'John']);
```

## Configuration

Configure in `Config/Email.php` or `.env`:

```env
# SMTP Settings
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password

# From Address
MAIL_FROM_ADDRESS=noreply@yourapp.com
MAIL_FROM_NAME="Your App"

# Features
MAIL_QUEUE=true
MAIL_TRACKING=true
```

## SMTP Providers

### Gmail

1. Enable 2FA
2. Generate App Password: https://myaccount.google.com/apppasswords
3. Use App Password in `.env`

```env
MAIL_MAILER=gmail
GMAIL_USERNAME=your@gmail.com
GMAIL_APP_PASSWORD=your-16-char-password
```

### SendGrid

```env
MAIL_MAILER=sendgrid
SENDGRID_API_KEY=your-api-key
```

### Amazon SES

```env
MAIL_MAILER=ses
SES_HOST=email-smtp.us-east-1.amazonaws.com
SES_USERNAME=your-access-key
SES_PASSWORD=your-secret-key
```

## Fluent API

```php
use Framework\Email\Email;

Email::to('user@example.com', 'John Doe')
    ->from('custom@example.com', 'Custom Sender')
    ->cc('manager@example.com')
    ->bcc('admin@example.com')
    ->replyTo('support@example.com')
    ->subject('Order Confirmation')
    ->html('<h1>Thanks for your order!</h1>')
    ->text('Thanks for your order!')
    ->attach('/path/to/invoice.pdf', 'invoice.pdf')
    ->priority('high')
    ->tag('orders')
    ->metadata(['order_id' => 12345])
    ->send(); // or ->queue()
```

## Queue Processing

### CLI Command

```bash
# Process once
./vireo email:queue:process

# Process with custom batch size
./vireo email:queue:process --limit=50

# Run as daemon (continuous processing)
./vireo email:queue:process --daemon

# Process one batch only
./vireo email:queue:process --once
```

### Helper Functions

```php
// Process queue
email_process_queue(100);

// Get queue depth
$pending = email_queue_depth();

// Cleanup old emails
$deleted = email_cleanup(30); // Delete emails older than 30 days
```

### Production Setup

**Supervisor** (recommended):

```ini
[program:email-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/vireo email:queue:process --daemon
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/email-worker.log
```

**Cron Job**:

```cron
* * * * * cd /path/to/app && ./vireo email:queue:process --once
```

## Templates

### Blade Templates

Create templates in `Infrastructure/Http/View/Email/`:

```blade
{{-- resources/views/email/welcome.blade.php --}}
<!DOCTYPE html>
<html>
<body>
    <h1>Welcome, {{ $name }}!</h1>
    <p>Thanks for joining {{ config('app.name') }}.</p>
    <a href="{{ $login_url }}">Get Started</a>
</body>
</html>
```

Usage:

```php
Email::to('user@example.com')
    ->subject('Welcome!')
    ->view('email.welcome', [
        'name' => 'John',
        'login_url' => 'https://app.com/login'
    ])
    ->send();
```

### Database Templates

Store reusable templates in the database:

```php
table('email_templates')->insert([
    'name' => 'Password Reset',
    'slug' => 'password-reset',
    'subject' => 'Reset Your Password',
    'body_html' => '<h2>Hi {{ $name }}</h2><p>Click here: {{ $reset_url }}</p>',
    'category' => 'authentication',
    'is_active' => true,
]);
```

## Attachments

```php
// From file path
Email::to('user@example.com')
    ->subject('Invoice')
    ->html('<p>See attached invoice</p>')
    ->attach('/path/to/invoice.pdf', 'January-Invoice.pdf')
    ->send();

// From data
$pdfContent = generatePdf();
Email::to('user@example.com')
    ->subject('Report')
    ->html('<p>See attached report</p>')
    ->attachData($pdfContent, 'report.pdf', 'application/pdf')
    ->send();
```

## Tracking

Email tracking is automatically enabled if configured:

```php
'tracking' => [
    'enabled' => true,
    'track_opens' => true,
    'track_clicks' => true,
],
```

Tracking injects:
- **Open tracking**: 1x1 transparent pixel
- **Click tracking**: Redirect URLs for all links

### Get Stats

```php
use Framework\Email\Tracking\Tracker;

$tracker = new Tracker();
$stats = $tracker->getStats($emailId);

// Returns:
// [
//     'sent' => 1,
//     'opened' => 1,
//     'clicked' => 2,
//     'bounced' => 0
// ]
```

## Advanced Usage

### Multiple Recipients

```php
Email::to('user1@example.com')
    ->to('user2@example.com')
    ->to('user3@example.com')
    ->subject('Newsletter')
    ->html('<p>Latest updates...</p>')
    ->queue();
```

### Priority Emails

```php
Email::to('admin@example.com')
    ->subject('URGENT: Server Alert')
    ->html('<p>Server down!</p>')
    ->priority('urgent')
    ->send();
```

### Campaigns

```php
// Tag emails as part of a campaign
Email::to('user@example.com')
    ->subject('Monthly Newsletter')
    ->html('...')
    ->campaign(1) // Campaign ID
    ->tag('newsletter')
    ->queue();
```

### Custom Mailer Config

```php
use Framework\Email\Mailer;

$customConfig = [
    'default' => 'smtp',
    'mailers' => [
        'smtp' => [
            'host' => 'custom-smtp.com',
            'port' => 587,
            'username' => 'user',
            'password' => 'pass',
        ],
    ],
];

$mailer = new Mailer($customConfig);
$mailer->send($message);
```

## Helper Functions Reference

| Function | Description |
|----------|-------------|
| `email($to, $subject, $body, $from)` | Send email immediately |
| `email_queue($to, $subject, $body, $delay)` | Queue email |
| `email_view($to, $subject, $view, $data, $queue)` | Send with template |
| `email_process_queue($batchSize)` | Process queue |
| `email_queue_depth()` | Get pending count |
| `email_cleanup($days)` | Delete old emails |

## Architecture

```
Framework/Email/
├── Email.php              # Facade
├── Message.php            # Message builder
├── Mailer.php             # Main mailer
├── Queue/
│   └── EmailQueue.php     # Queue processing
├── Template/
│   └── TemplateEngine.php # Blade rendering
├── Tracking/
│   └── Tracker.php        # Open/click tracking
└── Transports/
    └── SmtpTransport.php  # SMTP sending
```

## Error Handling

All email operations return a result array:

```php
$result = Email::to('user@example.com')
    ->subject('Test')
    ->html('<p>Test</p>')
    ->send();

if ($result['success']) {
    $emailId = $result['email_id'];
    echo "Email sent! ID: {$emailId}";
} else {
    echo "Error: " . $result['error'];
}
```

Errors are also logged to the `email` channel:

```php
logger('email')->info('Email sent', ['email_id' => 123]);
logger('email')->error('Send failed', ['error' => 'Connection timeout']);
```

## Testing

Test your email configuration:

```bash
./vireo email:test your@email.com
```

Check queue status:

```php
$depth = email_queue_depth();
echo "Pending emails: {$depth}";
```

## Troubleshooting

### Emails not sending

1. Check queue: `./vireo email:queue:process --once`
2. Check logs: `tail -f storage/logs/email.log`
3. Test config: `./vireo email:test your@email.com`

### Gmail "Less secure app" error

Don't use your regular password. Generate an App Password:
1. Enable 2FA on your Google Account
2. Visit: https://myaccount.google.com/apppasswords
3. Generate app password
4. Use the 16-character password in `.env`

### Rate limiting

Adjust in `Config/Email.php`:

```php
'rate_limit' => [
    'per_hour' => 100,
    'per_minute' => 5,
],
```

## License

Part of Vireo Framework - MIT License
