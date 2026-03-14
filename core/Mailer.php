<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Mailer - Sends emails via PHPMailer (SMTP or PHP mail)
 */
class Mailer {

    /** Last error message when send fails (for debugging) */
    public static $lastError = '';
    private static $sentWarned = false;

    private static function appendToSent(PHPMailer $mail): void {
        if (!defined('MAIL_APPEND_TO_SENT') || !MAIL_APPEND_TO_SENT) {
            return;
        }
        if (!function_exists('imap_open') || !function_exists('imap_append')) {
            if (!self::$sentWarned) {
                self::$sentWarned = true;
                error_log('Mailer: IMAP extension is not enabled; cannot append sent message to Sent folder.');
            }
            return;
        }
        if (!defined('IMAP_HOST') || !defined('IMAP_PORT') || !defined('IMAP_FLAGS')) {
            return;
        }
        $imapUser = defined('IMAP_USER') ? (string)IMAP_USER : '';
        $imapPass = defined('IMAP_PASS') ? (string)IMAP_PASS : '';
        if (trim($imapUser) === '' || trim($imapPass) === '') {
            return;
        }

        $server = '{' . IMAP_HOST . ':' . (int)IMAP_PORT . IMAP_FLAGS . '}';
        $inboxMailbox = $server . 'INBOX';
        $sentFolder = defined('IMAP_SENT_FOLDER') ? trim((string)IMAP_SENT_FOLDER) : 'Sent';
        $sentMailbox = $server . ($sentFolder !== '' ? $sentFolder : 'Sent');

        $imap = @imap_open($inboxMailbox, $imapUser, $imapPass);
        if ($imap === false) {
            error_log('Mailer IMAP open failed: ' . (function_exists('imap_last_error') ? (imap_last_error() ?: '') : 'unknown error'));
            return;
        }

        $mime = $mail->getSentMIMEMessage();
        if ($mime !== '') {
            $ok = @imap_append($imap, $sentMailbox, $mime, '\\Seen');
            if ($ok === false) {
                error_log('Mailer IMAP append failed: ' . (function_exists('imap_last_error') ? (imap_last_error() ?: '') : 'unknown error'));
            }
        }
        imap_close($imap);
    }

    /**
     * Send an email. Returns true on success.
     * @param bool $isHtml When true, body is HTML; otherwise plain text
     */
    public static function send(string $toEmail, string $subject, string $body, ?string $toName = null, bool $isHtml = false): bool {
        self::$lastError = '';
        $fromEmail =
            (defined('MAIL_FROM') && trim((string)MAIL_FROM) !== '') ? MAIL_FROM :
            ((defined('SMTP_USER') && trim((string)SMTP_USER) !== '') ? SMTP_USER :
            ((defined('ADMIN_EMAIL') && trim((string)ADMIN_EMAIL) !== '') ? ADMIN_EMAIL : 'noreply@speedylaundry.co.uk'));
        $fromName = defined('SITE_NAME') ? SITE_NAME : 'Speedy Laundry';

        $mail = new PHPMailer(true);

        try {
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->Encoding = 'base64';
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($toEmail, $toName ?? '');
            $mail->addReplyTo($fromEmail, $fromName);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->isHTML($isHtml);
            if ($isHtml) {
                $siteName = defined('SITE_NAME') ? SITE_NAME : 'Speedy Laundry';
                $mail->AltBody = "Thank you for your submission. We have received it and will reply soon.\n\n— {$siteName}";
            }

            $smtpHost = defined('SMTP_HOST') ? trim(SMTP_HOST) : '';
            if ($smtpHost !== '') {
                $mail->isSMTP();
                if (defined('SMTP_DEBUG') && SMTP_DEBUG && php_sapi_name() === 'cli') {
                    $mail->SMTPDebug = SMTP::DEBUG_SERVER;  // Full client + server conversation
                    $mail->Debugoutput = function ($str, $level) {
                        echo "[" . date('H:i:s') . "] $str\n";
                    };
                }
                $mail->Host = $smtpHost;
                $mail->Port = defined('SMTP_PORT') ? (int) SMTP_PORT : 587;
                $mail->SMTPSecure = defined('SMTP_SECURE') ? SMTP_SECURE : PHPMailer::ENCRYPTION_STARTTLS;
                $mail->SMTPAuth = true;
                $mail->Username = defined('SMTP_USER') ? SMTP_USER : '';
                $mail->Password = defined('SMTP_PASS') ? SMTP_PASS : '';
            } else {
                $mail->isMail();
            }

            $mail->send();
            self::appendToSent($mail);
            return true;
        } catch (Exception $e) {
            self::$lastError = $mail->ErrorInfo ?: $e->getMessage();
            error_log("Mailer error: " . self::$lastError);
            return false;
        }
    }
}
