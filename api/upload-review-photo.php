<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Method not allowed', null, 405);
}

$token = getBearerToken();
if (!$token) {
    sendResponse('error', 'Unauthorized', null, 401);
}
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT admin_id FROM admin_sessions WHERE token = ?");
    $stmt->execute([$token]);
    if (!$stmt->fetch()) {
        sendResponse('error', 'Unauthorized', null, 401);
    }
} catch (Exception $e) {
    sendResponse('error', 'Unauthorized', null, 401);
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    sendResponse('error', 'No file uploaded or upload error', null, 400);
}

$file = $_FILES['file'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
    sendResponse('error', 'Invalid file type. Use JPG, PNG or WEBP', null, 400);
}
if ($file['size'] > 5 * 1024 * 1024) {
    sendResponse('error', 'File too large. Max 5MB', null, 400);
}

$uploadDir = defined('UPLOAD_PATH') ? UPLOAD_PATH : __DIR__ . '/../uploads/';
$subDir = 'reviews/';
$dir = $uploadDir . $subDir;
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$name = 'rev_' . uniqid() . '.' . $ext;
$path = $dir . $name;

if (!move_uploaded_file($file['tmp_name'], $path)) {
    sendResponse('error', 'Failed to save file', null, 500);
}

$baseUrl = defined('UPLOAD_BASE_URL') ? rtrim(UPLOAD_BASE_URL, '/') : '';
$url = $baseUrl . '/uploads/' . $subDir . $name;

sendResponse('success', 'Photo uploaded', ['url' => $url]);
