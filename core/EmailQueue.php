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
    public static function push(string $toEmail, string $subject, string $body, string $type, ?string $toName = null, bool $isHtml = false, int $maxAttempts = 3): bool {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare(
                "INSERT INTO email_queue (to_email, to_name, subject, body, type, is_html, max_attempts) VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            return $stmt->execute([
                trim($toEmail),
                $toName ? trim($toName) : null,
                trim($subject),
                $body,
                $type,
                $isHtml ? 1 : 0,
                $maxAttempts
            ]);
        } catch (Exception $e) {
            error_log("EmailQueue push error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Process pending emails in the queue
     * @param int $batchSize Number of emails to process in one go
     * @return int Number of successfully sent emails
     */
    public static function process(int $batchSize = 20): int {
        require_once __DIR__ . '/Mailer.php';
        $processed = 0;

        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare(
                "SELECT * FROM email_queue 
                 WHERE status = 'pending' AND attempts < max_attempts 
                 ORDER BY created_at ASC LIMIT ?"
            );
            $stmt->bindValue(1, $batchSize, PDO::PARAM_INT);
            $stmt->execute();
            $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($emails as $email) {
                $success = Mailer::send(
                    $email['to_email'],
                    $email['subject'],
                    $email['body'],
                    $email['to_name'],
                    (int)$email['is_html'] === 1
                );

                if ($success) {
                    $deleteStmt = $db->prepare("DELETE FROM email_queue WHERE id = ?");
                    $deleteStmt->execute([$email['id']]);
                    $processed++;
                } else {
                    $updateStmt = $db->prepare(
                        "UPDATE email_queue SET status = ?, attempts = attempts + 1, sent_at = ?, error_message = ? WHERE id = ?"
                    );
                    $attempts = (int)$email['attempts'] + 1;
                    $newStatus = $attempts >= (int)$email['max_attempts'] ? 'failed' : 'pending';
                    $errorMsg = class_exists('Mailer') && !empty(Mailer::$lastError) ? Mailer::$lastError : 'Mail send failed';
                    $updateStmt->execute([$newStatus, null, $errorMsg, $email['id']]);
                }
            }
            return $processed;
        } catch (Exception $e) {
            error_log("EmailQueue process error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Queue enquiry notification emails (admin + user)
     */
    public static function queueEnquiryEmails(array $data): void {
        $name = $data['full_name'] ?? '';
        $email = $data['email'] ?? '';
        $phone = $data['phone'] ?? '';
        $address = $data['address'] ?? '';
        $postcode = $data['postcode'] ?? '';
        $service = $data['service'] ?? '';
        $message = $data['message'] ?? '';
        $siteName = defined('SITE_NAME') ? SITE_NAME : 'Speedy Laundry';
        $adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'info@speedylaundry.co.uk';

        // Admin: New submission (HTML Table)
        $adminSubject = "[{$siteName}] New Pickup Enquiry from {$name}";
        $adminBody = self::buildEnquiryAdminHtml($name, $email, $phone, $address, $postcode, $service, $message, $siteName);
        self::push($adminEmail, $adminSubject, $adminBody, 'enquiry_admin', null, true);

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
        $address = $data['address'] ?? '';
        $industry = $data['industry'] ?? '';
        $message = $data['message'] ?? '';
        $siteName = defined('SITE_NAME') ? SITE_NAME : 'Speedy Laundry';
        $adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'info@speedylaundry.co.uk';

        // Admin: New business submission (HTML Table)
        $adminSubject = "[{$siteName}] New Business Quote Request from {$businessName}";
        $adminBody = self::buildBusinessAdminHtml($businessName, $name, $email, $phone, $address, $industry, $message, $siteName);
        self::push($adminEmail, $adminSubject, $adminBody, 'business_admin', null, true);

        // User: Confirmation (HTML)
        $userSubject = "Your business request received – {$siteName}";
        $userBody = self::buildBusinessUserHtml($name, $siteName);
        self::push($email, $userSubject, $userBody, 'business_user', $name, true);
    }

    private static function buildEnquiryAdminHtml(string $name, string $email, string $phone, string $address, string $postcode, string $service, string $message, string $siteName): string {
        $rows = [
            'Name' => $name,
            'Email' => $email,
            'Phone' => $phone,
            'Address' => $address ?: 'Not specified',
            'Postcode' => $postcode,
            'Service' => $service ?: 'Not specified',
            'Message' => $message ?: '(No message)'
        ];
        
        $tableHtml = self::renderAdminTable("New Pickup Enquiry", $rows);

        // Admin emails: keep HTML minimal (storage-friendly)
        return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"></head><body>'
            . $tableHtml
            . '</body></html>';
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

    private static function buildBusinessAdminHtml(string $business, string $name, string $email, string $phone, string $address, string $industry, string $message, string $siteName): string {
        $rows = [
            'Business' => $business,
            'Contact' => $name,
            'Email' => $email,
            'Phone' => $phone,
            'Address' => $address ?: 'Not specified',
            'Industry' => $industry ?: 'Not specified',
            'Message' => $message ?: '(No message)'
        ];

        $tableHtml = self::renderAdminTable("New Business Quote Request", $rows);

        // Admin emails: keep HTML minimal (storage-friendly)
        return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"></head><body>'
            . $tableHtml
            . '</body></html>';
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
     * Helper to render a clean HTML table for admin emails
     */
    private static function renderAdminTable(string $title, array $data): string {
        $html = '<table width="100%" cellpadding="10" cellspacing="0" style="border-collapse: collapse; margin: 20px 0; border: 1px solid #e2e8f0; font-size: 14px;">';
        $html .= '<tr style="background: #f8fafc;"><th colspan="2" style="text-align: left; border-bottom: 2px solid #e2e8f0;">' . htmlspecialchars($title) . '</th></tr>';
        
        foreach ($data as $label => $value) {
            $html .= '<tr>';
            $html .= '<td width="35%" style="font-weight: bold; border-bottom: 1px solid #e2e8f0; color: #64748b; background: #fdfdfd;">' . htmlspecialchars($label) . '</td>';
            $html .= '<td style="border-bottom: 1px solid #e2e8f0; color: #1e293b;">' . nl2br(htmlspecialchars((string)$value)) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</table>';
        return $html;
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
        $logoUrl = (defined('MAIL_LOGO_URL') && trim((string)MAIL_LOGO_URL) !== '')
            ? (string)MAIL_LOGO_URL
            : ((defined('CLIENT_URL') ? rtrim(CLIENT_URL, '/') : 'https://speedylaundry.co.uk') . '/assets/logo-white.svg');

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
                            <img src="' . htmlspecialchars($logoUrl) . '" alt="Speedy Laundry" width="160" style="display:block; margin: 0 auto 12px auto;" />
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding:36px 32px;">
                            <h1 style="margin:0 0 20px 0;font-size:20px;font-weight:700;color:' . $textDark . ';">' . htmlspecialchars($title) . '</h1>
                            <p style="margin:0 0 16px 0;font-size:16px;line-height:1.6;color:' . $textDark . ';">' . $greeting . '</p>
                            <div style="margin:0 0 16px 0;font-size:16px;line-height:1.6;color:' . $textDark . ';">' . $message1 . '</div>
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
