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

// Refresh latest profile info from database
if (!empty($user['id'])) {
    $userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $userStmt->execute([$user['id']]);
    $freshUser = $userStmt->fetch(PDO::FETCH_ASSOC);
    if ($freshUser) {
        $user = array_merge($user, $freshUser);
    }
}

$assignedDistrict = trim($user['assigned_district'] ?? '');
if ($assignedDistrict === 'ALL' || empty($assignedDistrict)) {
    $assignedDistrict = '';
}

// Check user granular permissions
$canViewRegs = canViewRegistrations();
$canEditRegs = canEditRegistrations();
$isViewOnly = !$canEditRegs;

// Helper functions for clean display of school names and roster details
if (!function_exists('formatSchoolTitle')) {
    function formatSchoolTitle($text) {
        if (empty($text)) return '';
        $text = trim($text);
        // Add space before parenthesis if missing (e.g. ROMPICHERLA(BOYS) -> ROMPICHERLA (BOYS))
        $text = preg_replace('/([a-zA-Z0-9])\(/', '$1 (', $text);
        $keepUpper = [
            'ZPHS' => 'ZPHS',
            'HS' => 'HS',
            'MPL' => 'MPL',
            'GOVT' => 'Govt',
            'APMS' => 'APMS',
            'KGBV' => 'KGBV',
            'AP' => 'AP',
            'BC' => 'BC',
            'SC' => 'SC',
            'ST' => 'ST',
            'MPSR' => 'MPSR',
            'BBR' => 'BBR',
            'DR' => 'Dr.',
            'DR.' => 'Dr.',
            'UPS' => 'UPS',
            'PS' => 'PS',
            'ZP' => 'ZP',
            'ZPP' => 'ZPP',
            'MJP' => 'MJP',
            'APREIS' => 'APREIS'
        ];
        $words = preg_split('/\s+/', $text);
        $formatted = [];
        foreach ($words as $w) {
            $wTrim = trim($w);
            if ($wTrim === '') continue;

            if (preg_match('/^\((.+)\)$/', $wTrim, $m)) {
                $inner = $m[1];
                $uInner = strtoupper($inner);
                if (isset($keepUpper[$uInner])) {
                    $formatted[] = '(' . $keepUpper[$uInner] . ')';
                } else {
                    $formatted[] = '(' . ucfirst(strtolower($inner)) . ')';
                }
                continue;
            }

            $upper = strtoupper($wTrim);
            if (isset($keepUpper[$upper])) {
                $formatted[] = $keepUpper[$upper];
            } else {
                if (strpos($wTrim, '.') !== false) {
                    $formatted[] = strtoupper($wTrim);
                } else {
                    $formatted[] = ucfirst(strtolower($wTrim));
                }
            }
        }
        return implode(' ', $formatted);
    }
}

if (!function_exists('formatLocationTitle')) {
    function formatLocationTitle($text) {
        if (empty($text)) return ['name' => '', 'code' => ''];
        $text = trim($text);
        if (preg_match('/^([a-zA-Z\s\.\(\)\&]+)[\s\-]+(\d+)$/', $text, $matches)) {
            $name = trim($matches[1]);
            $code = trim($matches[2]);
            return ['name' => ucwords(strtolower($name)), 'code' => $code];
        }
        return ['name' => ucwords(strtolower($text)), 'code' => ''];
    }
}

if (!function_exists('formatDesignationTitle')) {
    function formatDesignationTitle($text) {
        if (empty($text)) return 'Principal';
        $u = strtoupper(trim($text));
        if (strpos($u, 'HEAD MASTER') !== false || strpos($u, 'HEADMASTER') !== false || $u === 'HM') {
            return 'Headmaster (HM)';
        }
        if (strpos($u, 'PRINCIPAL') !== false) {
            return 'Principal';
        }
        if (strpos($u, 'TEACHER') !== false) {
            return 'Teacher';
        }
        if (strpos($u, 'COORDINATOR') !== false) {
            return 'Coordinator';
        }
        return ucwords(strtolower($text));
    }
}

$statusMessage = '';
$statusType = 'success';

// Handle Coordinator Profile & Password Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_coordinator_profile'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $currentPass = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (empty($name)) {
        $statusMessage = "Full Name is required.";
        $statusType = 'error';
    } else {
        $canUpdate = true;
        $passUpdate = false;

        if (!empty($newPass)) {
            if (empty($currentPass)) {
                $statusMessage = "Please enter your current password to set a new password.";
                $statusType = 'error';
                $canUpdate = false;
            } elseif (!password_verify($currentPass, $user['password_hash'])) {
                $statusMessage = "Current password entered is incorrect.";
                $statusType = 'error';
                $canUpdate = false;
            } elseif (strlen($newPass) < 6) {
                $statusMessage = "New password must be at least 6 characters long.";
                $statusType = 'error';
                $canUpdate = false;
            } elseif ($newPass !== $confirmPass) {
                $statusMessage = "New password and confirm password do not match.";
                $statusType = 'error';
                $canUpdate = false;
            } else {
                $passUpdate = true;
            }
        }

        if ($canUpdate) {
            if ($passUpdate) {
                $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                $updStmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, mobile = ?, password_hash = ? WHERE id = ?");
                $updStmt->execute([$name, $email, $mobile, $newHash, $user['id']]);
                $user['password_hash'] = $newHash;
            } else {
                $updStmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, mobile = ? WHERE id = ?");
                $updStmt->execute([$name, $email, $mobile, $user['id']]);
            }

            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['mobile'] = $mobile;
            $user['name'] = $name;
            $user['email'] = $email;
            $user['mobile'] = $mobile;

            $statusMessage = "Coordinator profile updated successfully!" . ($passUpdate ? " Password was also changed." : "");
            $statusType = 'success';
        }
    }
}

