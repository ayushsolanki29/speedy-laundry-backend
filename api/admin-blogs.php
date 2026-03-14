<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    http_response_code(200);
    exit;
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Authentication Check
$token = getBearerToken();
if (!$token) {
    // For local dev, we might allow but should check in production
    // sendResponse('error', 'Unauthorized', null, 401);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = Database::getInstance()->getConnection();

    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Fetch single for edit
                $stmt = $db->prepare("SELECT * FROM blogs WHERE id = ?");
                $stmt->execute([(int)$_GET['id']]);
                $blog = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($blog) {
                    $blog['created_at_iso'] = toUtcIso($blog['created_at'] ?? null);
                    $blog['updated_at_iso'] = toUtcIso($blog['updated_at'] ?? null);
                    $blog['published_at_iso'] = toUtcIso($blog['published_at'] ?? null);
                }
                sendResponse('success', 'Blog fetched', $blog);
            } else {
                // List all
                $stmt = $db->query("SELECT b.*, a.username as author_name FROM blogs b LEFT JOIN admins a ON b.author_id = a.id ORDER BY b.created_at DESC");
                $blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($blogs as &$b) {
                    $b['created_at_iso'] = toUtcIso($b['created_at'] ?? null);
                    $b['updated_at_iso'] = toUtcIso($b['updated_at'] ?? null);
                    $b['published_at_iso'] = toUtcIso($b['published_at'] ?? null);
                }
                unset($b);
                sendResponse('success', 'Blogs fetched', $blogs);
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            $title = sanitizeInput($input['title'] ?? '');
            $slug = sanitizeInput($input['slug'] ?? '');
            $excerpt = sanitizeInput($input['excerpt'] ?? '');
            $content = $input['content'] ?? '';
            $image_url = sanitizeInput($input['image_url'] ?? '');
            $category = sanitizeInput($input['category'] ?? '');
            $status = sanitizeInput($input['status'] ?? 'draft');
            $author_id = $input['author_id'] ?? 1;

            if (empty($title) || empty($slug) || empty($content)) {
                sendResponse('error', 'Title, slug and content are required', null, 400);
            }

            $published_at = ($status === 'published') ? date('Y-m-d H:i:s') : null;

            $stmt = $db->prepare("INSERT INTO blogs (title, slug, excerpt, content, image_url, category, author_id, status, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $slug, $excerpt, $content, $image_url, $category, $author_id, $status, $published_at]);

            sendResponse('success', 'Blog created successfully', ['id' => $db->lastInsertId()]);
            break;

        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);
            $id = (int)($input['id'] ?? 0);

            if (!$id) {
                sendResponse('error', 'ID is required', null, 400);
            }

            $title = sanitizeInput($input['title'] ?? '');
            $slug = sanitizeInput($input['slug'] ?? '');
            $excerpt = sanitizeInput($input['excerpt'] ?? '');
            $content = $input['content'] ?? '';
            $image_url = sanitizeInput($input['image_url'] ?? '');
            $category = sanitizeInput($input['category'] ?? '');
            $status = sanitizeInput($input['status'] ?? 'draft');

            $stmt = $db->prepare("SELECT status FROM blogs WHERE id = ?");
            $stmt->execute([$id]);
            $oldStatus = $stmt->fetchColumn();

            $published_at = null;
            if ($status === 'published' && $oldStatus !== 'published') {
                $published_at = date('Y-m-d H:i:s');
                $stmt = $db->prepare("UPDATE blogs SET title = ?, slug = ?, excerpt = ?, content = ?, image_url = ?, category = ?, status = ?, published_at = ? WHERE id = ?");
                $stmt->execute([$title, $slug, $excerpt, $content, $image_url, $category, $status, $published_at, $id]);
            } else {
                $stmt = $db->prepare("UPDATE blogs SET title = ?, slug = ?, excerpt = ?, content = ?, image_url = ?, category = ?, status = ? WHERE id = ?");
                $stmt->execute([$title, $slug, $excerpt, $content, $image_url, $category, $status, $id]);
            }

            sendResponse('success', 'Blog updated successfully');
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) {
                sendResponse('error', 'ID is required', null, 400);
            }

            $stmt = $db->prepare("DELETE FROM blogs WHERE id = ?");
            $stmt->execute([$id]);

            sendResponse('success', 'Blog deleted successfully');
            break;

        default:
            sendResponse('error', 'Method not allowed', null, 405);
            break;
    }
} catch (Exception $e) {
    sendResponse('error', 'Server error: ' . $e->getMessage(), null, 500);
}
?>
