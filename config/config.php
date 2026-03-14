<?php
// Timezone (client is UK/London)
define('APP_TIMEZONE', getenv('APP_TIMEZONE') ?: 'Europe/London');
date_default_timezone_set(APP_TIMEZONE);

// Database Configuration
// define('DB_HOST', 'localhost');
// define('DB_USER', 'u344107577_speedy_user');
// define('DB_PASS', 'ThisLostGost@^57777');
// define('DB_NAME', 'u344107577_speedy_db');
// define('CLIENT_URL', 'https://speedylaundry.co.uk');
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'speedy_laundry');
define('CLIENT_URL', 'http://localhost:3000');

// API Configuration
define('API_SECRET', 'your_secret_key_here'); // Change this for production

// Email Configuration (for queue notifications)
// Admin mailbox (where enquiries/notifications go)
define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'info@speedylaundry.co.uk');
define('SITE_NAME', 'Speedy Laundry');

// Sender mailbox (From/Reply-To)
define('MAIL_FROM', getenv('MAIL_FROM') ?: 'info@speedylaundry.co.uk');
// Logo used in customer-facing emails
define('MAIL_LOGO_URL', getenv('MAIL_LOGO_URL') ?: 'https://server.speedylaundry.co.uk/cdn/uploads/images/69b5affb3d9fc.svg');

// Hostinger email settings (info@speedylaundry.co.uk)
// Incoming (IMAP): imap.hostinger.com:993 SSL/TLS
// Outgoing (SMTP): smtp.hostinger.com:465 SSL/TLS
// Incoming (POP):  pop.hostinger.com:995 SSL/TLS

// SMTP (PHPMailer) - leave empty to use PHP mail()
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.hostinger.com');
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 465)); // 587=tls, 465=ssl
define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'ssl');  // 'tls' for 587, 'ssl' for 465
define('SMTP_USER', getenv('SMTP_USER') ?: 'info@speedylaundry.co.uk');
define('SMTP_PASS', getenv('SMTP_PASS') ?: 'ThisLostGost@^57777');         // Set your mailbox/app password in env
define('SMTP_DEBUG', false);                       // Set true to see SMTP conversation in CLI

// Save sent messages into mailbox "Sent" (so Hostinger webmail shows them under Sent)
define('MAIL_APPEND_TO_SENT', (getenv('MAIL_APPEND_TO_SENT') ?: '1') === '1');
define('IMAP_HOST', getenv('IMAP_HOST') ?: 'imap.hostinger.com');
define('IMAP_PORT', (int)(getenv('IMAP_PORT') ?: 993));
define('IMAP_FLAGS', getenv('IMAP_FLAGS') ?: '/imap/ssl'); // e.g. /imap/ssl/novalidate-cert
define('IMAP_USER', getenv('IMAP_USER') ?: (defined('SMTP_USER') ? SMTP_USER : ''));
define('IMAP_PASS', getenv('IMAP_PASS') ?: (defined('SMTP_PASS') ? SMTP_PASS : ''));
define('IMAP_SENT_FOLDER', getenv('IMAP_SENT_FOLDER') ?: 'Sent');

// Upload base URL for review photos - must be web-accessible (e.g. http://localhost/speedy-laundry/backend)
define('UPLOAD_BASE_URL', 'http://localhost/speedy-laundry/backend');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// CORS Settings
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}
?>
