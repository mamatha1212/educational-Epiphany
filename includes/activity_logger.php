<?php
/**
 * EEMT 2027 - Coordinator & Staff Activity Logger & Admin Notification Engine
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

function initActivityLogsTable() {
    static $initialized = false;
    if ($initialized) return;

    $pdo = getDB();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    try {
        if ($driver === 'sqlite') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS coordinator_activity_logs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NULL,
                    user_name VARCHAR(150) NOT NULL,
                    user_role VARCHAR(50) NOT NULL DEFAULT 'coordinator',
                    district VARCHAR(100) NULL,
                    action_type VARCHAR(80) NOT NULL,
                    action_title VARCHAR(255) NOT NULL,
                    details TEXT NULL,
                    old_values TEXT NULL,
                    new_values TEXT NULL,
                    ip_address VARCHAR(45) NULL,
                    is_read INTEGER DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );
            ");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_activity_created ON coordinator_activity_logs(created_at DESC);");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_activity_read ON coordinator_activity_logs(is_read);");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS coordinator_activity_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NULL,
                    user_name VARCHAR(150) NOT NULL,
                    user_role VARCHAR(50) NOT NULL DEFAULT 'coordinator',
                    district VARCHAR(100) NULL,
                    action_type VARCHAR(80) NOT NULL,
                    action_title VARCHAR(255) NOT NULL,
                    details TEXT NULL,
                    old_values TEXT NULL,
                    new_values TEXT NULL,
                    ip_address VARCHAR(45) NULL,
                    is_read TINYINT(1) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_activity_created (created_at DESC),
                    INDEX idx_activity_read (is_read)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        }

        // Ensure profile_pic column exists on users table
        if ($driver === 'sqlite') {
            $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('profile_pic', $cols)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN profile_pic TEXT NULL");
            }
        } else {
            $cols = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_pic'")->fetchAll();
            if (empty($cols)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN profile_pic VARCHAR(255) NULL AFTER email");
            }
        }

        $initialized = true;
    } catch (Exception $e) {
        error_log("initActivityLogsTable error: " . $e->getMessage());
    }
}

/**
 * Log a coordinator/staff action for Admin Notification
 */
function logCoordinatorActivity($actionType, $actionTitle, $details = '', $oldValues = null, $newValues = null, $userOverride = null) {
    initActivityLogsTable();
    try {
        $pdo = getDB();
        $currentUser = $userOverride ?? ($_SESSION['staff_user'] ?? ($_SESSION['user'] ?? []));
        
        $userId = !empty($currentUser['id']) ? (int)$currentUser['id'] : null;
        $userName = !empty($currentUser['name']) ? $currentUser['name'] : (!empty($currentUser['username']) ? $currentUser['username'] : 'Staff Coordinator');
        $userRole = !empty($currentUser['role']) ? $currentUser['role'] : 'coordinator';
        $district = !empty($currentUser['assigned_district']) ? $currentUser['assigned_district'] : (!empty($_SESSION['staff_district']) ? $_SESSION['staff_district'] : null);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $oldJson = is_array($oldValues) ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : (is_string($oldValues) ? $oldValues : null);
        $newJson = is_array($newValues) ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : (is_string($newValues) ? $newValues : null);

        $stmt = $pdo->prepare("
            INSERT INTO coordinator_activity_logs (user_id, user_name, user_role, district, action_type, action_title, details, old_values, new_values, ip_address, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            $userId,
            $userName,
            $userRole,
            $district,
            $actionType,
            $actionTitle,
            $details,
            $oldJson,
            $newJson,
            $ip
        ]);
        return true;
    } catch (Exception $e) {
        error_log("Failed to log coordinator activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Fetch recent activities for Admin
 */
function getRecentCoordinatorActivities($limit = 50, $unreadOnly = false) {
    initActivityLogsTable();
    try {
        $pdo = getDB();
        $sql = "SELECT * FROM coordinator_activity_logs ";
        if ($unreadOnly) {
            $sql .= "WHERE is_read = 0 ";
        }
        $sql .= "ORDER BY created_at DESC LIMIT " . (int)$limit;
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("getRecentCoordinatorActivities error: " . $e->getMessage());
        return [];
    }
}

/**
 * Count unread activity notifications for Admin
 */
function getUnreadActivityCount() {
    initActivityLogsTable();
    try {
        $pdo = getDB();
        return (int)$pdo->query("SELECT COUNT(*) FROM coordinator_activity_logs WHERE is_read = 0")->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Mark activity notifications as read
 */
function markActivitiesAsRead($ids = null) {
    initActivityLogsTable();
    try {
        $pdo = getDB();
        if ($ids === null) {
            $pdo->exec("UPDATE coordinator_activity_logs SET is_read = 1 WHERE is_read = 0");
        } elseif (is_array($ids) && !empty($ids)) {
            $cleanIds = array_map('intval', $ids);
            if (!empty($cleanIds)) {
                $in = implode(',', $cleanIds);
                $pdo->exec("UPDATE coordinator_activity_logs SET is_read = 1 WHERE id IN ($in)");
            }
        }
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Human-friendly time ago helper
 */
function activityTimeAgo($datetime) {
    $time = strtotime($datetime);
    if (!$time) return 'Just now';
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('d M Y, h:i A', $time);
}
