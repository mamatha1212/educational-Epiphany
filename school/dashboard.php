<?php
/**
 * EEMT 2027 - School Portal Dashboard
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireSchoolLogin();

$school = currentSchool() ?: ($_SESSION['school'] ?? null);
if (!$school) {
    header("Location: ../school_login.php");
    exit;
}

$pdo = getDB();

// Refresh school record to ensure latest DB values
if (!empty($school['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM school_registrations_2027 WHERE id = ?");
    $stmt->execute([$school['id']]);
    $freshSchool = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($freshSchool) {
        $school = array_merge($school, $freshSchool);
    }
} elseif (!empty($school['reference_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM school_registrations_2027 WHERE reference_id = ?");
    $stmt->execute([$school['reference_id']]);
    $freshSchool = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($freshSchool) {
        $school = array_merge($school, $freshSchool);
    }
}

// Variables for display
$schoolName = $school['school_name'] ?? 'School';
$eemtsrc = $school['reference_id'] ?? $school['reference_number'] ?? $school['eemtsrc'] ?? '';
$udiseCode = $school['unique_code'] ?? $school['udise_code'] ?? 'N/A';
$inchargeName = $school['contact_person'] ?? $school['incharge_name'] ?? 'N/A';
$inchargeMobile = $school['mobile'] ?? $school['phone'] ?? $school['incharge_mobile'] ?? 'N/A';
$inchargeEmail = $school['email'] ?? '';
$inchargeDesignation = $school['designation'] ?? 'Coordinator / Head';
$district = $school['district'] ?? 'Andhra Pradesh';
$mandal = $school['mandal'] ?? 'N/A';
$totalStudents = $school['expected_students'] ?? $school['total_students'] ?? 0;
$officialSlipUrl = '../register_success.php?ref=' . urlencode($eemtsrc);

// Fetch published question papers
$qpStmt = $pdo->prepare("SELECT * FROM question_papers WHERE is_published = 1 ORDER BY id DESC");
$qpStmt->execute();
$questionPapers = $qpStmt->fetchAll(PDO::FETCH_ASSOC);
$totalPapersCount = count($questionPapers);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Dashboard - <?php echo htmlspecialchars($schoolName); ?> | EEMT 2027</title>
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

        /* School User Snippet */
        .sidebar-user {
            padding: 0.85rem 1rem;
            margin: 0.75rem 0.85rem;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.07);
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.1rem;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.3);
            flex-shrink: 0;
        }

        .user-meta {
            flex: 1;
            min-width: 0;
        }

        .user-name {
            font-size: 0.82rem;
            font-weight: 700;
            color: white;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.2;
        }

        .user-role {
            font-size: 0.7rem;
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
            cursor: pointer;
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
            flex-shrink: 0;
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
            padding: 0.85rem 0.85rem 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .sidebar-code-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.09);
            border-radius: 8px;
            padding: 8px 10px;
        }

        .code-card-label {
            font-size: 0.65rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .code-card-val {
            font-family: monospace;
            font-size: 0.95rem;
            font-weight: 800;
            color: #60a5fa;
            margin: 2px 0 6px;
        }

        .btn-copy-code {
            width: 100%;
            background: #2563eb;
            color: white;
            border: none;
            padding: 5px 8px;
            font-size: 0.75rem;
            font-weight: 700;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: background 0.15s ease;
        }

        .btn-copy-code:hover {
            background: #1d4ed8;
        }

        .logout-link {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            color: #ef4444;
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
            font-size: 1.15rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            display: flex;
            align-items: center;
            flex-direction: column !important;
            gap: 8px;
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
            max-width: 1300px;
            width: 100%;
            margin: 0 auto;
        }

        /* Tab Panes */
        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
            animation: fadeIn 0.2s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(4px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Hero Card */
        .hero-banner {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #3b82f6 100%);
            border-radius: 14px;
            padding: 1.75rem 2rem;
            color: white;
            box-shadow: 0 10px 25px -5px rgba(30, 58, 138, 0.25);
            margin-bottom: 1.75rem;
        }

        .hero-banner h1 {
            font-size: 1.6rem;
            font-weight: 800;
            margin: 0 0 0.4rem 0;
            letter-spacing: -0.01em;
            color: #ffffff;
        }

        .hero-banner p {
            margin: 0 0 1rem 0;
            color: #e0e7ff;
            font-size: 0.92rem;
            max-width: 750px;
            line-height: 1.5;
        }

        .hero-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .hero-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(255, 255, 255, 0.16);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.22);
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #ffffff;
        }

        /* KPI Cards Grid */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.25rem;
            margin-bottom: 1.75rem;
        }

        .kpi-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            border-left: 4px solid #2563eb;
        }

        .kpi-card.purple {
            border-left-color: #8b5cf6;
        }

        .kpi-card.amber {
            border-left-color: #f59e0b;
        }

        .kpi-card.green {
            border-left-color: #10b981;
        }

        .kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
            background: #eff6ff;
        }

        .kpi-card.purple .kpi-icon {
            background: #f5f3ff;
        }

        .kpi-card.amber .kpi-icon {
            background: #fffbeb;
        }

        .kpi-card.green .kpi-icon {
            background: #ecfdf5;
        }

        .kpi-value {
            font-size: 1.45rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.2;
        }

        .kpi-label {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 600;
            margin-top: 2px;
        }

        /* 2-Column Info Grid */
        .info-cards-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.75rem;
        }

        @media (max-width: 860px) {
            .info-cards-grid {
                grid-template-columns: 1fr;
            }
        }

        .modern-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        }

        .modern-card-header {
            padding: 1.15rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 12px;
            background: #ffffff;
        }

        .modern-card-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            background: #eff6ff;
            flex-shrink: 0;
        }

        .modern-card-title {
            font-size: 1.05rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
        }

        .modern-card-sub {
            font-size: 0.78rem;
            color: #64748b;
            margin-top: 2px;
        }

        .modern-card-body {
            padding: 0.75rem 1.5rem 1.25rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.88rem;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #64748b;
            font-weight: 600;
        }

        .detail-val {
            color: #0f172a;
            font-weight: 700;
            text-align: right;
        }

        .detail-val code {
            background: #f1f5f9;
            padding: 3px 7px;
            border-radius: 6px;
            font-family: monospace;
            color: #1e40af;
            font-weight: 700;
        }

        /* Question Paper Notice Card */
        .qp-notice-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 3rem 2rem;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            max-width: 800px;
            margin: 1rem auto;
        }

        .qp-badge-scheduled {
            display: inline-block;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 4px 12px;
            border-radius: 9999px;
            border: 1px solid #bfdbfe;
            margin-bottom: 1rem;
        }

        .qp-notice-title {
            font-size: 1.6rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 1rem 0;
            line-height: 1.35;
        }

        .qp-notice-desc {
            font-size: 0.95rem;
            color: #64748b;
            max-width: 600px;
            margin: 0 auto 2rem;
            line-height: 1.6;
        }

        .qp-bullets-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 1.25rem 1.5rem;
            text-align: left;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .qp-bullet-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.88rem;
            font-weight: 600;
            color: #334155;
        }

        .qp-bullet-dot {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #dcfce7;
            color: #15803d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 800;
            flex-shrink: 0;
        }

        /* Overlay for mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(2px);
            z-index: 999;
        }

        .sidebar-overlay.active {
            display: block;
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

        /* Stacked Brand Group (School Portal + School Name One By One) */
        .topbar-brand-group {
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-width: 0;
            text-align: left;
        }

        .topbar-main-title {
            font-size: 0.95rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.15;
            letter-spacing: -0.01em;
        }

        .topbar-school-name {
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748b;
            line-height: 1.2;
            margin-top: 1px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 165px;
        }

        .topbar-logout-btn {
            background: #fef2f2;
            color: #ef4444;
            border: 1px solid #fecaca;
            padding: 5px 12px;
            font-size: 0.8rem;
            font-weight: 700;
            border-radius: 6px;
            text-decoration: none;
            white-space: nowrap;
            transition: all 0.15s ease;
        }

        .topbar-logout-btn:hover {
            background: #fee2e2;
            color: #dc2626;
        }

        @media (min-width: 640px) {
            .topbar-main-title {
                font-size: 1.05rem;
            }

            .topbar-school-name {
                max-width: 320px;
                font-size: 0.82rem;
            }
        }

        @media (min-width: 1024px) {
            .topbar-school-name {
                max-width: 500px;
            }
        }

        @media (max-width: 640px) {
            .admin-topbar {
                height: 58px;
                padding: 0 0.75rem;
            }

            .topbar-left {
                gap: 8px;
            }

            .sidebar-toggle-btn {
                padding: 5px 8px;
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

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <div class="admin-shell">
        <!-- LEFT SIDEBAR -->
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
                        <span>School Portal</span>
                    </div>
                </a>
                <button type="button" class="sidebar-close-btn" onclick="closeSidebar()" aria-label="Close menu">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- School Info Snippet -->
            <div class="sidebar-user">
                <div class="user-avatar">
                    🏫
                </div>
                <div class="user-meta">
                    <div class="user-name" title="<?php echo htmlspecialchars($schoolName); ?>">
                        <?php echo htmlspecialchars($schoolName); ?>
                    </div>
                    <div class="user-role"><span class="status-dot"></span> UDISE:
                        <?php echo htmlspecialchars($udiseCode); ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar Navigation Menu (NO School Data, NO Incharge - shown inside dashboard!) -->
            <nav class="sidebar-nav">
                <div class="nav-section-title">Navigation</div>

                <a href="#dashboard" class="sidebar-item active" id="nav-dashboard"
                    onclick="switchTab('dashboard', event)">
                    <div class="sidebar-item-content">
                        <span class="sidebar-icon">📊</span>
                        <span>Dashboard</span>
                    </div>
                </a>

                <a href="#papers" class="sidebar-item" id="nav-papers" onclick="switchTab('papers', event)">
                    <div class="sidebar-item-content">
                        <span class="sidebar-icon">📄</span>
                        <span>Prelims Question Papers</span>
                    </div>
                    <span class="sidebar-badge" style="background: #2563eb; color: white;">28 Nov</span>
                </a>

                <div class="nav-section-title" style="margin-top: 0.5rem;">Quick Links</div>

                <a href="<?php echo htmlspecialchars($officialSlipUrl); ?>" target="_blank" class="sidebar-item">
                    <div class="sidebar-item-content">
                        <span class="sidebar-icon">🖨️</span>
                        <span>Registration Slip</span>
                    </div>
                    <span style="font-size: 0.75rem; color: #94a3b8;">↗</span>
                </a>

                <a href="https://educationalepiphany.org" target="_blank" class="sidebar-item">
                    <div class="sidebar-item-content">
                        <span class="sidebar-icon">🌐</span>
                        <span>Home Page</span>
                    </div>
                    <span style="font-size: 0.75rem; color: #94a3b8;">↗</span>
                </a>
            </nav>

            <!-- Sidebar Footer -->
            <div class="sidebar-footer">
                <div class="sidebar-code-card">
                    <div class="code-card-label">School Reference Code</div>
                    <div class="code-card-val"><?php echo htmlspecialchars($eemtsrc); ?></div>
                    <button type="button" class="btn-copy-code"
                        onclick="copyCode('<?php echo htmlspecialchars($eemtsrc); ?>')">
                        📋 <span id="copyText">Copy Code</span>
                    </button>
                </div>

                <a href="logout.php" class="logout-link">
                    <span>🚪</span>
                    <span>Sign Out</span>
                </a>
            </div>
        </aside>

        <!-- RIGHT MAIN CONTENT -->
        <div class="admin-main">
            <!-- Top App Bar (OFFICIAL SLIP REMOVED FROM HEADER) -->
            <header class="admin-topbar">
                <div class="topbar-left">
                    <button type="button" class="sidebar-toggle-btn" id="sidebarToggle" onclick="toggleSidebar()"
                        aria-label="Toggle Navigation">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <div style="display: flex; align-items: center; gap: 9px; min-width: 0;">
                        <img src="../assets/images/logo.png" alt="EEMT Logo"
                            style="height: 32px; width: 32px; object-fit: contain; flex-shrink: 0;"
                            onerror="this.src='../assets/images/logo.jpeg';">
                        <div class="topbar-brand-group">
                            <div class="topbar-main-title">School Portal</div>
                            <div class="topbar-school-name" title="<?php echo htmlspecialchars($schoolName); ?>">
                                <?php echo htmlspecialchars($schoolName); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="topbar-right">
                    <!-- ONLY Logout button beside header (date removed as requested) -->
                    <a href="logout.php" class="btn topbar-logout-btn">
                        Logout
                    </a>
                </div>
            </header>

            <!-- Main Content Wrapper -->
            <div class="admin-content">
                <!-- TAB 1: DASHBOARD -->
                <div id="tab-dashboard" class="tab-pane active">
                    <!-- Welcome Hero Banner -->
                    <div class="hero-banner">
                        <h1>Welcome back, <?php echo htmlspecialchars($schoolName); ?></h1>
                        <p>Track your school registration record, monitor prelims question paper notifications, and
                            access authorized examination materials for EEMT 2027.</p>
                        <div class="hero-chips">
                            <span class="hero-chip">
                                🏷️ Code: <?php echo htmlspecialchars($eemtsrc); ?>
                            </span>
                            <span class="hero-chip">
                                🏫 UDISE: <?php echo htmlspecialchars($udiseCode); ?>
                            </span>
                            <span class="hero-chip">
                                📍 <?php echo htmlspecialchars($district); ?>
                            </span>
                        </div>
                    </div>

                    <!-- 4 KPI Cards -->
                    <div class="kpi-grid">
                        <div class="kpi-card">
                            <div class="kpi-icon">👥</div>
                            <div>
                                <div class="kpi-value"><?php echo (int) $totalStudents; ?></div>
                                <div class="kpi-label">Registered Students</div>
                            </div>
                        </div>
                        <div class="kpi-card purple">
                            <div class="kpi-icon">📅</div>
                            <div>
                                <div class="kpi-value" style="font-size: 1.3rem;">28 Nov 2026</div>
                                <div class="kpi-label">Prelims Exam Date</div>
                            </div>
                        </div>
                        <div class="kpi-card amber">
                            <div class="kpi-icon">⏳</div>
                            <div>
                                <div class="kpi-value" style="font-size: 1.3rem;">Scheduled</div>
                                <div class="kpi-label">Exam Status</div>
                            </div>
                        </div>
                        <div class="kpi-card green">
                            <div class="kpi-icon">✅</div>
                            <div>
                                <div class="kpi-value" style="font-size: 1.3rem;">Confirmed</div>
                                <div class="kpi-label">Registration Status</div>
                            </div>
                        </div>
                    </div>

                    <!-- ALL SCHOOL DATA DIRECTLY IN DASHBOARD -->
                    <div class="info-cards-grid">
                        <!-- Modern Card 1: School Information -->
                        <div class="modern-card">
                            <div class="modern-card-header">
                                <div class="modern-card-icon">🏫</div>
                                <div>
                                    <h3 class="modern-card-title">School Information</h3>
                                    <div class="modern-card-sub">Official institutional profile on record</div>
                                </div>
                            </div>
                            <div class="modern-card-body">
                                <div class="detail-row">
                                    <span class="detail-label">School Name</span>
                                    <span
                                        class="detail-val"><?php echo htmlspecialchars($school['school_name'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">UDISE Code</span>
                                    <span
                                        class="detail-val"><code><?php echo htmlspecialchars($udiseCode); ?></code></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">EEMTSRC Reference</span>
                                    <span
                                        class="detail-val"><code><?php echo htmlspecialchars($eemtsrc); ?></code></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">School Category</span>
                                    <span
                                        class="detail-val"><?php echo htmlspecialchars($school['category'] ?? $school['school_type'] ?? 'High School'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Affiliated Board</span>
                                    <span
                                        class="detail-val"><?php echo htmlspecialchars($school['board'] ?? 'State Board'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">District</span>
                                    <span class="detail-val"><?php echo htmlspecialchars($district); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Mandal</span>
                                    <span class="detail-val"><?php echo htmlspecialchars($mandal); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Village / Location</span>
                                    <span
                                        class="detail-val"><?php echo htmlspecialchars($school['village'] ?? $school['address'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Pincode</span>
                                    <span
                                        class="detail-val"><?php echo htmlspecialchars($school['pincode'] ?? 'N/A'); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Modern Card 2: In-Charge Contact Person -->
                        <div class="modern-card">
                            <div class="modern-card-header">
                                <div class="modern-card-icon" style="background: #ecfdf5; color: #10b981;">👤</div>
                                <div>
                                    <h3 class="modern-card-title">In-Charge Contact Person</h3>
                                    <div class="modern-card-sub">Designated school exam coordinator</div>
                                </div>
                            </div>
                            <div class="modern-card-body">
                                <div class="detail-row">
                                    <span class="detail-label">In-Charge Name</span>
                                    <span class="detail-val"><?php echo htmlspecialchars($inchargeName); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Designation</span>
                                    <span
                                        class="detail-val"><?php echo htmlspecialchars($inchargeDesignation); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Mobile Number</span>
                                    <span class="detail-val">
                                        <a href="tel:<?php echo htmlspecialchars($inchargeMobile); ?>"
                                            style="color: #2563eb; text-decoration: none; font-weight: 700;">
                                            <?php echo htmlspecialchars($inchargeMobile); ?>
                                        </a>
                                    </span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Email Address</span>
                                    <span class="detail-val">
                                        <?php if (!empty($school['email'])): ?>
                                            <a href="mailto:<?php echo htmlspecialchars($school['email']); ?>"
                                                style="color: #2563eb; text-decoration: none;">
                                                <?php echo htmlspecialchars($school['email']); ?>
                                            </a>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Alternate Contact</span>
                                    <span
                                        class="detail-val"><?php echo htmlspecialchars($school['alternate_phone'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Registration Date</span>
                                    <span
                                        class="detail-val"><?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($school['created_at'] ?? 'now'))); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Official Slip</span>
                                    <span class="detail-val">
                                        <a href="<?php echo htmlspecialchars($officialSlipUrl); ?>" target="_blank"
                                            style="display: inline-flex; align-items: center; gap: 4px; color: #1d4ed8; font-weight: 700; text-decoration: none;">
                                            <span>View &amp; Print Slip</span>
                                            <span>↗</span>
                                        </a>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Exam Schedule Notice Card -->
                    <div class="modern-card"
                        style="padding: 1.25rem 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div
                                style="width: 44px; height: 44px; border-radius: 10px; background: #eff6ff; display: flex; align-items: center; justify-content: center; font-size: 1.4rem;">
                                📢
                            </div>
                            <div>
                                <div style="font-weight: 800; font-size: 1rem; color: #0f172a;">Prelims Exam Scheduled
                                    for 28th November 2026</div>
                                <div style="font-size: 0.82rem; color: #64748b; margin-top: 2px;">Question papers will
                                    be available in the Question Papers tab on examination morning.</div>
                            </div>
                        </div>
                        <button type="button" onclick="switchTab('papers', event)" class="btn"
                            style="background: #2563eb; color: white; padding: 8px 16px; font-weight: 700; font-size: 0.85rem; border-radius: 8px; border: none; cursor: pointer;">
                            Go to Question Papers Tab &rarr;
                        </button>
                    </div>
                </div><!-- /#tab-dashboard -->

                <!-- TAB 2: PRELIMS QUESTION PAPERS -->
                <div id="tab-papers" class="tab-pane">
                    <div style="margin-bottom: 1.5rem;">
                        <h2 style="font-size: 1.45rem; font-weight: 800; color: #0f172a; margin: 0 0 0.35rem 0;">Prelims
                            Question Papers</h2>
                        <p style="margin: 0; font-size: 0.88rem; color: #64748b;">EEMT 2027 Preliminary Examination
                            question papers for 7th and 10th standards.</p>
                    </div>

                    <?php if (!empty($questionPapers)): ?>
                        <!-- LIVE QUESTION PAPERS TABLE IF PUBLISHED -->
                        <div class="modern-card">
                            <div class="modern-card-header">
                                <div class="modern-card-icon">📄</div>
                                <div>
                                    <h3 class="modern-card-title">Available Question Papers</h3>
                                    <div class="modern-card-sub">Official examination papers released by State Coordination
                                        Committee</div>
                                </div>
                            </div>
                            <div class="modern-card-body" style="padding: 0;">
                                <div class="table-responsive">
                                    <table class="table" style="width: 100%; border-collapse: collapse; margin-bottom: 0;">
                                        <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                                            <tr>
                                                <th
                                                    style="padding: 0.85rem 1.25rem; font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.05em;">
                                                    Standard</th>
                                                <th
                                                    style="padding: 0.85rem 1.25rem; font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.05em;">
                                                    Paper Title</th>
                                                <th
                                                    style="padding: 0.85rem 1.25rem; font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.05em;">
                                                    Released At</th>
                                                <th
                                                    style="padding: 0.85rem 1.25rem; font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.05em; text-align: right;">
                                                    Download</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($questionPapers as $qp): ?>
                                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                                    <td style="padding: 1rem 1.25rem; vertical-align: middle;">
                                                        <span style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; font-size: 0.82rem; font-weight: 700; padding: 4px 10px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;">
                                                            🎓 <?php echo htmlspecialchars($qp['class'] ?? 'Class 10'); ?>
                                                        </span>
                                                    </td>
                                                    <td style="padding: 1rem 1.25rem; vertical-align: middle;">
                                                        <div style="font-weight: 700; color: #1e293b; font-size: 0.95rem;">
                                                            <?php echo htmlspecialchars($qp['title'] ?? 'EEMT 2027 Prelims Paper'); ?>
                                                        </div>
                                                        <div style="font-size: 0.78rem; color: #64748b; margin-top: 3px; display: flex; align-items: center; gap: 8px;">
                                                            <span>Subject: <strong style="color: #334155;"><?php echo htmlspecialchars($qp['subject'] ?? 'General'); ?></strong></span>
                                                            <?php if (!empty($qp['file_size'])): ?>
                                                                <span style="color: #cbd5e1;">&bull;</span>
                                                                <span><?php echo round($qp['file_size'] / 1024, 1); ?> KB</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td style="padding: 1rem 1.25rem; vertical-align: middle;">
                                                        <div style="color: #1e293b; font-size: 0.88rem; font-weight: 600;">
                                                            <?php echo htmlspecialchars(date('d M Y,', strtotime($qp['published_at'] ?? $qp['created_at'] ?? 'now'))); ?>
                                                        </div>
                                                        <div style="color: #64748b; font-size: 0.78rem;">
                                                            <?php echo htmlspecialchars(date('h:i A', strtotime($qp['published_at'] ?? $qp['created_at'] ?? 'now'))); ?>
                                                        </div>
                                                    </td>
                                                    <td style="padding: 1rem 1.25rem; text-align: right; vertical-align: middle;">
                                                        <a href="download_paper.php?id=<?php echo (int)$qp['id']; ?>" target="_blank"
                                                            class="btn"
                                                            style="background: #2563eb; color: white; padding: 8px 16px; font-size: 0.82rem; font-weight: 700; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(37,99,235,0.2); transition: all 0.2s;">
                                                            <span>📥 Download PDF</span>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- SCHEDULED HERO NOTICE: User Requirement -->
                        <div class="qp-notice-card">
                            <div class="qp-badge-scheduled">Scheduled Examination Event</div>
                            <h2 class="qp-notice-title">
                                Question paper will be updated on prelims exam day<br>
                                <span style="color: #2563eb;">(28th November 2026)</span>
                            </h2>
                            <p class="qp-notice-desc">
                                Once the question paper is uploaded by the State Coordinator or Admin team on the
                                examination morning, the official download links will appear here immediately.
                            </p>

                            <div class="qp-bullets-box">
                                <div class="qp-bullet-item">
                                    <span class="qp-bullet-dot">✓</span>
                                    <span>Encrypted High-Resolution PDF papers for 7th and 10th classes</span>
                                </div>
                                <div class="qp-bullet-item">
                                    <span class="qp-bullet-dot">✓</span>
                                    <span>Official OMR Response Sheets &amp; Invigilator Instructions</span>
                                </div>
                                <div class="qp-bullet-item">
                                    <span class="qp-bullet-dot">✓</span>
                                    <span>Official Answer Keys released following exam completion</span>
                                </div>
                            </div>

                            <div style="margin-top: 2rem;">
                                <button type="button" onclick="switchTab('dashboard', event)" class="btn"
                                    style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; padding: 8px 18px; font-weight: 700; border-radius: 8px; cursor: pointer;">
                                    &larr; Back to Dashboard
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div><!-- /#tab-papers -->
            </div>
        </div>
    </div>

    <script>
        // Prevent auto-scrolling on load
        if ('scrollRestoration' in history) {
            history.scrollRestoration = 'manual';
        }

        function switchTab(tabId, ev) {
            if (ev) ev.preventDefault();

            // Tab Panes
            document.querySelectorAll('.tab-pane').forEach(function (pane) {
                pane.classList.remove('active');
            });
            var target = document.getElementById('tab-' + tabId);
            if (target) {
                target.classList.add('active');
            }

            // Nav Items
            document.querySelectorAll('.sidebar-item').forEach(function (item) {
                item.classList.remove('active');
            });
            var navItem = document.getElementById('nav-' + tabId);
            if (navItem) {
                navItem.classList.add('active');
            }

            // Close mobile sidebar
            closeSidebar();

            // Reset scroll to top
            window.scrollTo({ top: 0, left: 0, behavior: 'instant' });

            // Update URL hash smoothly
            if (tabId === 'dashboard') {
                history.replaceState(null, '', window.location.pathname);
            } else {
                history.replaceState(null, '', '#' + tabId);
            }
        }

        function toggleSidebar() {
            var sb = document.getElementById('adminSidebar');
            var overlay = document.getElementById('sidebarOverlay');
            if (sb) sb.classList.toggle('sidebar-open');
            if (overlay) overlay.classList.toggle('active');
        }

        function closeSidebar() {
            var sb = document.getElementById('adminSidebar');
            var overlay = document.getElementById('sidebarOverlay');
            if (sb) sb.classList.remove('sidebar-open');
            if (overlay) overlay.classList.remove('active');
        }

        function copyCode(code) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(code).then(function () {
                    showCopied();
                }).catch(function () {
                    fallbackCopy(code);
                });
            } else {
                fallbackCopy(code);
            }
        }

        function fallbackCopy(code) {
            var ta = document.createElement('textarea');
            ta.value = code;
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                showCopied();
            } catch (e) { }
            document.body.removeChild(ta);
        }

        function showCopied() {
            var el = document.getElementById('copyText');
            if (el) {
                var old = el.textContent;
                el.textContent = 'Copied!';
                setTimeout(function () {
                    el.textContent = old;
                }, 2000);
            }
        }

        // Initial tab check on page load
        document.addEventListener('DOMContentLoaded', function () {
            var hash = window.location.hash.replace('#', '');
            if (hash === 'papers' || hash === 'prelims') {
                switchTab('papers');
            } else {
                switchTab('dashboard');
            }
            window.scrollTo({ top: 0, left: 0, behavior: 'instant' });
        });
    </script>
</body>

</html>