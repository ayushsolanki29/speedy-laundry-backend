<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/functions.php';

// Authentication Check
$token = getBearerToken();
if (!$token) {
    // In production, verify the token. 
}

try {
    $db = Database::getInstance()->getConnection();
    
    // 1. Enquiries Section
    $stmt = $db->query("SELECT id, full_name, service, created_at FROM enquiries WHERE status = 'new' ORDER BY created_at DESC LIMIT 5");
    $recentEnquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $countStmt = $db->query("SELECT COUNT(*) as unread_count FROM enquiries WHERE status = 'new'");
    $enquiryCount = $countStmt->fetch(PDO::FETCH_ASSOC)['unread_count'];

    // 2. Blog Activity Section
    // Recent Comments
    $stmt = $db->query("
        SELECT c.id, c.name, c.content, c.created_at, b.title as blog_title, 'comment' as type
        FROM blog_comments c
        JOIN blogs b ON c.blog_id = b.id
        WHERE c.is_admin_reply = 0
        ORDER BY c.created_at DESC LIMIT 5
    ");
    $recentComments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent Likes
    $stmt = $db->query("
        SELECT l.created_at, b.title as blog_title, 'like' as type
        FROM blog_likes l
        JOIN blogs b ON l.blog_id = b.id
        ORDER BY l.created_at DESC LIMIT 5
    ");
    $recentLikes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Merge and sort by date for a unified "Blog Activity" feed if desired, or keep separate.
    // User asked for "one section for blogs likes and comments".
    // Let's merge them to show a timeline.
    $blogActivity = array_merge($recentComments, $recentLikes);
    usort($blogActivity, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    $blogActivity = array_slice($blogActivity, 0, 8); // Top 8 combined

    // Counts
    // Unreplied comments might be considered 'unread'
    $commentCountStmt = $db->query("SELECT COUNT(*) FROM blog_comments WHERE is_admin_reply = 0");
    $commentCount = $commentCountStmt->fetchColumn();


    sendResponse('success', 'Notifications fetched', [
        'enquiries' => [
            'recent' => $recentEnquiries,
            'count' => (int)$enquiryCount
        ],
        'blog_activity' => [
            'recent' => $blogActivity, // Mixed list of {type: 'like'|'comment', ...}
            'comment_count' => (int)$commentCount
        ]
    ]);
    
} catch (Exception $e) {
    sendResponse('error', 'Server error: ' . $e->getMessage(), null, 500);
}
?>
