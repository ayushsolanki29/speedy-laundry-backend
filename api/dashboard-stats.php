<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse('error', 'Method not allowed', null, 405);
}

$range = $_GET['range'] ?? 'today';
$from = isset($_GET['from']) ? date('Y-m-d', strtotime($_GET['from'])) : null;
$to = isset($_GET['to']) ? date('Y-m-d', strtotime($_GET['to'])) : null;

$dateFilter = '';
$visitorFilter = '';
$enquiryFilter = '';

if ($range === 'custom' && $from && $to) {
    $dateFilter = "visit_date BETWEEN ? AND ?";
    $visitorParams = [$from, $to];
    $enquiryFilter = "DATE(created_at) BETWEEN ? AND ?";
    $enquiryParams = [$from, $to];
} else {
    switch ($range) {
        case 'today':
            $dateFilter = "visit_date = CURDATE()";
            $visitorParams = [];
            $enquiryFilter = "DATE(created_at) = CURDATE()";
            $enquiryParams = [];
            break;
        case 'week':
            $dateFilter = "visit_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            $visitorParams = [];
            $enquiryFilter = "created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            $enquiryParams = [];
            break;
        case 'month':
            $dateFilter = "visit_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            $visitorParams = [];
            $enquiryFilter = "created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            $enquiryParams = [];
            break;
        default:
            $dateFilter = "visit_date = CURDATE()";
            $visitorParams = [];
            $enquiryFilter = "DATE(created_at) = CURDATE()";
            $enquiryParams = [];
    }
}

try {
    $db = Database::getInstance()->getConnection();

    // Enquiries count (contact - exclude business) - filtered by date
    $sql = "SELECT COUNT(*) FROM enquiries WHERE service != 'business-quote' AND ($enquiryFilter)";
    $stmt = $db->prepare($sql);
    $stmt->execute($enquiryParams);
    $enquiryCount = (int) $stmt->fetchColumn();

    // New enquiries (unread) in range
    $sqlNew = "SELECT COUNT(*) FROM enquiries WHERE status = 'new' AND service != 'business-quote' AND ($enquiryFilter)";
    $stmtNew = $db->prepare($sqlNew);
    $stmtNew->execute($enquiryParams);
    $newEnquiryCount = (int) $stmtNew->fetchColumn();

    // Business leads count - filtered by date
    $sqlLeads = "SELECT COUNT(*) FROM enquiries WHERE service = 'business-quote' AND ($enquiryFilter)";
    $stmtLeads = $db->prepare($sqlLeads);
    $stmtLeads->execute($enquiryParams);
    $businessCount = (int) $stmtLeads->fetchColumn();

    // Visitors - filtered by date range
    $sqlVisitors = "SELECT visit_date, COUNT(*) as count FROM visits WHERE $dateFilter GROUP BY visit_date ORDER BY visit_date ASC";
    if (!empty($visitorParams)) {
        $stmtVisitors = $db->prepare($sqlVisitors);
        $stmtVisitors->execute($visitorParams);
    } else {
        $stmtVisitors = $db->query($sqlVisitors);
    }
    $visitorHistory = $stmtVisitors->fetchAll(PDO::FETCH_ASSOC);

    $totalVisits = 0;
    foreach ($visitorHistory as $v) {
        $totalVisits += (int) $v['count'];
    }

    // Blogs - not date filtered (static count, recent list)
    $blogCount = (int) $db->query("SELECT COUNT(*) FROM blogs WHERE status = 'published'")->fetchColumn();
    $recentBlogs = $db->query("SELECT id, title, slug, created_at FROM blogs WHERE status = 'published' ORDER BY created_at DESC LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);

    sendResponse('success', 'Dashboard stats', [
        'enquiries' => ['total' => $enquiryCount, 'new' => $newEnquiryCount],
        'leads' => ['total' => $businessCount],
        'visitors' => ['total' => $totalVisits, 'history' => $visitorHistory],
        'blogs' => ['count' => $blogCount, 'recent' => $recentBlogs],
        'range' => $range,
        'from' => $from,
        'to' => $to
    ]);
} catch (Exception $e) {
    sendResponse('error', 'Server error: ' . $e->getMessage(), null, 500);
}
