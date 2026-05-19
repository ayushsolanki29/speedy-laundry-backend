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
?>
