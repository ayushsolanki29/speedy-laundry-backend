<?php

/**
 * Send a JSON response and terminate cleanly.
 * Uses output buffering to prevent any stray PHP notices/warnings
 * from corrupting the JSON output.
 */
function sendResponse($status, $message, $data = null, $statusCode = 200) {
    // Discard any buffered output (notices, warnings) that accumulated before this call
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($statusCode);
    header('Content-Type: application/json');
    // Start a clean buffer so shutdown notices can't append to our response
    ob_start();
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ]);
    ob_end_flush();
    exit;
}

/**
 * Basic input sanitization
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Convert a DB datetime string (assumed in APP_TIMEZONE) to a UTC ISO-8601 string.
 * Helps the frontend avoid ambiguous Date parsing.
 */
function toUtcIso(?string $dateTimeString): ?string {
    if (!$dateTimeString) return null;

    $sourceTz = defined('APP_TIMEZONE') ? APP_TIMEZONE : 'UTC';
    try {
        $dt = new DateTime($dateTimeString, new DateTimeZone($sourceTz));
        $dt->setTimezone(new DateTimeZone('UTC'));
        // Use Z so JS parses consistently as UTC
        return $dt->format('Y-m-d\\TH:i:s\\Z');
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Get Bearer Token from Header
 */
function getBearerToken() {
    $authHeader = null;
    if (isset($_SERVER['Authorization'])) {
        $authHeader = $_SERVER['Authorization'];
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
        }
    }

    if ($authHeader) {
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $matches[1];
        }
    }
    return null;
}

/**
 * Verify Google reCAPTCHA v3 token.
 */
function verifyRecaptcha(?string $token): array {
    if (empty($token)) {
        return ['success' => false, 'error' => 'Security token missing. Please refresh and try again.'];
    }

    $secretKey = defined('RECAPTCHA_SECRET_KEY') ? RECAPTCHA_SECRET_KEY : '';
    if (empty($secretKey)) {
        error_log('RECAPTCHA_SECRET_KEY is not defined');
        return ['success' => true, 'score' => 1.0];
    }

    $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
    $postData = http_build_query([
        'secret' => $secretKey,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ]);

    $response = false;
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $verifyUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $response = curl_exec($ch);
        curl_close($ch);
    }

    if ($response === false) {
        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'content' => $postData,
                'timeout' => 8
            ]
        ]);
        $response = @file_get_contents($verifyUrl, false, $context);
    }

    if (!$response) {
        error_log('Failed to verify reCAPTCHA with Google servers.');
        return ['success' => false, 'error' => 'Security check temporarily unreachable. Please try again.'];
    }

    $result = json_decode($response, true);
    if (!isset($result['success']) || !$result['success']) {
        $errorCodes = isset($result['error-codes']) ? (array)$result['error-codes'] : ['verification failed'];
        $errorCodeStr = implode(', ', $errorCodes);
        error_log('reCAPTCHA failed: ' . $errorCodeStr . ' | Response: ' . $response);

        // If the error is 'browser-error' (commonly triggered when legitimate users have AdBlock,
        // Brave Shields, or in-app browsers that disrupt Google's telemetry), allow submission
        // through so real customers are not turned away.
        if (in_array('browser-error', $errorCodes, true)) {
            error_log('reCAPTCHA browser-error encountered (likely AdBlock/in-app browser). Allowing submission through.');
            return ['success' => true, 'score' => 0.5, 'note' => 'browser-error fallback'];
        }

        return ['success' => false, 'error' => 'Security verification failed. Please try again.'];
    }

    $score = isset($result['score']) ? (float)$result['score'] : 0.0;
    $threshold = defined('RECAPTCHA_SCORE_THRESHOLD') ? RECAPTCHA_SCORE_THRESHOLD : 0.5;

    if ($score < $threshold) {
        error_log("reCAPTCHA bot suspected. Score: {$score}");
        return ['success' => false, 'error' => 'Your submission was flagged by spam protection. Please try again or contact us directly.'];
    }

    return ['success' => true, 'score' => $score];
}
?>
