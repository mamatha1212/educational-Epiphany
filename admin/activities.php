<?php
/**
 * EEMT 2027 - Coordinator & Staff Activity Logs & Audit Trail (Admin Console)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/activity_logger.php';

$pdo = getDB();
initActivityLogsTable();

// Handle Mark All Read action
if (isset($_POST['mark_all_read'])) {
    markActivitiesAsRead();
    header("Location: activities.php?msg=marked_read");
    exit;
}

// Handle Mark Single Read action via AJAX or GET
if (isset($_GET['mark_read_id'])) {
    $readId = (int)$_GET['mark_read_id'];
    if ($readId > 0) {
        markActivitiesAsRead([$readId]);
    }
    header("Location: activities.php");
    exit;
}

// Handle Clear Old Logs (Older than 90 days)
if (isset($_POST['clear_old_logs'])) {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $pdo->exec("DELETE FROM coordinator_activity_logs WHERE created_at < datetime('now', '-90 days')");
    } else {
        $pdo->exec("DELETE FROM coordinator_activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    }
    header("Location: activities.php?msg=cleaned");
    exit;
}

// Filters
$typeFilter = trim($_GET['type'] ?? '');
$userFilter = trim($_GET['user'] ?? '');
$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? ''); // 'unread' or ''

$where = [];
$params = [];

if (!empty($typeFilter)) {
    $where[] = "action_type = :type";
    $params[':type'] = $typeFilter;
}

if (!empty($userFilter)) {
    $where[] = "user_name = :user";
    $params[':user'] = $userFilter;
}

if (!empty($statusFilter) && $statusFilter === 'unread') {
    $where[] = "is_read = 0";
}

if (!empty($search)) {
    $where[] = "(action_title LIKE :q OR details LIKE :q OR user_name LIKE :q OR district LIKE :q)";
    $params[':q'] = "%$search%";
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Count filtered
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM coordinator_activity_logs {$whereSql}");
$countStmt->execute($params);
$totalFiltered = (int)$countStmt->fetchColumn();

// Pagination
$perPage = 25;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$totalPages = max(1, (int)ceil($totalFiltered / $perPage));
if ($currentPage > $totalPages) $currentPage = $totalPages;
$offset = ($currentPage - 1) * $perPage;

// Fetch activities
$sql = "SELECT * FROM coordinator_activity_logs {$whereSql} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch distinct coordinators who performed actions
$allCoordinators = $pdo->query("SELECT DISTINCT user_name FROM coordinator_activity_logs WHERE user_name != '' ORDER BY user_name ASC")->fetchAll(PDO::FETCH_COLUMN);

// Fetch distinct action types
$allActionTypes = $pdo->query("SELECT DISTINCT action_type FROM coordinator_activity_logs WHERE action_type != '' ORDER BY action_type ASC")->fetchAll(PDO::FETCH_COLUMN);

// Unread count
$unreadCount = getUnreadActivityCount();

$pageTitle = "Coordinator Activity & Audit Logs - EEMT 2027";
require_once __DIR__ . '/includes/admin_header.php';
?>

<div style="max-width: 1300px; margin: 0 auto; padding-bottom: 3rem;">

    <!-- Breadcrumb & Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: #64748b; margin-bottom: 4px;">
                <a href="dashboard.php" style="color: #31328f; text-decoration: none; font-weight: 600;">Admin Console</a>
                <span>&rsaquo;</span>
                <span style="color: #0f172a; font-weight: 700;">Activity Logs &amp; Notifications</span>
            </div>
            <h1 style="font-size: 1.6rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 10px;">
                <span>📋 Coordinator Activity Trail</span>
                <?php if ($unreadCount > 0): ?>
                    <span style="font-size: 0.78rem; background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; padding: 2px 9px; border-radius: 12px; font-weight: 800;">
                        <?php echo $unreadCount; ?> Unread
                    </span>
                <?php endif; ?>
            </h1>
            <p style="font-size: 0.88rem; color: #64748b; margin: 4px 0 0;">
                Live audit trail of all actions performed by field staff and coordinators across Andhra Pradesh.
            </p>
        </div>

        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <?php if ($unreadCount > 0): ?>
                <form method="POST" action="activities.php" style="margin: 0;">
                    <button type="submit" name="mark_all_read" value="1" class="btn"
                        style="padding: 8px 16px; font-size: 0.85rem; font-weight: 700; background: #ecfdf5; color: #059669; border: 1.5px solid #a7f3d0; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                        <span>✓</span>
                        <span>Mark All as Read</span>
                    </button>
                </form>
            <?php endif; ?>

            <a href="dashboard.php" class="btn"
                style="padding: 8px 16px; font-size: 0.85rem; font-weight: 600; background: #ffffff; color: #475569; border: 1px solid #cbd5e1; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                <span>&larr; Back to Dashboard</span>
            </a>
        </div>
    </div>

    <!-- Filter Toolbar -->
    <div style="background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-sm);">
        <form method="GET" action="activities.php" style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;">
            
            <!-- Filter by Status -->
            <div style="flex: 1 1 140px; min-width: 130px;">
                <label style="font-size: 0.78rem; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Status</label>
                <select name="status" class="form-select" style="height: 40px; font-size: 0.88rem; width: 100%;" onchange="this.form.submit()">
                    <option value="">All Activities</option>
                    <option value="unread" <?php echo $statusFilter === 'unread' ? 'selected' : ''; ?>>Unread Only (<?php echo $unreadCount; ?>)</option>
                </select>
            </div>

            <!-- Filter by Coordinator -->
            <div style="flex: 1 1 180px; min-width: 160px;">
                <label style="font-size: 0.78rem; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Coordinator</label>
                <select name="user" class="form-select" style="height: 40px; font-size: 0.88rem; width: 100%;" onchange="this.form.submit()">
                    <option value="">All Staff &amp; Coordinators</option>
                    <?php foreach ($allCoordinators as $u): ?>
                        <option value="<?php echo htmlspecialchars($u); ?>" <?php echo $userFilter === $u ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filter by Action Type -->
            <div style="flex: 1 1 180px; min-width: 160px;">
                <label style="font-size: 0.78rem; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Action Type</label>
                <select name="type" class="form-select" style="height: 40px; font-size: 0.88rem; width: 100%;" onchange="this.form.submit()">
                    <option value="">All Actions</option>
                    <option value="REGISTRATION_EDIT" <?php echo $typeFilter === 'REGISTRATION_EDIT' ? 'selected' : ''; ?>>Registration Edits</option>
                    <option value="REGISTRATION_DELETE" <?php echo $typeFilter === 'REGISTRATION_DELETE' ? 'selected' : ''; ?>>Registration Deletes</option>
                    <option value="SCHOOL_ADD" <?php echo $typeFilter === 'SCHOOL_ADD' ? 'selected' : ''; ?>>School Added</option>
                    <option value="SCHOOL_EDIT" <?php echo $typeFilter === 'SCHOOL_EDIT' ? 'selected' : ''; ?>>School Renamed / Edited</option>
                    <option value="MANDAL_ADD" <?php echo $typeFilter === 'MANDAL_ADD' ? 'selected' : ''; ?>>Mandal Added</option>
                    <option value="MANDAL_RENAME" <?php echo $typeFilter === 'MANDAL_RENAME' ? 'selected' : ''; ?>>Mandal Renamed</option>
                    <option value="PAPER_UPLOAD" <?php echo $typeFilter === 'PAPER_UPLOAD' ? 'selected' : ''; ?>>Question Paper Uploads</option>
                    <option value="PROFILE_AVATAR" <?php echo $typeFilter === 'PROFILE_AVATAR' ? 'selected' : ''; ?>>Profile Picture Uploads</option>
                    <option value="PROFILE_UPDATE" <?php echo $typeFilter === 'PROFILE_UPDATE' ? 'selected' : ''; ?>>Profile Updates</option>
                </select>
            </div>

            <!-- Search Keyword -->
            <div style="flex: 2 1 220px; min-width: 180px;">
                <label style="font-size: 0.78rem; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Search Details</label>
                <input type="text" name="q" class="form-input" style="height: 40px; font-size: 0.88rem; width: 100%;"
                    placeholder="Search school name, coordinator, mandal..." value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <div style="display: flex; gap: 8px; align-items: flex-end; flex-shrink: 0;">
                <button type="submit" class="btn btn-primary"
                    style="height: 40px; padding: 0 1.25rem; background: #31328f; border: none; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                    🔍 Filter
                </button>
                <?php if (!empty($typeFilter) || !empty($userFilter) || !empty($search) || !empty($statusFilter)): ?>
                    <a href="activities.php" class="btn"
                        style="height: 40px; padding: 0 1rem; background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; font-weight: 600; display: inline-flex; align-items: center;">
                        Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Activity Stream Card -->
    <div style="background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: var(--shadow-sm);">
        <div style="padding: 1.15rem 1.5rem; background: #f8fafc; border-bottom: 1.5px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div style="font-weight: 800; font-size: 0.95rem; color: #0f172a;">
                Activity Log Records (<?php echo number_format($totalFiltered); ?> Total)
            </div>
            <div style="font-size: 0.8rem; color: #64748b;">
                Showing page <?php echo $currentPage; ?> of <?php echo $totalPages; ?>
            </div>
        </div>

        <?php if (empty($activities)): ?>
            <div style="padding: 4rem 2rem; text-align: center; color: #64748b;">
                <div style="font-size: 3rem; margin-bottom: 0.5rem;">🎉</div>
                <h3 style="font-size: 1.2rem; font-weight: 800; color: #1e293b; margin: 0 0 0.25rem;">No Activities Found</h3>
                <p style="font-size: 0.88rem; margin: 0;">
                    <?php if (!empty($whereSql)): ?>
                        No activity records matched your filter criteria. Try clearing filters.
                    <?php else: ?>
                        No coordinator activities have been logged yet. Any future actions will automatically appear here.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column;">
                <?php foreach ($activities as $act): 
                    $isUnread = (int)$act['is_read'] === 0;
                    $badgeBg = '#ececf7';
                    $badgeColor = '#31328f';
                    $icon = '📌';

                    if (str_contains($act['action_type'], 'REGISTRATION_EDIT')) {
                        $badgeBg = '#fef3c7';
                        $badgeColor = '#92400e';
                        $icon = '✏️';
                    } elseif (str_contains($act['action_type'], 'DELETE')) {
                        $badgeBg = '#fee2e2';
                        $badgeColor = '#b91c1c';
                        $icon = '🗑️';
                    } elseif (str_contains($act['action_type'], 'SCHOOL_ADD') || str_contains($act['action_type'], 'MANDAL_ADD')) {
                        $badgeBg = '#ecfdf5';
                        $badgeColor = '#047857';
                        $icon = '➕';
                    } elseif (str_contains($act['action_type'], 'SCHOOL_EDIT') || str_contains($act['action_type'], 'MANDAL_RENAME')) {
                        $badgeBg = '#e0f2fe';
                        $badgeColor = '#0369a1';
                        $icon = '🏫';
                    } elseif (str_contains($act['action_type'], 'AVATAR')) {
                        $badgeBg = '#fdf2f8';
                        $badgeColor = '#be185d';
                        $icon = '📸';
                    } elseif (str_contains($act['action_type'], 'PAPER')) {
                        $badgeBg = '#f5f3ff';
                        $badgeColor = '#6d28d9';
                        $icon = '📄';
                    }
                ?>
                    <div style="padding: 1.15rem 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; transition: background 0.15s ease; <?php echo $isUnread ? 'background: #fdfefe; border-left: 4px solid #31328f;' : ''; ?>">
                        <div style="display: flex; align-items: flex-start; gap: 12px; flex: 1;">
                            <div style="width: 40px; height: 40px; border-radius: 10px; background: <?php echo $badgeBg; ?>; color: <?php echo $badgeColor; ?>; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; box-shadow: 0 2px 6px rgba(0,0,0,0.04);">
                                <?php echo $icon; ?>
                            </div>

                            <div style="flex: 1; min-width: 0;">
                                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 3px;">
                                    <strong style="color: #0f172a; font-size: 0.94rem;">
                                        <?php echo htmlspecialchars($act['user_name']); ?>
                                    </strong>
                                    <span style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; padding: 2px 7px; border-radius: 6px; background: #f1f5f9; color: #475569;">
                                        <?php echo htmlspecialchars($act['user_role'] ?: 'Coordinator'); ?>
                                    </span>
                                    <?php if (!empty($act['district'])): ?>
                                        <span style="font-size: 0.74rem; color: #0284c7; background: #f0f9ff; border: 1px solid #bae6fd; padding: 2px 7px; border-radius: 6px; font-weight: 600;">
                                            📍 <?php echo htmlspecialchars($act['district']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($isUnread): ?>
                                        <span style="font-size: 0.68rem; font-weight: 800; background: #ef4444; color: #ffffff; padding: 1px 6px; border-radius: 10px;">
                                            NEW
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div style="font-size: 0.92rem; font-weight: 750; color: #1e293b; margin-bottom: 4px;">
                                    <?php echo htmlspecialchars($act['action_title']); ?>
                                </div>

                                <?php if (!empty($act['details'])): ?>
                                    <div style="font-size: 0.84rem; color: #475569; background: #f8fafc; border: 1px solid #e2e8f0; padding: 6px 12px; border-radius: 7px; margin-top: 6px; line-height: 1.45; word-break: break-word;">
                                        <?php echo htmlspecialchars($act['details']); ?>
                                    </div>
                                <?php endif; ?>

                                <div style="display: flex; align-items: center; gap: 12px; margin-top: 6px; font-size: 0.74rem; color: #94a3b8;">
                                    <span>🕒 <?php echo activityTimeAgo($act['created_at']); ?> (<?php echo date('d M Y, h:i:s A', strtotime($act['created_at'])); ?>)</span>
                                    <?php if (!empty($act['ip_address'])): ?>
                                        <span>🌐 IP: <?php echo htmlspecialchars($act['ip_address']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 6px; flex-shrink: 0;">
                            <span style="font-size: 0.72rem; font-weight: 750; background: <?php echo $badgeBg; ?>; color: <?php echo $badgeColor; ?>; padding: 4px 9px; border-radius: 6px; font-family: monospace;">
                                <?php echo htmlspecialchars($act['action_type']); ?>
                            </span>
                            <?php if ($isUnread): ?>
                                <a href="activities.php?mark_read_id=<?php echo $act['id']; ?>" style="font-size: 0.74rem; color: #059669; text-decoration: none; font-weight: 700; background: #ecfdf5; border: 1px solid #a7f3d0; padding: 2px 7px; border-radius: 5px;">
                                    ✓ Mark Read
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination Bar -->
            <?php if ($totalPages > 1): ?>
                <div style="padding: 1.25rem 1.5rem; background: #ffffff; border-top: 1.5px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <span style="font-size: 0.85rem; color: #64748b;">
                        Showing <?php echo $offset + 1; ?> &ndash; <?php echo min($offset + $perPage, $totalFiltered); ?> of <?php echo $totalFiltered; ?> records
                    </span>

                    <div style="display: flex; gap: 4px; align-items: center;">
                        <?php if ($currentPage > 1): ?>
                            <a href="activities.php?page=<?php echo $currentPage - 1; ?>&type=<?php echo urlencode($typeFilter); ?>&user=<?php echo urlencode($userFilter); ?>&status=<?php echo urlencode($statusFilter); ?>&q=<?php echo urlencode($search); ?>" class="btn" style="padding: 6px 12px; font-size: 0.82rem; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; border-radius: 6px;">
                                &larr; Prev
                            </a>
                        <?php endif; ?>

                        <span style="padding: 6px 12px; font-size: 0.82rem; font-weight: 700; color: #31328f; background: #ececf7; border-radius: 6px;">
                            <?php echo $currentPage; ?> / <?php echo $totalPages; ?>
                        </span>

                        <?php if ($currentPage < $totalPages): ?>
                            <a href="activities.php?page=<?php echo $currentPage + 1; ?>&type=<?php echo urlencode($typeFilter); ?>&user=<?php echo urlencode($userFilter); ?>&status=<?php echo urlencode($statusFilter); ?>&q=<?php echo urlencode($search); ?>" class="btn" style="padding: 6px 12px; font-size: 0.82rem; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; border-radius: 6px;">
                                Next &rarr;
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
