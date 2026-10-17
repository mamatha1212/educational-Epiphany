<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../includes/auth.php';

$currentPage = basename($_SERVER['PHP_SELF']);
if ($currentPage === 'schools_master.php') {
    requireSchoolMasterAccess();
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? 'Admin Console - EEMT 2027'); ?></title>
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
            --sidebar-active: #2563eb;
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
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
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
            <!-- Brand with Official Logo -->
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
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

            <!-- User Info Snippet -->
            <div class="sidebar-user">
                <div class="user-avatar" style="background: <?php echo isAdmin() ? 'linear-gradient(135deg, #2563eb, #1d4ed8)' : 'linear-gradient(135deg, #059669, #047857)'; ?>;">
                    <?php echo isAdmin() ? '🛡️' : '👔'; ?>
                </div>
                <div class="user-meta">
                    <div class="user-name"><?php echo htmlspecialchars($user['name'] ?? ($user['username'] ?? 'User')); ?></div>
                    <div class="user-role" style="color: <?php echo isAdmin() ? '#60a5fa' : '#34d399'; ?>;">
                        <span class="status-dot"></span> 
                        <?php echo isAdmin() ? 'State Administrator' : 'Staff Coordinator'; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar Navigation Menu -->
            <nav class="sidebar-nav">
                <div class="nav-section-title">Core Management</div>

                <?php if (isAdmin()): ?>
                    <a href="dashboard.php"
                        class="sidebar-item <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                        <div class="sidebar-item-content">
                            <span class="sidebar-icon">📊</span>
                            <span>State Dashboard</span>
                        </div>
                        <span class="sidebar-badge"><?php echo $totalSchoolsBadge; ?></span>
                    </a>
                <?php endif; ?>

                <a href="schools_master.php"
                    class="sidebar-item <?php echo $currentPage === 'schools_master.php' ? 'active' : ''; ?>">
                    <div class="sidebar-item-content">
                        <span class="sidebar-icon">🏫</span>
                        <span>Schools & Directory</span>
                    </div>
                    <span class="sidebar-badge"><?php echo $totalMasterBadge; ?></span>
                </a>

                <?php if (isAdmin()): ?>
                    <a href="coordinators.php"
                        class="sidebar-item <?php echo $currentPage === 'coordinators.php' ? 'active' : ''; ?>">
                        <div class="sidebar-item-content">
                            <span class="sidebar-icon">👥</span>
                            <span>Staff & Coordinators</span>
                        </div>
                        <span class="sidebar-badge"><?php echo $totalStaffBadge; ?></span>
                    </a>

                    <a href="question_papers.php"
                        class="sidebar-item <?php echo $currentPage === 'question_papers.php' ? 'active' : ''; ?>">
                        <div class="sidebar-item-content">
                            <span class="sidebar-icon">📄</span>
                            <span>Question Papers</span>
                        </div>
                        <span class="sidebar-badge"><?php echo $totalPapersBadge; ?></span>
                    </a>
                <?php endif; ?>

                <div class="nav-section-title" style="margin-top: 0.5rem;">Shortcuts & Views</div>

                <a href="../staff/dashboard.php" class="sidebar-item">
                    <div class="sidebar-item-content">
                        <span class="sidebar-icon">👔</span>
                        <span>Coordinator View</span>
                    </div>
                </a>

                <a href="../index.php" target="_blank" class="sidebar-item">
                    <div class="sidebar-item-content">
                        <span class="sidebar-icon">🌐</span>
                        <span>Public Website &nearr;</span>
                    </div>
                </a>
            </nav>

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
                            <span><?php echo htmlspecialchars($pageTitle ?? 'State Administration'); ?></span>
                        </h2>
                    </div>
                </div>

                <div class="topbar-right">
                    <span style="font-size: 0.8rem; color: #64748b; font-weight: 600;" class="d-none d-md-inline">
                        <?php echo date('D, d M Y'); ?>
                    </span>

                    <a href="export.php" class="btn btn-outline"
                        style="padding: 6px 12px; font-size: 0.8rem; border-radius: 6px;">
                        📥 Export CSV
                    </a>

                    <a href="../logout.php" class="btn"
                        style="background: #fef2f2; color: #ef4444; border: 1px solid #fecaca; padding: 6px 12px; font-size: 0.8rem; border-radius: 6px;">
                        Logout
                    </a>
                </div>
            </header>

            <!-- Main Content Wrapper -->
            <div class="admin-content">