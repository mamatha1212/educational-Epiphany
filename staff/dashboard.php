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

// Check user granular permissions
$canViewRegs = canViewRegistrations();
$canEditRegs = canEditRegistrations();
$isViewOnly = !$canEditRegs;

// Handle Status Updates by Staff
$statusMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!$canViewRegs || !$canEditRegs) {
        $statusMessage = "Access Denied: Your account does not have permission to edit school registrations status.";
    } else {
        $regId = (int) ($_POST['reg_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? 'submitted');
        $validStatuses = ['submitted', 'verified', 'contacted', 'completed'];

        if ($regId > 0 && in_array($newStatus, $validStatuses)) {
            $stmt = $pdo->prepare("UPDATE school_registrations_2027 SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $regId]);
            $statusMessage = "Status updated to " . strtoupper($newStatus) . " successfully.";
        }
    }
}

// Handle Full Edit of Registration Record by Staff Coordinator
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_reg'])) {
    if (!$canViewRegs || !$canEditRegs) {
        $statusMessage = "Access Denied: Your account does not have permission to edit school registrations.";
    } else {
        $regId = (int)($_POST['reg_id'] ?? 0);
        $schoolName = trim($_POST['school_name'] ?? '');
        $uniqueCode = trim($_POST['unique_code'] ?? '');
        $contactPerson = trim($_POST['contact_person'] ?? '');
        $designation = trim($_POST['designation'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $status = trim($_POST['status'] ?? 'submitted');
        $mandal = trim($_POST['mandal'] ?? '');
        $district = trim($_POST['district'] ?? '');
        if (!empty($assignedDistrict)) {
            $district = $assignedDistrict;
        }

        if ($regId > 0 && !empty($schoolName) && !empty($mobile)) {
            if (!empty($district)) {
                $upd = $pdo->prepare("UPDATE school_registrations_2027 SET school_name = ?, unique_code = ?, district = ?, mandal = ?, contact_person = ?, designation = ?, mobile = ?, email = ?, status = ? WHERE id = ?");
                $upd->execute([$schoolName, $uniqueCode, $district, $mandal, $contactPerson, $designation, $mobile, $email, $status, $regId]);
            } else {
                $upd = $pdo->prepare("UPDATE school_registrations_2027 SET school_name = ?, unique_code = ?, mandal = ?, contact_person = ?, designation = ?, mobile = ?, email = ?, status = ? WHERE id = ?");
                $upd->execute([$schoolName, $uniqueCode, $mandal, $contactPerson, $designation, $mobile, $email, $status, $regId]);
            }
            $statusMessage = "School registration for '$schoolName' updated successfully.";
        } else {
            $statusMessage = "School name and contact phone number are required.";
        }
    }
}


// Handle Question Paper Upload by Staff Coordinator (if permitted)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_paper'])) {
    if (!canManagePapers()) {
        $statusMessage = "Access Denied: You do not have permission to upload question papers.";
    } else {
        $title = trim($_POST['title'] ?? '');
        $classGrade = trim($_POST['class'] ?? '');
        $subject = trim($_POST['subject'] ?? 'General');
        $isPublished = isset($_POST['is_published']) ? 1 : 0;
        $uploaderName = $user['name'] ?? ($user['username'] ?? 'Coordinator');

        if (empty($title) || empty($classGrade) || empty($subject)) {
            $statusMessage = "Please fill in all required question paper fields.";
        } elseif (!isset($_FILES['paper_file']) || $_FILES['paper_file']['error'] !== UPLOAD_ERR_OK) {
            $statusMessage = "Please select a valid PDF file to upload.";
        } else {
            $file = $_FILES['paper_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext !== 'pdf') {
                $statusMessage = "Only PDF files are allowed for question papers.";
            } else {
                $uploadDir = __DIR__ . '/../uploads/question_papers/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
                $newFileName = 'paper_' . time() . '_' . $safeName . '.pdf';
                $destination = $uploadDir . $newFileName;

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $relPath = 'uploads/question_papers/' . $newFileName;
                    $stmt = $pdo->prepare("
                        INSERT INTO question_papers (title, class, subject, file_path, file_size, is_published, published_at, uploaded_by)
                        VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?)
                    ");
                    $stmt->execute([$title, $classGrade, $subject, $relPath, (int)$file['size'], $isPublished, $uploaderName]);
                    $statusMessage = "Question paper uploaded and saved successfully!";
                    $activeTab = 'prelims';
                } else {
                    $statusMessage = "Failed to save the uploaded PDF file.";
                }
            }
        }
    }
}

// Handle filters & active tab
$districtFilter = trim($_GET['district'] ?? ($assignedDistrict ?: ''));
$mandalFilter = trim($_GET['mandal'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 10);
if (!in_array($perPage, [10, 25, 50, 100])) {
    $perPage = 10;
}
$activeTab = trim($_GET['tab'] ?? ($canViewRegs ? 'registrations' : 'prelims'));
if (!in_array($activeTab, ['registrations', 'prelims', 'schools'])) {
    $activeTab = $canViewRegs ? 'registrations' : 'prelims';
}
if (!$canViewRegs && $activeTab === 'registrations') {
    $activeTab = 'prelims';
}

// Schools Directory Data for tab=schools
$canViewSchoolsDir = canViewSchools() || canManageSchools();
$totalMasterSchools = 0;
$totalDistrictsCount = 0;
$totalMandalsCount = 0;
$totalRegisteredMaster = 0;
$districtsSummary = [];
$mandalsSummary = [];
$schoolsList = [];

$schoolView = trim($_GET['view'] ?? 'districts');
if (!in_array($schoolView, ['districts', 'mandals', 'schools'])) {
    $schoolView = 'districts';
}
$schoolDistrict = trim($_GET['schools_district'] ?? '');
$schoolMandal = trim($_GET['schools_mandal'] ?? '');
$schoolSearch = trim($_GET['schools_search'] ?? '');

if ($canViewSchoolsDir) {
    $totalMasterSchools = (int) $pdo->query("SELECT COUNT(*) FROM schools_master")->fetchColumn();
    $totalDistrictsCount = (int) $pdo->query("SELECT COUNT(DISTINCT district) FROM schools_master WHERE district != ''")->fetchColumn();
    $totalMandalsCount = (int) $pdo->query("SELECT COUNT(DISTINCT mandal) FROM schools_master WHERE mandal != ''")->fetchColumn();
    $totalRegisteredMaster = (int) $pdo->query("SELECT COUNT(*) FROM schools_master WHERE is_registered = 1")->fetchColumn();

    $districtsSummary = $pdo->query("
        SELECT district, 
               COUNT(DISTINCT mandal) as total_mandals, 
               COUNT(*) as total_schools, 
               SUM(CASE WHEN is_registered = 1 THEN 1 ELSE 0 END) as registered_schools 
        FROM schools_master 
        WHERE district != '' 
        GROUP BY district 
        ORDER BY district ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    if ($schoolDistrict !== '') {
        $mStmt = $pdo->prepare("
            SELECT mandal, district, 
                   COUNT(*) as total_schools, 
                   SUM(CASE WHEN is_registered = 1 THEN 1 ELSE 0 END) as registered_schools 
            FROM schools_master 
            WHERE district = ? AND mandal != '' 
            GROUP BY mandal, district 
            ORDER BY mandal ASC
        ");
        $mStmt->execute([$schoolDistrict]);
        $mandalsSummary = $mStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($schoolSearch !== '' || ($schoolDistrict !== '' && $schoolMandal !== '')) {
        $sWhere = [];
        $sParams = [];
        if ($schoolDistrict !== '') {
            $sWhere[] = "district = :dist";
            $sParams[':dist'] = $schoolDistrict;
        }
        if ($schoolMandal !== '') {
            $sWhere[] = "mandal = :mand";
            $sParams[':mand'] = $schoolMandal;
        }
        if ($schoolSearch !== '') {
            $sWhere[] = "(school_name LIKE :kw OR unique_code LIKE :kw OR district LIKE :kw OR mandal LIKE :kw)";
            $sParams[':kw'] = "%$schoolSearch%";
        }
        $sWhereSql = !empty($sWhere) ? "WHERE " . implode(" AND ", $sWhere) : "";
        $sStmt = $pdo->prepare("SELECT id, district, mandal, school_name, unique_code, is_registered FROM schools_master {$sWhereSql} ORDER BY district, mandal, school_name LIMIT 100");
        $sStmt->execute($sParams);
        $schoolsList = $sStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Fetch published question papers
$papersStmt = $pdo->query("SELECT * FROM question_papers WHERE is_published = 1 ORDER BY id DESC");
$questionPapers = $papersStmt->fetchAll(PDO::FETCH_ASSOC);

function buildStaffPaginationUrl($p, $perPage, $district, $mandal, $status, $search) {
    $params = [
        'tab' => 'registrations',
        'page' => $p,
        'per_page' => $perPage,
    ];
    if ($district !== '') $params['district'] = $district;
    if ($mandal !== '') $params['mandal'] = $mandal;
    if ($status !== '') $params['status'] = $status;
    if ($search !== '') $params['search'] = $search;
    return 'dashboard.php?' . http_build_query($params);
}

if ($canViewRegs) {
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
    $totalSchools = (int) $pdo->query("SELECT COUNT(*) FROM school_registrations_2027 {$scopeWhere}")->fetchColumn();
    $verifiedCount = (int) $pdo->query("SELECT COUNT(*) FROM school_registrations_2027 " . ($scopeWhere ? "{$scopeWhere} AND (status = 'verified' OR status = 'contacted')" : "WHERE (status = 'verified' OR status = 'contacted')"))->fetchColumn();
    $totalMandals = (int) $pdo->query("SELECT COUNT(DISTINCT mandal) FROM school_registrations_2027 {$scopeWhere}")->fetchColumn();

    // Pagination 1: Count total matching records
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM school_registrations_2027 sr {$whereSql}");
    $countStmt->execute($params);
    $totalFiltered = (int) $countStmt->fetchColumn();

    // Pagination 2: Metrics
    $totalPages = max(1, (int) ceil($totalFiltered / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    // Pagination 3: Fetch sliced records with LIMIT & OFFSET
    $querySql = "SELECT sr.* FROM school_registrations_2027 sr {$whereSql} ORDER BY sr.id DESC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
    $stmt = $pdo->prepare($querySql);
    $stmt->execute($params);
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $filteredCount = $totalFiltered;

    $showingFrom = $totalFiltered > 0 ? $offset + 1 : 0;
    $showingTo = min($offset + count($registrations), $totalFiltered);
} else {
    $allDistricts = [];
    $totalSchools = 0;
    $verifiedCount = 0;
    $totalMandals = 0;
    $registrations = [];
    $filteredCount = 0;
    $totalFiltered = 0;
    $totalPages = 1;
    $showingFrom = 0;
    $showingTo = 0;
}

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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
            border: 1px solid #e2e8f0;
            transition: all 0.25s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
        }

        .kpi-card.kpi-green {
            border-top: 4px solid #059669;
        }

        .kpi-card.kpi-blue {
            border-top: 4px solid #2563eb;
        }

        .kpi-card.kpi-amber {
            border-top: 4px solid #f59e0b;
        }

        .kpi-card.kpi-purple {
            border-top: 4px solid #8b5cf6;
        }

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
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
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

        /* Pagination Buttons */
        .page-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            padding: 0 10px;
            border-radius: 6px;
            font-size: 0.82rem;
            font-weight: 600;
            color: #475569;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            text-decoration: none;
            transition: all 0.15s ease;
        }
        .page-btn:hover:not(.disabled):not(.active) {
            background: #f1f5f9;
            border-color: #94a3b8;
            color: #0f172a;
        }
        .page-btn.active {
            background: #059669;
            border-color: #059669;
            color: #ffffff;
            font-weight: 700;
            box-shadow: 0 2px 6px rgba(5, 150, 105, 0.3);
        }
        .page-btn.disabled {
            color: #cbd5e1;
            border-color: #e2e8f0;
            background: #f8fafc;
            cursor: not-allowed;
            pointer-events: none;
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
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
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

                <?php if ($canViewRegs): ?>
                    <a href="javascript:void(0)" onclick="switchDashboardTab('registrations')" id="sidebarLinkRegistrations"
                        class="sidebar-item <?php echo $activeTab === 'registrations' ? 'active' : ''; ?>">
                        <div class="sidebar-item-content">
                            <div class="sidebar-icon">📊</div>
                            <span>Registrations Data</span>
                        </div>
                        <span class="sidebar-badge" id="sidebarBadgeRegistrations"><?php echo $filteredCount; ?></span>
                    </a>
                <?php else: ?>
                    <a href="javascript:void(0)" onclick="switchDashboardTab('registrations')" id="sidebarLinkRegistrations"
                        class="sidebar-item <?php echo $activeTab === 'registrations' ? 'active' : ''; ?>"
                        style="opacity: 0.65;">
                        <div class="sidebar-item-content">
                            <div class="sidebar-icon">🔒</div>
                            <span>Registrations</span>
                        </div>
                        <span class="sidebar-badge" style="background: rgba(239,68,68,0.2); color: #fca5a5;">Locked</span>
                    </a>
                <?php endif; ?>

                <a href="javascript:void(0)" onclick="switchDashboardTab('prelims')" id="sidebarLinkPrelims"
                    class="sidebar-item <?php echo $activeTab === 'prelims' ? 'active' : ''; ?>">
                    <div class="sidebar-item-content">
                        <div class="sidebar-icon">📄</div>
                        <span>Prelims QP Portal</span>
                    </div>
                    <span class="sidebar-badge" id="sidebarBadgePrelims"><?php echo count($questionPapers); ?></span>
                </a>

                <?php if (canViewSchools() || canManageSchools()): ?>
                    <div class="nav-section-title">Directory Management</div>

                    <a href="javascript:void(0)" onclick="switchDashboardTab('schools')" id="sidebarLinkSchools"
                        class="sidebar-item <?php echo $activeTab === 'schools' ? 'active' : ''; ?>" style="color: #6ee7b7;">
                        <div class="sidebar-item-content">
                            <div class="sidebar-icon">🏛️</div>
                            <span>Schools, Mandals & Districts</span>
                        </div>
                        <?php if (canManageSchools()): ?>
                            <span class="sidebar-badge"
                                style="background: rgba(16, 185, 129, 0.25); color: #34d399; font-size: 0.7rem; font-weight: 700;">
                                Manage
                            </span>
                        <?php else: ?>
                            <span class="sidebar-badge"
                                style="background: rgba(59, 130, 246, 0.25); color: #93c5fd; font-size: 0.7rem; font-weight: 700;">
                                View Only
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>

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
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
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
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <div class="topbar-title">
                        <span>Field Operations & School Verification Roster</span>
                    </div>
                </div>

                <div class="topbar-right">
                    <span class="badge"
                        style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; font-weight: 700; padding: 6px 12px;">
                        Scope: <?php echo htmlspecialchars($user['assigned_district'] ?: 'All AP Districts'); ?>
                    </span>

                    <?php if ($canViewRegs && !$canEditRegs): ?>
                        <span class="badge"
                            style="background: #fffbeb; color: #b45309; border: 1px solid #fde68a; font-weight: 700; padding: 6px 12px; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 5px;">
                            👁️ Regs: View-Only Mode
                        </span>
                    <?php elseif ($canViewRegs && $canEditRegs): ?>
                        <span class="badge"
                            style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; font-weight: 700; padding: 6px 12px; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 5px;">
                            ✏️ Regs: Show & Edit Mode
                        </span>
                    <?php else: ?>
                        <span class="badge"
                            style="background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; font-weight: 700; padding: 6px 12px; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 5px;">
                            🔒 Regs: Access Restricted
                        </span>
                    <?php endif; ?>

                    <?php if (canViewSchools() || canManageSchools()): ?>
                        <button type="button" onclick="switchDashboardTab('schools')" class="btn"
                            style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; font-size: 0.85rem; padding: 6px 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                            🏛️ <?php echo canManageSchools() ? 'Manage Schools & Districts' : 'Schools & Mandals Directory'; ?>
                        </button>
                    <?php endif; ?>

                    <?php if (canManagePapers()): ?>
                        <button type="button" onclick="openUploadPaperModal()" class="btn"
                            style="background: #f5f3ff; color: #7c3aed; border: 1px solid #ddd6fe; font-size: 0.85rem; padding: 6px 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                            📤 Upload Question Paper
                        </button>
                    <?php endif; ?>

                    <button type="button" onclick="switchDashboardTab('prelims')" class="btn"
                        style="background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; font-size: 0.85rem; padding: 6px 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                        📄 Prelims QP (<?php echo count($questionPapers); ?>)
                    </button>

                    <a href="../logout.php" class="btn"
                        style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; font-size: 0.85rem; padding: 6px 12px; font-weight: 700;">
                        Logout
                    </a>
                </div>
            </header>

            <!-- Main Content Canvas -->
            <div class="admin-content">

                <?php $noticeMsg = trim($_GET['notice'] ?? '');
                if ($noticeMsg): ?>
                    <div class="alert alert-warning"
                        style="margin-bottom: 1.5rem; background: #fffbeb; border: 1px solid #fde68a; color: #92400e; padding: 12px 16px; border-radius: 8px; display: flex; align-items: center; gap: 10px;">
                        <span>⚠️</span>
                        <span><?php echo htmlspecialchars($noticeMsg); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($statusMessage): ?>
                    <div class="alert alert-success"
                        style="margin-bottom: 1.5rem; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 16px; border-radius: 8px;">
                        <span>✅</span>
                        <span><?php echo htmlspecialchars($statusMessage); ?></span>
                    </div>
                <?php endif; ?>

                <!-- TAB 1: SCHOOL REGISTRATIONS PANEL -->
                <div id="tabPanelRegistrations"
                    style="<?php echo $activeTab === 'registrations' ? '' : 'display: none;'; ?>">

                    <?php if (!$canViewRegs): ?>
                        <div
                            style="background: #ffffff; border-radius: 16px; padding: 4rem 2rem; text-align: center; border: 1.5px dashed #cbd5e1; box-shadow: 0 4px 14px rgba(0,0,0,0.03); margin-bottom: 2rem;">
                            <div
                                style="width: 72px; height: 72px; border-radius: 20px; background: #fef2f2; border: 1.5px solid #fecaca; display: inline-flex; align-items: center; justify-content: center; font-size: 2.2rem; color: #dc2626; margin: 0 auto 1.25rem;">
                                🔒
                            </div>
                            <h3 style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin: 0 0 0.5rem;">School
                                Registrations Access Restricted</h3>
                            <p
                                style="font-size: 0.95rem; color: #64748b; max-width: 520px; margin: 0 auto 1.5rem; line-height: 1.55;">
                                Your coordinator account does not currently have permission to view school registrations
                                data. Please contact a State Administrator to enable viewing access.
                            </p>
                            <button type="button" onclick="switchDashboardTab('prelims')" class="btn"
                                style="padding: 10px 22px; border-radius: 10px; font-weight: 700; background: linear-gradient(135deg, #7c3aed, #6d28d9); color: white; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                                📄 View Prelims Question Papers &rarr;
                            </button>
                        </div>
                    <?php else: ?>
                        <!-- KPI Metric Cards Grid -->
                        <div class="kpi-grid">

                            <div class="kpi-card kpi-green">
                                <div>
                                    <div
                                        style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">
                                        Registered Schools</div>
                                    <div style="font-size: 2.1rem; font-weight: 800; color: #064e3b; margin: 0.2rem 0;">
                                        <?php echo number_format($totalSchools); ?></div>
                                    <div style="font-size: 0.78rem; color: #059669; font-weight: 600;">In
                                        <?php echo $assignedDistrict ?: 'All Districts'; ?></div>
                                </div>
                                <div class="kpi-icon" style="background: #ecfdf5; color: #059669;">
                                    🏫
                                </div>
                            </div>

                            <div class="kpi-card kpi-blue">
                                <div>
                                    <div
                                        style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">
                                        Active Mandals</div>
                                    <div style="font-size: 2.1rem; font-weight: 800; color: #1e3a8a; margin: 0.2rem 0;">
                                        <?php echo number_format($totalMandals); ?></div>
                                    <div style="font-size: 0.78rem; color: #2563eb; font-weight: 600;">Coverage Across State
                                    </div>
                                </div>
                                <div class="kpi-icon" style="background: #eff6ff; color: #2563eb;">
                                    📍
                                </div>
                            </div>

                            <div class="kpi-card kpi-amber">
                                <div>
                                    <div
                                        style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">
                                        Verified / Contacted</div>
                                    <div style="font-size: 2.1rem; font-weight: 800; color: #92400e; margin: 0.2rem 0;">
                                        <?php echo number_format($verifiedCount); ?></div>
                                    <div style="font-size: 0.78rem; color: #d97706; font-weight: 600;">Followed-up Schools
                                    </div>
                                </div>
                                <div class="kpi-icon" style="background: #fffbeb; color: #d97706;">
                                    🤝
                                </div>
                            </div>

                            <div class="kpi-card kpi-purple" onclick="switchDashboardTab('prelims')"
                                style="cursor: pointer;" title="Click to view Prelims Question Papers Tab">
                                <div>
                                    <div
                                        style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">
                                        Prelims Papers</div>
                                    <div style="font-size: 2.1rem; font-weight: 800; color: #5b21b6; margin: 0.2rem 0;">
                                        <?php echo count($questionPapers); ?></div>
                                    <div style="font-size: 0.78rem; color: #7c3aed; font-weight: 600;">Switch to Prelims Tab
                                        &rarr;</div>
                                </div>
                                <div class="kpi-icon" style="background: #f5f3ff; color: #7c3aed;">
                                    📄
                                </div>
                            </div>

                        </div>

                        <!-- Filter & Search Toolbar -->
                        <div class="filter-toolbar">
                            <form method="GET" action="dashboard.php" class="filter-row">
                                <input type="hidden" name="tab" value="registrations">
                                <input type="hidden" name="per_page" value="<?php echo (int)$perPage; ?>">

                                <div class="form-group" style="margin-bottom: 0; flex: 1 1 160px; min-width: 130px;">
                                    <label class="form-label" for="district"
                                        style="font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem; display: block; white-space: nowrap;">District</label>
                                    <select name="district" id="district" class="form-select"
                                        style="height: 40px; font-size: 0.88rem; width: 100%;">
                                        <option value="">All Districts</option>
                                        <?php foreach ($allDistricts as $d): ?>
                                            <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $districtFilter === $d ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($d); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group" style="margin-bottom: 0; flex: 1 1 160px; min-width: 130px;">
                                    <label class="form-label" for="mandal"
                                        style="font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem; display: block; white-space: nowrap;">Mandal</label>
                                    <input type="text" name="mandal" id="mandal" class="form-input"
                                        style="height: 40px; font-size: 0.88rem; width: 100%;"
                                        placeholder="Type Mandal name..."
                                        value="<?php echo htmlspecialchars($mandalFilter); ?>">
                                </div>

                                <div class="form-group" style="margin-bottom: 0; flex: 1 1 140px; min-width: 120px;">
                                    <label class="form-label" for="status"
                                        style="font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem; display: block; white-space: nowrap;">Status</label>
                                    <select name="status" id="status" class="form-select"
                                        style="height: 40px; font-size: 0.88rem; width: 100%;">
                                        <option value="">All Statuses</option>
                                        <option value="submitted" <?php echo $statusFilter === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                                        <option value="contacted" <?php echo $statusFilter === 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                                        <option value="verified" <?php echo $statusFilter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                                    </select>
                                </div>

                                <div class="form-group" style="margin-bottom: 0; flex: 2 1 220px; min-width: 160px;">
                                    <label class="form-label" for="search"
                                        style="font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem; display: block; white-space: nowrap;">Keyword
                                        Search</label>
                                    <input type="text" name="search" id="search" class="form-input"
                                        style="height: 40px; font-size: 0.88rem; width: 100%;"
                                        placeholder="Name, UDISE, Mobile, Code..."
                                        value="<?php echo htmlspecialchars($search); ?>">
                                </div>

                                <div style="display: flex; gap: 8px; align-items: flex-end; flex-shrink: 0;">
                                    <button type="submit" class="btn btn-primary"
                                        style="height: 40px; padding: 0 1.25rem; background: #059669; border: none; white-space: nowrap; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                                        🔍 Filter
                                    </button>
                                    <a href="dashboard.php" class="btn"
                                        style="height: 40px; padding: 0 1rem; background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; white-space: nowrap; font-weight: 600; display: inline-flex; align-items: center;">
                                        Reset
                                    </a>
                                </div>
                            </form>

                            <?php if ($districtFilter !== '' || $mandalFilter !== '' || $statusFilter !== '' || $search !== ''): ?>
                                <div style="margin-top: 1rem; font-size: 0.88rem; color: #64748b;">
                                    Found <strong><?php echo $filteredCount; ?></strong> matching registrations.
                                    <a href="dashboard.php"
                                        style="color: #059669; text-decoration: underline; margin-left: 0.5rem; font-weight: 600;">Clear
                                        All Filters</a>
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
                                        Registered schools directory and status verification
                                    </span>
                                </div>
                                <span class="badge"
                                    style="background: #ecfdf5; color: #047857; font-weight: 700; border: 1px solid #a7f3d0; padding: 6px 12px; font-size: 0.82rem;">
                                    <?php echo $filteredCount; ?> Schools in View
                                </span>
                            </div>

                            <?php if (empty($registrations)): ?>
                                <div style="padding: 3.5rem 2rem; text-align: center; color: #64748b;">
                                    <div style="font-size: 2.8rem; margin-bottom: 0.5rem;">🏫</div>
                                    <h4 style="font-weight: 700; color: #334155; margin-bottom: 0.25rem;">No School
                                        Registrations Found</h4>
                                    <p style="font-size: 0.9rem; margin: 0;">Try adjusting your search query or filters above.
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive" style="overflow-x: auto; width: 100%;">
                                    <table class="table" style="width: 100%; min-width: 1100px; border-collapse: collapse;">
                                        <thead>
                                            <tr style="background: #f8fafc; border-bottom: 1.5px solid #e2e8f0;">
                                                <th
                                                    style="width: 160px; min-width: 160px; padding: 14px 16px; font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase;">
                                                    EEMTSRC (Ref ID)</th>
                                                <th
                                                    style="width: 320px; min-width: 260px; padding: 14px 16px; font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase;">
                                                    School Details</th>
                                                <th
                                                    style="width: 190px; min-width: 170px; padding: 14px 16px; font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase;">
                                                    Location</th>
                                                <th
                                                    style="width: 190px; min-width: 170px; padding: 14px 16px; font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase;">
                                                    In-Charge Person</th>
                                                <th
                                                    style="width: 180px; min-width: 160px; padding: 14px 16px; font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase;">
                                                    Mobile Number</th>
                                                <th
                                                    style="width: 160px; min-width: 140px; padding: 14px 16px; font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase;">
                                                    Status Tracking</th>
                                                <th
                                                    style="width: 170px; min-width: 150px; padding: 14px 16px; font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; text-align: right;">
                                                    Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($registrations as $row): ?>
                                                <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;">
                                                    <td style="padding: 14px 16px; vertical-align: middle;">
                                                        <span class="badge"
                                                            style="background: #eff6ff; color: #1d4ed8; font-family: monospace; font-size: 0.9rem; font-weight: 800; border: 1px solid #bfdbfe; white-space: nowrap; padding: 4px 8px;">
                                                            <?php echo htmlspecialchars($row['reference_id']); ?>
                                                        </span>
                                                        <div
                                                            style="font-size: 0.75rem; color: #64748b; margin-top: 5px; white-space: nowrap;">
                                                            Reg: <?php echo date('d M, h:i A', strtotime($row['created_at'])); ?>
                                                        </div>
                                                    </td>
                                                    <td style="padding: 14px 16px; vertical-align: middle;">
                                                        <strong
                                                            style="color: #0f172a; display: block; font-size: 0.94rem; line-height: 1.4;">
                                                            <?php echo htmlspecialchars($row['school_name']); ?>
                                                        </strong>
                                                        <span style="font-size: 0.78rem; color: #64748b;">
                                                            UDISE: <code
                                                                style="color: #0284c7; background: #f0f9ff; padding: 2px 5px; border-radius: 4px;"><?php echo htmlspecialchars($row['unique_code']); ?></code>
                                                        </span>
                                                    </td>
                                                    <td style="padding: 14px 16px; vertical-align: middle;">
                                                        <div style="font-weight: 700; color: #334155; font-size: 0.88rem;">
                                                            <?php echo htmlspecialchars($row['district']); ?></div>
                                                        <div style="font-size: 0.8rem; color: #64748b;">Mandal:
                                                            <?php echo htmlspecialchars($row['mandal']); ?></div>
                                                    </td>
                                                    <td style="padding: 14px 16px; vertical-align: middle;">
                                                        <div style="font-weight: 600; color: #1e293b;">
                                                            <?php echo htmlspecialchars($row['contact_person']); ?></div>
                                                        <span class="badge badge-info"
                                                            style="font-size: 0.72rem; padding: 2px 6px;"><?php echo htmlspecialchars($row['designation']); ?></span>
                                                    </td>
                                                    <td style="padding: 14px 16px; vertical-align: middle;">
                                                        <?php if (!empty($row['mobile'])): ?>
                                                            <a href="tel:<?php echo htmlspecialchars($row['mobile']); ?>"
                                                                style="font-weight: 700; color: #1e293b; font-size: 0.92rem; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; transition: all 0.15s ease;"
                                                                onmouseover="this.style.background='#eff6ff'; this.style.borderColor='#93c5fd'; this.style.color='#2563eb';"
                                                                onmouseout="this.style.background='#f8fafc'; this.style.borderColor='#e2e8f0'; this.style.color='#1e293b';"
                                                                title="Mobile: <?php echo htmlspecialchars($row['mobile']); ?>">
                                                                <span style="font-size: 0.95rem;">📱</span>
                                                                <span><?php echo htmlspecialchars($row['mobile']); ?></span>
                                                            </a>
                                                        <?php else: ?>
                                                            <span style="font-size: 0.82rem; color: #94a3b8; font-style: italic;">No Mobile</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="padding: 14px 16px; vertical-align: middle;">
                                                        <?php if ($canEditRegs): ?>
                                                            <form method="POST" action="dashboard.php"
                                                                style="display: flex; gap: 6px; align-items: center; margin: 0;">
                                                                <input type="hidden" name="update_status" value="1">
                                                                <input type="hidden" name="reg_id" value="<?php echo $row['id']; ?>">
                                                                <select name="status"
                                                                    style="font-size: 0.78rem; padding: 4px 6px; height: 32px; border-radius: 6px; border: 1.5px solid #cbd5e1; font-weight: 700; color: #1e293b; background: #fff; cursor: pointer;">
                                                                    <option value="submitted" <?php echo ($row['status'] ?? '') === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                                                                    <option value="contacted" <?php echo ($row['status'] ?? '') === 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                                                                    <option value="verified" <?php echo ($row['status'] ?? '') === 'verified' ? 'selected' : ''; ?>>Verified</option>
                                                                    <option value="completed" <?php echo ($row['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                                </select>
                                                                <button type="submit"
                                                                    style="height: 32px; padding: 0 10px; font-size: 0.75rem; font-weight: 700; border-radius: 6px; border: none; background: #2563eb; color: white; cursor: pointer; transition: background 0.15s ease;"
                                                                    onmouseover="this.style.background='#1d4ed8';"
                                                                    onmouseout="this.style.background='#2563eb';">
                                                                    Save
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <?php
                                                            $st = strtolower($row['status'] ?? 'submitted');
                                                            $bBg = '#f1f5f9';
                                                            $bColor = '#475569';
                                                            $bBdr = '#cbd5e1';
                                                            if ($st === 'verified') {
                                                                $bBg = '#ecfdf5';
                                                                $bColor = '#047857';
                                                                $bBdr = '#a7f3d0';
                                                            } elseif ($st === 'contacted') {
                                                                $bBg = '#eff6ff';
                                                                $bColor = '#1d4ed8';
                                                                $bBdr = '#bfdbfe';
                                                            } elseif ($st === 'completed') {
                                                                $bBg = '#f5f3ff';
                                                                $bColor = '#7c3aed';
                                                                $bBdr = '#ddd6fe';
                                                            }
                                                            ?>
                                                            <span
                                                                style="background: <?php echo $bBg; ?>; color: <?php echo $bColor; ?>; border: 1px solid <?php echo $bBdr; ?>; font-size: 0.8rem; font-weight: 700; padding: 5px 12px; border-radius: 20px; text-transform: uppercase; text-align: center; display: inline-block; letter-spacing: 0.03em;">
                                                                <?php echo htmlspecialchars($row['status'] ?? 'submitted'); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="padding: 14px 16px; vertical-align: middle; text-align: right; white-space: nowrap;">
                                                        <div style="display: inline-flex; align-items: center; gap: 5px;">
                                                            <button type="button" class="btn" onclick='openViewRegModal(<?php echo json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' style="padding: 5px 10px; font-size: 0.76rem; font-weight: 700; background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; border-radius: 6px; cursor: pointer;" title="View Registration Full Details">
                                                                👁️ View
                                                            </button>
                                                            <?php if ($canEditRegs): ?>
                                                                <button type="button" class="btn" onclick='openEditRegModal(<?php echo json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' style="padding: 5px 10px; font-size: 0.76rem; font-weight: 700; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; border-radius: 6px; cursor: pointer;" title="Edit Registration Details">
                                                                    ✏️ Edit
                                                                </button>
                                                            <?php endif; ?>
                                                            <a href="../register_success.php?ref=<?php echo urlencode($row['reference_id']); ?>" target="_blank" class="btn" style="padding: 5px 10px; font-size: 0.76rem; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; border-radius: 6px; text-decoration: none;" title="View Official Registration Slip">
                                                                📄 Slip
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination Bar -->
                                <?php if ($totalFiltered > 0): ?>
                                    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem; padding: 1.1rem 1.5rem; border-top: 1px solid #e2e8f0; background: #ffffff;">
                                        <!-- Showing info & Per-page selector -->
                                        <div style="display: flex; align-items: center; gap: 1rem; font-size: 0.85rem; color: #64748b; flex-wrap: wrap;">
                                            <span>
                                                Showing <strong><?php echo $showingFrom; ?></strong> to <strong><?php echo $showingTo; ?></strong> of <strong><?php echo number_format($totalFiltered); ?></strong> schools
                                            </span>
                                            <span style="color: #cbd5e1;">|</span>
                                            <div style="display: inline-flex; align-items: center; gap: 6px;">
                                                <label for="perPageSelect" style="font-size: 0.82rem; color: #475569; font-weight: 600;">Per page:</label>
                                                <select id="perPageSelect" onchange="window.location.href = this.value;" style="padding: 4px 8px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.82rem; background: #ffffff; color: #1e293b; cursor: pointer;">
                                                    <?php foreach ([10, 25, 50, 100] as $opt): ?>
                                                        <option value="<?php echo buildStaffPaginationUrl(1, $opt, $districtFilter, $mandalFilter, $statusFilter, $search); ?>" <?php echo $perPage === $opt ? 'selected' : ''; ?>>
                                                            <?php echo $opt; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Page Buttons -->
                                        <?php if ($totalPages > 1): ?>
                                            <div style="display: flex; align-items: center; gap: 4px;">
                                                <!-- First Page -->
                                                <a href="<?php echo buildStaffPaginationUrl(1, $perPage, $districtFilter, $mandalFilter, $statusFilter, $search); ?>" 
                                                   class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>" 
                                                   title="First Page">«</a>

                                                <!-- Previous Page -->
                                                <a href="<?php echo buildStaffPaginationUrl($page - 1, $perPage, $districtFilter, $mandalFilter, $statusFilter, $search); ?>" 
                                                   class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>" 
                                                   title="Previous Page">‹</a>

                                                <?php
                                                $startPage = max(1, $page - 2);
                                                $endPage = min($totalPages, $page + 2);

                                                if ($startPage > 1) {
                                                    echo '<a href="' . buildStaffPaginationUrl(1, $perPage, $districtFilter, $mandalFilter, $statusFilter, $search) . '" class="page-btn">1</a>';
                                                    if ($startPage > 2) {
                                                        echo '<span style="color: #94a3b8; padding: 0 4px;">…</span>';
                                                    }
                                                }

                                                for ($p = $startPage; $p <= $endPage; $p++):
                                                ?>
                                                    <a href="<?php echo buildStaffPaginationUrl($p, $perPage, $districtFilter, $mandalFilter, $statusFilter, $search); ?>" 
                                                       class="page-btn <?php echo $p === $page ? 'active' : ''; ?>">
                                                        <?php echo $p; ?>
                                                    </a>
                                                <?php endfor; ?>

                                                <?php
                                                if ($endPage < $totalPages) {
                                                    if ($endPage < $totalPages - 1) {
                                                        echo '<span style="color: #94a3b8; padding: 0 4px;">…</span>';
                                                    }
                                                    echo '<a href="' . buildStaffPaginationUrl($totalPages, $perPage, $districtFilter, $mandalFilter, $statusFilter, $search) . '" class="page-btn">' . $totalPages . '</a>';
                                                }
                                                ?>

                                                <!-- Next Page -->
                                                <a href="<?php echo buildStaffPaginationUrl($page + 1, $perPage, $districtFilter, $mandalFilter, $statusFilter, $search); ?>" 
                                                   class="page-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" 
                                                   title="Next Page">›</a>

                                                <!-- Last Page -->
                                                <a href="<?php echo buildStaffPaginationUrl($totalPages, $perPage, $districtFilter, $mandalFilter, $statusFilter, $search); ?>" 
                                                   class="page-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" 
                                                   title="Last Page">»</a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div> <!-- End .table-card -->
                    <?php endif; ?> <!-- End canViewRegs check -->
                </div> <!-- End #tabPanelRegistrations -->

                <!-- TAB 2: PRELIMS QUESTION PAPERS PANEL -->
                <div id="tabPanelPrelims" style="<?php echo $activeTab === 'prelims' ? '' : 'display: none;'; ?>">

                    <!-- Prelims Summary Stats Strip -->
                    <div
                        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                        <div
                            style="background: #ffffff; border: 1.5px solid #ddd6fe; border-radius: 12px; padding: 1.25rem; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 6px rgba(124,58,237,0.05);">
                            <div>
                                <div
                                    style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">
                                    Published Papers</div>
                                <div style="font-size: 1.85rem; font-weight: 800; color: #5b21b6; margin: 0.2rem 0;">
                                    <?php echo count($questionPapers); ?></div>
                                <div style="font-size: 0.76rem; color: #7c3aed; font-weight: 600;">Available for Schools
                                </div>
                            </div>
                            <div
                                style="width: 44px; height: 44px; border-radius: 10px; background: #f5f3ff; color: #7c3aed; display: flex; align-items: center; justify-content: center; font-size: 1.4rem;">
                                📄
                            </div>
                        </div>

                        <div
                            style="background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
                            <div>
                                <div
                                    style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">
                                    Target Classes</div>
                                <div style="font-size: 1.85rem; font-weight: 800; color: #0f172a; margin: 0.2rem 0;">5
                                    &ndash; 10</div>
                                <div style="font-size: 0.76rem; color: #059669; font-weight: 600;">State High Schools
                                </div>
                            </div>
                            <div
                                style="width: 44px; height: 44px; border-radius: 10px; background: #ecfdf5; color: #059669; display: flex; align-items: center; justify-content: center; font-size: 1.4rem;">
                                🎯
                            </div>
                        </div>

                        <div
                            style="background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
                            <div>
                                <div
                                    style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">
                                    Coordinator Access</div>
                                <div
                                    style="font-size: 1.02rem; font-weight: 800; color: <?php echo canManagePapers() ? '#6b21a8' : '#2563eb'; ?>; margin: 0.35rem 0;">
                                    <?php echo canManagePapers() ? 'Upload Permitted 📤' : 'View & Download 👁️'; ?>
                                </div>
                                <div style="font-size: 0.76rem; color: #64748b; font-weight: 600;">Exam Materials
                                    Console</div>
                            </div>
                            <div
                                style="width: 44px; height: 44px; border-radius: 10px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 1.4rem;">
                                🛡️
                            </div>
                        </div>
                    </div>

                    <!-- Published Prelims Question Papers Section -->
                    <div id="papers-section" class="table-card" style="border-top: 4px solid #8b5cf6;">
                        <div class="table-card-header"
                            style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                            <div>
                                <h3 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0 0 0.25rem;">
                                    📄 Published Prelims Question Papers Repository
                                </h3>
                                <p style="font-size: 0.82rem; color: #64748b; margin: 0;">
                                    Official examination papers repository for coordinators to assist school teachers
                                    and check syllabus files
                                </p>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span class="badge"
                                    style="background: #f5f3ff; color: #7c3aed; font-weight: 700; border: 1px solid #ddd6fe; padding: 6px 12px; font-size: 0.82rem;">
                                    <?php echo count($questionPapers); ?> Available
                                </span>
                                <?php if (canManagePapers()): ?>
                                    <button type="button" onclick="openUploadPaperModal()" class="btn"
                                        style="background: linear-gradient(135deg, #7c3aed, #6d28d9); color: white; border: none; padding: 7px 15px; font-size: 0.82rem; font-weight: 700; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 8px rgba(124,58,237,0.25);">
                                        📤 Upload Question Paper
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div style="padding: 1.5rem;">
                            <?php if (empty($questionPapers)): ?>
                                <div style="padding: 3rem 2rem; text-align: center; color: #64748b;">
                                    <div style="font-size: 2.5rem; margin-bottom: 0.75rem;">📂</div>
                                    <strong
                                        style="font-size: 1rem; color: #1e293b; display: block; margin-bottom: 0.25rem;">No
                                        question papers have been published yet</strong>
                                    <span style="font-size: 0.85rem; color: #64748b;">
                                        When examination question papers are published, they will be accessible here for
                                        downloading and field distribution.
                                    </span>
                                    <?php if (canManagePapers()): ?>
                                        <div style="margin-top: 1.25rem;">
                                            <button type="button" onclick="openUploadPaperModal()" class="btn btn-primary"
                                                style="background: #7c3aed; border: none; font-size: 0.88rem; font-weight: 700; padding: 8px 18px; border-radius: 8px; cursor: pointer;">
                                                📤 Upload Question Paper Now
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div
                                    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
                                    <?php foreach ($questionPapers as $paper): ?>
                                        <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; display: flex; justify-content: space-between; align-items: center; gap: 12px; transition: all 0.2s ease;"
                                            onmouseover="this.style.borderColor='#8b5cf6'; this.style.boxShadow='0 4px 12px rgba(139,92,246,0.1)'"
                                            onmouseout="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none'">
                                            <div>
                                                <div style="font-weight: 800; color: #0f172a; font-size: 0.98rem;">
                                                    <?php echo htmlspecialchars($paper['title']); ?>
                                                </div>
                                                <div
                                                    style="font-size: 0.78rem; color: #64748b; margin-top: 6px; display: flex; align-items: center; gap: 8px;">
                                                    <span
                                                        style="background: #eff6ff; color: #1e40af; padding: 2px 7px; border-radius: 4px; font-weight: 700;">Class
                                                        <?php echo htmlspecialchars($paper['class']); ?></span>
                                                    <span
                                                        style="background: #f1f5f9; color: #475569; padding: 2px 7px; border-radius: 4px; font-weight: 600;"><?php echo htmlspecialchars($paper['subject'] ?? 'General'); ?></span>
                                                </div>
                                            </div>
                                            <a href="../school/download_paper.php?id=<?php echo $paper['id']; ?>" class="btn"
                                                style="background: #7c3aed; color: white; padding: 8px 14px; font-size: 0.82rem; border-radius: 8px; text-decoration: none; font-weight: 700; white-space: nowrap; display: inline-flex; align-items: center; gap: 6px;">
                                                ⬇️ Download
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div> <!-- End #tabPanelPrelims -->

                <!-- TAB PANEL 3: SCHOOLS, MANDALS & DISTRICTS DIRECTORY -->
                <div id="tabPanelSchools" style="<?php echo $activeTab === 'schools' ? 'display: block;' : 'display: none;'; ?>">
                    
                    <!-- View-Only Mode Banner -->
                    <div style="background: #eff6ff; border: 1.5px solid #bfdbfe; color: #1e40af; padding: 12px 18px; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-size: 1.25rem;">👁️</span>
                            <div>
                                <strong style="font-size: 0.92rem;">Directory View-Only Access:</strong>
                                <span style="font-size: 0.84rem; display: block; margin-top: 2px; color: #3b82f6;">
                                    Browse, search, and lookup revenue districts, mandals, schools, and unique UDISE codes.
                                </span>
                            </div>
                        </div>
                        <span style="font-size: 0.74rem; background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; padding: 4px 10px; border-radius: 20px; font-weight: 700; white-space: nowrap;">
                            Read-Only Mode
                        </span>
                    </div>

                    <!-- 4 KPI Cards for Schools Directory -->
                    <div class="kpi-grid">
                        <div class="kpi-card" style="border-top: 4px solid #db2777;">
                            <div>
                                <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">1. Districts</div>
                                <div style="font-size: 2.1rem; font-weight: 800; color: #831843; margin: 0.2rem 0;"><?php echo $totalDistrictsCount; ?></div>
                                <div style="font-size: 0.78rem; color: #db2777; font-weight: 600;">Revenue Districts Covered</div>
                            </div>
                            <div class="kpi-icon" style="background: #fdf2f8; color: #db2777;">🏛️</div>
                        </div>

                        <div class="kpi-card" style="border-top: 4px solid #059669;">
                            <div>
                                <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">2. Taluks / Mandals</div>
                                <div style="font-size: 2.1rem; font-weight: 800; color: #064e3b; margin: 0.2rem 0;"><?php echo number_format($totalMandalsCount); ?></div>
                                <div style="font-size: 0.78rem; color: #059669; font-weight: 600;">Block / Mandal Clusters</div>
                            </div>
                            <div class="kpi-icon" style="background: #ecfdf5; color: #059669;">🗺️</div>
                        </div>

                        <div class="kpi-card" style="border-top: 4px solid #2563eb;">
                            <div>
                                <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">3. Master Schools</div>
                                <div style="font-size: 2.1rem; font-weight: 800; color: #1e3a8a; margin: 0.2rem 0;"><?php echo number_format($totalMasterSchools); ?></div>
                                <div style="font-size: 0.78rem; color: #2563eb; font-weight: 600;">Total AP Govt Schools</div>
                            </div>
                            <div class="kpi-icon" style="background: #eff6ff; color: #2563eb;">🏫</div>
                        </div>

                        <div class="kpi-card" style="border-top: 4px solid #7c3aed;">
                            <div>
                                <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">Enrolled Schools</div>
                                <div style="font-size: 2.1rem; font-weight: 800; color: #4c1d95; margin: 0.2rem 0;"><?php echo number_format($totalRegisteredMaster); ?></div>
                                <div style="font-size: 0.78rem; color: #7c3aed; font-weight: 600;"><?php echo number_format($totalMasterSchools - $totalRegisteredMaster); ?> Open for Registration</div>
                            </div>
                            <div class="kpi-icon" style="background: #f5f3ff; color: #7c3aed;">🎓</div>
                        </div>
                    </div>

                    <!-- Search & Filter Toolbar -->
                    <div class="filter-toolbar" style="margin-bottom: 1.5rem;">
                        <form method="GET" action="dashboard.php" class="filter-row">
                            <input type="hidden" name="tab" value="schools">
                            
                            <div class="form-group" style="margin-bottom: 0; flex: 1 1 200px; min-width: 160px;">
                                <label class="form-label" style="font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem; display: block;">Filter District</label>
                                <select name="schools_district" class="form-select" style="height: 40px; font-size: 0.88rem; width: 100%;" onchange="this.form.submit()">
                                    <option value="">All Districts (28)</option>
                                    <?php foreach ($districtsSummary as $distItem): ?>
                                        <option value="<?php echo htmlspecialchars($distItem['district']); ?>" <?php echo $schoolDistrict === $distItem['district'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($distItem['district']); ?> (<?php echo $distItem['total_schools']; ?> Schools)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group" style="margin-bottom: 0; flex: 2 1 280px; min-width: 200px;">
                                <label class="form-label" style="font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem; display: block;">Search Directory</label>
                                <input type="text" name="schools_search" class="form-input" style="height: 40px; font-size: 0.88rem; width: 100%;" placeholder="Search school name, UDISE code, mandal..." value="<?php echo htmlspecialchars($schoolSearch); ?>">
                            </div>

                            <div style="display: flex; gap: 8px; align-items: flex-end; flex-shrink: 0;">
                                <button type="submit" class="btn btn-primary" style="height: 40px; padding: 0 1.25rem; background: #059669; border: none; white-space: nowrap; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                                    🔍 Search
                                </button>
                                <?php if ($schoolDistrict !== '' || $schoolSearch !== '' || $schoolMandal !== ''): ?>
                                    <a href="dashboard.php?tab=schools" class="btn" style="height: 40px; padding: 0 1rem; background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; white-space: nowrap; font-weight: 600; display: inline-flex; align-items: center;">
                                        Reset
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>

                    <!-- Directory Tables View -->
                    <?php if ($schoolSearch !== '' || ($schoolDistrict !== '' && $schoolMandal !== '')): ?>
                        <!-- SCHOOLS RESULTS TABLE -->
                        <div class="table-card">
                            <div class="table-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                <div>
                                    <h3 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0 0 0.25rem;">
                                        🏫 Schools Directory (<?php echo count($schoolsList); ?> Results)
                                    </h3>
                                    <span style="font-size: 0.82rem; color: #64748b;">
                                        <?php if ($schoolDistrict): ?>District: <strong><?php echo htmlspecialchars($schoolDistrict); ?></strong><?php endif; ?>
                                        <?php if ($schoolMandal): ?> | Mandal: <strong><?php echo htmlspecialchars($schoolMandal); ?></strong><?php endif; ?>
                                        <?php if ($schoolSearch): ?> | Query: "<strong><?php echo htmlspecialchars($schoolSearch); ?></strong>"<?php endif; ?>
                                    </span>
                                </div>
                                <a href="dashboard.php?tab=schools<?php echo $schoolDistrict ? '&schools_district=' . urlencode($schoolDistrict) : ''; ?>" class="btn" style="padding: 6px 14px; font-size: 0.82rem; font-weight: 700; background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; border-radius: 8px;">
                                    &larr; Back to Mandals
                                </a>
                            </div>
                            <div style="overflow-x: auto;">
                                <table class="data-table" style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                                            <th style="padding: 12px 16px; text-align: left; font-size: 0.82rem; font-weight: 700; color: #475569;">UDISE Code</th>
                                            <th style="padding: 12px 16px; text-align: left; font-size: 0.82rem; font-weight: 700; color: #475569;">School Name</th>
                                            <th style="padding: 12px 16px; text-align: left; font-size: 0.82rem; font-weight: 700; color: #475569;">Mandal</th>
                                            <th style="padding: 12px 16px; text-align: left; font-size: 0.82rem; font-weight: 700; color: #475569;">District</th>
                                            <th style="padding: 12px 16px; text-align: center; font-size: 0.82rem; font-weight: 700; color: #475569;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($schoolsList)): ?>
                                            <tr>
                                                <td colspan="5" style="text-align: center; padding: 2.5rem; color: #64748b;">
                                                    No schools found matching your search criteria.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($schoolsList as $sch): ?>
                                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                                    <td style="padding: 12px 16px;">
                                                        <span class="badge" style="background: #f1f5f9; color: #0f172a; font-family: monospace; font-weight: 700; border: 1px solid #cbd5e1;">
                                                            <?php echo htmlspecialchars($sch['unique_code']); ?>
                                                        </span>
                                                    </td>
                                                    <td style="padding: 12px 16px; font-weight: 700; color: #0f172a;">
                                                        <?php echo htmlspecialchars($sch['school_name']); ?>
                                                    </td>
                                                    <td style="padding: 12px 16px; color: #475569; font-weight: 600;">
                                                        <?php echo htmlspecialchars($sch['mandal']); ?>
                                                    </td>
                                                    <td style="padding: 12px 16px; color: #64748b;">
                                                        <?php echo htmlspecialchars($sch['district']); ?>
                                                    </td>
                                                    <td style="padding: 12px 16px; text-align: center;">
                                                        <?php if ((int)$sch['is_registered'] === 1): ?>
                                                            <span class="badge" style="background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; font-weight: 700;">
                                                                Enrolled
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge" style="background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; font-weight: 600;">
                                                                Available
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    <?php elseif ($schoolDistrict !== ''): ?>
                        <!-- MANDALS IN SELECTED DISTRICT -->
                        <div class="table-card">
                            <div class="table-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                <div>
                                    <h3 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0 0 0.25rem;">
                                        🗺️ Mandals in <?php echo htmlspecialchars($schoolDistrict); ?> (<?php echo count($mandalsSummary); ?> Mandals)
                                    </h3>
                                    <span style="font-size: 0.82rem; color: #64748b;">Click "View Schools" to list all registered and available schools in that mandal block</span>
                                </div>
                                <a href="dashboard.php?tab=schools" class="btn" style="padding: 6px 14px; font-size: 0.82rem; font-weight: 700; background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; border-radius: 8px;">
                                    &larr; All Districts
                                </a>
                            </div>
                            <div style="overflow-x: auto;">
                                <table class="data-table" style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                                            <th style="padding: 12px 16px; text-align: left; font-size: 0.82rem; font-weight: 700; color: #475569;">#</th>
                                            <th style="padding: 12px 16px; text-align: left; font-size: 0.82rem; font-weight: 700; color: #475569;">Mandal / Block</th>
                                            <th style="padding: 12px 16px; text-align: left; font-size: 0.82rem; font-weight: 700; color: #475569;">District</th>
                                            <th style="padding: 12px 16px; text-align: center; font-size: 0.82rem; font-weight: 700; color: #475569;">Total Schools</th>
                                            <th style="padding: 12px 16px; text-align: center; font-size: 0.82rem; font-weight: 700; color: #475569;">Enrolled Schools</th>
                                            <th style="padding: 12px 16px; text-align: right; font-size: 0.82rem; font-weight: 700; color: #475569;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($mandalsSummary as $idx => $m): ?>
                                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                                <td style="padding: 12px 16px; color: #94a3b8; font-weight: 600;"><?php echo $idx + 1; ?></td>
                                                <td style="padding: 12px 16px; font-weight: 800; color: #0f172a; font-size: 0.95rem;">
                                                    <?php echo htmlspecialchars($m['mandal']); ?>
                                                </td>
                                                <td style="padding: 12px 16px; color: #64748b; font-weight: 600;">
                                                    <?php echo htmlspecialchars($m['district']); ?>
                                                </td>
                                                <td style="padding: 12px 16px; text-align: center;">
                                                    <span class="badge" style="background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; font-weight: 700;">
                                                        🏫 <?php echo number_format($m['total_schools']); ?>
                                                    </span>
                                                </td>
                                                <td style="padding: 12px 16px; text-align: center;">
                                                    <?php if ((int)$m['registered_schools'] > 0): ?>
                                                        <span class="badge" style="background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; font-weight: 700;">
                                                            🎓 <?php echo $m['registered_schools']; ?> Enrolled
                                                        </span>
                                                    <?php else: ?>
                                                        <span style="color: #94a3b8; font-size: 0.82rem;">0</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding: 12px 16px; text-align: right;">
                                                    <a href="dashboard.php?tab=schools&schools_district=<?php echo urlencode($schoolDistrict); ?>&schools_mandal=<?php echo urlencode($m['mandal']); ?>" class="btn" style="background: #2563eb; color: white; padding: 6px 12px; font-size: 0.8rem; font-weight: 700; border-radius: 7px; text-decoration: none;">
                                                        🔍 View Schools &rarr;
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- ALL 28 DISTRICTS TABLE -->
                        <div class="table-card">
                            <div class="table-card-header">
                                <h3 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0 0 0.25rem;">
                                    🏛️ Andhra Pradesh Revenue Districts (28)
                                </h3>
                                <span style="font-size: 0.82rem; color: #64748b;">
                                    Click <strong>"View Mandals"</strong> to drill down into sub-district blocks and school registries
                                </span>
                            </div>
                            <div style="overflow-x: auto;">
                                <table class="data-table" style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                                            <th style="padding: 12px 16px; text-align: left; font-size: 0.82rem; font-weight: 700; color: #475569;">#</th>
                                            <th style="padding: 12px 16px; text-align: left; font-size: 0.82rem; font-weight: 700; color: #475569;">District Name</th>
                                            <th style="padding: 12px 16px; text-align: center; font-size: 0.82rem; font-weight: 700; color: #475569;">Total Mandals</th>
                                            <th style="padding: 12px 16px; text-align: center; font-size: 0.82rem; font-weight: 700; color: #475569;">Total Schools</th>
                                            <th style="padding: 12px 16px; text-align: center; font-size: 0.82rem; font-weight: 700; color: #475569;">Enrolled Schools</th>
                                            <th style="padding: 12px 16px; text-align: right; font-size: 0.82rem; font-weight: 700; color: #475569;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($districtsSummary as $idx => $d): ?>
                                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                                <td style="padding: 12px 16px; color: #94a3b8; font-weight: 600;"><?php echo $idx + 1; ?></td>
                                                <td style="padding: 12px 16px; font-weight: 800; color: #0f172a; font-size: 0.95rem;">
                                                    <?php echo htmlspecialchars($d['district']); ?>
                                                </td>
                                                <td style="padding: 12px 16px; text-align: center;">
                                                    <span class="badge" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; font-weight: 700;">
                                                        🗺️ <?php echo $d['total_mandals']; ?> Mandals
                                                    </span>
                                                </td>
                                                <td style="padding: 12px 16px; text-align: center;">
                                                    <span class="badge" style="background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; font-weight: 700;">
                                                        🏫 <?php echo number_format($d['total_schools']); ?> Schools
                                                    </span>
                                                </td>
                                                <td style="padding: 12px 16px; text-align: center;">
                                                    <?php if ((int)$d['registered_schools'] > 0): ?>
                                                        <span class="badge" style="background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; font-weight: 700;">
                                                            🎓 <?php echo $d['registered_schools']; ?> Enrolled
                                                        </span>
                                                    <?php else: ?>
                                                        <span style="color: #94a3b8; font-size: 0.82rem;">0</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding: 12px 16px; text-align: right;">
                                                    <a href="dashboard.php?tab=schools&schools_district=<?php echo urlencode($d['district']); ?>" class="btn" style="background: #059669; color: white; padding: 6px 14px; font-size: 0.82rem; font-weight: 700; border-radius: 7px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
                                                        🔍 View Mandals &rarr;
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                </div> <!-- End #tabPanelSchools -->

            </div> <!-- End .admin-content -->
        </main> <!-- End .admin-main -->

    </div> <!-- End .admin-shell -->

    <!-- MODAL: VIEW SCHOOL REGISTRATION DETAILS -->
    <div id="viewRegModal" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: #ffffff; border-radius: 18px; width: 100%; max-width: 650px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); overflow: hidden; display: flex; flex-direction: column; animation: modalSlide 0.2s ease-out;">
            <!-- Header -->
            <div style="padding: 1.25rem 1.75rem; background: linear-gradient(135deg, #f8fafc, #ecfdf5); border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 40px; height: 40px; border-radius: 10px; background: #ecfdf5; color: #059669; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; border: 1px solid #a7f3d0;">
                        🏫
                    </div>
                    <div>
                        <h3 id="view_school_title" style="margin: 0; font-size: 1.12rem; font-weight: 800; color: #0f172a; line-height: 1.3;">School Details</h3>
                        <div style="display: flex; align-items: center; gap: 8px; margin-top: 3px;">
                            <span id="view_ref_badge" class="badge" style="background: #eff6ff; color: #1e40af; font-weight: 700; font-family: monospace; font-size: 0.78rem;"></span>
                            <span id="view_status_badge" class="badge" style="font-size: 0.75rem; text-transform: uppercase;"></span>
                        </div>
                    </div>
                </div>
                <button type="button" onclick="closeViewRegModal()" style="background: transparent; border: none; font-size: 1.5rem; color: #94a3b8; cursor: pointer; padding: 4px; line-height: 1;" onmouseover="this.style.color='#0f172a'" onmouseout="this.style.color='#94a3b8'">&times;</button>
            </div>

            <!-- Body -->
            <div style="padding: 1.5rem 1.75rem; display: grid; grid-template-columns: 1fr 1fr; gap: 14px; max-height: 70vh; overflow-y: auto;">
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px;">
                    <div style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase;">UDISE Code</div>
                    <div id="view_udise" style="font-weight: 700; color: #0284c7; font-size: 0.95rem; margin-top: 2px;"></div>
                </div>

                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px;">
                    <div style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Location</div>
                    <div id="view_location" style="font-weight: 700; color: #334155; font-size: 0.92rem; margin-top: 2px;"></div>
                </div>

                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px;">
                    <div style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Contact Person</div>
                    <div id="view_contact_person" style="font-weight: 700; color: #0f172a; font-size: 0.92rem; margin-top: 2px;"></div>
                    <div id="view_designation" style="font-size: 0.78rem; color: #64748b; margin-top: 2px;"></div>
                </div>

                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px;">
                    <div style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Phone Number</div>
                    <div style="margin-top: 3px;">
                        <a id="view_phone_link" href="#" style="font-weight: 700; color: #2563eb; text-decoration: none; font-size: 0.95rem;"></a>
                    </div>
                    <div style="margin-top: 4px;">
                        <a id="view_wa_link" href="#" target="_blank" style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; font-size: 0.72rem; font-weight: 700; background: #25d366; color: #fff; border-radius: 6px; text-decoration: none;">💬 WhatsApp</a>
                    </div>
                </div>

                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px; grid-column: span 2;">
                    <div style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Email Address</div>
                    <div id="view_email" style="font-weight: 600; color: #334155; font-size: 0.9rem; margin-top: 2px;"></div>
                </div>

                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px; grid-column: span 2;">
                    <div style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Registration Timestamp</div>
                    <div id="view_created_at" style="font-size: 0.85rem; color: #64748b; margin-top: 2px;"></div>
                </div>
            </div>

            <!-- Footer -->
            <div style="padding: 1rem 1.75rem; background: #ffffff; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                <a id="view_slip_link" href="#" target="_blank" class="btn" style="padding: 8px 14px; font-size: 0.82rem; font-weight: 700; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; text-decoration: none; border-radius: 8px;">
                    📄 View Official Slip
                </a>
                <div style="display: flex; gap: 8px;">
                    <?php if ($canEditRegs): ?>
                        <button type="button" id="view_to_edit_btn" class="btn btn-primary" style="padding: 8px 16px; font-size: 0.82rem; font-weight: 700; border-radius: 8px; background: #059669; border: none; color: #ffffff;">
                            ✏️ Edit Record
                        </button>
                    <?php endif; ?>
                    <button type="button" onclick="closeViewRegModal()" class="btn" style="padding: 8px 14px; font-size: 0.82rem; font-weight: 600; background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; border-radius: 8px;">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: EDIT SCHOOL REGISTRATION (FOR COORDINATOR) -->
    <?php if ($canEditRegs): ?>
    <div id="editRegModal" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: #ffffff; border-radius: 18px; width: 100%; max-width: 650px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); overflow: hidden; display: flex; flex-direction: column; animation: modalSlide 0.2s ease-out;">
            <div style="padding: 1.25rem 1.75rem; background: linear-gradient(135deg, #f8fafc, #ecfdf5); border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 1.25rem;">✏️</span>
                    <div>
                        <h3 style="margin: 0; font-size: 1.12rem; font-weight: 800; color: #0f172a;">Edit School Registration</h3>
                        <span id="edit_reg_ref_display" style="font-size: 0.78rem; font-family: monospace; color: #059669; font-weight: 700;"></span>
                    </div>
                </div>
                <button type="button" onclick="closeEditRegModal()" style="background: transparent; border: none; font-size: 1.5rem; color: #94a3b8; cursor: pointer; padding: 4px; line-height: 1;" onmouseover="this.style.color='#0f172a'" onmouseout="this.style.color='#94a3b8'">&times;</button>
            </div>

            <form method="POST" action="dashboard.php" style="padding: 1.5rem 1.75rem; display: flex; flex-direction: column; gap: 14px; max-height: 75vh; overflow-y: auto;">
                <input type="hidden" name="edit_reg" value="1">
                <input type="hidden" id="edit_reg_id" name="reg_id" value="">

                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">School Name *</label>
                    <input type="text" id="edit_reg_school_name" name="school_name" class="form-input" style="width: 100%; height: 40px; border-radius: 8px; font-size: 0.9rem;" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div>
                        <label style="font-size: 0.8rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">UDISE Code *</label>
                        <input type="text" id="edit_reg_unique_code" name="unique_code" class="form-input" style="width: 100%; height: 40px; border-radius: 8px; font-size: 0.9rem;" required>
                    </div>
                    <div>
                        <label style="font-size: 0.8rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Status *</label>
                        <select id="edit_reg_status" name="status" class="form-select" style="width: 100%; height: 40px; border-radius: 8px; font-size: 0.9rem; font-weight: 700;">
                            <option value="submitted">Submitted</option>
                            <option value="contacted">Contacted</option>
                            <option value="verified">Verified</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div>
                        <label style="font-size: 0.8rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">District <?php echo !empty($assignedDistrict) ? '(Assigned)' : '*'; ?></label>
                        <input type="text" id="edit_reg_district" name="district" class="form-input" style="width: 100%; height: 40px; border-radius: 8px; font-size: 0.9rem;" <?php echo !empty($assignedDistrict) ? 'readonly style="background:#f8fafc;"' : 'required'; ?>>
                    </div>
                    <div>
                        <label style="font-size: 0.8rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Mandal *</label>
                        <input type="text" id="edit_reg_mandal" name="mandal" class="form-input" style="width: 100%; height: 40px; border-radius: 8px; font-size: 0.9rem;" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div>
                        <label style="font-size: 0.8rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Contact Person *</label>
                        <input type="text" id="edit_reg_contact_person" name="contact_person" class="form-input" style="width: 100%; height: 40px; border-radius: 8px; font-size: 0.9rem;" required>
                    </div>
                    <div>
                        <label style="font-size: 0.8rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Designation</label>
                        <input type="text" id="edit_reg_designation" name="designation" class="form-input" style="width: 100%; height: 40px; border-radius: 8px; font-size: 0.9rem;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div>
                        <label style="font-size: 0.8rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Mobile Number *</label>
                        <input type="tel" id="edit_reg_mobile" name="mobile" pattern="[0-9]{10}" class="form-input" style="width: 100%; height: 40px; border-radius: 8px; font-size: 0.9rem;" required>
                    </div>
                    <div>
                        <label style="font-size: 0.8rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Email Address</label>
                        <input type="email" id="edit_reg_email" name="email" class="form-input" style="width: 100%; height: 40px; border-radius: 8px; font-size: 0.9rem;">
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px; padding-top: 12px; border-top: 1px solid #e2e8f0;">
                    <button type="button" onclick="closeEditRegModal()" class="btn" style="height: 40px; padding: 0 16px; font-size: 0.85rem; font-weight: 600; background: #ffffff; color: #475569; border: 1.5px solid #cbd5e1; border-radius: 8px;">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" style="height: 40px; padding: 0 20px; font-size: 0.85rem; font-weight: 700; border-radius: 8px; background: #059669; color: #ffffff; border: none;">
                        💾 Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        let currentViewingRow = null;

        function openViewRegModal(row) {
            currentViewingRow = row;
            document.getElementById('view_school_title').textContent = row.school_name || 'School Record';
            document.getElementById('view_ref_badge').textContent = row.reference_id || '';
            
            const stBadge = document.getElementById('view_status_badge');
            const st = (row.status || 'submitted').toLowerCase();
            stBadge.textContent = st;
            if (st === 'verified') {
                stBadge.style.background = '#ecfdf5'; stBadge.style.color = '#047857'; stBadge.style.border = '1px solid #a7f3d0';
            } else if (st === 'contacted') {
                stBadge.style.background = '#eff6ff'; stBadge.style.color = '#1d4ed8'; stBadge.style.border = '1px solid #bfdbfe';
            } else if (st === 'completed') {
                stBadge.style.background = '#f5f3ff'; stBadge.style.color = '#7c3aed'; stBadge.style.border = '1px solid #ddd6fe';
            } else {
                stBadge.style.background = '#f8fafc'; stBadge.style.color = '#475569'; stBadge.style.border = '1px solid #cbd5e1';
            }
            
            document.getElementById('view_udise').textContent = row.unique_code || 'N/A';
            document.getElementById('view_location').textContent = (row.district || '') + ' • ' + (row.mandal || '');
            document.getElementById('view_contact_person').textContent = row.contact_person || 'N/A';
            document.getElementById('view_designation').textContent = row.designation || 'Headmaster';
            
            const phoneEl = document.getElementById('view_phone_link');
            phoneEl.textContent = row.mobile || 'No phone';
            phoneEl.href = 'tel:' + (row.mobile || '');
            
            const rawDigits = (row.mobile || '').replace(/[^0-9]/g, '');
            const cleanPhone = rawDigits.length === 10 ? '91' + rawDigits : rawDigits;
            const waText = encodeURIComponent("Respected Headmaster, Greetings from EEMT 2027 Coordinator regarding your registration (" + (row.reference_id || '') + ").");
            document.getElementById('view_wa_link').href = 'https://api.whatsapp.com/send?phone=' + cleanPhone + '&text=' + waText;
            
            document.getElementById('view_email').textContent = row.email || 'N/A';
            document.getElementById('view_created_at').textContent = row.created_at || 'N/A';
            document.getElementById('view_slip_link').href = '../register_success.php?ref=' + encodeURIComponent(row.reference_id || '');
            
            const editBtn = document.getElementById('view_to_edit_btn');
            if (editBtn) {
                editBtn.onclick = function() {
                    closeViewRegModal();
                    openEditRegModal(row);
                };
            }

            document.getElementById('viewRegModal').style.display = 'flex';
        }

        function closeViewRegModal() {
            document.getElementById('viewRegModal').style.display = 'none';
        }

        function openEditRegModal(row) {
            const editModal = document.getElementById('editRegModal');
            if (!editModal) return;

            document.getElementById('edit_reg_id').value = row.id || '';
            document.getElementById('edit_reg_ref_display').textContent = 'Ref ID: ' + (row.reference_id || '');
            document.getElementById('edit_reg_school_name').value = row.school_name || '';
            document.getElementById('edit_reg_unique_code').value = row.unique_code || '';
            document.getElementById('edit_reg_status').value = row.status || 'submitted';
            document.getElementById('edit_reg_district').value = row.district || '';
            document.getElementById('edit_reg_mandal').value = row.mandal || '';
            document.getElementById('edit_reg_contact_person').value = row.contact_person || '';
            document.getElementById('edit_reg_designation').value = row.designation || '';
            document.getElementById('edit_reg_mobile').value = row.mobile || '';
            document.getElementById('edit_reg_email').value = row.email || '';

            editModal.style.display = 'flex';
        }

        function closeEditRegModal() {
            const editModal = document.getElementById('editRegModal');
            if (editModal) editModal.style.display = 'none';
        }

        function openUploadPaperModal() {
            const m = document.getElementById('uploadPaperModal');
            if (m) m.style.display = 'flex';
        }

        function closeUploadPaperModal() {
            const m = document.getElementById('uploadPaperModal');
            if (m) m.style.display = 'none';
        }

        window.addEventListener('click', function(e) {
            if (e.target.id === 'viewRegModal') closeViewRegModal();
            if (e.target.id === 'editRegModal') closeEditRegModal();
            if (e.target.id === 'uploadPaperModal') closeUploadPaperModal();
        });

        // Tab Switching Logic
        function switchDashboardTab(tabName) {
            const regPanel = document.getElementById('tabPanelRegistrations');
            const prelimsPanel = document.getElementById('tabPanelPrelims');
            const schoolsPanel = document.getElementById('tabPanelSchools');
            const sidebarReg = document.getElementById('sidebarLinkRegistrations');
            const sidebarPrelims = document.getElementById('sidebarLinkPrelims');
            const sidebarSchools = document.getElementById('sidebarLinkSchools');
            const topbarTitle = document.querySelector('.topbar-title span');

            if (sidebarReg) sidebarReg.classList.remove('active');
            if (sidebarPrelims) sidebarPrelims.classList.remove('active');
            if (sidebarSchools) sidebarSchools.classList.remove('active');

            if (tabName === 'schools') {
                if (regPanel) regPanel.style.display = 'none';
                if (prelimsPanel) prelimsPanel.style.display = 'none';
                if (schoolsPanel) schoolsPanel.style.display = 'block';

                if (sidebarSchools) sidebarSchools.classList.add('active');
                if (topbarTitle) topbarTitle.textContent = 'Schools, Mandals & Districts Directory';

                try {
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', 'schools');
                    window.history.replaceState({}, '', url);
                } catch (e) { }
            } else if (tabName === 'prelims') {
                if (regPanel) regPanel.style.display = 'none';
                if (schoolsPanel) schoolsPanel.style.display = 'none';
                if (prelimsPanel) prelimsPanel.style.display = 'block';

                if (sidebarPrelims) sidebarPrelims.classList.add('active');
                if (topbarTitle) topbarTitle.textContent = 'Prelims Question Papers Repository';

                try {
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', 'prelims');
                    window.history.replaceState({}, '', url);
                } catch (e) { }
            } else {
                if (prelimsPanel) prelimsPanel.style.display = 'none';
                if (schoolsPanel) schoolsPanel.style.display = 'none';
                if (regPanel) regPanel.style.display = 'block';

                if (sidebarReg) sidebarReg.classList.add('active');
                if (topbarTitle) topbarTitle.textContent = 'Field Operations & School Verification Roster';

                try {
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', 'registrations');
                    window.history.replaceState({}, '', url);
                } catch (e) { }
            }
        }

        // Auto-switch on page load
        document.addEventListener('DOMContentLoaded', () => {
            try {
                const urlParams = new URLSearchParams(window.location.search);
                const reqTab = urlParams.get('tab');
                if (reqTab === 'schools' || urlParams.get('schools_district') || urlParams.get('schools_search')) {
                    switchDashboardTab('schools');
                } else if (reqTab === 'prelims' || window.location.hash === '#prelims' || window.location.hash === '#papers-section') {
                    switchDashboardTab('prelims');
                }
            } catch (e) { }

            // Sidebar Toggle on Mobile
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

    <!-- MODAL: UPLOAD PRELIMS QUESTION PAPER -->
    <?php if (canManagePapers()): ?>
    <div id="uploadPaperModal" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: #ffffff; border-radius: 18px; width: 100%; max-width: 540px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); overflow: hidden; display: flex; flex-direction: column;">
            <div style="padding: 1.25rem 1.75rem; background: linear-gradient(135deg, #f5f3ff, #ede9fe); border-bottom: 1px solid #ddd6fe; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 1.3rem;">📤</span>
                    <div>
                        <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #4c1d95;">Upload Prelims Question Paper</h3>
                        <span style="font-size: 0.78rem; color: #6d28d9;">Publish exam question paper PDF for schools</span>
                    </div>
                </div>
                <button type="button" onclick="closeUploadPaperModal()" style="background: transparent; border: none; font-size: 1.5rem; color: #8b5cf6; cursor: pointer;">&times;</button>
            </div>
            <form method="POST" action="dashboard.php?tab=prelims" enctype="multipart/form-data" style="padding: 1.5rem 1.75rem; display: flex; flex-direction: column; gap: 14px;">
                <input type="hidden" name="upload_paper" value="1">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 0.85rem; color: #334155; margin-bottom: 4px; display: block;">Paper Title *</label>
                    <input type="text" name="title" required class="form-input" placeholder="e.g. Class 10 State Prelims Mathematics Paper 1" style="height: 42px; width: 100%;">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 0.85rem; color: #334155; margin-bottom: 4px; display: block;">Target Class *</label>
                        <select name="class" required class="form-select" style="height: 42px; width: 100%;">
                            <option value="">Select Class</option>
                            <option value="5">Class 5</option>
                            <option value="6">Class 6</option>
                            <option value="7">Class 7</option>
                            <option value="8">Class 8</option>
                            <option value="9">Class 9</option>
                            <option value="10">Class 10</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 0.85rem; color: #334155; margin-bottom: 4px; display: block;">Subject *</label>
                        <select name="subject" required class="form-select" style="height: 42px; width: 100%;">
                            <option value="Mathematics">Mathematics</option>
                            <option value="Physical Science">Physical Science</option>
                            <option value="Biological Science">Biological Science</option>
                            <option value="Social Studies">Social Studies</option>
                            <option value="English">English</option>
                            <option value="Telugu">Telugu</option>
                            <option value="Hindi">Hindi</option>
                            <option value="General">General Knowledge</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 0.85rem; color: #334155; margin-bottom: 4px; display: block;">Question Paper PDF *</label>
                    <input type="file" name="paper_file" accept=".pdf,application/pdf" required class="form-input" style="height: 42px; padding: 6px; width: 100%;">
                </div>
                <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                    <input type="checkbox" name="is_published" id="is_published_chk" value="1" checked style="width: 16px; height: 16px; accent-color: #7c3aed;">
                    <label for="is_published_chk" style="font-size: 0.85rem; font-weight: 600; color: #334155; cursor: pointer;">Publish immediately for all schools</label>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px; border-top: 1px solid #e2e8f0; padding-top: 14px;">
                    <button type="button" onclick="closeUploadPaperModal()" class="btn" style="padding: 9px 18px; font-weight: 600; background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn" style="padding: 9px 22px; font-weight: 700; background: linear-gradient(135deg, #7c3aed, #6d28d9); color: white; border: none; border-radius: 8px; box-shadow: 0 4px 12px rgba(124,58,237,0.3);">Upload & Publish</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

</body>

</html>