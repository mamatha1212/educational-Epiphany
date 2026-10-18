<?php
/**
 * EEMT 2027 - State Admin Control Panel
 * Enhanced UI with Responsive Filtering & Server-Side Pagination
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$user = currentUser();
$pdo = getDB();
$message = '';
$messageType = '';

// Handle Admin Actions (Delete invalid/test registration)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_reg'])) {
    $delId = (int)($_POST['reg_id'] ?? 0);
    if ($delId > 0) {
        $delStmt = $pdo->prepare("DELETE FROM school_registrations_2027 WHERE id = ?");
        $delStmt->execute([$delId]);
        $message = "Registration record #$delId has been permanently deleted.";
        $messageType = 'success';
    }
}

// Handle Admin Actions (Edit registration record)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_reg'])) {
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

    if ($regId > 0 && !empty($schoolName) && !empty($mobile)) {
        $upd = $pdo->prepare("UPDATE school_registrations_2027 SET school_name = ?, unique_code = ?, contact_person = ?, designation = ?, mobile = ?, email = ?, status = ?, mandal = ?, district = ? WHERE id = ?");
        $upd->execute([$schoolName, $uniqueCode, $contactPerson, $designation, $mobile, $email, $status, $mandal, $district, $regId]);
        $message = "Registration record for '$schoolName' updated successfully.";
        $messageType = 'success';
    } else {
        $message = "School name and contact phone number are required.";
        $messageType = 'error';
    }
}

// Handle search and filters
$districtFilter = trim($_GET['district'] ?? '');
$mandalFilter = trim($_GET['mandal'] ?? '');
$search = trim($_GET['search'] ?? '');
$perPage = isset($_GET['per_page']) ? max(5, min(100, (int)$_GET['per_page'])) : 10;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// Base Query Conditions
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

if ($search !== '') {
    $where[] = "(sr.reference_id LIKE :s OR sr.school_name LIKE :s OR sr.unique_code LIKE :s OR sr.contact_person LIKE :s OR sr.mobile LIKE :s OR sr.email LIKE :s)";
    $params[':s'] = "%$search%";
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Fetch Core KPIs
$totalSchools = (int)$pdo->query("SELECT COUNT(*) FROM school_registrations_2027")->fetchColumn();
$totalDistricts = (int)$pdo->query("SELECT COUNT(DISTINCT district) FROM school_registrations_2027")->fetchColumn();
$totalMandals = (int)$pdo->query("SELECT COUNT(DISTINCT mandal) FROM school_registrations_2027")->fetchColumn();
$totalPapers = (int)$pdo->query("SELECT COUNT(*) FROM question_papers WHERE is_published = 1")->fetchColumn();

// Fetch Distinct Districts for Filter Dropdown
$districtsStmt = $pdo->query("SELECT DISTINCT district FROM schools_master ORDER BY district ASC");
$allDistricts = $districtsStmt->fetchAll(PDO::FETCH_COLUMN);

// Pagination: 1. Total filtered records count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM school_registrations_2027 sr {$whereSql}");
$countStmt->execute($params);
$totalFiltered = (int)$countStmt->fetchColumn();

// Pagination: 2. Calculate pagination metrics
$totalPages = max(1, (int)ceil($totalFiltered / $perPage));
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}
$offset = ($currentPage - 1) * $perPage;

// Pagination: 3. Fetch sliced records
$querySql = "SELECT sr.* FROM school_registrations_2027 sr {$whereSql} ORDER BY sr.id DESC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
$stmt = $pdo->prepare($querySql);
$stmt->execute($params);
$registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$showingFrom = $totalFiltered > 0 ? $offset + 1 : 0;
$showingTo = min($offset + count($registrations), $totalFiltered);

// Helper function to build pagination URLs
function buildPaginationUrl($page, $perPage, $district, $mandal, $search) {
    $params = [
        'page' => $page,
        'per_page' => $perPage,
    ];
    if ($district !== '') $params['district'] = $district;
    if ($mandal !== '') $params['mandal'] = $mandal;
    if ($search !== '') $params['search'] = $search;
    return 'dashboard.php?' . http_build_query($params);
}

$pageTitle = "Statewide Registrations";
require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
/* Modern Dashboard Styling Enhancements */
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
.kpi-card.kpi-blue { border-top: 4px solid #31328f; }
.kpi-card.kpi-green { border-top: 4px solid #10b981; }
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

.filter-grid {
    display: flex;
    flex-direction: row;
    align-items: flex-end;
    gap: 0.75rem;
    width: 100%;
}
.filter-item-district {
    flex: 1 1 180px;
    min-width: 130px;
}
.filter-item-mandal {
    flex: 1 1 180px;
    min-width: 130px;
}
.filter-item-search {
    flex: 2 1 240px;
    min-width: 160px;
}
.filter-actions-group {
    display: flex;
    gap: 8px;
    align-items: flex-end;
    flex-shrink: 0;
}
@media (max-width: 768px) {
    .filter-grid {
        flex-wrap: wrap;
    }
    .filter-item-district,
    .filter-item-mandal,
    .filter-item-search {
        flex: 1 1 100%;
        min-width: 100%;
    }
    .filter-actions-group {
        width: 100%;
        flex-wrap: wrap;
    }
    .filter-actions-group .btn {
        flex: 1;
    }
}

.custom-pagination {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
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
    background: #31328f;
    border-color: #31328f;
    color: #ffffff;
    font-weight: 700;
    box-shadow: 0 2px 6px rgba(37,99,235,0.3);
}
.page-btn.disabled {
    color: #cbd5e1;
    border-color: #e2e8f0;
    background: #f8fafc;
    cursor: not-allowed;
    pointer-events: none;
}
</style>

<!-- Status Alerts -->
<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>" style="margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 10px;">
        <span style="font-size: 1.2rem;">ℹ️</span>
        <span><?php echo htmlspecialchars($message); ?></span>
    </div>
<?php endif; ?>

<!-- 4 Modern Metric KPI Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 1.75rem;">
    <!-- Card 1 -->
    <div class="kpi-card kpi-blue">
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">Registered Schools</div>
            <div style="font-size: 2rem; font-weight: 800; color: #0f172a; margin-top: 0.2rem; line-height: 1.1;"><?php echo number_format($totalSchools); ?></div>
            <div style="font-size: 0.78rem; color: #10b981; margin-top: 0.35rem; font-weight: 600;">Active AP Govt Schools</div>
        </div>
        <div class="kpi-icon" style="background: #ececf7; color: #31328f;">
            🏫
        </div>
    </div>

    <!-- Card 2 -->
    <div class="kpi-card kpi-green">
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">Districts Covered</div>
            <div style="font-size: 2rem; font-weight: 800; color: #0f172a; margin-top: 0.2rem; line-height: 1.1;"><?php echo $totalDistricts; ?></div>
            <div style="font-size: 0.78rem; color: #64748b; margin-top: 0.35rem; font-weight: 600;">Across Andhra Pradesh</div>
        </div>
        <div class="kpi-icon" style="background: #ecfdf5; color: #10b981;">
            📍
        </div>
    </div>

    <!-- Card 3 -->
    <div class="kpi-card kpi-amber">
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">Mandals Covered</div>
            <div style="font-size: 2rem; font-weight: 800; color: #0f172a; margin-top: 0.2rem; line-height: 1.1;"><?php echo $totalMandals; ?></div>
            <div style="font-size: 0.78rem; color: #d97706; margin-top: 0.35rem; font-weight: 600;">Taluk / Block Scope</div>
        </div>
        <div class="kpi-icon" style="background: #fffbeb; color: #d97706;">
            🗺️
        </div>
    </div>

    <!-- Card 4 -->
    <div class="kpi-card kpi-purple">
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">Prelims Papers</div>
            <div style="font-size: 2rem; font-weight: 800; color: #0f172a; margin-top: 0.2rem; line-height: 1.1;"><?php echo $totalPapers; ?></div>
            <div style="font-size: 0.78rem; color: #7c3aed; margin-top: 0.35rem; font-weight: 600;">Live for Schools</div>
        </div>
        <div class="kpi-icon" style="background: #f5f3ff; color: #7c3aed;">
            📄
        </div>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card" style="margin-bottom: 1.5rem; padding: 1.1rem 1.25rem; box-shadow: var(--shadow-sm); border: 1px solid #e2e8f0; border-radius: 12px; background: #ffffff;">
    <form method="GET" action="dashboard.php" class="filter-grid">
        <input type="hidden" name="per_page" value="<?php echo (int)$perPage; ?>">

        <div class="form-group filter-item-district" style="margin-bottom: 0;">
            <label class="form-label" for="district" style="font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem; display: block; white-space: nowrap;">
                Filter by District
            </label>
            <select name="district" id="district" class="form-select" style="font-size: 0.88rem; height: 42px; width: 100%;">
                <option value="">All 26 Districts</option>
                <?php foreach ($allDistricts as $d): ?>
                    <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $districtFilter === $d ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($d); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group filter-item-mandal" style="margin-bottom: 0;">
            <label class="form-label" for="mandal" style="font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem; display: block; white-space: nowrap;">
                Filter by Mandal
            </label>
            <input type="text" name="mandal" id="mandal" class="form-input" style="font-size: 0.88rem; height: 42px; width: 100%;" placeholder="Type Mandal name..." value="<?php echo htmlspecialchars($mandalFilter); ?>">
        </div>

        <div class="form-group filter-item-search" style="margin-bottom: 0;">
            <label class="form-label" for="search" style="font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem; display: block; white-space: nowrap;">
                Search Anything
            </label>
            <input type="text" name="search" id="search" class="form-input" style="font-size: 0.88rem; height: 42px; width: 100%;" placeholder="Ref ID, School, Phone, Code..." value="<?php echo htmlspecialchars($search); ?>">
        </div>

        <div class="filter-actions-group">
            <button type="submit" class="btn btn-primary" style="height: 42px; padding: 0 1.25rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; gap: 6px; white-space: nowrap;">
                🔍 Filter
            </button>
            <?php if ($districtFilter !== '' || $mandalFilter !== '' || $search !== ''): ?>
                <a href="dashboard.php" class="btn" style="height: 42px; padding: 0 0.9rem; background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; font-weight: 600; white-space: nowrap;" title="Reset Filters">
                    ✕ Reset
                </a>
            <?php endif; ?>
            <a href="export.php?district=<?php echo urlencode($districtFilter); ?>&mandal=<?php echo urlencode($mandalFilter); ?>&search=<?php echo urlencode($search); ?>" class="btn btn-success" style="height: 42px; padding: 0 1.15rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; gap: 6px; white-space: nowrap; text-decoration: none;">
                📥 Export CSV
            </a>
        </div>
    </form>

    <?php if ($districtFilter !== '' || $mandalFilter !== '' || $search !== ''): ?>
        <div style="margin-top: 1rem; padding-top: 0.75rem; border-top: 1px dashed #e2e8f0; font-size: 0.85rem; color: #64748b; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
            <div>
                Filtered results: <strong><?php echo number_format($totalFiltered); ?></strong> matching records found.
            </div>
            <a href="dashboard.php" style="color: #31328f; text-decoration: underline; font-weight: 600;">Clear all filters</a>
        </div>
    <?php endif; ?>
</div>

<!-- Statewide Schools Data Table -->
<div class="card" style="padding: 0; overflow: hidden; box-shadow: var(--shadow-sm); border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 2rem;">
    <!-- Table Header & Controls Bar -->
    <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; background: #f8fafc;">
        <div>
            <h3 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                <span>Statewide Registered Government Schools</span>
                <span class="badge" style="background: #ececf7; color: #31328f; border: 1px solid #c8c9eb; font-size: 0.78rem; font-weight: 700; padding: 3px 8px;">
                    <?php echo number_format($totalFiltered); ?> Total
                </span>
            </h3>
            <span style="font-size: 0.8rem; color: #64748b; margin-top: 3px; display: block;">
                Central registration master list for Andhra Pradesh EEMT 2027
            </span>
        </div>

        <!-- Per Page Selector Form -->
        <div style="display: flex; align-items: center; gap: 8px; font-size: 0.82rem; color: #64748b;">
            <span>Rows per page:</span>
            <select onchange="location.href='<?php echo buildPaginationUrl(1, '__PP__', $districtFilter, $mandalFilter, $search); ?>'.replace('__PP__', this.value);" 
                    style="padding: 4px 8px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.82rem; font-weight: 600; background: #fff; cursor: pointer;">
                <option value="10" <?php echo $perPage === 10 ? 'selected' : ''; ?>>10</option>
                <option value="25" <?php echo $perPage === 25 ? 'selected' : ''; ?>>25</option>
                <option value="50" <?php echo $perPage === 50 ? 'selected' : ''; ?>>50</option>
                <option value="100" <?php echo $perPage === 100 ? 'selected' : ''; ?>>100</option>
            </select>
        </div>
    </div>

    <?php if (empty($registrations)): ?>
        <div style="padding: 3.5rem 1.5rem; text-align: center; color: #64748b;">
            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🔍</div>
            <h4 style="font-weight: 700; color: #334155; margin-bottom: 0.25rem;">No Registrations Found</h4>
            <p style="font-size: 0.9rem; margin: 0;">No school records match your current filter parameters.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive" style="overflow-x: auto; width: 100%;">
            <table class="table" style="margin-bottom: 0; width: 100%; min-width: 1450px; border-collapse: collapse;">
                <thead style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                    <tr>
                        <th style="font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; color: #475569; padding: 14px 18px; width: 65px; min-width: 60px; text-align: center;">S.No</th>
                        <th style="font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; color: #475569; padding: 14px 18px; width: 160px; min-width: 160px;">Ref ID (EEMTSRC)</th>
                        <th style="font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; color: #475569; padding: 14px 18px; width: 340px; min-width: 300px;">School Name</th>
                        <th style="font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; color: #475569; padding: 14px 18px; width: 220px; min-width: 200px;">District / Mandal</th>
                        <th style="font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; color: #475569; padding: 14px 18px; width: 140px; min-width: 130px;">UDISE Code</th>
                        <th style="font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; color: #475569; padding: 14px 18px; width: 200px; min-width: 180px;">In-Charge Person</th>
                        <th style="font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; color: #475569; padding: 14px 18px; width: 220px; min-width: 200px;">Mobile / Email</th>
                        <th style="font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; color: #475569; padding: 14px 18px; width: 180px; min-width: 160px;">Registered On</th>
                        <th style="font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; color: #475569; padding: 14px 18px; width: 130px; min-width: 120px; text-align: right;">Admin Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registrations as $index => $row): 
                        $sno = ($offset ?? 0) + $index + 1;
                    ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 14px 18px; vertical-align: middle; text-align: center;">
                                <span style="display: inline-flex; align-items: center; justify-content: center; min-width: 28px; height: 28px; padding: 0 6px; border-radius: 6px; background: #f1f5f9; color: #475569; font-weight: 750; font-size: 0.82rem; border: 1px solid #e2e8f0;">
                                    <?php echo $sno; ?>
                                </span>
                            </td>
                            <td style="padding: 14px 18px; vertical-align: middle;">
                                <span class="badge" style="background: #ececf7; color: #1d4ed8; font-family: monospace; font-size: 0.88rem; font-weight: 700; border: 1px solid #c8c9eb; white-space: nowrap;">
                                    <?php echo htmlspecialchars($row['reference_id']); ?>
                                </span>
                            </td>
                            <td style="padding: 14px 18px; vertical-align: middle;">
                                <strong style="color: #0f172a; display: block; font-size: 0.94rem; line-height: 1.4;">
                                    <?php echo htmlspecialchars($row['school_name']); ?>
                                </strong>
                                <span style="font-size: 0.75rem; color: #64748b;">EEMT 2027 Prelims</span>
                            </td>
                            <td style="padding: 14px 18px; vertical-align: middle;">
                                <div style="font-weight: 700; color: #334155; font-size: 0.88rem; white-space: nowrap;"><?php echo htmlspecialchars($row['district']); ?></div>
                                <div style="font-size: 0.8rem; color: #64748b; white-space: nowrap;"><?php echo htmlspecialchars($row['mandal']); ?></div>
                            </td>
                            <td style="padding: 14px 18px; vertical-align: middle;">
                                <code style="background: #f1f5f9; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.85rem; color: #0284c7; font-weight: 600; white-space: nowrap;">
                                    <?php echo htmlspecialchars($row['unique_code']); ?>
                                </code>
                            </td>
                            <td style="padding: 14px 18px; vertical-align: middle;">
                                <div style="font-weight: 600; color: #1e293b; font-size: 0.88rem; white-space: nowrap;"><?php echo htmlspecialchars($row['contact_person']); ?></div>
                                <span class="badge badge-info" style="font-size: 0.7rem; padding: 0.15rem 0.45rem; font-weight: 600; white-space: nowrap;"><?php echo htmlspecialchars($row['designation']); ?></span>
                            </td>
                            <td style="padding: 14px 18px; vertical-align: middle;">
                                <div><a href="tel:<?php echo htmlspecialchars($row['mobile']); ?>" style="color: #31328f; font-weight: 600; font-size: 0.85rem; text-decoration: none; white-space: nowrap;"><?php echo htmlspecialchars($row['mobile']); ?></a></div>
                                <div style="font-size: 0.78rem; color: #64748b; margin-top: 2px;"><?php echo htmlspecialchars($row['email']); ?></div>
                            </td>
                            <td style="padding: 14px 18px; vertical-align: middle; font-size: 0.8rem; color: #64748b; white-space: nowrap;">
                                <?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?>
                            </td>
                            <td style="padding: 14px 18px; vertical-align: middle; text-align: right; white-space: nowrap;">
                                <div style="display: inline-flex; align-items: center; gap: 5px;">
                                    <button type="button" class="btn" onclick='openViewRegModal(<?php echo json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' style="padding: 5px 10px; font-size: 0.76rem; font-weight: 700; background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; border-radius: 6px; cursor: pointer;" title="View Registration Full Details">
                                        👁️ View
                                    </button>
                                    <button type="button" class="btn" onclick='openEditRegModal(<?php echo json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' style="padding: 5px 10px; font-size: 0.76rem; font-weight: 700; background: #ececf7; color: #1d4ed8; border: 1px solid #c8c9eb; border-radius: 6px; cursor: pointer;" title="Edit Registration Details">
                                        ✏️ Edit
                                    </button>
                                    <a href="../register_success.php?ref=<?php echo urlencode($row['reference_id']); ?>" target="_blank" class="btn" style="padding: 5px 10px; font-size: 0.76rem; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; border-radius: 6px; text-decoration: none;" title="View Official Registration Slip">
                                        📄 Slip
                                    </a>
                                    <form method="POST" action="dashboard.php" style="display: inline-block; margin: 0;" onsubmit="return confirm('Permanently delete registration for <?php echo htmlspecialchars(addslashes($row['school_name'])); ?> (<?php echo $row['reference_id']; ?>)?');">
                                        <input type="hidden" name="reg_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" name="delete_reg" value="1" class="btn" style="padding: 5px 8px; font-size: 0.76rem; font-weight: 600; background: #fef2f1; color: #ef3b2d; border: 1px solid #fed7d5; border-radius: 6px; cursor: pointer;" title="Delete Record">
                                            🗑️
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Modern Pagination Bar Footer -->
        <div style="padding: 1.1rem 1.5rem; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; background: #ffffff;">
            <div style="font-size: 0.85rem; color: #64748b;">
                Showing <strong><?php echo $showingFrom; ?></strong> to <strong><?php echo $showingTo; ?></strong> of <strong><?php echo number_format($totalFiltered); ?></strong> registrations
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="custom-pagination">
                    <!-- First Page -->
                    <a href="<?php echo buildPaginationUrl(1, $perPage, $districtFilter, $mandalFilter, $search); ?>" 
                       class="page-btn <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>" 
                       title="First Page">«</a>

                    <!-- Previous Page -->
                    <a href="<?php echo buildPaginationUrl($currentPage - 1, $perPage, $districtFilter, $mandalFilter, $search); ?>" 
                       class="page-btn <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>" 
                       title="Previous Page">‹</a>

                    <?php
                    // Windowed page numbers: show max 5 surrounding current
                    $startPage = max(1, $currentPage - 2);
                    $endPage = min($totalPages, $currentPage + 2);

                    if ($startPage > 1) {
                        echo '<a href="' . buildPaginationUrl(1, $perPage, $districtFilter, $mandalFilter, $search) . '" class="page-btn">1</a>';
                        if ($startPage > 2) {
                            echo '<span style="color: #94a3b8; padding: 0 4px;">…</span>';
                        }
                    }

                    for ($p = $startPage; $p <= $endPage; $p++):
                    ?>
                        <a href="<?php echo buildPaginationUrl($p, $perPage, $districtFilter, $mandalFilter, $search); ?>" 
                           class="page-btn <?php echo $p === $currentPage ? 'active' : ''; ?>">
                            <?php echo $p; ?>
                        </a>
                    <?php endfor; ?>

                    <?php
                    if ($endPage < $totalPages) {
                        if ($endPage < $totalPages - 1) {
                            echo '<span style="color: #94a3b8; padding: 0 4px;">…</span>';
                        }
                        echo '<a href="' . buildPaginationUrl($totalPages, $perPage, $districtFilter, $mandalFilter, $search) . '" class="page-btn">' . $totalPages . '</a>';
                    }
                    ?>

                    <!-- Next Page -->
                    <a href="<?php echo buildPaginationUrl($currentPage + 1, $perPage, $districtFilter, $mandalFilter, $search); ?>" 
                       class="page-btn <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>" 
                       title="Next Page">›</a>

                    <!-- Last Page -->
                    <a href="<?php echo buildPaginationUrl($totalPages, $perPage, $districtFilter, $mandalFilter, $search); ?>" 
                       class="page-btn <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>" 
                       title="Last Page">»</a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- MODAL: VIEW SCHOOL REGISTRATION DETAILS -->
<div id="viewRegModal" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;">
    <div style="background: #ffffff; border-radius: 18px; width: 100%; max-width: 650px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); overflow: hidden; display: flex; flex-direction: column; animation: modalSlide 0.2s ease-out;">
        <!-- Header -->
        <div style="padding: 1.25rem 1.75rem; background: linear-gradient(135deg, #f8fafc, #ececf7); border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: #ececf7; color: #31328f; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; border: 1px solid #c8c9eb;">
                    🏫
                </div>
                <div>
                    <h3 id="view_school_title" style="margin: 0; font-size: 1.12rem; font-weight: 800; color: #0f172a; line-height: 1.3;">School Details</h3>
                    <div style="display: flex; align-items: center; gap: 8px; margin-top: 3px;">
                        <span id="view_ref_badge" class="badge" style="background: #dbeafe; color: #31328f; font-weight: 700; font-family: monospace; font-size: 0.78rem;"></span>
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
                <button type="button" id="view_to_edit_btn" class="btn btn-primary" style="padding: 8px 16px; font-size: 0.82rem; font-weight: 700; border-radius: 8px;">
                    ✏️ Edit Record
                </button>
                <button type="button" onclick="closeViewRegModal()" class="btn" style="padding: 8px 14px; font-size: 0.82rem; font-weight: 600; background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; border-radius: 8px;">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: EDIT SCHOOL REGISTRATION -->
<div id="editRegModal" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;">
    <div style="background: #ffffff; border-radius: 18px; width: 100%; max-width: 650px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); overflow: hidden; display: flex; flex-direction: column; animation: modalSlide 0.2s ease-out;">
        <div style="padding: 1.25rem 1.75rem; background: linear-gradient(135deg, #f8fafc, #ececf7); border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 1.25rem;">✏️</span>
                <div>
                    <h3 style="margin: 0; font-size: 1.12rem; font-weight: 800; color: #0f172a;">Edit School Registration</h3>
                    <span id="edit_reg_ref_display" style="font-size: 0.78rem; font-family: monospace; color: #31328f; font-weight: 700;"></span>
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
                    <label style="font-size: 0.8rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">District *</label>
                    <input type="text" id="edit_reg_district" name="district" class="form-input" style="width: 100%; height: 40px; border-radius: 8px; font-size: 0.9rem;" required>
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
                <button type="submit" class="btn btn-primary" style="height: 40px; padding: 0 20px; font-size: 0.85rem; font-weight: 700; border-radius: 8px; background: #31328f; color: #ffffff; border: none;">
                    💾 Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

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
    const waText = encodeURIComponent("Respected Headmaster, Greetings from EEMT 2027 Admin Team regarding your registration (" + (row.reference_id || '') + ").");
    document.getElementById('view_wa_link').href = 'https://api.whatsapp.com/send?phone=' + cleanPhone + '&text=' + waText;
    
    document.getElementById('view_email').textContent = row.email || 'N/A';
    document.getElementById('view_created_at').textContent = row.created_at || 'N/A';
    document.getElementById('view_slip_link').href = '../register_success.php?ref=' + encodeURIComponent(row.reference_id || '');
    
    document.getElementById('view_to_edit_btn').onclick = function() {
        closeViewRegModal();
        openEditRegModal(row);
    };

    document.getElementById('viewRegModal').style.display = 'flex';
}

function closeViewRegModal() {
    document.getElementById('viewRegModal').style.display = 'none';
}

function openEditRegModal(row) {
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

    document.getElementById('editRegModal').style.display = 'flex';
}

function closeEditRegModal() {
    document.getElementById('editRegModal').style.display = 'none';
}

window.addEventListener('click', function(e) {
    if (e.target.id === 'viewRegModal') closeViewRegModal();
    if (e.target.id === 'editRegModal') closeEditRegModal();
});
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
