<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Mailer - Sends emails exclusively via SMTP (PHPMailer).
 *
 * PHP mail() / sendmail fallback is intentionally removed.
 * All delivery goes through smtp.hostinger.com to avoid blacklisted
 * shared-hosting IPs (Spamhaus block on 82.165.159.5 and similar).
 *
 * Logging is controlled by three constants in config.php:
 *   MAIL_LOG       true/false  – enable/disable the mail activity log
 *   MAIL_LOG_PATH  string      – absolute path to the log file
 *   MAIL_LOG_SMTP  true/false  – also log the full SMTP dialogue
 */
class Mailer {

    /** Last error message from the most recent send attempt. */
    public static string $lastError = '';

    private static bool $sentWarned = false;

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Send an email via SMTP only.
     *
     * @param string      $toEmail      Recipient address.
     * @param string      $subject      Email subject.
     * @param string      $body         Email body (HTML or plain text).
     * @param string|null $toName       Recipient display name.
     * @param bool        $isHtml       True when $body contains HTML.
     * @param string|null $replyTo      Reply-To address (defaults to From).
     * @param string|null $replyToName  Reply-To display name.
     *
     * @return bool  True on success, false on failure (error in self::$lastError).
     */
    public static function send(
        string  $toEmail,
        string  $subject,
        string  $body,
        ?string $toName      = null,
        bool    $isHtml      = false,
        ?string $replyTo     = null,
        ?string $replyToName = null
    ): bool {
        self::$lastError = '';

        // Collect SMTP dialogue lines when MAIL_LOG_SMTP is on
        $smtpDialogue = [];

        $mail = null; // declared here so catch blocks can reference it safely

        try {
            // --- Resolve and validate SMTP credentials -----------------------
            $smtpHost = self::resolveCredential('SMTP_HOST');
            $smtpUser = self::resolveCredential('SMTP_USER');
            $smtpPass = self::resolveCredential('SMTP_PASS');

            if ($smtpHost === '') {
                throw new \RuntimeException(
                    'SMTP_HOST is not configured. Set it in config.php or as an environment variable.'
                );
            }
            if ($smtpUser === '') {
                throw new \RuntimeException(
                    'SMTP_USER is not configured. Set it in config.php or as an environment variable.'
                );
            }
            if ($smtpPass === '') {
                throw new \RuntimeException(
                    'SMTP_PASS is not configured. Set it in config.php or as an environment variable.'
                );
            }

            $smtpPort   = defined('SMTP_PORT')  ? (int) SMTP_PORT              : 465;
            $smtpSecure = defined('SMTP_SECURE') ? trim((string) SMTP_SECURE)  : 'ssl';

            // --- Resolve From / Reply-To -------------------------------------
            $fromEmail = self::resolveCredential('MAIL_FROM');
            if ($fromEmail === '') {
                $fromEmail = $smtpUser;
            }
            $fromName = defined('SITE_NAME') ? (string) SITE_NAME : 'Speedy Laundry';

            // --- Build the message -------------------------------------------
            $mail = new PHPMailer(true); // true = throw exceptions

            $mail->CharSet  = PHPMailer::CHARSET_UTF8;
            $mail->Encoding = 'base64';

            // SMTP only — no isMail() / sendmail fallback
            $mail->isSMTP();
            $mail->SMTPAuth   = true;
            $mail->Host       = $smtpHost;
            $mail->Port       = $smtpPort;
            $mail->SMTPSecure = $smtpSecure;
            $mail->Username   = $smtpUser;
            $mail->Password   = $smtpPass;

            // SMTP dialogue capture
            // MAIL_LOG_SMTP=true  → DEBUG_SERVER (full client+server conversation)
            // SMTP_DEBUG=true     → same, but also echoes to PHP error_log (legacy flag)
            $wantSmtpLog = (defined('MAIL_LOG_SMTP') && MAIL_LOG_SMTP)
                        || (defined('SMTP_DEBUG')    && SMTP_DEBUG);

            $mail->SMTPDebug  = $wantSmtpLog ? SMTP::DEBUG_SERVER : SMTP::DEBUG_OFF;
            $mail->Debugoutput = function (string $str, int $level) use (&$smtpDialogue): void {
                $line = rtrim($str);
                $smtpDialogue[] = $line;
                // Also mirror to PHP error log when the legacy SMTP_DEBUG flag is on
                if (defined('SMTP_DEBUG') && SMTP_DEBUG) {
                    error_log('[SMTP] ' . $line);
                }
            };

            // Addresses
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($toEmail, $toName ?? '');
            $mail->addReplyTo($replyTo ?: $fromEmail, $replyToName ?: $fromName);

            // Content
            $mail->Subject = $subject;
            $mail->isHTML($isHtml);
            $mail->Body    = $body;
            if ($isHtml) {
                $mail->AltBody = "Thank you for your submission. We have received it and will reply soon.\n\n— {$fromName}";
            }

            // --- Send --------------------------------------------------------
            $mail->send();

            // Log success
            self::writeLog('SUCCESS', $fromEmail, $toEmail, $subject, '', $smtpDialogue);

            // Optionally copy to IMAP Sent folder
            self::appendToSent($mail);

            return true;

        } catch (Exception $e) {
            // PHPMailer exception — includes raw SMTP server response
            self::$lastError = ($mail !== null ? $mail->ErrorInfo : '') ?: $e->getMessage();
            error_log('[Mailer] PHPMailer exception: ' . self::$lastError);
            self::writeLog('FAILED', '', $toEmail, $subject, self::$lastError, $smtpDialogue);
            return false;

        } catch (\RuntimeException $e) {
            // Configuration error (missing credentials, etc.)
            self::$lastError = $e->getMessage();
            error_log('[Mailer] Configuration error: ' . self::$lastError);
            self::writeLog('FAILED', '', $toEmail, $subject, self::$lastError, $smtpDialogue);
            return false;

        } catch (\Throwable $e) {
            // Catch-all for unexpected errors
            self::$lastError = $e->getMessage();
            error_log('[Mailer] Unexpected error: ' . self::$lastError);
            self::writeLog('FAILED', '', $toEmail, $subject, self::$lastError, $smtpDialogue);
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Logging
    // -------------------------------------------------------------------------

    /**
     * Write a human-readable entry to the mail log file.
     *
     * Each entry looks like:
     *
     *   ════════════════════════════════════════════════════════════
     *   [2026-05-20 14:32:01 BST]  ✔ SUCCESS
     *   From    : info@speedylaundry.co.uk
     *   To      : customer@example.com
     *   Subject : Your enquiry has been received
     *   ────────────────────────────────────────────────────────────
     *
     * On failure the "Error" line is added, and when MAIL_LOG_SMTP is
     * enabled the full SMTP dialogue is appended below a separator.
     *
     * Controlled by:
     *   MAIL_LOG       – master on/off switch
     *   MAIL_LOG_PATH  – where to write
     *   MAIL_LOG_SMTP  – whether to include the SMTP dialogue
     *
     * @param string   $status      'SUCCESS' or 'FAILED'
     * @param string   $from        Sender address (empty string if unknown)
     * @param string   $to          Recipient address
     * @param string   $subject     Email subject
     * @param string   $error       Error message (empty on success)
     * @param string[] $dialogue    SMTP dialogue lines collected during send
     */
    private static function writeLog(
        string $status,
        string $from,
        string $to,
        string $subject,
        string $error,
        array  $dialogue
    ): void {
        if (!defined('MAIL_LOG') || !MAIL_LOG) {
            return;
        }

        $logPath = defined('MAIL_LOG_PATH') ? trim((string) MAIL_LOG_PATH) : '';
        if ($logPath === '') {
            return;
        }

        // Ensure the log directory exists
        $logDir = dirname($logPath);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $icon      = ($status === 'SUCCESS') ? '✔' : '✘';
        $timestamp = date('Y-m-d H:i:s T');

        $lines   = [];
        $lines[] = str_repeat('═', 60);
        $lines[] = "[{$timestamp}]  {$icon} {$status}";
        $lines[] = "From    : " . ($from !== '' ? $from : '(unknown)');
        $lines[] = "To      : {$to}";
        $lines[] = "Subject : {$subject}";

        if ($error !== '') {
            $lines[] = "Error   : {$error}";
        }

        // Append SMTP dialogue when requested and available
        $wantSmtpLog = defined('MAIL_LOG_SMTP') && MAIL_LOG_SMTP;
        if ($wantSmtpLog && !empty($dialogue)) {
            $lines[] = str_repeat('─', 60);
            $lines[] = 'SMTP Dialogue:';
            foreach ($dialogue as $dl) {
                $lines[] = '  ' . $dl;
            }
        }

        $lines[] = str_repeat('─', 60);
        $lines[] = ''; // blank line between entries

        $entry = implode(PHP_EOL, $lines) . PHP_EOL;

        // Append to log file; suppress errors so a write failure never breaks the app
        @file_put_contents($logPath, $entry, FILE_APPEND | LOCK_EX);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Read a PHP constant and trim whitespace.
     * Returns an empty string if the constant is not defined.
     */
    private static function resolveCredential(string $name): string
    {
        if (!defined($name)) {
            return '';
        }
        return trim((string) constant($name));
    }

    /**
     * Append the sent message to the IMAP Sent folder (optional feature).
     * Silently skips if IMAP is not configured or the extension is missing.
     */
    private static function appendToSent(PHPMailer $mail): void
    {
        // Hard-disabled: IMAP append causes PHP shutdown notices on Hostinger
        // that corrupt JSON API responses. Re-enable only after fixing IMAP config.
        if (!defined('MAIL_APPEND_TO_SENT') || !MAIL_APPEND_TO_SENT) {
            return;
        }
        if (!function_exists('imap_open') || !function_exists('imap_append')) {
            if (!self::$sentWarned) {
                self::$sentWarned = true;
                error_log('[Mailer] IMAP extension not enabled; skipping Sent-folder append.');
            }
            return;
        }
        if (!defined('IMAP_HOST') || !defined('IMAP_PORT') || !defined('IMAP_FLAGS')) {
            return;
        }

        $imapUser = self::resolveCredential('IMAP_USER');
        $imapPass = self::resolveCredential('IMAP_PASS');
        if ($imapUser === '' || $imapPass === '') {
            return;
        }

        $server      = '{' . IMAP_HOST . ':' . (int) IMAP_PORT . IMAP_FLAGS . '}';
        $sentFolder  = defined('IMAP_SENT_FOLDER') ? trim((string) IMAP_SENT_FOLDER) : 'Sent';
        $sentMailbox = $server . ($sentFolder !== '' ? $sentFolder : 'Sent');
        $inbox       = $server . 'INBOX';

        $imap = @imap_open($inbox, $imapUser, $imapPass);
        if ($imap === false) {
            error_log('[Mailer] IMAP open failed: ' . (imap_last_error() ?: 'unknown'));
            return;
        }

        $mime = $mail->getSentMIMEMessage();
        if ($mime !== '') {
            if (!@imap_append($imap, $sentMailbox, $mime, '\\Seen')) {
                error_log('[Mailer] IMAP append failed: ' . (imap_last_error() ?: 'unknown'));
            }
        }

        imap_close($imap);
    }
}
