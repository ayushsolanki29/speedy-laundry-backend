<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'speedy_laundry');

// API Configuration
define('API_SECRET', 'your_secret_key_here'); // Change this for production

// Email Configuration (for queue notifications)
define('ADMIN_EMAIL', 'ayushsolanki2901@gmail.com');
define('SITE_NAME', 'Speedy Laundry');

// SMTP (PHPMailer) - leave empty to use PHP mail()
define('SMTP_HOST', 'smtp.gmail.com');           // e.g. smtp.gmail.com
define('SMTP_PORT', 465);          // 587 = tls, 465 = ssl
define('SMTP_SECURE', 'ssl');      // 'tls' for 587, 'ssl' for 465
define('SMTP_USER', 'developer.speedylaundry@gmail.com');           // SMTP username
define('SMTP_PASS', 'rftxqivoggzidrfq');           // SMTP password / app password
define('SMTP_DEBUG', false);                       // Set true to see SMTP conversation in CLI

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
