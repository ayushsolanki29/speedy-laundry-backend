<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$range = $_GET['range'] ?? 'today';
$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;
$date_filter = "";

if ($range === 'custom' && $from && $to) {
    // Basic sanitization for date format
    $from = date('Y-m-d', strtotime($from));
    $to = date('Y-m-d', strtotime($to));
    $date_filter = "visit_date BETWEEN '$from' AND '$to'";
} else {
    switch ($range) {
        case 'today':
            $date_filter = "visit_date = CURDATE()";
            break;
        case 'yesterday':
            $date_filter = "visit_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'week':
            $date_filter = "visit_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $date_filter = "visit_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
        default:
            $date_filter = "visit_date = CURDATE()";
    }
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Total visits in range
    $total_visits = $db->query("SELECT COUNT(*) FROM visits WHERE $date_filter")->fetchColumn();
    
    // Visits today (always for quick reference)
    $today = date('Y-m-d');
    $visits_today = $db->query("SELECT COUNT(*) FROM visits WHERE visit_date = '$today'")->fetchColumn();
    
    // History for the selected range
    $stats_history = $db->query("
        SELECT visit_date, COUNT(*) as count 
        FROM visits 
        WHERE $date_filter
        GROUP BY visit_date 
        ORDER BY visit_date ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent logs in range
    $recent_logs = $db->query("
        SELECT * FROM visits 
        WHERE $date_filter
        ORDER BY created_at DESC 
        LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);

    sendResponse('success', 'Footfall stats fetched', [
        'total_visits' => (int)$total_visits,
        'visits_today' => (int)$visits_today,
        'history' => $stats_history,
        'recent_logs' => $recent_logs,
        'range' => $range
    ]);
    
} catch (Exception $e) {
    sendResponse('error', 'Server error: ' . $e->getMessage(), null, 500);
}
?>
