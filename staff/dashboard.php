<?php
/**
 * EEMT 2027 - Staff & Coordinator Dashboard
 * Modern Left-Sidebar Workspace matching Admin & School Dashboards
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireStaffLogin();

$user = currentUser();
$pdo = getDB();

$assignedDistrict = trim($user['assigned_district'] ?? '');
if ($assignedDistrict === 'ALL' || empty($assignedDistrict)) {
    $assignedDistrict = '';
}

// Handle Status Updates by Staff
$statusMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $regId = (int)($_POST['reg_id'] ?? 0);
    $newStatus = trim($_POST['status'] ?? 'submitted');
    $validStatuses = ['submitted', 'verified', 'contacted', 'completed'];

    if ($regId > 0 && in_array($newStatus, $validStatuses)) {
        $stmt = $pdo->prepare("UPDATE school_registrations_2027 SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $regId]);
        $statusMessage = "Status updated to " . strtoupper($newStatus) . " successfully.";
    }
}

// Handle filters
$districtFilter = trim($_GET['district'] ?? ($assignedDistrict ?: ''));
$mandalFilter = trim($_GET['mandal'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

// Base Query
$where = [];
$params = [];

if ($districtFilter !== '') {
    $where[] = "sr.district = :district";
    $params[':district'] = $districtFilter;
}

if ($mandalFilter !== '') {
    $where[] = "sr.mandal LIKE :mandal";
    $params[':mandal'] = "%$mandalFilter%";
}

if ($statusFilter !== '') {
    $where[] = "sr.status = :status";
    $params[':status'] = $statusFilter;
}

if ($search !== '') {
    $where[] = "(sr.reference_id LIKE :s OR sr.school_name LIKE :s OR sr.unique_code LIKE :s OR sr.contact_person LIKE :s OR sr.mobile LIKE :s OR sr.email LIKE :s)";
    $params[':s'] = "%$search%";
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Fetch distinct districts
$districtsStmt = $pdo->query("SELECT DISTINCT district FROM schools_master ORDER BY district ASC");
$allDistricts = $districtsStmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch stats for Coordinator scope
$scopeWhere = $assignedDistrict ? "WHERE district = " . $pdo->quote($assignedDistrict) : "";
$totalSchools = (int)$pdo->query("SELECT COUNT(*) FROM school_registrations_2027 {$scopeWhere}")->fetchColumn();
$verifiedCount = (int)$pdo->query("SELECT COUNT(*) FROM school_registrations_2027 " . ($scopeWhere ? "{$scopeWhere} AND (status = 'verified' OR status = 'contacted')" : "WHERE (status = 'verified' OR status = 'contacted')"))->fetchColumn();
$totalMandals = (int)$pdo->query("SELECT COUNT(DISTINCT mandal) FROM school_registrations_2027 {$scopeWhere}")->fetchColumn();

// Fetch published question papers
$papersStmt = $pdo->query("SELECT * FROM question_papers WHERE is_published = 1 ORDER BY id DESC");
$questionPapers = $papersStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch school registrations
$stmt = $pdo->prepare("SELECT sr.* FROM school_registrations_2027 sr {$whereSql} ORDER BY sr.id DESC");
$stmt->execute($params);
$registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
$filteredCount = count($registrations);

$pageTitle = "Coordinator Dashboard - EEMT 2027";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/app.css">
    <style>
        :root {
            --sidebar-width: 270px;
            --sidebar-bg: #0f172a;
            --sidebar-border: #1e293b;
            --sidebar-hover: #1e293b;
            --sidebar-active: #059669;
            --topbar-height: 64px;
        }

        body {
            background-color: #f1f5f9;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            color: #1e293b;
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
        .sidebar-header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-right: 0.75rem;
        }

        .sidebar-brand {
            padding: 1.25rem 1.25rem 1rem;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: white;
            flex: 1;
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
            color: #34d399;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            display: block;
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

        /* User Snippet */
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
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1rem;
            box-shadow: 0 4px 10px rgba(5, 150, 105, 0.3);
            flex-shrink: 0;
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
            color: #34d399;
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
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
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
            flex-shrink: 0;
        }

        .sidebar-badge {
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
            font-size: 0.72rem;
            padding: 2px 7px;
            border-radius: 10px;
            font-weight: 700;
        }

        .sidebar-item.active .sidebar-badge {
            background: rgba(255, 255, 255, 0.25);
        }

        /* Sidebar Footer */
        .sidebar-footer {
            padding: 0.85rem 0.85rem 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .sidebar-scope-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.09);
            border-radius: 8px;
            padding: 8px 10px;
        }

        .scope-card-label {
            font-size: 0.65rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .scope-card-val {
            font-size: 0.85rem;
            font-weight: 800;
            color: #34d399;
            margin: 2px 0 0;
        }

        .logout-link {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            color: #fca5a5;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.15s ease;
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
            gap: 10px;
        }

        /* Main Content Viewport */
        .admin-content {
            flex: 1;
            padding: 2rem;
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
        }

        /* KPI Card Styles */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .kpi-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 1.25rem 1.4rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
            transition: all 0.25s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(0,0,0,0.08);
        }

        .kpi-card.kpi-green { border-top: 4px solid #059669; }
        .kpi-card.kpi-blue { border-top: 4px solid #2563eb; }
        .kpi-card.kpi-amber { border-top: 4px solid #f59e0b; }
        .kpi-card.kpi-purple { border-top: 4px solid #8b5cf6; }

        .kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }

        /* Filter Toolbar */
        .filter-toolbar {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }

        .filter-row {
            display: flex;
            flex-direction: row;
            align-items: flex-end;
            gap: 0.75rem;
            width: 100%;
            flex-wrap: wrap;
        }

        /* Table Card */
        .table-card {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            margin-bottom: 2.5rem;
        }

        .table-card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
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

            .sidebar-close-btn {
                display: inline-flex;
            }

            .admin-topbar {
                padding: 0 1rem;
            }

            .admin-content {
                padding: 1.25rem 1rem;
            }
        }
    </style>
</head>

<body>

<div class="admin-shell">
    
    <!-- LEFT SIDEBAR PANEL -->
    <aside class="admin-sidebar" id="adminSidebar">
        
        <!-- Brand Header with Logo -->
        <div class="sidebar-header-row">
            <a href="dashboard.php" class="sidebar-brand">
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
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- User Profile Card -->
        <div class="sidebar-user">
            <div class="user-avatar">
                👔
            </div>
            <div class="user-meta">
                <div class="user-name"><?php echo htmlspecialchars($user['name'] ?? $user['username']); ?></div>
                <div class="user-role">
                    <span class="status-dot"></span> 
                    <?php echo htmlspecialchars(strtoupper($user['role'] ?? 'COORDINATOR')); ?>
                </div>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="sidebar-nav">
            <div class="nav-section-title">Field Operations</div>

            <a href="dashboard.php" class="sidebar-item active">
                <div class="sidebar-item-content">
                    <div class="sidebar-icon">🏫</div>
                    <span>School Registrations</span>
                </div>
                <span class="sidebar-badge"><?php echo $filteredCount; ?></span>
            </a>

            <a href="#papers-section" class="sidebar-item">
                <div class="sidebar-item-content">
                    <div class="sidebar-icon">📄</div>
                    <span>Prelims QP Portal</span>
                </div>
                <span class="sidebar-badge"><?php echo count($questionPapers); ?></span>
            </a>

            <?php if (canManageSchools()): ?>
                <div class="nav-section-title">Directory Management</div>

                <a href="../admin/schools_master.php" class="sidebar-item" style="color: #6ee7b7;">
                    <div class="sidebar-item-content">
                        <div class="sidebar-icon">🏛️</div>
                        <span>Schools & Districts</span>
                    </div>
                    <span class="sidebar-badge" style="background: rgba(16, 185, 129, 0.25); color: #34d399; font-size: 0.7rem; font-weight: 700;">Permitted</span>
                </a>
            <?php endif; ?>

            <div class="nav-section-title">Data & Outreach</div>

            <a href="../admin/export.php?district=<?php echo urlencode($districtFilter); ?>&mandal=<?php echo urlencode($mandalFilter); ?>&search=<?php echo urlencode($search); ?>" class="sidebar-item">
                <div class="sidebar-item-content">
                    <div class="sidebar-icon">📥</div>
                    <span>Export CSV</span>
                </div>
            </a>

            <a href="../register.php" target="_blank" class="sidebar-item">
                <div class="sidebar-item-content">
                    <div class="sidebar-icon">🔗</div>
                    <span>Public Registration</span>
                </div>
            </a>

            <?php if (isAdmin()): ?>
                <div class="nav-section-title">System Switcher</div>
                <a href="../admin/dashboard.php" class="sidebar-item" style="color: #60a5fa;">
                    <div class="sidebar-item-content">
                        <div class="sidebar-icon">🛡️</div>
                        <span>Admin Console</span>
                    </div>
                </a>
            <?php endif; ?>
        </nav>

        <!-- Sidebar Footer -->
        <div class="sidebar-footer">
            <div class="sidebar-scope-card">
                <div class="scope-card-label">Assigned Scope</div>
                <div class="scope-card-val">
                    <?php echo htmlspecialchars($user['assigned_district'] ?: 'All Districts (Statewide)'); ?>
                </div>
            </div>

            <a href="../logout.php" class="logout-link">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                <span>Logout Session</span>
            </a>
        </div>
    </aside>

    <!-- RIGHT MAIN VIEWPORT -->
    <main class="admin-main">
        
        <!-- Topbar -->
        <header class="admin-topbar">
            <div class="topbar-left">
                <button type="button" class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle sidebar">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div class="topbar-title">
                    <span>Field Operations & School Verification Roster</span>
                </div>
            </div>

            <div class="topbar-right">
                <span class="badge" style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; font-weight: 700; padding: 6px 12px;">
                    Scope: <?php echo htmlspecialchars($user['assigned_district'] ?: 'All AP Districts'); ?>
                </span>
                
                <?php if (canManageSchools()): ?>
                    <a href="../admin/schools_master.php" class="btn" style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; font-size: 0.85rem; padding: 6px 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                        🏛️ Manage Schools & Districts
                    </a>
                <?php endif; ?>

                <a href="#papers-section" class="btn" style="background: #f5f3ff; color: #7c3aed; border: 1px solid #ddd6fe; font-size: 0.85rem; padding: 6px 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                    📄 Prelims QP (<?php echo count($questionPapers); ?>)
                </a>

                <a href="../logout.php" class="btn" style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; font-size: 0.85rem; padding: 6px 12px; font-weight: 700;">
                    Logout
                </a>
            </div>
        </header>

        <!-- Main Content Canvas -->
        <div class="admin-content">
            
            <?php $noticeMsg = trim($_GET['notice'] ?? ''); if ($noticeMsg): ?>
                <div class="alert alert-warning" style="margin-bottom: 1.5rem; background: #fffbeb; border: 1px solid #fde68a; color: #92400e; padding: 12px 16px; border-radius: 8px; display: flex; align-items: center; gap: 10px;">
                    <span>⚠️</span>
                    <span><?php echo htmlspecialchars($noticeMsg); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($statusMessage): ?>
                <div class="alert alert-success" style="margin-bottom: 1.5rem; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 16px; border-radius: 8px;">
                    <span>✅</span>
                    <span><?php echo htmlspecialchars($statusMessage); ?></span>
                </div>
            <?php endif; ?>

            <!-- KPI Metric Cards Grid -->
            <div class="kpi-grid">
                
                <div class="kpi-card kpi-green">
                    <div>
                        <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">Registered Schools</div>
                        <div style="font-size: 2.1rem; font-weight: 800; color: #064e3b; margin: 0.2rem 0;"><?php echo number_format($totalSchools); ?></div>
                        <div style="font-size: 0.78rem; color: #059669; font-weight: 600;">In <?php echo $assignedDistrict ?: 'All Districts'; ?></div>
                    </div>
                    <div class="kpi-icon" style="background: #ecfdf5; color: #059669;">
                        🏫
                    </div>
                </div>

                <div class="kpi-card kpi-blue">
                    <div>
                        <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">Active Mandals</div>
                        <div style="font-size: 2.1rem; font-weight: 800; color: #1e3a8a; margin: 0.2rem 0;"><?php echo number_format($totalMandals); ?></div>
                        <div style="font-size: 0.78rem; color: #2563eb; font-weight: 600;">Coverage Across State</div>
                    </div>
                    <div class="kpi-icon" style="background: #eff6ff; color: #2563eb;">
                        📍
                    </div>
                </div>

                <div class="kpi-card kpi-amber">
                    <div>
                        <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">Verified / Contacted</div>
                        <div style="font-size: 2.1rem; font-weight: 800; color: #92400e; margin: 0.2rem 0;"><?php echo number_format($verifiedCount); ?></div>
                        <div style="font-size: 0.78rem; color: #d97706; font-weight: 600;">Followed-up Schools</div>
                    </div>
                    <div class="kpi-icon" style="background: #fffbeb; color: #d97706;">
                        🤝
                    </div>
                </div>

                <div class="kpi-card kpi-purple">
                    <div>
                        <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">Prelims Papers</div>
                        <div style="font-size: 2.1rem; font-weight: 800; color: #5b21b6; margin: 0.2rem 0;"><?php echo count($questionPapers); ?></div>
                        <div style="font-size: 0.78rem; color: #7c3aed; font-weight: 600;">Published Question Papers</div>
                    </div>
                    <div class="kpi-icon" style="background: #f5f3ff; color: #7c3aed;">
                        📄
                    </div>
                </div>

            </div>

            <!-- Filter & Search Toolbar -->
            <div class="filter-toolbar">
                <form method="GET" action="dashboard.php" class="filter-row">
                    
                    <div class="form-group" style="margin-bottom: 0; flex: 1 1 160px; min-width: 130px;">
                        <label class="form-label" for="district" style="font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem; display: block; white-space: nowrap;">District</label>
                        <select name="district" id="district" class="form-select" style="height: 40px; font-size: 0.88rem; width: 100%;">
                            <option value="">All Districts</option>
                            <?php foreach ($allDistricts as $d): ?>
                                <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $districtFilter === $d ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($d); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0; flex: 1 1 160px; min-width: 130px;">
                        <label class="form-label" for="mandal" style="font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem; display: block; white-space: nowrap;">Mandal</label>
                        <input type="text" name="mandal" id="mandal" class="form-input" style="height: 40px; font-size: 0.88rem; width: 100%;" placeholder="Type Mandal name..." value="<?php echo htmlspecialchars($mandalFilter); ?>">
                    </div>

                    <div class="form-group" style="margin-bottom: 0; flex: 1 1 140px; min-width: 120px;">
                        <label class="form-label" for="status" style="font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem; display: block; white-space: nowrap;">Status</label>
                        <select name="status" id="status" class="form-select" style="height: 40px; font-size: 0.88rem; width: 100%;">
                            <option value="">All Statuses</option>
                            <option value="submitted" <?php echo $statusFilter === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                            <option value="contacted" <?php echo $statusFilter === 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                            <option value="verified" <?php echo $statusFilter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0; flex: 2 1 220px; min-width: 160px;">
                        <label class="form-label" for="search" style="font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem; display: block; white-space: nowrap;">Keyword Search</label>
                        <input type="text" name="search" id="search" class="form-input" style="height: 40px; font-size: 0.88rem; width: 100%;" placeholder="Name, UDISE, Mobile, Code..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div style="display: flex; gap: 8px; align-items: flex-end; flex-shrink: 0;">
                        <button type="submit" class="btn btn-primary" style="height: 40px; padding: 0 1.25rem; background: #059669; border: none; white-space: nowrap; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                            🔍 Filter
                        </button>
                        <a href="dashboard.php" class="btn" style="height: 40px; padding: 0 1rem; background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; white-space: nowrap; font-weight: 600; display: inline-flex; align-items: center;">
                            Reset
                        </a>
                        <a href="../admin/export.php?district=<?php echo urlencode($districtFilter); ?>&mandal=<?php echo urlencode($mandalFilter); ?>&search=<?php echo urlencode($search); ?>" class="btn btn-outline" style="height: 40px; padding: 0 1.15rem; white-space: nowrap; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                            📥 Export CSV
                        </a>
                    </div>
                </form>

                <?php if ($districtFilter !== '' || $mandalFilter !== '' || $statusFilter !== '' || $search !== ''): ?>
                    <div style="margin-top: 1rem; font-size: 0.88rem; color: #64748b;">
                        Found <strong><?php echo $filteredCount; ?></strong> matching registrations. 
                        <a href="dashboard.php" style="color: #059669; text-decoration: underline; margin-left: 0.5rem; font-weight: 600;">Clear All Filters</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Registered Schools Verification Roster Table -->
            <div class="table-card">
                <div class="table-card-header">
                    <div>
                        <h3 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0;">
                            Registered Schools Verification Roster
                        </h3>
                        <span style="font-size: 0.8rem; color: #64748b;">
                            Direct WhatsApp & phone outreach tools for school coordinators
                        </span>
                    </div>
                    <span class="badge" style="background: #ecfdf5; color: #047857; font-weight: 700; border: 1px solid #a7f3d0; padding: 6px 12px; font-size: 0.82rem;">
                        <?php echo $filteredCount; ?> Schools in View
                    </span>
                </div>

                <?php if (empty($registrations)): ?>
                    <div style="padding: 3.5rem 2rem; text-align: center; color: #64748b;">
                        <div style="font-size: 2.8rem; margin-bottom: 0.5rem;">🏫</div>
                        <h4 style="font-weight: 700; color: #334155; margin-bottom: 0.25rem;">No School Registrations Found</h4>
                        <p style="font-size: 0.9rem; margin: 0;">Try adjusting your search query or filters above.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive" style="overflow-x: auto; width: 100%;">
                        <table class="table" style="width: 100%; min-width: 1100px; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f8fafc; border-bottom: 1.5px solid #e2e8f0;">
                                    <th style="width: 160px; min-width: 160px; padding: 14px 16px; font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase;">EEMTSRC (Ref ID)</th>
                                    <th style="width: 320px; min-width: 260px; padding: 14px 16px; font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase;">School Details</th>
                                    <th style="width: 190px; min-width: 170px; padding: 14px 16px; font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase;">Location</th>
                                    <th style="width: 190px; min-width: 170px; padding: 14px 16px; font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase;">In-Charge Person</th>
                                    <th style="width: 210px; min-width: 190px; padding: 14px 16px; font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase;">Outreach / Actions</th>
                                    <th style="width: 160px; min-width: 140px; padding: 14px 16px; font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase;">Status Tracking</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($registrations as $row): 
                                    $rawPhone = preg_replace('/[^0-9]/', '', $row['mobile']);
                                    $cleanPhone = strlen($rawPhone) === 10 ? '91' . $rawPhone : $rawPhone;
                                    $waText = rawurlencode("Respected Headmaster, Greetings from EEMT 2027 Coordinator Team.\nRegarding your registration for " . $row['school_name'] . " (EEMTSRC: " . $row['reference_id'] . "). Please let us know if you have received the Prelims Question Papers.");
                                ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;">
                                        <td style="padding: 14px 16px; vertical-align: middle;">
                                            <span class="badge" style="background: #eff6ff; color: #1d4ed8; font-family: monospace; font-size: 0.9rem; font-weight: 800; border: 1px solid #bfdbfe; white-space: nowrap; padding: 4px 8px;">
                                                <?php echo htmlspecialchars($row['reference_id']); ?>
                                            </span>
                                            <div style="font-size: 0.75rem; color: #64748b; margin-top: 5px; white-space: nowrap;">
                                                Reg: <?php echo date('d M, h:i A', strtotime($row['created_at'])); ?>
                                            </div>
                                        </td>
                                        <td style="padding: 14px 16px; vertical-align: middle;">
                                            <strong style="color: #0f172a; display: block; font-size: 0.94rem; line-height: 1.4;">
                                                <?php echo htmlspecialchars($row['school_name']); ?>
                                            </strong>
                                            <span style="font-size: 0.78rem; color: #64748b;">
                                                UDISE: <code style="color: #0284c7; background: #f0f9ff; padding: 2px 5px; border-radius: 4px;"><?php echo htmlspecialchars($row['unique_code']); ?></code>
                                            </span>
                                        </td>
                                        <td style="padding: 14px 16px; vertical-align: middle;">
                                            <div style="font-weight: 700; color: #334155; font-size: 0.88rem;"><?php echo htmlspecialchars($row['district']); ?></div>
                                            <div style="font-size: 0.8rem; color: #64748b;">Mandal: <?php echo htmlspecialchars($row['mandal']); ?></div>
                                        </td>
                                        <td style="padding: 14px 16px; vertical-align: middle;">
                                            <div style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($row['contact_person']); ?></div>
                                            <span class="badge badge-info" style="font-size: 0.72rem; padding: 2px 6px;"><?php echo htmlspecialchars($row['designation']); ?></span>
                                        </td>
                                        <td style="padding: 14px 16px; vertical-align: middle;">
                                            <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                                                <!-- WhatsApp Direct Outreach -->
                                                <?php if (!empty($cleanPhone)): ?>
                                                    <a href="https://api.whatsapp.com/send?phone=<?php echo $cleanPhone; ?>&text=<?php echo $waText; ?>" target="_blank" rel="noopener noreferrer" class="btn" style="background: #25d366; color: white; padding: 5px 9px; font-size: 0.76rem; border-radius: 6px; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;" title="Send WhatsApp Message">
                                                        💬 WhatsApp
                                                    </a>
                                                    <!-- Direct Phone Call -->
                                                    <a href="tel:<?php echo htmlspecialchars($row['mobile']); ?>" class="btn btn-outline" style="padding: 5px 9px; font-size: 0.76rem; border-radius: 6px; color: #2563eb; display: inline-flex; align-items: center; gap: 4px;" title="Call Headmaster">
                                                        📞 Call
                                                    </a>
                                                <?php else: ?>
                                                    <span style="font-size: 0.75rem; color: #94a3b8;">No phone</span>
                                                <?php endif; ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: #64748b; margin-top: 5px;">
                                                <?php echo htmlspecialchars($row['mobile']); ?>
                                            </div>
                                        </td>
                                        <td style="padding: 14px 16px; vertical-align: middle;">
                                            <form method="POST" action="dashboard.php" style="display: flex; gap: 4px; align-items: center;">
                                                <input type="hidden" name="reg_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="update_status" value="1">
                                                
                                                <select name="status" class="form-select" style="font-size: 0.8rem; padding: 5px 8px; width: 100%; font-weight: 600; height: 34px; border-radius: 6px;" onchange="this.form.submit()">
                                                    <option value="submitted" <?php echo ($row['status'] ?? '') === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                                                    <option value="contacted" <?php echo ($row['status'] ?? '') === 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                                                    <option value="verified" <?php echo ($row['status'] ?? '') === 'verified' ? 'selected' : ''; ?>>Verified</option>
                                                </select>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Published Prelims Question Papers Section -->
            <div id="papers-section" class="table-card" style="border-top: 4px solid #8b5cf6;">
                <div class="table-card-header">
                    <div>
                        <h3 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0 0 0.25rem;">
                            📄 Published Prelims Question Papers Repository
                        </h3>
                        <p style="font-size: 0.82rem; color: #64748b; margin: 0;">
                            Official examination papers repository for coordinators to assist school teachers and check syllabus files
                        </p>
                    </div>
                    <span class="badge" style="background: #f5f3ff; color: #7c3aed; font-weight: 700; border: 1px solid #ddd6fe; padding: 6px 12px; font-size: 0.82rem;">
                        <?php echo count($questionPapers); ?> Available
                    </span>
                </div>

                <div style="padding: 1.5rem;">
                    <?php if (empty($questionPapers)): ?>
                        <div style="padding: 2rem; text-align: center; color: #64748b; font-size: 0.9rem;">
                            No question papers have been published yet by administrators.
                        </div>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
                            <?php foreach ($questionPapers as $paper): ?>
                                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.15rem; display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                                    <div>
                                        <div style="font-weight: 700; color: #0f172a; font-size: 0.95rem;">
                                            <?php echo htmlspecialchars($paper['title']); ?>
                                        </div>
                                        <div style="font-size: 0.78rem; color: #64748b; margin-top: 4px;">
                                            Class: <strong><?php echo htmlspecialchars($paper['class']); ?></strong> &bull; Subject: <?php echo htmlspecialchars($paper['subject'] ?? 'General'); ?>
                                        </div>
                                    </div>
                                    <a href="../school/download_paper.php?id=<?php echo $paper['id']; ?>" class="btn" style="background: #8b5cf6; color: white; padding: 6px 12px; font-size: 0.8rem; border-radius: 6px; text-decoration: none; font-weight: 700; white-space: nowrap;">
                                        ⬇️ Download
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div> <!-- End .admin-content -->
    </main> <!-- End .admin-main -->

</div> <!-- End .admin-shell -->

<script>
// Sidebar Toggle on Mobile
document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('sidebarToggle');
    const closeBtn = document.getElementById('sidebarCloseBtn');
    const sidebar = document.getElementById('adminSidebar');

    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('sidebar-open');
        });
    }

    if (closeBtn && sidebar) {
        closeBtn.addEventListener('click', () => {
            sidebar.classList.remove('sidebar-open');
        });
    }

    // Close on tap outside on mobile
    document.addEventListener('click', (e) => {
        if (sidebar && sidebar.classList.contains('sidebar-open') && !sidebar.contains(e.target) && e.target !== toggleBtn) {
            sidebar.classList.remove('sidebar-open');
        }
    });
});
</script>

</body>
</html>
