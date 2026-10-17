<?php
/**
 * EEMT 2027 - Schools & Master Directory Management (Admin Only)
 * Allows managing districts, mandals, schools, and school unique codes (UDISE)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$pdo = getDB();
$message = '';
$messageType = '';

// Handle CSV Export
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    $expDistrict = trim($_GET['district'] ?? '');
    $expMandal = trim($_GET['mandal'] ?? '');
    $expStatus = trim($_GET['status'] ?? '');
    $expSearch = trim($_GET['search'] ?? '');

    $whereExp = [];
    $paramsExp = [];

    if (!empty($expDistrict)) {
        $whereExp[] = "district = :district";
        $paramsExp[':district'] = $expDistrict;
    }
    if (!empty($expMandal)) {
        $whereExp[] = "mandal = :mandal";
        $paramsExp[':mandal'] = $expMandal;
    }
    if ($expStatus === 'registered') {
        $whereExp[] = "is_registered = 1";
    } elseif ($expStatus === 'unregistered') {
        $whereExp[] = "is_registered = 0";
    }
    if (!empty($expSearch)) {
        $whereExp[] = "(school_name LIKE :s OR unique_code LIKE :s OR mandal LIKE :s)";
        $paramsExp[':s'] = "%{$expSearch}%";
    }

    $whereSqlExp = !empty($whereExp) ? 'WHERE ' . implode(' AND ', $whereExp) : '';
    $stmtExp = $pdo->prepare("SELECT id, district, mandal, school_name, unique_code, is_registered, created_at FROM schools_master {$whereSqlExp} ORDER BY district ASC, mandal ASC, school_name ASC");
    $stmtExp->execute($paramsExp);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="eemt2027_schools_master_' . date('Ymd_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    // Output UTF-8 BOM for Microsoft Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, ['ID', 'District', 'Mandal', 'School Name', 'UDISE / Unique Code', 'Registration Status', 'Created At']);

    while ($row = $stmtExp->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            $row['district'],
            $row['mandal'],
            $row['school_name'],
            $row['unique_code'],
            $row['is_registered'] == 1 ? 'Registered' : 'Available',
            $row['created_at']
        ]);
    }
    fclose($output);
    exit;
}

// Handle POST Actions (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_school') {
        $district = trim($_POST['district'] ?? '');
        $mandal = trim($_POST['mandal'] ?? '');
        $schoolName = trim($_POST['school_name'] ?? '');
        $uniqueCode = trim($_POST['unique_code'] ?? '');

        if (empty($district) || empty($mandal) || empty($schoolName) || empty($uniqueCode)) {
            $message = "All fields (District, Mandal, School Name, and Unique Code) are required.";
            $messageType = 'error';
        } else {
            // Check if unique_code already exists
            $checkStmt = $pdo->prepare("SELECT id, school_name FROM schools_master WHERE unique_code = :code");
            $checkStmt->execute([':code' => $uniqueCode]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $message = "Unique Code '$uniqueCode' already belongs to school '{$existing['school_name']}' (ID: #{$existing['id']}). Codes must be unique.";
                $messageType = 'error';
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO schools_master (district, mandal, school_name, unique_code, is_registered, created_at)
                    VALUES (:district, :mandal, :school_name, :unique_code, 0, CURRENT_TIMESTAMP)
                ");
                $ins->execute([
                    ':district' => $district,
                    ':mandal' => $mandal,
                    ':school_name' => $schoolName,
                    ':unique_code' => $uniqueCode
                ]);
                $message = "School '$schoolName' (UDISE: $uniqueCode) added successfully to master directory.";
                $messageType = 'success';
            }
        }
    } elseif ($action === 'edit_school') {
        $schoolId = (int)($_POST['school_id'] ?? 0);
        $district = trim($_POST['district'] ?? '');
        $mandal = trim($_POST['mandal'] ?? '');
        $schoolName = trim($_POST['school_name'] ?? '');
        $uniqueCode = trim($_POST['unique_code'] ?? '');
        $isRegistered = isset($_POST['is_registered']) ? (int)$_POST['is_registered'] : 0;

        if ($schoolId <= 0 || empty($district) || empty($mandal) || empty($schoolName) || empty($uniqueCode)) {
            $message = "Please fill in all required fields.";
            $messageType = 'error';
        } else {
            // Check unique code conflict with other schools
            $checkStmt = $pdo->prepare("SELECT id, school_name FROM schools_master WHERE unique_code = :code AND id != :id");
            $checkStmt->execute([':code' => $uniqueCode, ':id' => $schoolId]);
            $conflict = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($conflict) {
                $message = "Unique Code '$uniqueCode' is already assigned to '{$conflict['school_name']}' (ID: #{$conflict['id']}).";
                $messageType = 'error';
            } else {
                $upd = $pdo->prepare("
                    UPDATE schools_master 
                    SET district = :district, mandal = :mandal, school_name = :school_name, unique_code = :unique_code, is_registered = :is_reg
                    WHERE id = :id
                ");
                $upd->execute([
                    ':district' => $district,
                    ':mandal' => $mandal,
                    ':school_name' => $schoolName,
                    ':unique_code' => $uniqueCode,
                    ':is_reg' => $isRegistered,
                    ':id' => $schoolId
                ]);

                // Also sync changes to school_registrations_2027 if enrolled
                $syncStmt = $pdo->prepare("
                    UPDATE school_registrations_2027 
                    SET district = :district, mandal = :mandal, school_name = :school_name, unique_code = :unique_code 
                    WHERE school_id = :id
                ");
                $syncStmt->execute([
                    ':district' => $district,
                    ':mandal' => $mandal,
                    ':school_name' => $schoolName,
                    ':unique_code' => $uniqueCode,
                    ':id' => $schoolId
                ]);

                $message = "School record #$schoolId updated successfully.";
                $messageType = 'success';
            }
        }
    } elseif ($action === 'delete_school') {
        $schoolId = (int)($_POST['school_id'] ?? 0);

        if ($schoolId > 0) {
            // Check if enrolled in registrations
            $regChk = $pdo->prepare("SELECT id, reference_id FROM school_registrations_2027 WHERE school_id = ?");
            $regChk->execute([$schoolId]);
            $enrolled = $regChk->fetch(PDO::FETCH_ASSOC);

            if ($enrolled) {
                $message = "Cannot delete school: It is already registered in EEMT 2027 with Reference Code {$enrolled['reference_id']}. Delete the registration record first if needed.";
                $messageType = 'error';
            } else {
                $del = $pdo->prepare("DELETE FROM schools_master WHERE id = ?");
                $del->execute([$schoolId]);
                $message = "School entry #$schoolId removed from directory.";
                $messageType = 'success';
            }
        }
    }
}

// KPI Metrics
$totalMaster = (int)$pdo->query("SELECT COUNT(*) FROM schools_master")->fetchColumn();
$districtsCount = (int)$pdo->query("SELECT COUNT(DISTINCT district) FROM schools_master WHERE district != ''")->fetchColumn();
$mandalsCount = (int)$pdo->query("SELECT COUNT(DISTINCT mandal) FROM schools_master WHERE mandal != ''")->fetchColumn();
$registeredMaster = (int)$pdo->query("SELECT COUNT(*) FROM schools_master WHERE is_registered = 1")->fetchColumn();

// Fetch Distinct Districts for Filter & Form
$districtsList = $pdo->query("SELECT DISTINCT district FROM schools_master WHERE district != '' ORDER BY district ASC")->fetchAll(PDO::FETCH_COLUMN);

// Filters
$districtFilter = trim($_GET['district'] ?? '');
$mandalFilter = trim($_GET['mandal'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

$where = [];
$params = [];

if (!empty($districtFilter)) {
    $where[] = "district = :district";
    $params[':district'] = $districtFilter;
}
if (!empty($mandalFilter)) {
    $where[] = "mandal = :mandal";
    $params[':mandal'] = $mandalFilter;
}
if ($statusFilter === 'registered') {
    $where[] = "is_registered = 1";
} elseif ($statusFilter === 'unregistered') {
    $where[] = "is_registered = 0";
}
if (!empty($search)) {
    $where[] = "(school_name LIKE :s OR unique_code LIKE :s OR mandal LIKE :s)";
    $params[':s'] = "%{$search}%";
}

$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Count for pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM schools_master {$whereSql}");
$countStmt->execute($params);
$totalFiltered = (int)$countStmt->fetchColumn();

// Pagination params
$perPage = in_array((int)($_GET['per_page'] ?? 25), [15, 25, 50, 100, 250]) ? (int)$_GET['per_page'] : 25;
$totalPages = max(1, (int)ceil($totalFiltered / $perPage));
$page = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $perPage;

// Fetch filtered rows
$query = "SELECT * FROM schools_master {$whereSql} ORDER BY district ASC, mandal ASC, school_name ASC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($query);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$schools = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch mandals for current selected district if applicable
$mandalsForDistrict = [];
if (!empty($districtFilter)) {
    $mStmt = $pdo->prepare("SELECT DISTINCT mandal FROM schools_master WHERE district = ? ORDER BY mandal ASC");
    $mStmt->execute([$districtFilter]);
    $mandalsForDistrict = $mStmt->fetchAll(PDO::FETCH_COLUMN);
}

$pageTitle = "Schools & Directory Manager - EEMT 2027";
require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
.sm-metric-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.25rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 1.25rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.sm-metric-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.06);
}
.sm-metric-icon {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    flex-shrink: 0;
}
.sm-input {
    width: 100%;
    height: 42px;
    padding: 0 14px;
    border: 1.5px solid #cbd5e1;
    border-radius: 9px;
    background: #f8fafc;
    font-size: 0.9rem;
    color: #1e293b;
    outline: none;
    box-sizing: border-box;
    transition: all 0.2s ease;
}
.sm-input:focus {
    background: #ffffff;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
}
.sm-select {
    width: 100%;
    height: 42px;
    padding: 0 36px 0 12px;
    border: 1.5px solid #cbd5e1;
    border-radius: 9px;
    background-color: #f8fafc;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 15px;
    appearance: none;
    -webkit-appearance: none;
    font-size: 0.9rem;
    color: #1e293b;
    outline: none;
    box-sizing: border-box;
    cursor: pointer;
    transition: all 0.2s ease;
}
.sm-select:focus {
    background-color: #ffffff;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
}
.sm-label {
    display: block;
    font-size: 0.8rem;
    font-weight: 700;
    color: #475569;
    margin-bottom: 0.4rem;
}
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(5px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 1.25rem;
}
.modal-card {
    background: #ffffff;
    width: 100%;
    max-width: 580px;
    border-radius: 16px;
    box-shadow: 0 20px 45px rgba(0,0,0,0.25);
    overflow: hidden;
    animation: modalSlideIn 0.2s ease-out forwards;
}
@keyframes modalSlideIn {
    from { opacity: 0; transform: translateY(12px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
</style>

<!-- Page Header Banner -->
<div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.35rem;">
            <a href="dashboard.php" style="color: #64748b; text-decoration: none; font-size: 0.85rem; font-weight: 600;">Admin</a>
            <span style="color: #cbd5e1;">/</span>
            <span style="color: #2563eb; font-size: 0.85rem; font-weight: 700;">Schools & Directory</span>
        </div>
        <h1 style="font-size: 1.65rem; font-weight: 800; color: #0f172a; margin: 0 0 0.3rem 0;">
            Schools & Directory Manager
        </h1>
        <p style="margin: 0; font-size: 0.88rem; color: #64748b;">
            Manage Andhra Pradesh revenue districts, mandals, government schools, and 11-digit UDISE unique codes.
        </p>
    </div>

    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <a href="schools_master.php?action=export&district=<?php echo urlencode($districtFilter); ?>&mandal=<?php echo urlencode($mandalFilter); ?>&status=<?php echo urlencode($statusFilter); ?>&search=<?php echo urlencode($search); ?>" 
           class="btn" style="background: #ffffff; border: 1.5px solid #cbd5e1; color: #334155; padding: 0 1.15rem; height: 42px; font-size: 0.88rem; font-weight: 700; border-radius: 9px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Export Master (CSV)
        </a>
        <button type="button" onclick="openAddSchoolModal()" 
                class="btn" style="background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; border: none; padding: 0 1.35rem; height: 42px; font-size: 0.88rem; font-weight: 700; border-radius: 9px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(37,99,235,0.25);">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            + Add School / Code
        </button>
    </div>
</div>

<!-- Alert Notifications -->
<?php if ($message): ?>
    <div style="background: <?php echo $messageType === 'success' ? '#f0fdf4' : '#fef2f2'; ?>; border: 1.5px solid <?php echo $messageType === 'success' ? '#bbf7d0' : '#fecaca'; ?>; color: <?php echo $messageType === 'success' ? '#166534' : '#991b1b'; ?>; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; font-size: 0.9rem; font-weight: 600;">
        <span><?php echo $messageType === 'success' ? '✅' : '⚠️'; ?></span>
        <span><?php echo htmlspecialchars($message); ?></span>
    </div>
<?php endif; ?>

<!-- 4 KPI Metrics Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem; margin-bottom: 1.75rem;">
    <!-- Metric 1: Total Master Schools -->
    <div class="sm-metric-card">
        <div class="sm-metric-icon" style="background: #eff6ff; color: #2563eb;">🏫</div>
        <div>
            <div style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">Master Schools</div>
            <div style="font-size: 1.75rem; font-weight: 800; color: #0f172a; line-height: 1.2; margin-top: 2px;">
                <?php echo number_format($totalMaster); ?>
            </div>
            <div style="font-size: 0.75rem; color: #2563eb; font-weight: 600; margin-top: 2px;">AP Govt Listed Schools</div>
        </div>
    </div>

    <!-- Metric 2: Districts -->
    <div class="sm-metric-card">
        <div class="sm-metric-icon" style="background: #fdf2f8; color: #db2777;">📍</div>
        <div>
            <div style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">Districts</div>
            <div style="font-size: 1.75rem; font-weight: 800; color: #0f172a; line-height: 1.2; margin-top: 2px;">
                <?php echo $districtsCount; ?>
            </div>
            <div style="font-size: 0.75rem; color: #db2777; font-weight: 600; margin-top: 2px;">Revenue Districts Covered</div>
        </div>
    </div>

    <!-- Metric 3: Mandals -->
    <div class="sm-metric-card">
        <div class="sm-metric-icon" style="background: #f0fdf4; color: #16a34a;">🗺️</div>
        <div>
            <div style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">Taluks / Mandals</div>
            <div style="font-size: 1.75rem; font-weight: 800; color: #0f172a; line-height: 1.2; margin-top: 2px;">
                <?php echo $mandalsCount; ?>
            </div>
            <div style="font-size: 0.75rem; color: #16a34a; font-weight: 600; margin-top: 2px;">Sub-District Block Clusters</div>
        </div>
    </div>

    <!-- Metric 4: Registration Rate -->
    <div class="sm-metric-card">
        <div class="sm-metric-icon" style="background: #faf5ff; color: #9333ea;">🎓</div>
        <div>
            <div style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">EEMT 2027 Registered</div>
            <div style="font-size: 1.75rem; font-weight: 800; color: #0f172a; line-height: 1.2; margin-top: 2px;">
                <?php echo number_format($registeredMaster); ?>
            </div>
            <div style="font-size: 0.75rem; color: #9333ea; font-weight: 600; margin-top: 2px;">
                <?php echo number_format($totalMaster - $registeredMaster); ?> Available for Enrollment
            </div>
        </div>
    </div>
</div>

<!-- Search & Filtering Toolbar -->
<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
    <form method="GET" action="schools_master.php" id="filterForm">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: flex-end;">
            
            <!-- District Filter -->
            <div>
                <label class="sm-label" for="district">District</label>
                <select id="district" name="district" class="sm-select" onchange="onDistrictChange(this.value)">
                    <option value="">All Districts (<?php echo count($districtsList); ?>)</option>
                    <?php foreach ($districtsList as $d): ?>
                        <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $districtFilter === $d ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($d); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Mandal Filter -->
            <div>
                <label class="sm-label" for="mandal">Mandal</label>
                <select id="mandal" name="mandal" class="sm-select">
                    <option value="">All Mandals</option>
                    <?php foreach ($mandalsForDistrict as $m): ?>
                        <option value="<?php echo htmlspecialchars($m); ?>" <?php echo $mandalFilter === $m ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($m); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status Filter -->
            <div>
                <label class="sm-label" for="status">Registration Status</label>
                <select id="status" name="status" class="sm-select">
                    <option value="">All Schools</option>
                    <option value="registered" <?php echo $statusFilter === 'registered' ? 'selected' : ''; ?>>Registered in EEMT</option>
                    <option value="unregistered" <?php echo $statusFilter === 'unregistered' ? 'selected' : ''; ?>>Available in Directory</option>
                </select>
            </div>

            <!-- Search Field -->
            <div>
                <label class="sm-label" for="search">Search School or UDISE Code</label>
                <input type="text" id="search" name="search" class="sm-input" placeholder="e.g. 28142500311 or Vetlapalem..." value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn" style="height: 42px; background: #2563eb; color: white; border: none; padding: 0 1.25rem; font-weight: 700; border-radius: 9px; cursor: pointer; flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Filter
                </button>
                <?php if (!empty($districtFilter) || !empty($mandalFilter) || !empty($statusFilter) || !empty($search)): ?>
                    <a href="schools_master.php" class="btn" style="height: 42px; background: #f1f5f9; color: #475569; border: 1.5px solid #cbd5e1; padding: 0 1rem; font-weight: 700; border-radius: 9px; text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">
                        Reset
                    </a>
                <?php endif; ?>
            </div>

        </div>
    </form>
</div>

<!-- Master Schools Data Table Card -->
<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03); margin-bottom: 2rem;">
    <!-- Card Header Info -->
    <div style="padding: 1.15rem 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; background: #f8fafc;">
        <div>
            <span style="font-weight: 800; color: #0f172a; font-size: 1.05rem;">Master School Records</span>
            <span style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; font-size: 0.78rem; font-weight: 700; padding: 2px 10px; border-radius: 9999px; margin-left: 8px;">
                <?php echo number_format($totalFiltered); ?> Schools Found
            </span>
        </div>
        
        <div style="font-size: 0.85rem; color: #64748b;">
            Showing <?php echo $totalFiltered > 0 ? number_format($offset + 1) : 0; ?> to <?php echo number_format(min($offset + $perPage, $totalFiltered)); ?> of <?php echo number_format($totalFiltered); ?> entries
        </div>
    </div>

    <!-- Table -->
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; text-align: left; margin: 0;">
            <thead style="background: #f8fafc; border-bottom: 1.5px solid #e2e8f0;">
                <tr>
                    <th style="padding: 0.85rem 1.25rem; font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.05em; width: 60px;">ID</th>
                    <th style="padding: 0.85rem 1.25rem; font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.05em;">UDISE / Unique Code</th>
                    <th style="padding: 0.85rem 1.25rem; font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.05em;">School Name</th>
                    <th style="padding: 0.85rem 1.25rem; font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.05em;">District</th>
                    <th style="padding: 0.85rem 1.25rem; font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.05em;">Mandal</th>
                    <th style="padding: 0.85rem 1.25rem; font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.05em; text-align: center;">Status</th>
                    <th style="padding: 0.85rem 1.25rem; font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.05em; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($schools)): ?>
                    <tr>
                        <td colspan="7" style="padding: 3rem 1.5rem; text-align: center; color: #64748b;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">🔍</div>
                            <div style="font-weight: 700; color: #1e293b; font-size: 1rem;">No matching schools found</div>
                            <div style="font-size: 0.85rem; margin-top: 4px;">Try refining your district, mandal, or search keyword.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($schools as $s): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;" onmouseover="this.style.background='#f8fafc';" onmouseout="this.style.background='#ffffff';">
                            <td style="padding: 1rem 1.25rem; color: #94a3b8; font-size: 0.82rem; font-family: monospace;">
                                #<?php echo $s['id']; ?>
                            </td>
                            <td style="padding: 1rem 1.25rem;">
                                <div style="display: inline-flex; align-items: center; gap: 6px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 4px 8px;">
                                    <code style="font-family: ui-monospace, SFMono-Regular, monospace; font-size: 0.85rem; font-weight: 700; color: #1e40af;">
                                        <?php echo htmlspecialchars($s['unique_code']); ?>
                                    </code>
                                    <button type="button" onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($s['unique_code']); ?>'); this.innerText='✓'; setTimeout(() => this.innerText='📋', 1500);" title="Copy UDISE Code" style="background: none; border: none; cursor: pointer; font-size: 0.85rem; padding: 0; color: #64748b;">
                                        📋
                                    </button>
                                </div>
                            </td>
                            <td style="padding: 1rem 1.25rem;">
                                <div style="font-weight: 700; color: #0f172a; font-size: 0.92rem;">
                                    <?php echo htmlspecialchars($s['school_name']); ?>
                                </div>
                            </td>
                            <td style="padding: 1rem 1.25rem;">
                                <span style="background: #fdf2f8; color: #be185d; border: 1px solid #fbcfe8; font-size: 0.78rem; font-weight: 700; padding: 3px 8px; border-radius: 6px;">
                                    <?php echo htmlspecialchars($s['district']); ?>
                                </span>
                            </td>
                            <td style="padding: 1rem 1.25rem; color: #475569; font-size: 0.88rem; font-weight: 600;">
                                <?php echo htmlspecialchars($s['mandal']); ?>
                            </td>
                            <td style="padding: 1rem 1.25rem; text-align: center;">
                                <?php if ((int)$s['is_registered'] === 1): ?>
                                    <span style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; font-size: 0.76rem; font-weight: 700; padding: 3px 9px; border-radius: 9999px; display: inline-flex; align-items: center; gap: 4px;">
                                        <span style="height: 6px; width: 6px; background: #10b981; border-radius: 50%;"></span>
                                        Enrolled
                                    </span>
                                <?php else: ?>
                                    <span style="background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; font-size: 0.76rem; font-weight: 600; padding: 3px 9px; border-radius: 9999px;">
                                        Available
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 1rem 1.25rem; text-align: right; white-space: nowrap;">
                                <button type="button" onclick='openEditSchoolModal(<?php echo json_encode($s, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' 
                                        style="height: 32px; padding: 0 10px; background: #f1f5f9; color: #1e293b; border: 1px solid #cbd5e1; border-radius: 7px; font-size: 0.8rem; font-weight: 700; cursor: pointer; transition: all 0.15s ease;" onmouseover="this.style.background='#e2e8f0';" onmouseout="this.style.background='#f1f5f9';">
                                    ✏️ Edit
                                </button>
                                <form method="POST" action="schools_master.php" style="display: inline-block; margin-left: 6px;" onsubmit="return confirm('Are you sure you want to delete school: <?php echo htmlspecialchars(addslashes($s['school_name'])); ?> (UDISE: <?php echo htmlspecialchars(addslashes($s['unique_code'])); ?>)?');">
                                    <input type="hidden" name="action" value="delete_school">
                                    <input type="hidden" name="school_id" value="<?php echo $s['id']; ?>">
                                    <button type="submit" style="height: 32px; padding: 0 9px; background: #fff1f2; color: #e11d48; border: 1px solid #fecdd3; border-radius: 7px; font-size: 0.8rem; font-weight: 700; cursor: pointer; transition: all 0.15s ease;" onmouseover="this.style.background='#ffe4e6';" onmouseout="this.style.background='#fff1f2';">
                                        🗑️
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Table Footer with Pagination -->
    <?php if ($totalPages > 1): ?>
        <div style="padding: 1rem 1.5rem; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            
            <!-- Rows Per Page Selector -->
            <div style="display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: #64748b;">
                <span>Rows per page:</span>
                <select onchange="location.href='schools_master.php?district=<?php echo urlencode($districtFilter); ?>&mandal=<?php echo urlencode($mandalFilter); ?>&status=<?php echo urlencode($statusFilter); ?>&search=<?php echo urlencode($search); ?>&per_page=' + this.value;" style="height: 32px; border: 1px solid #cbd5e1; border-radius: 6px; padding: 0 8px; background: white; font-size: 0.85rem; outline: none;">
                    <?php foreach ([15, 25, 50, 100, 250] as $opt): ?>
                        <option value="<?php echo $opt; ?>" <?php echo $perPage === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Page Navigation -->
            <div style="display: flex; gap: 4px; align-items: center;">
                <?php
                $baseQuery = "schools_master.php?district=" . urlencode($districtFilter) . "&mandal=" . urlencode($mandalFilter) . "&status=" . urlencode($statusFilter) . "&search=" . urlencode($search) . "&per_page={$perPage}&page=";
                ?>

                <!-- First / Prev -->
                <?php if ($page > 1): ?>
                    <a href="<?php echo $baseQuery . '1'; ?>" style="height: 32px; min-width: 32px; padding: 0 8px; border: 1px solid #cbd5e1; border-radius: 6px; background: white; color: #334155; font-size: 0.85rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">&laquo;</a>
                    <a href="<?php echo $baseQuery . ($page - 1); ?>" style="height: 32px; min-width: 32px; padding: 0 8px; border: 1px solid #cbd5e1; border-radius: 6px; background: white; color: #334155; font-size: 0.85rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">&lsaquo;</a>
                <?php endif; ?>

                <!-- Page Window -->
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                for ($p = $startPage; $p <= $endPage; $p++):
                ?>
                    <a href="<?php echo $baseQuery . $p; ?>" style="height: 32px; min-width: 32px; padding: 0 10px; border: 1px solid <?php echo $p === $page ? '#2563eb' : '#cbd5e1'; ?>; border-radius: 6px; background: <?php echo $p === $page ? '#2563eb' : '#ffffff'; ?>; color: <?php echo $p === $page ? '#ffffff' : '#334155'; ?>; font-size: 0.85rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">
                        <?php echo $p; ?>
                    </a>
                <?php endfor; ?>

                <!-- Next / Last -->
                <?php if ($page < $totalPages): ?>
                    <a href="<?php echo $baseQuery . ($page + 1); ?>" style="height: 32px; min-width: 32px; padding: 0 8px; border: 1px solid #cbd5e1; border-radius: 6px; background: white; color: #334155; font-size: 0.85rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">&rsaquo;</a>
                    <a href="<?php echo $baseQuery . $totalPages; ?>" style="height: 32px; min-width: 32px; padding: 0 8px; border: 1px solid #cbd5e1; border-radius: 6px; background: white; color: #334155; font-size: 0.85rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">&raquo;</a>
                <?php endif; ?>
            </div>

        </div>
    <?php endif; ?>
</div>

<!-- MODAL 1: ADD NEW SCHOOL -->
<div id="addSchoolModal" class="modal-overlay">
    <div class="modal-card">
        <div style="padding: 1.25rem 1.75rem; border-bottom: 1px solid #edf2f7; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 38px; height: 38px; border-radius: 10px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                    🏫
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #0f172a;">Add New School</h3>
                    <div style="font-size: 0.78rem; color: #64748b;">Add an official government school to the central directory</div>
                </div>
            </div>
            <button type="button" onclick="closeAddSchoolModal()" style="background: none; border: none; font-size: 1.5rem; line-height: 1; color: #94a3b8; cursor: pointer;">&times;</button>
        </div>

        <form method="POST" action="schools_master.php" style="padding: 1.5rem 1.75rem;">
            <input type="hidden" name="action" value="add_school">

            <!-- District & Mandal -->
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1.15rem;">
                <div>
                    <label class="sm-label" for="add_district">District <span style="color: #ef4444;">*</span></label>
                    <input list="districtsDatalist" id="add_district" name="district" class="sm-input" placeholder="Type or select district..." required onchange="loadMandalsForAdd(this.value)">
                    <datalist id="districtsDatalist">
                        <?php foreach ($districtsList as $d): ?>
                            <option value="<?php echo htmlspecialchars($d); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div>
                    <label class="sm-label" for="add_mandal">Mandal <span style="color: #ef4444;">*</span></label>
                    <input list="mandalsAddDatalist" id="add_mandal" name="mandal" class="sm-input" placeholder="Type or select mandal..." required>
                    <datalist id="mandalsAddDatalist"></datalist>
                </div>
            </div>

            <!-- School Name -->
            <div style="margin-bottom: 1.15rem;">
                <label class="sm-label" for="add_school_name">School Full Name <span style="color: #ef4444;">*</span></label>
                <input type="text" id="add_school_name" name="school_name" class="sm-input" placeholder="e.g. ZPHS VETLAPALEM / GOVT HIGH SCHOOL..." required>
            </div>

            <!-- School Unique Code / UDISE -->
            <div style="margin-bottom: 1.5rem;">
                <label class="sm-label" for="add_unique_code">School Unique Code / UDISE Code <span style="color: #ef4444;">*</span></label>
                <input type="text" id="add_unique_code" name="unique_code" class="sm-input" placeholder="e.g. 28142500311 (11-digit UDISE)" required>
                <div style="font-size: 0.75rem; color: #64748b; margin-top: 4px;">Must be unique across all Andhra Pradesh schools. Used for school verification & logins.</div>
            </div>

            <!-- Modal Action Buttons Footer -->
            <div style="display: flex; justify-content: flex-end; align-items: center; gap: 10px; border-top: 1px solid #edf2f7; padding-top: 1.25rem;">
                <button type="button" onclick="closeAddSchoolModal()" style="height: 42px; padding: 0 1.25rem; background: #ffffff; color: #475569; border: 1.5px solid #cbd5e1; font-weight: 700; font-size: 0.88rem; border-radius: 9px; cursor: pointer;">
                    Cancel
                </button>
                <button type="submit" style="height: 42px; padding: 0 1.5rem; background: #2563eb; color: #ffffff; border: none; font-weight: 700; font-size: 0.88rem; border-radius: 9px; cursor: pointer; box-shadow: 0 4px 12px rgba(37,99,235,0.25);">
                    Save New School
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 2: EDIT SCHOOL -->
<div id="editSchoolModal" class="modal-overlay">
    <div class="modal-card">
        <div style="padding: 1.25rem 1.75rem; border-bottom: 1px solid #edf2f7; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 38px; height: 38px; border-radius: 10px; background: #fef3c7; color: #d97706; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                    ✏️
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #0f172a;">Edit School Master Record</h3>
                    <div id="modalEditSubtitle" style="font-size: 0.78rem; color: #64748b;">#0 • School</div>
                </div>
            </div>
            <button type="button" onclick="closeEditSchoolModal()" style="background: none; border: none; font-size: 1.5rem; line-height: 1; color: #94a3b8; cursor: pointer;">&times;</button>
        </div>

        <form method="POST" action="schools_master.php" style="padding: 1.5rem 1.75rem;">
            <input type="hidden" name="action" value="edit_school">
            <input type="hidden" id="edit_school_id" name="school_id" value="">

            <!-- District & Mandal -->
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1.15rem;">
                <div>
                    <label class="sm-label" for="edit_district">District <span style="color: #ef4444;">*</span></label>
                    <input list="districtsDatalist" id="edit_district" name="district" class="sm-input" required>
                </div>

                <div>
                    <label class="sm-label" for="edit_mandal">Mandal <span style="color: #ef4444;">*</span></label>
                    <input type="text" id="edit_mandal" name="mandal" class="sm-input" required>
                </div>
            </div>

            <!-- School Name -->
            <div style="margin-bottom: 1.15rem;">
                <label class="sm-label" for="edit_school_name">School Full Name <span style="color: #ef4444;">*</span></label>
                <input type="text" id="edit_school_name" name="school_name" class="sm-input" required>
            </div>

            <!-- School Unique Code / UDISE -->
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <label class="sm-label" for="edit_unique_code">UDISE / Unique Code <span style="color: #ef4444;">*</span></label>
                    <input type="text" id="edit_unique_code" name="unique_code" class="sm-input" required>
                </div>

                <div>
                    <label class="sm-label" for="edit_is_registered">Enrollment Status</label>
                    <select id="edit_is_registered" name="is_registered" class="sm-select">
                        <option value="0">⚪ Available (Not Registered)</option>
                        <option value="1">🟢 Enrolled in EEMT 2027</option>
                    </select>
                </div>
            </div>

            <!-- Modal Action Buttons Footer -->
            <div style="display: flex; justify-content: flex-end; align-items: center; gap: 10px; border-top: 1px solid #edf2f7; padding-top: 1.25rem;">
                <button type="button" onclick="closeEditSchoolModal()" style="height: 42px; padding: 0 1.25rem; background: #ffffff; color: #475569; border: 1.5px solid #cbd5e1; font-weight: 700; font-size: 0.88rem; border-radius: 9px; cursor: pointer;">
                    Cancel
                </button>
                <button type="submit" style="height: 42px; padding: 0 1.5rem; background: #2563eb; color: #ffffff; border: none; font-weight: 700; font-size: 0.88rem; border-radius: 9px; cursor: pointer; box-shadow: 0 4px 12px rgba(37,99,235,0.25);">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Dynamic cascade for Mandal filter when District changes
function onDistrictChange(district) {
    const mandalSelect = document.getElementById('mandal');
    mandalSelect.innerHTML = '<option value="">All Mandals</option>';
    
    if (!district) return;

    fetch('../api/get_mandals.php?district=' + encodeURIComponent(district))
        .then(res => res.json())
        .then(mandals => {
            mandals.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m;
                opt.textContent = m;
                mandalSelect.appendChild(opt);
            });
        })
        .catch(err => console.error('Error fetching mandals:', err));
}

// Dynamic cascade for Add modal
function loadMandalsForAdd(district) {
    const list = document.getElementById('mandalsAddDatalist');
    list.innerHTML = '';
    if (!district) return;

    fetch('../api/get_mandals.php?district=' + encodeURIComponent(district))
        .then(res => res.json())
        .then(mandals => {
            mandals.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m;
                list.appendChild(opt);
            });
        })
        .catch(err => console.error('Error fetching mandals for add:', err));
}

function openAddSchoolModal() {
    document.getElementById('add_district').value = '';
    document.getElementById('add_mandal').value = '';
    document.getElementById('add_school_name').value = '';
    document.getElementById('add_unique_code').value = '';
    document.getElementById('addSchoolModal').style.display = 'flex';
}

function closeAddSchoolModal() {
    document.getElementById('addSchoolModal').style.display = 'none';
}

function openEditSchoolModal(school) {
    document.getElementById('edit_school_id').value = school.id;
    document.getElementById('edit_district').value = school.district || '';
    document.getElementById('edit_mandal').value = school.mandal || '';
    document.getElementById('edit_school_name').value = school.school_name || '';
    document.getElementById('edit_unique_code').value = school.unique_code || '';
    document.getElementById('edit_is_registered').value = school.is_registered || '0';
    document.getElementById('modalEditSubtitle').textContent = '#' + school.id + ' • ' + (school.school_name || 'School');
    
    document.getElementById('editSchoolModal').style.display = 'flex';
}

function closeEditSchoolModal() {
    document.getElementById('editSchoolModal').style.display = 'none';
}

// Modal backdrop click-out
window.addEventListener('click', function(e) {
    const addModal = document.getElementById('addSchoolModal');
    const editModal = document.getElementById('editSchoolModal');
    if (e.target === addModal) closeAddSchoolModal();
    if (e.target === editModal) closeEditSchoolModal();
});
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
