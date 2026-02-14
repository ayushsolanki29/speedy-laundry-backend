<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$slug = isset($_GET['slug']) ? sanitizeInput($_GET['slug']) : null;
$ip_address = $_SERVER['REMOTE_ADDR'];

if (!$slug) {
    sendResponse('error', 'Slug is required', null, 400);
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Fetch Blog Details
    $stmt = $db->prepare("SELECT b.*, a.username as author_name 
                          FROM blogs b 
                          LEFT JOIN admins a ON b.author_id = a.id 
                          WHERE b.slug = ? AND b.status = 'published'");
    $stmt->execute([$slug]);
    $blog = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$blog) {
        sendResponse('error', 'Blog post not found', null, 404);
    }

    // Check if user liked this blog
    $stmt = $db->prepare("SELECT COUNT(*) FROM blog_likes WHERE blog_id = ? AND ip_address = ?");
    $stmt->execute([$blog['id'], $ip_address]);
    $is_liked = $stmt->fetchColumn() > 0;

    // Fetch Comments
    $stmt = $db->prepare("
        SELECT id, name, content, created_at, is_admin_reply, parent_id
        FROM blog_comments 
        WHERE blog_id = ? 
        ORDER BY created_at ASC
    ");
    $stmt->execute([$blog['id']]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Structure comments (simple nesting for admin replies)
    $structuredComments = [];
    $commentMap = [];

    foreach ($comments as $comment) {
        $comment['replies'] = [];
        $commentMap[$comment['id']] = $comment;
    }

    foreach ($comments as $comment) {
        if ($comment['parent_id']) {
            if (isset($commentMap[$comment['parent_id']])) {
                $commentMap[$comment['parent_id']]['replies'][] = $comment;
            }
        } else {
            $structuredComments[] = &$commentMap[$comment['id']];
        }
    }

    // Attach interaction data
    $blog['is_liked'] = $is_liked;
    $blog['comments'] = $structuredComments;

    sendResponse('success', 'Blog post fetched successfully', $blog);
    
} catch (Exception $e) {
    sendResponse('error', 'Server error: ' . $e->getMessage(), null, 500);
}
?>
