<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    http_response_code(200);
    exit;
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Authentication Check
$token = getBearerToken();
if (!$token) {
    sendResponse('error', 'Unauthorized', null, 401);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = Database::getInstance()->getConnection();

    switch ($method) {
        case 'GET':
            // If blog_id is provided, get comments for that blog
            // Otherwise get all recent comments for the moderation panel
            if (isset($_GET['blog_id'])) {
                $stmt = $db->prepare("
                    SELECT c.*, b.title as blog_title, b.slug as blog_slug
                    FROM blog_comments c 
                    JOIN blogs b ON c.blog_id = b.id 
                    WHERE c.blog_id = ? 
                    ORDER BY c.created_at ASC
                ");
                $stmt->execute([(int)$_GET['blog_id']]);
            } else {
                $stmt = $db->query("
                    SELECT c.*, b.title as blog_title, b.slug as blog_slug
                    FROM blog_comments c 
                    JOIN blogs b ON c.blog_id = b.id 
                    ORDER BY c.created_at DESC
                ");
            }
            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($comments as &$c) {
                $c['created_at_iso'] = toUtcIso($c['created_at'] ?? null);
            }
            unset($c);
            sendResponse('success', 'Comments fetched', $comments);
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $blogId = (int)($input['blog_id'] ?? 0);
            $parentId = (int)($input['parent_id'] ?? 0);
            $content = sanitizeInput($input['content'] ?? '');

            if (!$blogId || !$parentId || empty($content)) {
                sendResponse('error', 'Blog ID, Parent ID, and content are required', null, 400);
            }

            // Verify parent comment exists and is not already a reply
            $stmt = $db->prepare("SELECT id FROM blog_comments WHERE id = ? AND is_admin_reply = 0");
            $stmt->execute([$parentId]);
            if (!$stmt->fetch()) {
                sendResponse('error', 'Invalid parent comment', null, 400);
            }

            // Insert admin reply
            $stmt = $db->prepare("
                INSERT INTO blog_comments (blog_id, name, email, content, is_admin_reply, parent_id) 
                VALUES (?, ?, ?, ?, 1, ?)
            ");
            $stmt->execute([
                $blogId, 
                'Admin', 
                'admin@speedylaundry.com', 
                $content, 
                $parentId
            ]);

            sendResponse('success', 'Reply posted successfully');
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) {
                sendResponse('error', 'ID is required', null, 400);
            }

            // Decrement blog comment count before deleting
            $stmt = $db->prepare("SELECT blog_id FROM blog_comments WHERE id = ?");
            $stmt->execute([$id]);
            $blogId = $stmt->fetchColumn();

            if ($blogId) {
                $stmt = $db->prepare("DELETE FROM blog_comments WHERE id = ? OR parent_id = ?");
                $stmt->execute([$id, $id]);
                
                // Recalculate or just subtract (safest is to recount)
                $stmt = $db->prepare("UPDATE blogs SET comments_count = (SELECT COUNT(*) FROM blog_comments WHERE blog_id = ?) WHERE id = ?");
                $stmt->execute([$blogId, $blogId]);
            }

            sendResponse('success', 'Comment deleted successfully');
            break;

        default:
            sendResponse('error', 'Method not allowed', null, 405);
            break;
    }
} catch (Exception $e) {
    sendResponse('error', 'Server error: ' . $e->getMessage(), null, 500);
}
?>
