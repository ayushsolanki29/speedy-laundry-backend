<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/functions.php';

/**
 * EmailQueue - Add notification emails to queue (non-blocking)
 * Emails are processed by scripts/process-email-queue.php (cron)
 */
class EmailQueue {

    /**
     * Queue an email. Returns true on success.
     * @param bool $isHtml When true, body is HTML
     */
    public static function push(string $toEmail, string $subject, string $body, string $type, ?string $toName = null, bool $isHtml = false): bool {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare(
                "INSERT INTO email_queue (to_email, to_name, subject, body, type, is_html) VALUES (?, ?, ?, ?, ?, ?)"
            );
            return $stmt->execute([
                trim($toEmail),
                $toName ? trim($toName) : null,
                trim($subject),
                $body,
                $type,
                $isHtml ? 1 : 0
            ]);
        } catch (Exception $e) {
            error_log("EmailQueue push error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Queue enquiry notification emails (admin + user)
     */
    public static function queueEnquiryEmails(array $data): void {
        $name = $data['full_name'] ?? '';
        $email = $data['email'] ?? '';
        $phone = $data['phone'] ?? '';
        $postcode = $data['postcode'] ?? '';
        $service = $data['service'] ?? '';
        $message = $data['message'] ?? '';
        $siteName = defined('SITE_NAME') ? SITE_NAME : 'Speedy Laundry';
        $adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'info@speedylaundry.co.uk';

        // Admin: New submission
        $adminSubject = "[{$siteName}] New Pickup Enquiry from {$name}";
        $adminBody = self::buildEnquiryAdminBody($name, $email, $phone, $postcode, $service, $message);
        self::push($adminEmail, $adminSubject, $adminBody, 'enquiry_admin');

        // User: Confirmation (HTML)
        $userSubject = "Your submission received – {$siteName}";
        $userBody = self::buildEnquiryUserHtml($name, $siteName);
        self::push($email, $userSubject, $userBody, 'enquiry_user', $name, true);
    }

    /**
     * Queue business enquiry notification emails (admin + user)
     */
    public static function queueBusinessEmails(array $data): void {
        $businessName = $data['business_name'] ?? '';
        $name = $data['full_name'] ?? '';
        $email = $data['email'] ?? '';
        $phone = $data['phone'] ?? '';
        $industry = $data['industry'] ?? '';
        $message = $data['message'] ?? '';
        $siteName = defined('SITE_NAME') ? SITE_NAME : 'Speedy Laundry';
        $adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'info@speedylaundry.co.uk';

        // Admin: New business submission
        $adminSubject = "[{$siteName}] New Business Quote Request from {$businessName}";
        $adminBody = self::buildBusinessAdminBody($businessName, $name, $email, $phone, $industry, $message);
        self::push($adminEmail, $adminSubject, $adminBody, 'business_admin');

        // User: Confirmation (HTML)
        $userSubject = "Your business request received – {$siteName}";
        $userBody = self::buildBusinessUserHtml($name, $siteName);
        self::push($email, $userSubject, $userBody, 'business_user', $name, true);
    }

    private static function buildEnquiryAdminBody(string $name, string $email, string $phone, string $postcode, string $service, string $message): string {
        $lines = [
            "New pickup enquiry submitted:",
            "",
            "Name: {$name}",
            "Email: {$email}",
            "Phone: {$phone}",
            "Postcode: {$postcode}",
            "Service: " . ($service ?: 'Not specified'),
            "",
            "Message:",
            $message ?: '(No message)',
            "",
            "— Please log in to your admin panel to respond."
        ];
        return implode("\n", $lines);
    }

    private static function buildEnquiryUserBody(string $name, string $siteName): string {
        return "Hi {$name},\n\n"
            . "Thank you for your enquiry. We have received your submission and will reply soon.\n\n"
            . "Our team will get back to you as quickly as possible.\n\n"
            . "Best regards,\n{$siteName}";
    }

    private static function buildEnquiryUserHtml(string $name, string $siteName): string {
        return self::wrapHtmlEmail(
            "Your Pickup Enquiry – We've Got It!",
            "Hi " . htmlspecialchars($name) . ",",
            "Thank you for your enquiry. We have received your submission and our team will get back to you shortly.",
            "We're excited to help with your laundry needs. You can expect a response from us very soon.",
            $siteName,
            "enquiry"
        );
    }

    private static function buildBusinessAdminBody(string $business, string $name, string $email, string $phone, string $industry, string $message): string {
        $lines = [
            "New business quote request submitted:",
            "",
            "Business: {$business}",
            "Contact: {$name}",
            "Email: {$email}",
            "Phone: {$phone}",
            "Industry: " . ($industry ?: 'Not specified'),
            "",
            "Message:",
            $message ?: '(No message)',
            "",
            "— Please log in to your admin panel to respond."
        ];
        return implode("\n", $lines);
    }

    private static function buildBusinessUserBody(string $name, string $siteName): string {
        return "Hi {$name},\n\n"
            . "Thank you for your business quote request. We have received your submission and will reply soon.\n\n"
            . "Our team will get back to you as quickly as possible.\n\n"
            . "Best regards,\n{$siteName}";
    }

    private static function buildBusinessUserHtml(string $name, string $siteName): string {
        return self::wrapHtmlEmail(
            "Your Business Quote Request – Received!",
            "Hi " . htmlspecialchars($name) . ",",
            "Thank you for your business quote request. We have received your submission and our team will review it shortly.",
            "We're looking forward to partnering with you. You can expect a response from us very soon.",
            $siteName,
            "business"
        );
    }

    /**
     * HTML email wrapper – matches Speedy Laundry brand (primary #0095da, clean, premium)
     */
    private static function wrapHtmlEmail(
        string $title,
        string $greeting,
        string $message1,
        string $message2,
        string $siteName,
        string $variant = "enquiry"
    ): string {
        $primary = '#0095da';
        $headerBg = '#1e293b';
        $textDark = '#1e293b';
        $textMuted = '#64748b';
        $lightBg = '#f8fafc';
        $icon = $variant === 'business' ? '🏢' : '🧺';

        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
</head>
<body style="margin:0;padding:0;font-family:\'Segoe UI\',Tahoma,Geneva,Verdana,sans-serif;background:#f1f5f9;color:' . $textDark . ';">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f1f5f9;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
                    <!-- Header -->
                    <tr>
                        <td style="background:' . $primary . ';padding:28px 32px;text-align:center;">
                            <p style="margin:0 0 8px 0;font-size:36px;line-height:1;">' . $icon . '</p>
                            <p style="margin:0;font-size:22px;font-weight:700;color:#ffffff;letter-spacing:-0.02em;">' . htmlspecialchars($siteName) . '</p>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding:36px 32px;">
                            <h1 style="margin:0 0 20px 0;font-size:20px;font-weight:700;color:' . $textDark . ';">' . htmlspecialchars($title) . '</h1>
                            <p style="margin:0 0 16px 0;font-size:16px;line-height:1.6;color:' . $textDark . ';">' . $greeting . '</p>
                            <p style="margin:0 0 16px 0;font-size:16px;line-height:1.6;color:' . $textDark . ';">' . htmlspecialchars($message1) . '</p>
                            <p style="margin:0 0 24px 0;font-size:16px;line-height:1.6;color:' . $textMuted . ';">' . htmlspecialchars($message2) . '</p>
                            <div style="height:1px;background:#e2e8f0;margin:0 0 24px 0;"></div>
                            <p style="margin:0;font-size:14px;color:' . $textMuted . ';">Best regards,<br><strong style="color:' . $primary . ';">' . htmlspecialchars($siteName) . '</strong></p>
                        </td>
                    </tr>
                    <!-- Footer -->
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
}
