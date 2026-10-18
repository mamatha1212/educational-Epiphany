<?php
/**
 * AJAX API for Admin Activity Notifications
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_logger.php';

header('Content-Type: application/json');

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_all_read') {
        markActivitiesAsRead();
        echo json_encode(['success' => true, 'unread_count' => 0]);
        exit;
    } elseif ($action === 'mark_single_read') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            markActivitiesAsRead([$id]);
        }
        $newCount = getUnreadActivityCount();
        echo json_encode(['success' => true, 'unread_count' => $newCount]);
        exit;
    }
}

$count = getUnreadActivityCount();
$activities = getRecentCoordinatorActivities(8);
echo json_encode(['success' => true, 'unread_count' => $count, 'activities' => $activities]);
