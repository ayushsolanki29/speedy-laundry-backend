# Email Queue

When users submit **Contact** or **Business** enquiries, two emails are queued:
1. **Admin** – "New submission found" notification
2. **User** – "Your submission is received, we will reply soon"

Emails are processed asynchronously via the queue (non-blocking, faster response).

## Setup

1. Install dependencies: `composer install` (in `backend/`)
2. Run migrations: `php apply_updates.php` from backend/ (creates `email_queue`, blog tables if needed)
3. Configure SMTP in `backend/config/config.php`:

```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USER', 'your@gmail.com');
define('SMTP_PASS', 'your-app-password');  // Gmail: use App Password, not main password
```

Leave `SMTP_HOST` empty to use PHP `mail()`.

4. Run the queue processor.

**Manual run (for testing):**
```bash
php f:/Software/laragon/www/speedy-laundry/backend/scripts/process-email-queue.php
```

**Windows Task Scheduler (like cron):**
1. Open Task Scheduler (search in Start menu)
2. Create Basic Task → Name: "Speedy Laundry Email Queue"
3. Trigger: Daily, repeat every 5 minutes
4. Action: Start a program
   - Program: `C:\laragon\bin\php\php-8.x\php.exe` (your Laragon PHP path)
   - Arguments: `f:/Software/laragon/www/speedy-laundry/backend/scripts/process-email-queue.php`
5. Save

**Linux/macOS cron:**
```bash
# Every 5 minutes
*/5 * * * * php /path/to/speedy-laundry/backend/scripts/process-email-queue.php
```

## PHPMailer

Uses PHPMailer for reliable delivery. SMTP recommended for production.
