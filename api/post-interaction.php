<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = isset($input['action']) ? $input['action'] : '';
$blogIds = isset($input['blog_id']) ? sanitizeInput($input['blog_id']) : 0;
// Support simple ID if posted directly
if (!$blogIds && isset($_POST['blog_id'])) $blogIds = sanitizeInput($_POST['blog_id']);
// JSON body overrides POST form data usually
if ($input && isset($input['blog_id'])) $blogIds = sanitizeInput($input['blog_id']);


if (!$blogIds) {
    sendResponse('error', 'Blog ID is required', null, 400);
}

try {
    $db = Database::getInstance()->getConnection();

    if ($action === 'like') {
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        // Check if already liked
        $stmt = $db->prepare("SELECT id FROM blog_likes WHERE blog_id = ? AND ip_address = ?");
        $stmt->execute([$blogIds, $ip_address]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Unlike
            $db->prepare("DELETE FROM blog_likes WHERE id = ?")->execute([$existing['id']]);
            // Decrease count
            $db->prepare("UPDATE blogs SET likes_count = GREATEST(likes_count - 1, 0) WHERE id = ?")->execute([$blogIds]);
            $liked = false;
        } else {
            // Like
            $db->prepare("INSERT INTO blog_likes (blog_id, ip_address, user_agent) VALUES (?, ?, ?)")
               ->execute([$blogIds, $ip_address, $_SERVER['HTTP_USER_AGENT']]);
            // Increase count
            $db->prepare("UPDATE blogs SET likes_count = likes_count + 1 WHERE id = ?")->execute([$blogIds]);
            $liked = true;
        }

        // Get new count
        $stmt = $db->prepare("SELECT likes_count FROM blogs WHERE id = ?");
        $stmt->execute([$blogIds]);
        $count = $stmt->fetchColumn();

        sendResponse('success', 'Like toggled', ['liked' => $liked, 'count' => $count]);

    } elseif ($action === 'comment') {
        $name = isset($input['name']) ? sanitizeInput($input['name']) : '';
        $email = isset($input['email']) ? sanitizeInput($input['email']) : '';
        $content = isset($input['content']) ? sanitizeInput($input['content']) : '';

        if (!$name || !$email || !$content) {
            sendResponse('error', 'All fields are required', null, 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendResponse('error', 'Invalid email format', null, 400);
        }

        // Cooldown Validation: Check for last comment by user on this blog
        // Rule: Can comment only if previous comment has admin reply
        // Find latest comment by this email for this blog
        $stmt = $db->prepare("
            SELECT c.id, 
                   (SELECT COUNT(*) FROM blog_comments r WHERE r.parent_id = c.id AND r.is_admin_reply = 1) as reply_count
            FROM blog_comments c 
            WHERE c.blog_id = ? AND c.email = ? AND c.is_admin_reply = 0
            ORDER BY c.created_at DESC LIMIT 1
        ");
        $stmt->execute([$blogIds, $email]);
        $lastComment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($lastComment && $lastComment['reply_count'] == 0) {
            // User has a pending unreplied comment
            sendResponse('error', 'Please wait for an admin reply before commenting again.', ['cooldown' => true], 403);
        }

        // Add Comment
        $stmt = $db->prepare("INSERT INTO blog_comments (blog_id, name, email, content) VALUES (?, ?, ?, ?)");
        $stmt->execute([$blogIds, $name, $email, $content]);
        
        // Update comment count
        $db->prepare("UPDATE blogs SET comments_count = comments_count + 1 WHERE id = ?")->execute([$blogIds]);

        sendResponse('success', 'Comment added successfully');
    } else {
        sendResponse('error', 'Invalid action', null, 400);
    }

} catch (Exception $e) {
    sendResponse('error', 'Server error: ' . $e->getMessage(), null, 500);
}
?>
