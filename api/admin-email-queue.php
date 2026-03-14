<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/../core/EmailQueue.php';

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

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = Database::getInstance()->getConnection();

    switch ($method) {
        case 'GET':
            // Fetch only PENDING USER-FACING notifications
            // (Hides admin notifications and already processed logs)
            $stmt = $db->query("SELECT * FROM email_queue 
                                WHERE status = 'pending' 
                                AND type NOT LIKE '%_admin'
                                ORDER BY created_at DESC");
            $queue = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($queue as &$item) {
                $item['created_at_iso'] = toUtcIso($item['created_at'] ?? null);
                $item['sent_at_iso'] = toUtcIso($item['sent_at'] ?? null);
            }
            unset($item);
            
            // Get simplified stats
            $countStmt = $db->query("SELECT COUNT(*) FROM email_queue WHERE status = 'pending' AND type NOT LIKE '%_admin'");
            $pendingCount = (int)$countStmt->fetchColumn();
            
            sendResponse('success', 'User email queue fetched', [
                'items' => $queue,
                'stats' => ['pending' => $pendingCount]
            ]);
            break;

        case 'POST':
            // Trigger processing
            $processed = EmailQueue::process(20);
            sendResponse('success', "Processed {$processed} email(s)", [
                'processed_count' => $processed
            ]);
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if ($id) {
                $stmt = $db->prepare("DELETE FROM email_queue WHERE id = ?");
                $stmt->execute([$id]);
                sendResponse('success', 'Queue item deleted');
            } else {
                // Clear old failed logs
                $stmt = $db->query("DELETE FROM email_queue WHERE status = 'failed' AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
                $count = $stmt->rowCount();
                sendResponse('success', "Cleared {$count} old failed entries");
            }
            break;

        default:
            sendResponse('error', 'Method not allowed', null, 405);
            break;
    }
} catch (Exception $e) {
    sendResponse('error', 'Server error: ' . $e->getMessage(), null, 500);
}
?>
