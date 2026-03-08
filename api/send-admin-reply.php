<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/../core/Mailer.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Method not allowed', null, 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$enquiryId = (int) ($input['enquiry_id'] ?? 0);
$message = trim($input['message'] ?? '');
$subject = trim($input['subject'] ?? '');

if (!$enquiryId || !$message) {
    sendResponse('error', 'Enquiry ID and message are required', null, 400);
}

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM enquiries WHERE id = ?");
    $stmt->execute([$enquiryId]);
    $enquiry = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$enquiry) {
        sendResponse('error', 'Enquiry not found', null, 404);
    }

    $siteName = defined('SITE_NAME') ? SITE_NAME : 'Speedy Laundry';
    $toEmail = $enquiry['email'];
    $toName = $enquiry['full_name'];
    $finalSubject = $subject ?: "Re: Your enquiry – {$siteName}";

    $htmlBody = buildAdminReplyHtml($toName, $message, $siteName);

    $success = Mailer::send($toEmail, $finalSubject, $htmlBody, $toName, true);

    if ($success) {
        sendResponse('success', 'Reply sent successfully');
    } else {
        sendResponse('error', 'Failed to send email. ' . (Mailer::$lastError ?: 'Please try again.'), null, 500);
    }
} catch (Exception $e) {
    sendResponse('error', 'Server error: ' . $e->getMessage(), null, 500);
}

function buildAdminReplyHtml(string $name, string $message, string $siteName): string {
    $primary = '#0095da';
    $textDark = '#1e293b';
    $textMuted = '#64748b';
    $lightBg = '#f8fafc';
    $messageHtml = nl2br(htmlspecialchars($message));

    return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reply from ' . htmlspecialchars($siteName) . '</title>
</head>
<body style="margin:0;padding:0;font-family:\'Segoe UI\',Tahoma,Geneva,Verdana,sans-serif;background:#f1f5f9;color:' . $textDark . ';">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f1f5f9;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
                    <tr>
                        <td style="background:' . $primary . ';padding:28px 32px;text-align:center;">
                            <img src="' . (defined('CLIENT_URL') ? rtrim(CLIENT_URL, '/') : 'https://speedylaundry.co.uk') . '/assets/logo-white.svg" alt="Speedy Laundry" width="160" style="display:block; margin: 0 auto 12px auto;" />
                            <p style="margin:0;font-size:22px;font-weight:700;color:#ffffff;letter-spacing:-0.02em;">' . htmlspecialchars($siteName) . '</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:36px 32px;">
                            <h1 style="margin:0 0 20px 0;font-size:20px;font-weight:700;color:' . $textDark . ';">Response to your enquiry</h1>
                            <p style="margin:0 0 16px 0;font-size:16px;line-height:1.6;color:' . $textDark . ';">Hi ' . htmlspecialchars($name) . ',</p>
                            <p style="margin:0 0 24px 0;font-size:16px;line-height:1.6;color:' . $textDark . ';">' . $messageHtml . '</p>
                            <div style="height:1px;background:#e2e8f0;margin:0 0 24px 0;"></div>
                            <p style="margin:0;font-size:14px;color:' . $textMuted . ';">Best regards,<br><strong style="color:' . $primary . ';">' . htmlspecialchars($siteName) . '</strong></p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:' . $lightBg . ';padding:20px 32px;text-align:center;border-top:1px solid #e2e8f0;">
                            <p style="margin:0;font-size:12px;color:' . $textMuted . ';">Premium laundry & dry cleaning with free pickup and delivery</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
}