// Handle Status Updates by Staff
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
$activeTab = trim($_GET['tab'] ?? 'profile');
if (!in_array($activeTab, ['profile', 'registrations', 'prelims', 'schools'])) {
    $activeTab = 'profile';
}
if (!$canViewRegs && $activeTab === 'registrations') {
    $activeTab = 'profile';
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
            padding: 0 1.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
            gap: 1rem;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .sidebar-toggle-btn {
            display: none;
            background: none;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 6px 10px;
            cursor: pointer;
            color: #334155;
            flex-shrink: 0;
        }

        .topbar-breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .topbar-breadcrumb .breadcrumb-root {
            color: #64748b;
            font-weight: 600;
            font-size: 0.88rem;
        }

        .topbar-breadcrumb .breadcrumb-sep {
            color: #cbd5e1;
            font-size: 0.82rem;
        }

        .topbar-breadcrumb .breadcrumb-current {
            color: #0f172a;
            font-weight: 800;
            letter-spacing: -0.01em;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .topbar-scope-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
            border-radius: 999px;
            padding: 5px 12px;
            font-size: 0.78rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .topbar-scope-chip .chip-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #16a34a;
            box-shadow: 0 0 0 2px rgba(22, 163, 74, 0.2);
        }

        .topbar-user-pill {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 4px 12px 4px 5px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 999px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .topbar-user-pill:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
            transform: translateY(-1px);
        }

        .topbar-user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 800;
            flex-shrink: 0;
            box-shadow: 0 2px 6px rgba(5, 150, 105, 0.25);
        }

        .topbar-user-details {
            display: flex;
            flex-direction: column;
            text-align: left;
            line-height: 1.15;
        }

        .topbar-user-name {
            font-size: 0.82rem;
            font-weight: 700;
            color: #0f172a;
            max-width: 140px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .topbar-user-sub {
            font-size: 0.68rem;
            font-weight: 600;
            color: #64748b;
        }

        .topbar-logout-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: #ffffff;
            color: #ef4444;
            border: 1px solid #fecaca;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .topbar-logout-btn:hover {
            background: #fef2f2;
            border-color: #ef4444;
            color: #dc2626;
        }

        /* Profile Hero Banner */
        .profile-hero-card {
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 45%, #1e293b 100%);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 20px;
            padding: 2rem;
            color: #ffffff;
            box-shadow: 0 16px 36px -10px rgba(15, 23, 42, 0.35);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .profile-hero-card::before {
            content: '';
            position: absolute;
            top: -60%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.18) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .profile-hero-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-bottom: 1.75rem;
            position: relative;
            z-index: 1;
        }

        .profile-hero-user {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }

        .profile-hero-avatar {
            width: 72px;
            height: 72px;
            border-radius: 18px;
            background: linear-gradient(135deg, #059669 0%, #0d9488 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 800;
            color: #ffffff;
            box-shadow: 0 8px 24px rgba(5, 150, 105, 0.35);
            border: 3px solid rgba(255, 255, 255, 0.2);
            flex-shrink: 0;
            position: relative;
        }

        .profile-hero-avatar .avatar-online-dot {
            position: absolute;
            bottom: -2px;
            right: -2px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #10b981;
            border: 2.5px solid #0f172a;
        }

        .profile-hero-name {
            font-size: 1.6rem;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.02em;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .badge-glass-role {
            background: rgba(99, 102, 241, 0.25);
            border: 1px solid rgba(165, 180, 252, 0.35);
            color: #c7d2fe;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 999px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .badge-glass-active {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(52, 211, 153, 0.4);
            color: #6ee7b7;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .profile-hero-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .hero-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 9px;
            font-size: 0.84rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            border: none;
        }

        .hero-action-btn.btn-white {
            background: #ffffff;
            color: #0f172a;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        .hero-action-btn.btn-white:hover {
            background: #f8fafc;
            transform: translateY(-1px);
        }

        .hero-action-btn.btn-glass {
            background: rgba(255, 255, 255, 0.12);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.22);
            backdrop-filter: blur(8px);
        }
        .hero-action-btn.btn-glass:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.35);
            transform: translateY(-1px);
        }

        /* Hero Info Tiles Grid */
        .profile-tiles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 0.85rem;
            position: relative;
            z-index: 1;
        }

        .profile-info-tile {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 12px;
            padding: 12px 14px;
            backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s ease;
        }

        .profile-info-tile:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.25);
        }

        .tile-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.12);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .tile-meta {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .tile-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .tile-val {
            font-size: 0.86rem;
            font-weight: 700;
            color: #ffffff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Permission Cards Grid */
        .perm-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2.5rem;
        }

        .perm-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.4rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.25s ease;
            position: relative;
        }

        .perm-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
            border-color: #cbd5e1;
        }

        .perm-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 1.1rem;
        }

        .perm-header-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .perm-icon-box {
            width: 42px;
            height: 42px;
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }

        .perm-card-title {
            font-size: 0.98rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            line-height: 1.2;
        }

        .perm-card-category {
            font-size: 0.74rem;
            font-weight: 600;
            color: #64748b;
            margin-top: 2px;
        }

        .perm-badge {
            font-size: 0.72rem;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 999px;
            white-space: nowrap;
        }

        .perm-list {
            list-style: none !important;
            padding: 0 !important;
            margin: 0 !important;
            display: flex;
            flex-direction: column;
            gap: 8px;
            border-top: 1px solid #f1f5f9;
            padding-top: 0.9rem !important;
            font-size: 0.83rem;
        }

        .perm-item {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            color: #334155;
            line-height: 1.45;
        }

        .perm-item.restricted {
            color: #94a3b8;
        }

        .perm-check-icon {
            width: 17px;
            height: 17px;
            border-radius: 50%;
            background: #dcfce7;
            color: #15803d;
            font-size: 10px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .perm-cross-icon {
            width: 17px;
            height: 17px;
            border-radius: 50%;
            background: #fee2e2;
            color: #dc2626;
            font-size: 10px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 2px;
        }

        /* Edit Profile Card */
        .profile-edit-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 1.75rem 2rem;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
            margin-bottom: 2.5rem;
        }

        .profile-edit-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 1.1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
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
            border-top: 4px solid #31328f;
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

        /* Modern Table Card & Roster Styles */
        .table-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            margin-bottom: 2.5rem;
        }

        .table-card-header {
            padding: 1.25rem 1.75rem;
            border-bottom: 1.5px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            background: #ffffff;
        }

        .table-responsive {
            overflow-x: auto;
            width: 100%;
            position: relative;
        }

        /* Slim Custom Scrollbar */
        .table-responsive::-webkit-scrollbar {
            height: 6px;
        }
        .table-responsive::-webkit-scrollbar-track {
            background: #f8fafc;
        }
        .table-responsive::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 999px;
        }
        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .table-responsive {
            overflow-x: auto;
            width: 100%;
            -webkit-overflow-scrolling: touch;
            border-radius: 0 0 14px 14px;
            scrollbar-width: thin;
            scrollbar-color: #94a3b8 #f1f5f9;
        }

        .table-responsive::-webkit-scrollbar {
            height: 9px;
        }

        .table-responsive::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 6px;
        }

        .table-responsive::-webkit-scrollbar-thumb {
            background: #94a3b8;
            border-radius: 6px;
        }

        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }

        .roster-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            text-align: left;
            min-width: 1250px;
        }

        .roster-table thead th {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #f8fafc;
            border-bottom: 1.5px solid #e2e8f0;
            padding: 14px 18px;
            font-size: 0.76rem;
            font-weight: 800;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }

        .roster-table tbody tr {
            border-bottom: 1px solid #f1f5f9;
            transition: all 0.15s ease;
            background: #ffffff;
        }

        .roster-table tbody tr:hover {
            background: #f8fafc;
        }

        .roster-table tbody td {
            padding: 14px 18px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }

        /* Actions Column - regular flow, no sticky */
        .roster-table thead th.col-actions {
            text-align: right;
            white-space: nowrap;
        }

        .roster-table tbody td.col-actions {
            text-align: right;
            white-space: nowrap;
        }

        /* S.No Chip */
        .roster-sno-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 28px;
            height: 28px;
            padding: 0 6px;
            border-radius: 6px;
            background: #f1f5f9;
            color: #475569;
            font-weight: 750;
            font-size: 0.82rem;
            border: 1px solid #e2e8f0;
        }

        /* Ref ID Chip */
        .roster-ref-chip {
            display: inline-block;
            background: #eef2ff;
            color: #3730a3;
            border: 1px solid #c7d2fe;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.84rem;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 6px;
            white-space: nowrap;
            letter-spacing: 0.02em;
        }

        .roster-date-sub {
            font-size: 0.74rem;
            color: #64748b;
            margin-top: 5px;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* School & UDISE */
        .roster-school-name {
            font-size: 0.94rem;
            font-weight: 750;
            color: #0f172a;
            line-height: 1.45;
            margin-bottom: 5px;
        }

        .roster-udise-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.75rem;
            color: #0284c7;
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            padding: 3px 8px;
            border-radius: 5px;
            font-weight: 600;
        }

        .roster-udise-pill .udise-num {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-weight: 700;
        }

        /* Location */
        .roster-location-box {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .roster-district-line {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .roster-district-name {
            font-size: 0.90rem;
            font-weight: 750;
            color: #1e293b;
        }

        .roster-code-pill {
            font-size: 0.70rem;
            font-weight: 700;
            color: #64748b;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            padding: 1px 6px;
            border-radius: 4px;
            font-family: ui-monospace, monospace;
        }

        .roster-mandal-line {
            font-size: 0.80rem;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .roster-mandal-line .mandal-pin {
            font-size: 0.78rem;
        }

        .roster-mandal-name {
            color: #334155;
            font-weight: 650;
        }

        /* In-Charge Person */
        .roster-person-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .roster-person-avatar {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            color: #ffffff;
            font-weight: 800;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 2px 5px rgba(5, 150, 105, 0.2);
        }

        .roster-person-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
        }

        .roster-person-name {
            font-size: 0.90rem;
            font-weight: 750;
            color: #1e293b;
            line-height: 1.25;
            white-space: nowrap;
        }

        .roster-designation-badge {
            font-size: 0.70rem;
            font-weight: 700;
            color: #475569;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            padding: 2px 7px;
            border-radius: 4px;
            text-transform: capitalize;
            white-space: nowrap;
            width: fit-content;
        }

        /* Contact Phone Button */
        .roster-phone-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 12px;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.84rem;
            font-weight: 700;
            color: #0f766e;
            text-decoration: none;
            transition: all 0.15s ease;
            white-space: nowrap;
            width: fit-content;
        }

        .roster-phone-btn:hover {
            background: #f0fdf4;
            border-color: #86efac;
            color: #15803d;
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(16, 185, 129, 0.15);
        }

        /* Status Form */
        .roster-status-form {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin: 0;
        }

        .roster-status-select {
            height: 34px;
            padding: 0 8px;
            font-size: 0.78rem;
            font-weight: 700;
            border-radius: 7px;
            border: 1.5px solid #cbd5e1;
            background: #ffffff;
            color: #1e293b;
            cursor: pointer;
            outline: none;
            transition: border-color 0.15s ease;
        }

        .roster-status-select:focus {
            border-color: #31328f;
        }

        .roster-status-save-btn {
            height: 34px;
            padding: 0 12px;
            font-size: 0.76rem;
            font-weight: 800;
            border-radius: 7px;
            border: none;
            background: #31328f;
            color: #ffffff;
            cursor: pointer;
            transition: all 0.15s ease;
            white-space: nowrap;
            box-shadow: 0 2px 6px rgba(49, 50, 143, 0.2);
        }

        .roster-status-save-btn:hover {
            background: #252673;
            transform: translateY(-1px);
        }

        /* Actions Wrap */
        .roster-actions-wrap {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }

        .roster-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 11px;
            font-size: 0.76rem;
            font-weight: 700;
            border-radius: 7px;
            cursor: pointer;
            transition: all 0.15s ease;
            text-decoration: none;
            border: 1px solid transparent;
        }

        .roster-action-btn.btn-view {
            background: #f0fdf4;
            color: #15803d;
            border-color: #bbf7d0;
        }
        .roster-action-btn.btn-view:hover {
            background: #dcfce7;
            border-color: #86efac;
            transform: translateY(-1px);
        }

        .roster-action-btn.btn-edit {
            background: #ececf7;
            color: #31328f;
            border-color: #c8c9eb;
        }
        .roster-action-btn.btn-edit:hover {
            background: #dbdcf3;
            border-color: #a8a9df;
            transform: translateY(-1px);
        }

        .roster-action-btn.btn-slip {
            background: #f8fafc;
            color: #475569;
            border-color: #cbd5e1;
        }
        .roster-action-btn.btn-slip:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
            color: #0f172a;
            transform: translateY(-1px);
        }

        /* Mobile Horizontal Scroll Hint */
        .mobile-scroll-hint {
            display: none;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 12px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.74rem;
            font-weight: 700;
            color: #64748b;
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
                padding: 0 0.85rem;
                height: 58px;
            }

            .topbar-scope-chip {
                display: none;
            }

            .topbar-user-details {
                display: none;
            }

            .admin-content {
                padding: 1rem 0.75rem;
            }

            .mobile-scroll-hint {
                display: flex;
            }
        }

        @media (max-width: 768px) {
            .topbar-left {
                gap: 8px;
                min-width: 0;
            }

            .breadcrumb-root,
            .breadcrumb-sep {
                display: none;
            }

            .breadcrumb-current {
                font-size: 0.84rem;
                max-width: 140px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .topbar-logout-btn {
                padding: 5px 8px;
                font-size: 0.75rem;
                gap: 4px;
            }

            /* 2 Columns in Mobile View for KPI Cards */
            .kpi-grid,
            .stats-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 0.65rem !important;
                margin-bottom: 1rem !important;
            }

            .kpi-card {
                padding: 0.85rem 0.75rem !important;
                border-radius: 10px !important;
                gap: 6px !important;
            }

            .kpi-card > div:first-child {
                min-width: 0;
            }

            .kpi-card > div:first-child > div:first-child {
                font-size: 0.68rem !important;
                letter-spacing: 0.3px !important;
            }

            .kpi-card > div:first-child > div:nth-child(2) {
                font-size: 1.4rem !important;
                margin: 0.15rem 0 !important;
                line-height: 1.2 !important;
            }

            .kpi-card > div:first-child > div:last-child {
                font-size: 0.68rem !important;
                line-height: 1.25 !important;
                word-break: break-word;
            }

            .kpi-icon {
                width: 32px !important;
                height: 32px !important;
                font-size: 1rem !important;
                border-radius: 8px !important;
                flex-shrink: 0 !important;
            }

            .filter-toolbar {
                padding: 0.9rem;
                margin-bottom: 1rem;
            }

            .filter-row {
                gap: 0.65rem;
            }

            .filter-row .form-group {
                flex: 1 1 100% !important;
                min-width: 100% !important;
            }

            .filter-actions-wrap {
                width: 100%;
                display: flex;
                gap: 8px;
                margin-top: 0.25rem;
            }

            .filter-actions-wrap .btn {
                flex: 1;
                justify-content: center;
                height: 42px !important;
            }

            .table-card {
                border-radius: 12px;
                margin-bottom: 1.5rem;
            }

            .table-card-header {
                padding: 0.85rem 1rem;
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .table-card-header h3 {
                font-size: 1rem !important;
            }

            .admin-content {
                padding: 0.75rem 0.5rem;
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
                <div class="nav-section-title">My Account</div>

                <a href="javascript:void(0)" onclick="switchDashboardTab('profile')" id="sidebarLinkProfile"
                    class="sidebar-item <?php echo $activeTab === 'profile' ? 'active' : ''; ?>">
                    <div class="sidebar-item-content">
                        <div class="sidebar-icon">👤</div>
                        <span>Profile &amp; Permissions</span>
                    </div>
                    <span class="sidebar-badge" style="background: rgba(16, 185, 129, 0.25); color: #34d399; font-size: 0.72rem;">Overview</span>
                </a>

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
                    <div class="topbar-breadcrumb">
                        <span class="breadcrumb-root">Coordinator Portal</span>
                        <span class="breadcrumb-sep">/</span>
                        <span class="breadcrumb-current" id="topbarActiveTitle"><?php 
                            if ($activeTab === 'profile') echo 'Profile & Permissions';
                            elseif ($activeTab === 'schools') echo 'Schools & Mandals Directory';
                            elseif ($activeTab === 'prelims') echo 'Prelims Question Papers';
                            else echo 'School Registrations Roster';
                        ?></span>
                    </div>
                </div>

                <div class="topbar-right">
                    <!-- Territory Scope Badge -->
                    <div class="topbar-scope-chip" title="Assigned Territorial Scope">
                        <span class="chip-dot"></span>
                        <span><?php echo htmlspecialchars($user['assigned_district'] ?: 'All AP Districts (Statewide)'); ?></span>
                    </div>

                    <!-- Coordinator Profile Pill -->
                    <div class="topbar-user-pill" onclick="switchDashboardTab('profile')" title="View profile & permissions">
                        <div class="topbar-user-avatar">
                            <?php echo strtoupper(substr($user['name'] ?: $user['username'], 0, 1)); ?>
                        </div>
                        <div class="topbar-user-details">
                            <span class="topbar-user-name"><?php echo htmlspecialchars($user['name'] ?: $user['username']); ?></span>
                            <span class="topbar-user-sub">Field Coordinator</span>
                        </div>
                    </div>

                    <!-- Clean Logout Link -->
                    <a href="../logout.php" class="topbar-logout-btn" title="Sign out of coordinator portal">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        <span>Logout</span>
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
                    <div class="alert alert-<?php echo ($statusType ?? 'success') === 'error' ? 'danger' : 'success'; ?>"
                        style="margin-bottom: 1.5rem; background: <?php echo ($statusType ?? 'success') === 'error' ? '#fef2f2' : '#ecfdf5'; ?>; border: 1px solid <?php echo ($statusType ?? 'success') === 'error' ? '#fca5a5' : '#a7f3d0'; ?>; color: <?php echo ($statusType ?? 'success') === 'error' ? '#991b1b' : '#065f46'; ?>; padding: 12px 16px; border-radius: 8px;">
                        <span><?php echo ($statusType ?? 'success') === 'error' ? '⚠️' : '✅'; ?></span>
                        <span><?php echo htmlspecialchars($statusMessage); ?></span>
                    </div>
                <?php endif; ?>

                <!-- TAB PANEL 0: COORDINATOR PROFILE & DETAILED PERMISSIONS -->
                <div id="tabPanelProfile" style="<?php echo $activeTab === 'profile' ? 'display: block;' : 'display: none;'; ?>">

                    <!-- 1. HERO PROFILE SUMMARY BANNER -->
                    <div class="profile-hero-card">
                        <div class="profile-hero-top">
                            <div class="profile-hero-user">
                                <div class="profile-hero-avatar">
                                    <?php echo strtoupper(substr($user['name'] ?: $user['username'], 0, 1)); ?>
                                    <span class="avatar-online-dot" title="Account active and verified"></span>
                                </div>
                                <div class="profile-hero-title-group">
                                    <div class="profile-hero-name">
                                        <span><?php echo htmlspecialchars($user['name'] ?: $user['username']); ?></span>
                                    </div>
                                    <div class="profile-hero-badges">
                                        <span class="badge-glass-role">Field Coordinator</span>
                                        <span class="badge-glass-active">● Active &amp; Authorized</span>
                                        <span style="font-size: 0.72rem; color: #94a3b8; background: rgba(255,255,255,0.08); padding: 2px 8px; border-radius: 999px;">ID: #<?php echo (int)$user['id']; ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Action Shortcuts -->
                            <div class="profile-hero-actions">
                                <a href="#editProfileSection" class="hero-action-btn btn-white">
                                    ✏️ Edit Profile Form &darr;
                                </a>
                                <button type="button" onclick="switchDashboardTab('registrations')" class="hero-action-btn btn-glass">
                                    📊 School Registrations (<?php echo $filteredCount; ?>) &rarr;
                                </button>
                                <button type="button" onclick="switchDashboardTab('schools')" class="hero-action-btn btn-glass">
                                    🏛️ Schools Directory &rarr;
                                </button>
                            </div>
                        </div>

                        <!-- 4 Distinct Key Details Tiles -->
                        <div class="profile-tiles-grid">
                            <div class="profile-info-tile">
                                <div class="tile-icon">👤</div>
                                <div class="tile-meta">
                                    <span class="tile-label">Username</span>
                                    <span class="tile-val">@<?php echo htmlspecialchars($user['username']); ?></span>
                                </div>
                            </div>

                            <div class="profile-info-tile">
                                <div class="tile-icon">📍</div>
                                <div class="tile-meta">
                                    <span class="tile-label">Jurisdiction / Territory</span>
                                    <span class="tile-val"><?php echo htmlspecialchars($user['assigned_district'] ?: 'Statewide (All Districts)'); ?></span>
                                </div>
                            </div>

                            <div class="profile-info-tile">
                                <div class="tile-icon">✉️</div>
                                <div class="tile-meta">
                                    <span class="tile-label">Official Email</span>
                                    <span class="tile-val"><?php echo htmlspecialchars($user['email'] ?: 'Not configured'); ?></span>
                                </div>
                            </div>

                            <div class="profile-info-tile">
                                <div class="tile-icon">📞</div>
                                <div class="tile-meta">
                                    <span class="tile-label">Contact Mobile</span>
                                    <span class="tile-val"><?php echo htmlspecialchars($user['mobile'] ?: 'Not configured'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. PERMISSIONS BREAKDOWN IN DETAIL -->
                    <div style="margin-bottom: 2rem;">
                        <div style="margin-bottom: 1.25rem;">
                            <h3 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0 0 0.25rem; display: flex; align-items: center; gap: 8px;">
                                <span>🛡️</span>
                                <span>Assigned Operational Permissions &amp; Authority</span>
                            </h3>
                            <p style="font-size: 0.85rem; color: #64748b; margin: 0;">
                                Detailed overview of administrative privileges, verification authority, and data access levels granted to your account.
                            </p>
                        </div>

                        <div class="perm-grid">

                            <!-- Permission Card 1: School Registrations -->
                            <div class="perm-card" style="border-top: 4px solid <?php echo $canEditRegs ? '#059669' : ($canViewRegs ? '#f59e0b' : '#ef4444'); ?>;">
                                <div>
                                    <div class="perm-card-header">
                                        <div class="perm-header-left">
                                            <div class="perm-icon-box" style="background: <?php echo $canEditRegs ? '#ecfdf5' : '#fffbeb'; ?>; color: <?php echo $canEditRegs ? '#059669' : '#d97706'; ?>;">
                                                📊
                                            </div>
                                            <div>
                                                <h4 class="perm-card-title">School Registrations</h4>
                                                <div class="perm-card-category">Verification &amp; Follow-up</div>
                                            </div>
                                        </div>
                                        <?php if ($canEditRegs): ?>
                                            <span class="perm-badge" style="background: #dcfce7; color: #15803d; border: 1px solid #86efac;">
                                                ✓ Full Edit Mode
                                            </span>
                                        <?php elseif ($canViewRegs): ?>
                                            <span class="perm-badge" style="background: #fef3c7; color: #92400e; border: 1px solid #fde68a;">
                                                👁️ View Only
                                            </span>
                                        <?php else: ?>
                                            <span class="perm-badge" style="background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5;">
                                                🔒 Restricted
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <ul class="perm-list">
                                        <li class="perm-item">
                                            <span class="perm-check-icon">✓</span>
                                            <span>Access registered government schools roster</span>
                                        </li>
                                        <li class="perm-item">
                                            <span class="perm-check-icon">✓</span>
                                            <span>Filter by District, Mandal &amp; Verification Status</span>
                                        </li>
                                        <li class="perm-item">
                                            <span class="perm-check-icon">✓</span>
                                            <span>Search schools by Name, UDISE code, or In-Charge</span>
                                        </li>
                                        <li class="perm-item">
                                            <span class="perm-check-icon">✓</span>
                                            <span>View &amp; download official School Registration Slips</span>
                                        </li>
                                        <?php if ($canEditRegs): ?>
                                            <li class="perm-item">
                                                <span class="perm-check-icon">✓</span>
                                                <span><strong>Update verification status</strong> (Submitted, Verified, Contacted, Completed)</span>
                                            </li>
                                            <li class="perm-item">
                                                <span class="perm-check-icon">✓</span>
                                                <span><strong>Edit school coordinator contacts</strong> (Teacher Name, Mobile, Email)</span>
                                            </li>
                                        <?php else: ?>
                                            <li class="perm-item restricted">
                                                <span class="perm-cross-icon">✕</span>
                                                <span>Status updating restricted (View-Only mode)</span>
                                            </li>
                                            <li class="perm-item restricted">
                                                <span class="perm-cross-icon">✕</span>
                                                <span>School contact record editing restricted to Administrator</span>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>

                            <!-- Permission Card 2: Schools Directory -->
                            <div class="perm-card" style="border-top: 4px solid <?php echo canManageSchools() ? '#059669' : (canViewSchools() ? '#31328f' : '#ef4444'); ?>;">
                                <div>
                                    <div class="perm-card-header">
                                        <div class="perm-header-left">
                                            <div class="perm-icon-box" style="background: #ececf7; color: #31328f;">
                                                🏛️
                                            </div>
                                            <div>
                                                <h4 class="perm-card-title">Schools Directory</h4>
                                                <div class="perm-card-category">AP Master Database</div>
                                            </div>
                                        </div>
                                        <?php if (canManageSchools()): ?>
                                            <span class="perm-badge" style="background: #dcfce7; color: #15803d; border: 1px solid #86efac;">
                                                ✓ Manage &amp; Edit
                                            </span>
                                        <?php elseif (canViewSchools()): ?>
                                            <span class="perm-badge" style="background: #ececf7; color: #31328f; border: 1px solid #c8c9eb;">
                                                👁️ View Only
                                            </span>
                                        <?php else: ?>
                                            <span class="perm-badge" style="background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5;">
                                                🔒 Restricted
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <ul class="perm-list">
                                        <li class="perm-item">
                                            <span class="perm-check-icon">✓</span>
                                            <span>Browse 28 AP Revenue Districts and all Mandals</span>
                                        </li>
                                        <li class="perm-item">
                                            <span class="perm-check-icon">✓</span>
                                            <span>Access Government High Schools and Upper Primary list</span>
                                        </li>
                                        <li class="perm-item">
                                            <span class="perm-check-icon">✓</span>
                                            <span>Verify 11-digit official UDISE school codes</span>
                                        </li>
                                        <li class="perm-item">
                                            <span class="perm-check-icon">✓</span>
                                            <span>Check registration completion status per mandal</span>
                                        </li>
                                        <?php if (canManageSchools()): ?>
                                            <li class="perm-item">
                                                <span class="perm-check-icon">✓</span>
                                                <span><strong>Add or modify master school records</strong></span>
                                            </li>
                                        <?php else: ?>
                                            <li class="perm-item restricted">
                                                <span class="perm-cross-icon">✕</span>
                                                <span>Master modifications restricted to Administrator</span>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>

                            <!-- Permission Card 3: Prelims Question Papers -->
                            <div class="perm-card" style="border-top: 4px solid <?php echo canManagePapers() ? '#7c3aed' : (canViewPapers() ? '#31328f' : '#ef4444'); ?>;">
                                <div>
                                    <div class="perm-card-header">
                                        <div class="perm-header-left">
                                            <div class="perm-icon-box" style="background: #f5f3ff; color: #7c3aed;">
                                                📄
                                            </div>
                                            <div>
                                                <h4 class="perm-card-title">Prelims Exam Papers</h4>
                                                <div class="perm-card-category">Examination Repository</div>
                                            </div>
                                        </div>
                                        <?php if (canManagePapers()): ?>
                                            <span class="perm-badge" style="background: #ede9fe; color: #6d28d9; border: 1px solid #ddd6fe;">
                                                ✓ Upload &amp; Manage
                                            </span>
                                        <?php elseif (canViewPapers()): ?>
                                            <span class="perm-badge" style="background: #ececf7; color: #31328f; border: 1px solid #c8c9eb;">
                                                ⬇️ Download Access
                                            </span>
                                        <?php else: ?>
                                            <span class="perm-badge" style="background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5;">
                                                🔒 Restricted
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <ul class="perm-list">
                                        <li class="perm-item">
                                            <span class="perm-check-icon">✓</span>
                                            <span>Access Prelims Question Papers repository</span>
                                        </li>
                                        <li class="perm-item">
                                            <span class="perm-check-icon">✓</span>
                                            <span>Download examination PDF files for standards 7 and 10</span>
                                        </li>
                                        <li class="perm-item">
                                            <span class="perm-check-icon">✓</span>
                                            <span>Monitor exam day release schedule (28 Nov 2026)</span>
                                        </li>
                                        <?php if (canManagePapers()): ?>
                                            <li class="perm-item">
                                                <span class="perm-check-icon">✓</span>
                                                <span><strong>Upload and publish official question papers</strong></span>
                                            </li>
                                        <?php else: ?>
                                            <li class="perm-item restricted">
                                                <span class="perm-cross-icon">✕</span>
                                                <span>Question paper uploading restricted to Publishing Admins</span>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>

                            <!-- Permission Card 4: Territorial Scope -->
                            <div class="perm-card" style="border-top: 4px solid #0284c7;">
                                <div>
                                    <div class="perm-card-header">
                                        <div class="perm-header-left">
                                            <div class="perm-icon-box" style="background: #f0f9ff; color: #0284c7;">
                                                🗺️
                                            </div>
                                            <div>
                                                <h4 class="perm-card-title">Territory Scope</h4>
                                                <div class="perm-card-category">Assigned Jurisdiction</div>
                                            </div>
                                        </div>
                                        <span class="perm-badge" style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;">
                                            <?php echo htmlspecialchars($user['assigned_district'] ?: 'All Districts'); ?>
                                        </span>
                                    </div>

                                    <ul class="perm-list">
                                        <li class="perm-item">
                                            <span class="perm-check-icon">✓</span>
                                            <span>Assigned Territory: <strong><?php echo htmlspecialchars($user['assigned_district'] ?: 'Statewide (All AP Districts)'); ?></strong></span>
                                        </li>
                                        <li class="perm-item">
                                            <span class="perm-check-icon">✓</span>
                                            <span>Authorized for field outreach and Headmaster coordination</span>
                                        </li>
                                        <li class="perm-item">
                                            <span class="perm-check-icon">✓</span>
                                            <span>Exam center logistics &amp; student enrollment monitoring</span>
                                        </li>
                                        <li class="perm-item">
                                            <span class="perm-check-icon">✓</span>
                                            <span>Official EEMT 2027 District Representative Authority</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- 3. EDIT COORDINATOR PROFILE SECTION -->
                    <div id="editProfileSection" class="profile-edit-card">
                        <div class="profile-edit-header">
                            <div>
                                <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                                    <span>✏️</span>
                                    <span>Edit Coordinator Profile &amp; Credentials</span>
                                </h3>
                                <p style="margin: 3px 0 0; font-size: 0.85rem; color: #64748b;">
                                    Update your official contact details or change your dashboard login password.
                                </p>
                            </div>
                            <span style="font-size: 0.8rem; font-weight: 700; color: #475569; background: #f8fafc; border: 1px solid #e2e8f0; padding: 5px 12px; border-radius: 6px;">
                                User ID: #<?php echo (int)$user['id']; ?>
                            </span>
                        </div>

                        <form method="POST" action="dashboard.php?tab=profile" style="display: flex; flex-direction: column; gap: 1.5rem;">
                            <input type="hidden" name="update_coordinator_profile" value="1">

                            <!-- Section 1: Contact Information -->
                            <div>
                                <div style="font-size: 0.92rem; font-weight: 800; color: #1e293b; margin-bottom: 0.85rem; display: flex; align-items: center; gap: 8px;">
                                    <span>👤</span>
                                    <span>Personal &amp; Contact Details</span>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem;">
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label class="form-label" style="font-size: 0.85rem; font-weight: 700; color: #334155; margin-bottom: 5px; display: block;">
                                            Coordinator Full Name <span class="required" style="color: #ef4444;">*</span>
                                        </label>
                                        <input type="text" name="name" required class="form-control" style="height: 44px; border-radius: 8px;"
                                            value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" placeholder="Your full name">
                                    </div>

                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label class="form-label" style="font-size: 0.85rem; font-weight: 700; color: #334155; margin-bottom: 5px; display: block;">
                                            Username <span style="font-weight: 400; color: #64748b;">(System Locked)</span>
                                        </label>
                                        <input type="text" disabled class="form-control" style="height: 44px; border-radius: 8px; background: #f8fafc; color: #64748b; cursor: not-allowed;"
                                            value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>">
                                        <div style="font-size: 0.72rem; color: #94a3b8; margin-top: 3px;">Username cannot be changed.</div>
                                    </div>

                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label class="form-label" style="font-size: 0.85rem; font-weight: 700; color: #334155; margin-bottom: 5px; display: block;">
                                            Official Email Address
                                        </label>
                                        <input type="email" name="email" class="form-control" style="height: 44px; border-radius: 8px;"
                                            value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" placeholder="coordinator@example.com">
                                    </div>

                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label class="form-label" style="font-size: 0.85rem; font-weight: 700; color: #334155; margin-bottom: 5px; display: block;">
                                            Contact Mobile Number
                                        </label>
                                        <input type="text" name="mobile" class="form-control" style="height: 44px; border-radius: 8px;"
                                            value="<?php echo htmlspecialchars($user['mobile'] ?? ''); ?>" placeholder="10-digit mobile number">
                                    </div>
                                </div>
                            </div>

                            <!-- Section 2: Password Change Block -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.35rem;">
                                <div style="font-size: 0.95rem; font-weight: 800; color: #1e293b; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 8px;">
                                    <span>🔒</span>
                                    <span>Change Login Password (Optional)</span>
                                </div>
                                <div style="font-size: 0.78rem; color: #64748b; margin-bottom: 1rem;">
                                    Leave the password fields below empty if you only want to update your name or contact details.
                                </div>

                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label class="form-label" style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                                            Current Password
                                        </label>
                                        <input type="password" name="current_password" class="form-control" style="height: 42px; border-radius: 8px;" placeholder="Enter current password">
                                    </div>

                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label class="form-label" style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                                            New Password (min 6 characters)
                                        </label>
                                        <input type="password" name="new_password" class="form-control" style="height: 42px; border-radius: 8px;" placeholder="Enter new password">
                                    </div>

                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label class="form-label" style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                                            Confirm New Password
                                        </label>
                                        <input type="password" name="confirm_password" class="form-control" style="height: 42px; border-radius: 8px;" placeholder="Confirm new password">
                                    </div>
                                </div>
                            </div>

                            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                                <button type="submit" class="btn btn-primary"
                                    style="padding: 11px 26px; font-weight: 800; font-size: 0.92rem; background: #059669; border: none; border-radius: 9px; cursor: pointer; box-shadow: 0 4px 14px rgba(5, 150, 105, 0.3); display: inline-flex; align-items: center; gap: 8px;">
                                    <span>💾</span>
                                    <span>Save Profile Changes &rarr;</span>
                                </button>
                            </div>
                        </form>
                    </div>

                </div> <!-- End #tabPanelProfile -->

                <!-- TAB 1: SCHOOL REGISTRATIONS PANEL -->
                <div id="tabPanelRegistrations"
                    style="<?php echo $activeTab === 'registrations' ? '' : 'display: none;'; ?>">

                    <?php if (!$canViewRegs): ?>
                        <div
                            style="background: #ffffff; border-radius: 16px; padding: 4rem 2rem; text-align: center; border: 1.5px dashed #cbd5e1; box-shadow: 0 4px 14px rgba(0,0,0,0.03); margin-bottom: 2rem;">
                            <div
                                style="width: 72px; height: 72px; border-radius: 20px; background: #fef2f1; border: 1.5px solid #fed7d5; display: inline-flex; align-items: center; justify-content: center; font-size: 2.2rem; color: #d92d20; margin: 0 auto 1.25rem;">
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
                                    <div style="font-size: 2.1rem; font-weight: 800; color: #31328f; margin: 0.2rem 0;">
                                        <?php echo number_format($totalMandals); ?></div>
                                    <div style="font-size: 0.78rem; color: #31328f; font-weight: 600;">Coverage Across State
                                    </div>
                                </div>
                                <div class="kpi-icon" style="background: #ececf7; color: #31328f;">
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

                                <div class="filter-actions-wrap" style="display: flex; gap: 8px; align-items: flex-end; flex-shrink: 0;">
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
                                    <h3 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                                        <span>📋</span>
                                        <span>Registered Schools Verification Roster</span>
                                    </h3>
                                    <span style="font-size: 0.8rem; color: #64748b;">
                                        Official school registrations directory, verification tracking, and coordinator contact details
                                    </span>
                                </div>
                                <span class="badge"
                                    style="background: #ecfdf5; color: #047857; font-weight: 800; border: 1px solid #a7f3d0; padding: 6px 14px; font-size: 0.82rem; border-radius: 999px;">
                                    ● <?php echo $filteredCount; ?> Schools in View
                                </span>
                            </div>

                            <?php if (empty($registrations)): ?>
                                <div style="padding: 4rem 2rem; text-align: center; color: #64748b;">
                                    <div style="font-size: 3rem; margin-bottom: 0.75rem;">🏫</div>
                                    <h4 style="font-weight: 800; color: #1e293b; margin-bottom: 0.35rem; font-size: 1.15rem;">No School Registrations Found</h4>
                                    <p style="font-size: 0.88rem; margin: 0; color: #64748b;">Try adjusting your keyword search query, district or status filters above.</p>
                                </div>
                            <?php else: ?>
                                <div class="mobile-scroll-hint">
                                    <span>👉 Swipe left / right to view all columns &amp; actions</span>
                                </div>
                                <div class="table-responsive">
                                    <table class="roster-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 70px; min-width: 65px; text-align: center;">S.No</th>
                                                <th style="width: 175px; min-width: 170px;">EEMTSRC (Ref ID)</th>
                                                <th style="min-width: 360px;">School Details</th>
                                                <th style="width: 250px; min-width: 230px;">District &amp; Mandal</th>
                                                <th style="width: 250px; min-width: 230px;">In-Charge Person</th>
                                                <th style="width: 200px; min-width: 185px;">Contact Mobile</th>
                                                <th class="col-actions" style="width: 220px; min-width: 210px;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($registrations as $index => $row): ?>
                                                <?php $sno = ($offset ?? 0) + $index + 1; ?>
                                                <tr>
                                                    <!-- Col 0: S.No -->
                                                    <td style="text-align: center;">
                                                        <span class="roster-sno-chip"><?php echo $sno; ?></span>
                                                    </td>

                                                    <!-- Col 1: Ref ID & Date -->
                                                    <td>
                                                        <span class="roster-ref-chip">
                                                            #<?php echo htmlspecialchars($row['reference_id']); ?>
                                                        </span>
                                                        <div class="roster-date-sub">
                                                            <span>📅</span>
                                                            <span><?php echo date('d M, h:i A', strtotime($row['created_at'])); ?></span>
                                                        </div>
                                                    </td>

                                                    <!-- Col 2: School Name & UDISE -->
                                                    <td>
                                                        <div class="roster-school-name" title="<?php echo htmlspecialchars($row['school_name']); ?>">
                                                            <?php echo htmlspecialchars(formatSchoolTitle($row['school_name'] ?? '')); ?>
                                                        </div>
                                                        <div class="roster-udise-pill">
                                                            <span class="udise-lbl">UDISE:</span>
                                                            <span class="udise-num"><?php echo htmlspecialchars($row['unique_code']); ?></span>
                                                        </div>
                                                    </td>

                                                    <!-- Col 3: District & Mandal -->
                                                    <td>
                                                        <div class="roster-location-box">
                                                            <div class="roster-district-line">
                                                                <?php $distInfo = formatLocationTitle($row['district'] ?? ''); ?>
                                                                <span class="roster-district-name"><?php echo htmlspecialchars($distInfo['name']); ?></span>
                                                                <?php if (!empty($distInfo['code'])): ?>
                                                                    <span class="roster-code-pill" title="District Code"><?php echo htmlspecialchars($distInfo['code']); ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="roster-mandal-line">
                                                                <?php $mandalInfo = formatLocationTitle($row['mandal'] ?? ''); ?>
                                                                <span class="mandal-pin">📍</span>
                                                                <span style="color: #64748b; font-weight: 500;">Mandal:</span>
                                                                <span class="roster-mandal-name"><?php echo htmlspecialchars($mandalInfo['name']); ?></span>
                                                            </div>
                                                        </div>
                                                    </td>

                                                    <!-- Col 4: In-Charge Person -->
                                                    <td>
                                                        <div class="roster-person-wrap">
                                                            <div class="roster-person-avatar">
                                                                <?php echo strtoupper(substr($row['contact_person'] ?: 'P', 0, 1)); ?>
                                                            </div>
                                                            <div class="roster-person-info">
                                                                <span class="roster-person-name" title="<?php echo htmlspecialchars($row['contact_person']); ?>">
                                                                    <?php echo htmlspecialchars(ucwords(strtolower($row['contact_person'] ?: 'In-Charge'))); ?>
                                                                </span>
                                                                <span class="roster-designation-badge">
                                                                    <?php echo htmlspecialchars(formatDesignationTitle($row['designation'] ?? '')); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </td>

                                                    <!-- Col 5: Contact Mobile -->
                                                    <td>
                                                        <?php if (!empty($row['mobile'])): ?>
                                                            <a href="tel:<?php echo htmlspecialchars($row['mobile']); ?>"
                                                                class="roster-phone-btn"
                                                                title="Call <?php echo htmlspecialchars($row['contact_person']); ?>">
                                                                <svg width="13" height="13" fill="currentColor" viewBox="0 0 24 24"><path d="M6.62 10.79a15.05 15.05 0 006.59 6.59l2.2-2.2a1 1 0 011.02-.24 11.72 11.72 0 003.68.59 1 1 0 011 1v3.5a1 1 0 01-1 1A17 17 0 013 4a1 1 0 011-1h3.5a1 1 0 011 1 11.72 11.72 0 00.59 3.68 1 1 0 01-.24 1.02l-2.23 2.09z"/></svg>
                                                                <span><?php echo htmlspecialchars($row['mobile']); ?></span>
                                                            </a>
                                                        <?php else: ?>
                                                            <span style="font-size: 0.80rem; color: #94a3b8; font-style: italic;">No Mobile</span>
                                                        <?php endif; ?>
                                                    </td>

                                                    <!-- Col 6: Actions (Sticky) -->
                                                    <td class="col-actions">
                                                        <div class="roster-actions-wrap">
                                                            <button type="button" class="roster-action-btn btn-view" onclick='openViewRegModal(<?php echo json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' title="View Registration Full Details">
                                                                👁️ View
                                                            </button>
                                                            <?php if ($canEditRegs): ?>
                                                                <button type="button" class="roster-action-btn btn-edit" onclick='openEditRegModal(<?php echo json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' title="Edit Registration Details">
                                                                    ✏️ Edit
                                                                </button>
                                                            <?php endif; ?>
                                                            <a href="../register_success.php?ref=<?php echo urlencode($row['reference_id']); ?>" target="_blank" class="roster-action-btn btn-slip" title="View Official Registration Slip">
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
                    <div class="kpi-grid" style="margin-bottom: 1.5rem;">
                        <div class="kpi-card" style="border-top: 4px solid #8b5cf6;">
                            <div>
                                <div
                                    style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">
                                    Published Papers</div>
                                <div style="font-size: 1.85rem; font-weight: 800; color: #5b21b6; margin: 0.2rem 0;">
                                    <?php echo count($questionPapers); ?></div>
                                <div style="font-size: 0.76rem; color: #7c3aed; font-weight: 600;">Available for Schools
                                </div>
                            </div>
                            <div class="kpi-icon" style="background: #f5f3ff; color: #7c3aed;">
                                📄
                            </div>
                        </div>

                        <div class="kpi-card" style="border-top: 4px solid #059669;">
                            <div>
                                <div
                                    style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">
                                    Target Classes</div>
                                <div style="font-size: 1.85rem; font-weight: 800; color: #0f172a; margin: 0.2rem 0;">5
                                    &ndash; 10</div>
                                <div style="font-size: 0.76rem; color: #059669; font-weight: 600;">State High Schools
                                </div>
                            </div>
                            <div class="kpi-icon" style="background: #ecfdf5; color: #059669;">
                                🎯
                            </div>
                        </div>

                        <div class="kpi-card" style="border-top: 4px solid #31328f;">
                            <div>
                                <div
                                    style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">
                                    Coordinator Access</div>
                                <div
                                    style="font-size: 1.02rem; font-weight: 800; color: <?php echo canManagePapers() ? '#6b21a8' : '#31328f'; ?>; margin: 0.35rem 0;">
                                    <?php echo canManagePapers() ? 'Upload Permitted 📤' : 'View & Download 👁️'; ?>
                                </div>
                                <div style="font-size: 0.76rem; color: #64748b; font-weight: 600;">Exam Materials
                                    Console</div>
                            </div>
                            <div class="kpi-icon" style="background: #ececf7; color: #31328f;">
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
                                                        style="background: #ececf7; color: #31328f; padding: 2px 7px; border-radius: 4px; font-weight: 700;">Class
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
                    <div style="background: #ececf7; border: 1.5px solid #c8c9eb; color: #31328f; padding: 12px 18px; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-size: 1.25rem;">👁️</span>
                            <div>
                                <strong style="font-size: 0.92rem;">Directory View-Only Access:</strong>
                                <span style="font-size: 0.84rem; display: block; margin-top: 2px; color: #3b82f6;">
                                    Browse, search, and lookup revenue districts, mandals, schools, and unique UDISE codes.
                                </span>
                            </div>
                        </div>
                        <span style="font-size: 0.74rem; background: #dbeafe; color: #31328f; border: 1px solid #c8c9eb; padding: 4px 10px; border-radius: 20px; font-weight: 700; white-space: nowrap;">
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

                        <div class="kpi-card" style="border-top: 4px solid #31328f;">
                            <div>
                                <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">3. Master Schools</div>
                                <div style="font-size: 2.1rem; font-weight: 800; color: #31328f; margin: 0.2rem 0;"><?php echo number_format($totalMasterSchools); ?></div>
                                <div style="font-size: 0.78rem; color: #31328f; font-weight: 600;">Total AP Govt Schools</div>
                            </div>
                            <div class="kpi-icon" style="background: #ececf7; color: #31328f;">🏫</div>
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
                                                    <span class="badge" style="background: #ececf7; color: #31328f; border: 1px solid #c8c9eb; font-weight: 700;">
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
                                                    <a href="dashboard.php?tab=schools&schools_district=<?php echo urlencode($schoolDistrict); ?>&schools_mandal=<?php echo urlencode($m['mandal']); ?>" class="btn" style="background: #31328f; color: white; padding: 6px 12px; font-size: 0.8rem; font-weight: 700; border-radius: 7px; text-decoration: none;">
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
                                                    <span class="badge" style="background: #ececf7; color: #31328f; border: 1px solid #c8c9eb; font-weight: 700;">
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
                            <span id="view_ref_badge" class="badge" style="background: #ececf7; color: #31328f; font-weight: 700; font-family: monospace; font-size: 0.78rem;"></span>
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
                        <a id="view_phone_link" href="#" style="font-weight: 700; color: #31328f; text-decoration: none; font-size: 0.95rem;"></a>
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
                <a id="view_slip_link" href="#" target="_blank" class="btn" style="padding: 8px 14px; font-size: 0.82rem; font-weight: 700; background: #ececf7; color: #1d4ed8; border: 1px solid #c8c9eb; text-decoration: none; border-radius: 8px;">
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
                stBadge.style.background = '#ececf7'; stBadge.style.color = '#1d4ed8'; stBadge.style.border = '1px solid #c8c9eb';
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
            const profilePanel = document.getElementById('tabPanelProfile');
            const regPanel = document.getElementById('tabPanelRegistrations');
            const prelimsPanel = document.getElementById('tabPanelPrelims');
            const schoolsPanel = document.getElementById('tabPanelSchools');
            const sidebarProfile = document.getElementById('sidebarLinkProfile');
            const sidebarReg = document.getElementById('sidebarLinkRegistrations');
            const sidebarPrelims = document.getElementById('sidebarLinkPrelims');
            const sidebarSchools = document.getElementById('sidebarLinkSchools');
            const topbarTitle = document.getElementById('topbarActiveTitle') || document.querySelector('.topbar-title span');

            if (sidebarProfile) sidebarProfile.classList.remove('active');
            if (sidebarReg) sidebarReg.classList.remove('active');
            if (sidebarPrelims) sidebarPrelims.classList.remove('active');
            if (sidebarSchools) sidebarSchools.classList.remove('active');

            if (tabName === 'profile') {
                if (profilePanel) profilePanel.style.display = 'block';
                if (regPanel) regPanel.style.display = 'none';
                if (prelimsPanel) prelimsPanel.style.display = 'none';
                if (schoolsPanel) schoolsPanel.style.display = 'none';

                if (sidebarProfile) sidebarProfile.classList.add('active');
                if (topbarTitle) topbarTitle.textContent = 'Profile & Permissions';

                try {
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', 'profile');
                    window.history.replaceState({}, '', url);
                } catch (e) { }
            } else if (tabName === 'schools') {
                if (profilePanel) profilePanel.style.display = 'none';
                if (regPanel) regPanel.style.display = 'none';
                if (prelimsPanel) prelimsPanel.style.display = 'none';
                if (schoolsPanel) schoolsPanel.style.display = 'block';

                if (sidebarSchools) sidebarSchools.classList.add('active');
                if (topbarTitle) topbarTitle.textContent = 'Schools & Mandals Directory';

                try {
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', 'schools');
                    window.history.replaceState({}, '', url);
                } catch (e) { }
            } else if (tabName === 'prelims') {
                if (profilePanel) profilePanel.style.display = 'none';
                if (schoolsPanel) schoolsPanel.style.display = 'none';
                if (regPanel) regPanel.style.display = 'none';
                if (prelimsPanel) prelimsPanel.style.display = 'block';

                if (sidebarPrelims) sidebarPrelims.classList.add('active');
                if (topbarTitle) topbarTitle.textContent = 'Prelims Question Papers';

                try {
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', 'prelims');
                    window.history.replaceState({}, '', url);
                } catch (e) { }
            } else {
                if (profilePanel) profilePanel.style.display = 'none';
                if (prelimsPanel) prelimsPanel.style.display = 'none';
                if (schoolsPanel) schoolsPanel.style.display = 'none';
                if (regPanel) regPanel.style.display = 'block';

                if (sidebarReg) sidebarReg.classList.add('active');
                if (topbarTitle) topbarTitle.textContent = 'School Registrations Roster';

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
                } else if (reqTab === 'registrations') {
                    switchDashboardTab('registrations');
                } else {
                    switchDashboardTab('profile');
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