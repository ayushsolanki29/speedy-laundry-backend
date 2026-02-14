#!/usr/bin/env php
<?php

if (php_sapi_name() !== 'cli') {
    die('CLI only');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Mailer.php';

$BATCH_SIZE = 20;
$processed = 0;

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare(
        "SELECT id, to_email, to_name, subject, body, attempts, max_attempts, COALESCE(is_html, 0) as is_html 
         FROM email_queue 
         WHERE status = 'pending' AND attempts < max_attempts 
         ORDER BY created_at ASC 
         LIMIT ?"
    );
    $stmt->bindValue(1, $BATCH_SIZE, PDO::PARAM_INT);
    $stmt->execute();
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($emails as $email) {
        $success = Mailer::send(
            $email['to_email'],
            $email['subject'],
            $email['body'],
            $email['to_name'],
            !empty($email['is_html'])
        );

        $updateStmt = $db->prepare(
            "UPDATE email_queue SET status = ?, attempts = attempts + 1, sent_at = ?, error_message = ? WHERE id = ?"
        );

        if ($success) {
            $updateStmt->execute(['sent', date('Y-m-d H:i:s'), null, $email['id']]);
            $processed++;
        } else {
            $attempts = $email['attempts'] + 1;
            $newStatus = $attempts >= $email['max_attempts'] ? 'failed' : 'pending';
            $errorMsg = class_exists('Mailer') && !empty(Mailer::$lastError) ? Mailer::$lastError : 'Mail send failed';
            $updateStmt->execute([$newStatus, null, $errorMsg, $email['id']]);
        }
    }

    if ($processed > 0) {
        echo date('Y-m-d H:i:s') . " - Processed {$processed} email(s)\n";
    }
} catch (Exception $e) {
    error_log("Email queue processor error: " . $e->getMessage());
    exit(1);
}
