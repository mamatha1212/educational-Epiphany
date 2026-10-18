<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../includes/auth.php';

$currentPage = basename($_SERVER['PHP_SELF']);
if ($currentPage === 'schools_master.php' || $currentPage === 'schools.php') {
    requireSchoolMasterAccess();
} elseif ($currentPage === 'question_papers.php') {
    requireQuestionPapersAccess();
} else {
    requireAdmin();
}

$user = currentUser();

// Calculate counts for badges
$badgePDO = getDB();
$totalPapersBadge = (int) $badgePDO->query("SELECT COUNT(*) FROM question_papers WHERE is_published = 1")->fetchColumn();
$totalStaffBadge = (int) $badgePDO->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
$totalSchoolsBadge = (int) $badgePDO->query("SELECT COUNT(*) FROM school_registrations_2027")->fetchColumn();
$rawMasterCount = (int) $badgePDO->query("SELECT COUNT(*) FROM schools_master")->fetchColumn();
$totalMasterBadge = $rawMasterCount > 999 ? round($rawMasterCount / 1000, 1) . 'k' : $rawMasterCount;

require_once __DIR__ . '/../../includes/activity_logger.php';
$unreadActivitiesCount = isAdmin() ? getUnreadActivityCount() : 0;
$recentActivitiesForDropdown = isAdmin() ? getRecentCoordinatorActivities(6) : [];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? (isAdmin() ? 'Admin Console - EEMT 2027' : 'Coordinator Portal - EEMT 2027')); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/app.css">
    <style>
        :root {
            --sidebar-width: 270px;
            --sidebar-bg: #0f172a;
            --sidebar-border: #1e293b;
            --sidebar-hover: #1e293b;
            --sidebar-active: #31328f;
            --topbar-height: 64px;
        }

        body {
            background-color: #f1f5f9;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        /* Full App Shell */
        .admin-shell {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }

        /* Left Side Panel (Sidebar) */
        .admin-sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            color: #e2e8f0;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 1000;
            border-right: 1px solid var(--sidebar-border);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
        }

        /* Sidebar Brand */
        .sidebar-brand {
            padding: 1.25rem 1.25rem 1rem;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            text-decoration: none;
            color: white;
        }

        .sidebar-logo-frame {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: #ffffff;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
            flex-shrink: 0;
        }

        .sidebar-logo {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .sidebar-brand-text h1 {
            font-size: 1.1rem;
            font-weight: 800;
            letter-spacing: -0.01em;
            color: #ffffff;
            line-height: 1.2;
            margin: 0;
        }

        .sidebar-brand-text span {
            font-size: 0.7rem;
            color: #60a5fa;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            display: block;
        }

        /* Admin User Snippet */
        .sidebar-user {
            padding: 1rem 1.25rem;
            margin: 0.75rem 1rem;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.07);
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            background: linear-gradient(135deg, #31328f, #1d4ed8);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1rem;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.3);
        }

        .user-meta {
            flex: 1;
            min-width: 0;
        }

        .user-name {
            font-size: 0.88rem;
            font-weight: 700;
            color: white;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.2;
        }

        .user-role {
            font-size: 0.72rem;
            color: #10b981;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 2px;
        }

        .status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #10b981;
            display: inline-block;
            box-shadow: 0 0 6px #10b981;
        }

        /* Sidebar Nav */
        .sidebar-nav {
            flex: 1;
            padding: 0.5rem 0.75rem;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .nav-section-title {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
            padding: 0.75rem 0.75rem 0.35rem;
        }

        .sidebar-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.65rem 0.85rem;
            color: #94a3b8;
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.88rem;
            font-weight: 600;
            transition: all 0.15s ease;
        }

        .sidebar-item:hover {
            color: #ffffff;
            background: var(--sidebar-hover);
        }

        .sidebar-item.active {
            color: #ffffff;
            background: var(--sidebar-active);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
        }

        .sidebar-item-content {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-icon {
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        .sidebar-badge {
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
            font-size: 0.72rem;
            padding: 2px 7px;

            font-weight: 700;
        }

        .sidebar-item.active .sidebar-badge {
            background: rgba(255, 255, 255, 0.25);
        }

        /* Sidebar Footer */
        .sidebar-footer {
            padding: 1rem 1rem 1.25rem;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .sidebar-footer a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0.55rem 0.75rem;
            color: #94a3b8;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.15s ease;
        }

        .sidebar-footer a:hover {
            color: white;
            background: rgba(255, 255, 255, 0.05);
        }

        .logout-link:hover {
            color: #f87171 !important;
            background: rgba(239, 68, 68, 0.1) !important;
        }

        /* Right Content Container */
        .admin-main {
            margin-left: var(--sidebar-width);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            min-height: 100vh;
        }

        /* Modern Top Bar */
        .admin-topbar {
            height: var(--topbar-height);
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        /* Notifications Bell & Dropdown Tray */
        .notif-dropdown-wrap {
            position: relative;
        }

        .notif-bell-btn {
            position: relative;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            width: 38px;
            height: 38px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            cursor: pointer;
            transition: all 0.2s ease;
            color: #334155;
        }

        .notif-bell-btn:hover {
            background: #ececf7;
            border-color: #31328f;
            color: #31328f;
            transform: translateY(-1px);
        }

        .notif-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: #ffffff;
            font-size: 0.65rem;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 10px;
            border: 2px solid #ffffff;
            line-height: 1;
            box-shadow: 0 2px 5px rgba(239, 68, 68, 0.3);
        }

        .notif-dropdown-panel {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 370px;
            max-width: 90vw;
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            animation: notifSlide 0.2s ease-out;
        }

        @keyframes notifSlide {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .notif-dropdown-header {
            padding: 12px 16px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .notif-item {
            padding: 10px 14px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            text-decoration: none;
            color: inherit;
            transition: background 0.15s ease;
        }

        .notif-item:hover {
            background: #f8fafc;
        }

        .notif-item.unread {
            background: #fbfbfe;
            border-left: 3px solid #31328f;
        }

        .notif-dropdown-footer {
            padding: 10px 16px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            text-align: center;
        }

        .notif-dropdown-footer a {
            font-size: 0.82rem;
            font-weight: 750;
            color: #31328f;
            text-decoration: none;
        }

        .notif-dropdown-footer a:hover {
            text-decoration: underline;
        }

        .sidebar-toggle-btn {
            display: none;
            background: none;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 6px 10px;
            cursor: pointer;
            color: #334155;
        }

        .topbar-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            letter-spacing: -0.01em;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Main Content Viewport */
        .admin-content {
            flex: 1;
            padding: 2rem;
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
        }

        /* Responsive Breakpoints */
        @media (max-width: 992px) {
            .admin-sidebar {
                transform: translateX(-100%);
            }

            .admin-sidebar.sidebar-open {
                transform: translateX(0);
                box-shadow: 0 0 40px rgba(0, 0, 0, 0.5);
            }

            .admin-main {
                margin-left: 0;
            }

            .sidebar-toggle-btn {
                display: flex;
            }

            .admin-topbar {
                padding: 0 1rem;
            }

            .admin-content {
                padding: 1.25rem 1rem;
            }
        }

        .sidebar-header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-right: 0.75rem;
        }

        .sidebar-header-row .sidebar-brand {
            border-bottom: none;
            flex: 1;
        }

        .sidebar-close-btn {
            display: none;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #e2e8f0;
            width: 34px;
            height: 34px;
            border-radius: 8px;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s ease;
            flex-shrink: 0;
        }

        .sidebar-close-btn:hover {
            background: rgba(239, 68, 68, 0.2);
            border-color: rgba(239, 68, 68, 0.4);
            color: #fca5a5;
        }

        @media (max-width: 992px) {
            .sidebar-close-btn {
                display: inline-flex;
            }
        }
    </style>
</head>

<body>

    <div class="admin-shell">
        <!-- LEFT SIDEBAR PANEL -->
        <aside class="admin-sidebar" id="adminSidebar">
            <?php if (isAdmin()): ?>
                <!-- ADMIN BRAND & PROFILE -->
                <div class="sidebar-header-row">
                    <a href="dashboard.php" class="sidebar-brand">
                        <div class="sidebar-logo-frame">
                            <img src="../assets/images/logo.png" alt="EEMT Logo" class="sidebar-logo"
                                onerror="this.src='../assets/images/logo.jpeg';">
                        </div>
                        <div class="sidebar-brand-text">
                            <h1>EEMT 2027</h1>
                            <span>Admin Console</span>
                        </div>
                    </a>
                    <button type="button" class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close menu">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="sidebar-user">
                    <div class="user-avatar" style="background: linear-gradient(135deg, #31328f, #1d4ed8);">
                        🛡️
                    </div>
                    <div class="user-meta">
                        <div class="user-name">
                            <?php echo htmlspecialchars($user['name'] ?? ($user['username'] ?? 'Admin')); ?>
                        </div>
                        <div class="user-role" style="color: #60a5fa;">
                            <span class="status-dot"></span> State Administrator
                        </div>
                    </div>
                </div>

                <!-- Admin Navigation Menu -->
                <nav class="sidebar-nav">
                    <div class="nav-section-title">Core Management</div>

                    <a href="dashboard.php" class="sidebar-item <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                        <div class="sidebar-item-content">
                            <span class="sidebar-icon">📊</span>
                            <span>Registrations Data</span>
                        </div>
                        <span class="sidebar-badge"><?php echo $totalSchoolsBadge; ?></span>
                    </a>

                    <a href="schools_master.php" class="sidebar-item <?php echo ($currentPage === 'schools_master.php' || $currentPage === 'schools.php') ? 'active' : ''; ?>">
                        <div class="sidebar-item-content">
                            <span class="sidebar-icon">🏫</span>
                            <span>Schools, Mandals & Districts</span>
                        </div>
                        <span class="sidebar-badge"><?php echo $totalMasterBadge; ?></span>
                    </a>

                    <a href="coordinators.php" class="sidebar-item <?php echo $currentPage === 'coordinators.php' ? 'active' : ''; ?>">
                        <div class="sidebar-item-content">
                            <span class="sidebar-icon">👥</span>
                            <span>Staff & Coordinators</span>
                        </div>
                        <span class="sidebar-badge"><?php echo $totalStaffBadge; ?></span>
                    </a>

                    <a href="question_papers.php" class="sidebar-item <?php echo $currentPage === 'question_papers.php' ? 'active' : ''; ?>">
                        <div class="sidebar-item-content">
                            <span class="sidebar-icon">📄</span>
                            <span>Question Papers</span>
                        </div>
                        <span class="sidebar-badge"><?php echo $totalPapersBadge; ?></span>
                    </a>

                    <a href="activities.php" class="sidebar-item <?php echo $currentPage === 'activities.php' ? 'active' : ''; ?>">
                        <div class="sidebar-item-content">
                            <span class="sidebar-icon">📋</span>
                            <span>Activity &amp; Audit Logs</span>
                        </div>
                        <?php if ($unreadActivitiesCount > 0): ?>
                            <span class="sidebar-badge" style="background: #ef4444; color: #ffffff; font-weight: 800; border-radius: 10px; padding: 2px 7px;">
                                <?php echo $unreadActivitiesCount; ?>
                            </span>
                        <?php else: ?>
                            <span class="sidebar-badge" style="background: rgba(255,255,255,0.1); color: #94a3b8;">
                                Log
                            </span>
                        <?php endif; ?>
                    </a>

                    <div class="nav-section-title" style="margin-top: 0.5rem;">Shortcuts & Views</div>

                    <a href="../index.php" target="_blank" class="sidebar-item">
                        <div class="sidebar-item-content">
                            <span class="sidebar-icon">🌐</span>
                            <span>Public Website &nearr;</span>
                        </div>
                    </a>
                </nav>
            <?php else: ?>
                <!-- COORDINATOR BRAND & PROFILE -->
                <div class="sidebar-header-row">
                    <a href="../staff/dashboard.php" class="sidebar-brand">
                        <div class="sidebar-logo-frame">
                            <img src="../assets/images/logo.png" alt="EEMT Logo" class="sidebar-logo"
                                onerror="this.src='../assets/images/logo.jpeg';">
                        </div>
                        <div class="sidebar-brand-text">
                            <h1>EEMT 2027</h1>
                            <span>Coordinator Portal</span>
                        </div>
                    </a>
                    <button type="button" class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close menu">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="sidebar-user">
                    <div class="user-avatar" style="background: linear-gradient(135deg, #059669, #047857);">
                        👔
                    </div>
                    <div class="user-meta">
                        <div class="user-name">
                            <?php echo htmlspecialchars($user['name'] ?? ($user['username'] ?? 'Coordinator')); ?>
                        </div>
                        <div class="user-role" style="color: #34d399;">
                            <span class="status-dot"></span> Staff Coordinator
                        </div>
                    </div>
                </div>

                <!-- Coordinator Navigation Menu -->
                <nav class="sidebar-nav">
                    <div class="nav-section-title">Field Operations</div>

                    <a href="../staff/dashboard.php?tab=registrations" class="sidebar-item">
                        <div class="sidebar-item-content">
                            <span class="sidebar-icon">📊</span>
                            <span>Registrations Data</span>
                        </div>
                        <span class="sidebar-badge"><?php echo $totalSchoolsBadge; ?></span>
                    </a>

                    <a href="../staff/dashboard.php?tab=prelims" class="sidebar-item">
                        <div class="sidebar-item-content">
                            <span class="sidebar-icon">📄</span>
                            <span>Prelims QP Portal</span>
                        </div>
                        <span class="sidebar-badge"><?php echo $totalPapersBadge; ?></span>
                    </a>

                    <div class="nav-section-title">Directory Management</div>

                    <a href="schools_master.php" class="sidebar-item active" style="color: #6ee7b7;">
                        <div class="sidebar-item-content">
                            <span class="sidebar-icon">🏛️</span>
                            <span>Schools, Mandals & Districts</span>
                        </div>
                        <span class="sidebar-badge" style="background: rgba(16, 185, 129, 0.25); color: #34d399; font-size: 0.7rem; font-weight: 700;">
                            <?php echo canManageSchools() ? 'Manage' : 'View Only'; ?>
                        </span>
                    </a>

                    <?php if (canManagePapers()): ?>
                        <div class="nav-section-title">Exam Management</div>
                        <a href="question_papers.php" class="sidebar-item" style="color: #c084fc;">
                            <div class="sidebar-item-content">
                                <span class="sidebar-icon">📝</span>
                                <span>Manage Exam Papers</span>
                            </div>
                        </a>
                    <?php endif; ?>

                </nav>
            <?php endif; ?>

            <!-- Sidebar Footer -->
            <div class="sidebar-footer">
                <a href="../logout.php" class="logout-link">
                    <span>🚪</span>
                    <span>Sign Out</span>
                </a>
            </div>
        </aside>

        <!-- RIGHT MAIN CONTENT -->
        <div class="admin-main">
            <!-- Top App Bar -->
            <header class="admin-topbar">
                <div class="topbar-left">
                    <button type="button" class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle Navigation">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <img src="../assets/images/logo.png" alt="EEMT Logo"
                            style="height: 32px; width: 32px; object-fit: contain;"
                            onerror="this.src='../assets/images/logo.jpeg';">
                        <h2 class="topbar-title">
                            <span><?php echo htmlspecialchars($pageTitle ?? (isAdmin() ? 'State Administration' : 'Coordinator Portal')); ?></span>
                        </h2>
                    </div>
                </div>

                <div class="topbar-right" style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 0.8rem; color: #64748b; font-weight: 600;" class="d-none d-md-inline">
                        <?php echo date('D, d M Y'); ?>
                    </span>

                    <?php if (isAdmin()): ?>
                        <!-- Coordinator Activity Notification Bell -->
                        <div class="notif-dropdown-wrap">
                            <button type="button" id="notifBellBtn" class="notif-bell-btn" title="Coordinator Activity Notifications" onclick="toggleNotifTray(event)">
                                🔔
                                <?php if ($unreadActivitiesCount > 0): ?>
                                    <span class="notif-badge" id="notifBadgeCounter"><?php echo $unreadActivitiesCount; ?></span>
                                <?php endif; ?>
                            </button>

                            <div id="notifDropdown" class="notif-dropdown-panel" style="display: none;">
                                <div class="notif-dropdown-header">
                                    <div>
                                        <strong style="font-size: 0.9rem; color: #0f172a;">Coordinator Notifications</strong>
                                        <span style="font-size: 0.72rem; color: #64748b; display: block;">Real-time staff actions &amp; edits</span>
                                    </div>
                                    <?php if ($unreadActivitiesCount > 0): ?>
                                        <button type="button" onclick="markAllNotificationsRead(event)" id="markAllReadBtn" style="background: transparent; border: none; font-size: 0.74rem; font-weight: 700; color: #059669; cursor: pointer;">
                                            ✓ Mark read
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <div class="notif-dropdown-list" style="max-height: 330px; overflow-y: auto;">
                                    <?php if (empty($recentActivitiesForDropdown)): ?>
                                        <div style="padding: 1.5rem; text-align: center; color: #94a3b8; font-size: 0.82rem;">
                                            No recent coordinator actions yet.
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($recentActivitiesForDropdown as $ract): 
                                            $rIsUnread = (int)$ract['is_read'] === 0;
                                        ?>
                                            <a href="activities.php" class="notif-item <?php echo $rIsUnread ? 'unread' : ''; ?>">
                                                <div style="width: 32px; height: 32px; border-radius: 8px; background: #ececf7; color: #31328f; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0;">
                                                    <?php 
                                                        if (str_contains($ract['action_type'], 'REGISTRATION')) echo '✏️';
                                                        elseif (str_contains($ract['action_type'], 'DELETE')) echo '🗑️';
                                                        elseif (str_contains($ract['action_type'], 'SCHOOL')) echo '🏫';
                                                        elseif (str_contains($ract['action_type'], 'MANDAL')) echo '🗺️';
                                                        elseif (str_contains($ract['action_type'], 'AVATAR')) echo '📸';
                                                        elseif (str_contains($ract['action_type'], 'PAPER')) echo '📄';
                                                        else echo '📌';
                                                    ?>
                                                </div>
                                                <div style="flex: 1; min-width: 0;">
                                                    <div style="font-size: 0.82rem; font-weight: 700; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                        <?php echo htmlspecialchars($ract['action_title']); ?>
                                                    </div>
                                                    <div style="font-size: 0.74rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 1px;">
                                                        By <strong><?php echo htmlspecialchars($ract['user_name']); ?></strong><?php echo !empty($ract['district']) ? ' (' . htmlspecialchars($ract['district']) . ')' : ''; ?>
                                                    </div>
                                                    <div style="font-size: 0.68rem; color: #94a3b8; margin-top: 2px;">
                                                        <?php echo activityTimeAgo($ract['created_at']); ?>
                                                    </div>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>

                                <div class="notif-dropdown-footer">
                                    <a href="activities.php">View All Activity Trail &rarr;</a>
                                </div>
                            </div>
                        </div>

                        <a href="export.php" class="btn btn-outline"
                            style="padding: 6px 12px; font-size: 0.8rem; border-radius: 6px;">
                            📥 Export CSV
                        </a>
                    <?php endif; ?>

                    <a href="../logout.php" class="btn"
                        style="background: #fef2f1; color: #ef3b2d; border: 1px solid #fed7d5; padding: 6px 12px; font-size: 0.8rem; border-radius: 6px;">
                        Logout
                    </a>
                </div>

                <script>
                function toggleNotifTray(e) {
                    if (e) e.stopPropagation();
                    const tray = document.getElementById('notifDropdown');
                    if (tray) {
                        tray.style.display = tray.style.display === 'none' ? 'flex' : 'none';
                    }
                }

                function markAllNotificationsRead(e) {
                    if (e) e.stopPropagation();
                    fetch('api_activities.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=mark_all_read'
                    }).then(r => r.json()).then(data => {
                        if (data.success) {
                            const badge = document.getElementById('notifBadgeCounter');
                            if (badge) badge.remove();
                            const btn = document.getElementById('markAllReadBtn');
                            if (btn) btn.remove();
                            document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
                        }
                    }).catch(err => console.error(err));
                }

                document.addEventListener('click', (e) => {
                    const wrap = document.querySelector('.notif-dropdown-wrap');
                    const tray = document.getElementById('notifDropdown');
                    if (tray && wrap && !wrap.contains(e.target)) {
                        tray.style.display = 'none';
                    }
                });
                </script>
            </header>

            <!-- Main Content Wrapper -->
            <div class="admin-content">