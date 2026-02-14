<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    http_response_code(200);
    exit;
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

try {
    $db = Database::getInstance()->getConnection();
    
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : null;
    $search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : null;

    $query = "SELECT b.*, a.username as author_name 
              FROM blogs b 
              LEFT JOIN admins a ON b.author_id = a.id 
              WHERE b.status = 'published'";
    
    $params = [];
    if ($category) {
        $query .= " AND b.category = :category";
    }
    if ($search) {
        $query .= " AND (b.title LIKE :search OR b.content LIKE :search)";
    }

    $query .= " ORDER BY b.published_at DESC LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($query);
    
    if ($category) {
        $stmt->bindValue(':category', $category);
    }
    if ($search) {
        $stmt->bindValue(':search', "%$search%");
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) FROM blogs WHERE status = 'published'";
    if ($category) {
        $countQuery .= " AND category = :category";
    }
    if ($search) {
        $countQuery .= " AND (title LIKE :search OR content LIKE :search)";
    }
    
    $countStmt = $db->prepare($countQuery);
    if ($category) {
        $countStmt->bindValue(':category', $category);
    }
    if ($search) {
        $countStmt->bindValue(':search', "%$search%");
    }
    $countStmt->execute();
    $totalCount = $countStmt->fetchColumn();

    sendResponse('success', 'Blogs fetched successfully', [
        'blogs' => $blogs,
        'total' => (int)$totalCount,
        'limit' => $limit,
        'offset' => $offset
    ]);
    
} catch (Exception $e) {
    sendResponse('error', 'Server error: ' . $e->getMessage(), null, 500);
}
?>
