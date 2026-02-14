<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = Database::getInstance()->getConnection();

    if ($method === 'GET') {
        $stmt = $db->query("
            SELECT id, name, content, rating, photo_url, display_order, is_pinned, created_at 
            FROM reviews 
            ORDER BY is_pinned DESC, display_order ASC, id DESC
        ");
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
        sendResponse('success', 'Reviews fetched', $reviews);
    }

    if ($method === 'POST' || $method === 'PUT') {
        $token = getBearerToken();
        if (!$token) {
            sendResponse('error', 'Unauthorized', null, 401);
        }
        $stmt = $db->prepare("SELECT admin_id FROM admin_sessions WHERE token = ?");
        $stmt->execute([$token]);
        if (!$stmt->fetch()) {
            sendResponse('error', 'Unauthorized', null, 401);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            sendResponse('error', 'Invalid input', null, 400);
        }

        $name = isset($input['name']) ? sanitizeInput($input['name']) : '';
        $content = isset($input['content']) ? trim($input['content']) : '';
        $rating = isset($input['rating']) ? min(5, max(1, (int) $input['rating'])) : 5;
        $photo_url = isset($input['photo_url']) ? trim($input['photo_url']) : null;
        $display_order = isset($input['display_order']) ? (int) $input['display_order'] : 0;
        $is_pinned = isset($input['is_pinned']) ? ($input['is_pinned'] ? 1 : 0) : 0;

        if (empty($name) || empty($content)) {
            sendResponse('error', 'Name and content are required', null, 400);
        }

        if ($method === 'POST') {
            $stmt = $db->prepare("INSERT INTO reviews (name, content, rating, photo_url, display_order, is_pinned) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $content, $rating, $photo_url, $display_order, $is_pinned]);
            sendResponse('success', 'Review added');
        } else {
            $id = (int) ($input['id'] ?? 0);
            if (!$id) sendResponse('error', 'Review ID required', null, 400);
            $stmt = $db->prepare("UPDATE reviews SET name=?, content=?, rating=?, photo_url=?, display_order=?, is_pinned=? WHERE id=?");
            $stmt->execute([$name, $content, $rating, $photo_url, $display_order, $is_pinned, $id]);
            sendResponse('success', 'Review updated');
        }
    }

    if ($method === 'DELETE') {
        $token = getBearerToken();
        if (!$token) sendResponse('error', 'Unauthorized', null, 401);
        $stmt = $db->prepare("SELECT admin_id FROM admin_sessions WHERE token = ?");
        $stmt->execute([$token]);
        if (!$stmt->fetch()) sendResponse('error', 'Unauthorized', null, 401);

        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) sendResponse('error', 'ID required', null, 400);
        $db->prepare("DELETE FROM reviews WHERE id = ?")->execute([$id]);
        sendResponse('success', 'Review deleted');
    }

    sendResponse('error', 'Method not allowed', null, 405);
} catch (Exception $e) {
    sendResponse('error', 'Server error: ' . $e->getMessage(), null, 500);
}
