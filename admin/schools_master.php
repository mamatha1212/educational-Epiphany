<?php
/**
 * EEMT 2027 - Master Directory Manager (Districts, Mandals, Schools & UDISE Codes)
 * 3-Level Hierarchical Architecture with Add, Edit, and Delete across all tiers.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$pdo = getDB();
$message = '';
$messageType = '';

// Active View: 'districts' (default), 'mandals', 'schools'
$view = $_GET['view'] ?? 'districts';
if (!in_array($view, ['districts', 'mandals', 'schools'])) {
    $view = 'districts';
}

$selectedDistrict = trim($_GET['district'] ?? '');
$selectedMandal = trim($_GET['mandal'] ?? '');
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

// Handle CSV Exports
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="eemt2027_' . $view . '_export_' . date('Ymd_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

    if ($view === 'districts') {
        fputcsv($out, ['District Name', 'Total Mandals', 'Total Schools', 'Enrolled Schools']);
        $sql = "SELECT district, COUNT(DISTINCT mandal) as total_mandals, COUNT(*) as total_schools, SUM(CASE WHEN is_registered = 1 THEN 1 ELSE 0 END) as registered_schools FROM schools_master WHERE district != '' GROUP BY district ORDER BY district ASC";
        foreach ($pdo->query($sql) as $r) {
            fputcsv($out, [$r['district'], $r['total_mandals'], $r['total_schools'], $r['registered_schools']]);
        }
    } elseif ($view === 'mandals') {
        fputcsv($out, ['Mandal Name', 'District Name', 'Total Schools', 'Enrolled Schools']);
        $whereM = $selectedDistrict ? "WHERE district = " . $pdo->quote($selectedDistrict) : "WHERE mandal != ''";
        $sql = "SELECT mandal, district, COUNT(*) as total_schools, SUM(CASE WHEN is_registered = 1 THEN 1 ELSE 0 END) as registered_schools FROM schools_master {$whereM} GROUP BY district, mandal ORDER BY district ASC, mandal ASC";
        foreach ($pdo->query($sql) as $r) {
            fputcsv($out, [$r['mandal'], $r['district'], $r['total_schools'], $r['registered_schools']]);
        }
    } else {
        fputcsv($out, ['ID', 'District', 'Mandal', 'School Name', 'UDISE Code', 'Status']);
        $w = [];
        if ($selectedDistrict) $w[] = "district = " . $pdo->quote($selectedDistrict);
        if ($selectedMandal) $w[] = "mandal = " . $pdo->quote($selectedMandal);
        $wStr = !empty($w) ? "WHERE " . implode(' AND ', $w) : "";
        $sql = "SELECT id, district, mandal, school_name, unique_code, is_registered FROM schools_master {$wStr} ORDER BY district ASC, mandal ASC, school_name ASC";
        foreach ($pdo->query($sql) as $r) {
            fputcsv($out, [$r['id'], $r['district'], $r['mandal'], $r['school_name'], $r['unique_code'], $r['is_registered'] == 1 ? 'Registered' : 'Available']);
        }
    }
    fclose($out);
    exit;
}

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. ADD DISTRICT
    if ($action === 'add_district') {
        $district = trim($_POST['district'] ?? '');
        $initialMandal = trim($_POST['initial_mandal'] ?? 'Headquarters');
        $initialSchool = trim($_POST['initial_school'] ?? 'District Model School');
        $initialCode = trim($_POST['initial_code'] ?? '');

        if (empty($district)) {
            $message = "District name is required.";
            $messageType = 'error';
        } else {
            // Check if district already exists
            $chk = $pdo->prepare("SELECT COUNT(*) FROM schools_master WHERE LOWER(district) = LOWER(?)");
            $chk->execute([$district]);
            if ((int)$chk->fetchColumn() > 0) {
                $message = "District '$district' already exists in the master directory.";
                $messageType = 'error';
            } else {
                if (empty($initialCode)) {
                    $initialCode = 'AP' . rand(100000000, 999999999);
                }
                $ins = $pdo->prepare("INSERT INTO schools_master (district, mandal, school_name, unique_code, is_registered, created_at) VALUES (?, ?, ?, ?, 0, CURRENT_TIMESTAMP)");
                $ins->execute([$district, $initialMandal, $initialSchool, $initialCode]);
                $message = "District '$district' created successfully with initial mandal '$initialMandal'.";
                $messageType = 'success';
                $view = 'districts';
            }
        }
    }

    // 2. EDIT / RENAME DISTRICT
    elseif ($action === 'edit_district') {
        $oldDistrict = trim($_POST['old_district'] ?? '');
        $newDistrict = trim($_POST['new_district'] ?? '');

        if (empty($oldDistrict) || empty($newDistrict)) {
            $message = "Both current and new district names are required.";
            $messageType = 'error';
        } else {
            // Update schools_master
            $updMaster = $pdo->prepare("UPDATE schools_master SET district = ? WHERE district = ?");
            $updMaster->execute([$newDistrict, $oldDistrict]);

            // Sync with registrations
            $updReg = $pdo->prepare("UPDATE school_registrations_2027 SET district = ? WHERE district = ?");
            $updReg->execute([$newDistrict, $oldDistrict]);

            // Sync with coordinator assignments
            $updUsers = $pdo->prepare("UPDATE users SET assigned_district = ? WHERE assigned_district = ?");
            $updUsers->execute([$newDistrict, $oldDistrict]);

            $message = "District renamed from '$oldDistrict' to '$newDistrict' across directory and registrations.";
            $messageType = 'success';
            if ($selectedDistrict === $oldDistrict) {
                $selectedDistrict = $newDistrict;
            }
        }
    }

    // 3. DELETE DISTRICT
    elseif ($action === 'delete_district') {
        $district = trim($_POST['district'] ?? '');

        if (!empty($district)) {
            $regChk = $pdo->prepare("SELECT COUNT(*) FROM school_registrations_2027 WHERE district = ?");
            $regChk->execute([$district]);
            $enrolledCount = (int)$regChk->fetchColumn();

            if ($enrolledCount > 0) {
                $message = "Cannot delete district '$district': It contains $enrolledCount actively registered schools in EEMT 2027.";
                $messageType = 'error';
            } else {
                $del = $pdo->prepare("DELETE FROM schools_master WHERE district = ?");
                $del->execute([$district]);
                $message = "District '$district' and its associated records have been removed.";
                $messageType = 'success';
                $view = 'districts';
            }
        }
    }

    // 4. ADD MANDAL
    elseif ($action === 'add_mandal') {
        $district = trim($_POST['district'] ?? '');
        $mandal = trim($_POST['mandal'] ?? '');
        $initialSchool = trim($_POST['initial_school'] ?? 'Govt High School ' . $mandal);
        $initialCode = trim($_POST['initial_code'] ?? '');

        if (empty($district) || empty($mandal)) {
            $message = "Both District and Mandal name are required.";
            $messageType = 'error';
        } else {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM schools_master WHERE LOWER(district) = LOWER(?) AND LOWER(mandal) = LOWER(?)");
            $chk->execute([$district, $mandal]);
            if ((int)$chk->fetchColumn() > 0) {
                $message = "Mandal '$mandal' already exists in district '$district'.";
                $messageType = 'error';
            } else {
                if (empty($initialCode)) {
                    $initialCode = 'AP' . rand(100000000, 999999999);
                }
                $ins = $pdo->prepare("INSERT INTO schools_master (district, mandal, school_name, unique_code, is_registered, created_at) VALUES (?, ?, ?, ?, 0, CURRENT_TIMESTAMP)");
                $ins->execute([$district, $mandal, $initialSchool, $initialCode]);
                $message = "Mandal '$mandal' created successfully under district '$district'.";
                $messageType = 'success';
                $view = 'mandals';
                $selectedDistrict = $district;
            }
        }
    }

    // 5. EDIT / RENAME MANDAL
    elseif ($action === 'edit_mandal') {
        $district = trim($_POST['district'] ?? '');
        $oldMandal = trim($_POST['old_mandal'] ?? '');
        $newMandal = trim($_POST['new_mandal'] ?? '');

        if (empty($district) || empty($oldMandal) || empty($newMandal)) {
            $message = "District, old mandal, and new mandal names are required.";
            $messageType = 'error';
        } else {
            $updMaster = $pdo->prepare("UPDATE schools_master SET mandal = ? WHERE district = ? AND mandal = ?");
            $updMaster->execute([$newMandal, $district, $oldMandal]);

            $updReg = $pdo->prepare("UPDATE school_registrations_2027 SET mandal = ? WHERE district = ? AND mandal = ?");
            $updReg->execute([$newMandal, $district, $oldMandal]);

            $message = "Mandal renamed from '$oldMandal' to '$newMandal' in district '$district'.";
            $messageType = 'success';
            if ($selectedMandal === $oldMandal) {
                $selectedMandal = $newMandal;
            }
        }
    }

    // 6. DELETE MANDAL
    elseif ($action === 'delete_mandal') {
        $district = trim($_POST['district'] ?? '');
        $mandal = trim($_POST['mandal'] ?? '');

        if (!empty($district) && !empty($mandal)) {
            $regChk = $pdo->prepare("SELECT COUNT(*) FROM school_registrations_2027 WHERE district = ? AND mandal = ?");
            $regChk->execute([$district, $mandal]);
            $enrolled = (int)$regChk->fetchColumn();

            if ($enrolled > 0) {
                $message = "Cannot delete mandal '$mandal': It contains $enrolled actively registered schools.";
                $messageType = 'error';
            } else {
                $del = $pdo->prepare("DELETE FROM schools_master WHERE district = ? AND mandal = ?");
                $del->execute([$district, $mandal]);
                $message = "Mandal '$mandal' removed from district '$district'.";
                $messageType = 'success';
            }
        }
    }

    // 7. ADD SCHOOL
    elseif ($action === 'add_school') {
        $district = trim($_POST['district'] ?? '');
        $mandal = trim($_POST['mandal'] ?? '');
        $schoolName = trim($_POST['school_name'] ?? '');
        $uniqueCode = trim($_POST['unique_code'] ?? '');

        if (empty($district) || empty($mandal) || empty($schoolName) || empty($uniqueCode)) {
            $message = "District, Mandal, School Name, and Unique Code are all required.";
            $messageType = 'error';
        } else {
            $codeChk = $pdo->prepare("SELECT id, school_name FROM schools_master WHERE unique_code = ?");
            $codeChk->execute([$uniqueCode]);
            $existing = $codeChk->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $message = "Unique Code '$uniqueCode' already belongs to '{$existing['school_name']}' (#{$existing['id']}).";
                $messageType = 'error';
            } else {
                $ins = $pdo->prepare("INSERT INTO schools_master (district, mandal, school_name, unique_code, is_registered, created_at) VALUES (?, ?, ?, ?, 0, CURRENT_TIMESTAMP)");
                $ins->execute([$district, $mandal, $schoolName, $uniqueCode]);
                $message = "School '$schoolName' (UDISE: $uniqueCode) added to $mandal, $district.";
                $messageType = 'success';
                $view = 'schools';
                $selectedDistrict = $district;
                $selectedMandal = $mandal;
            }
        }
    }

    // 8. EDIT SCHOOL
    elseif ($action === 'edit_school') {
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
            $codeChk = $pdo->prepare("SELECT id, school_name FROM schools_master WHERE unique_code = ? AND id != ?");
            $codeChk->execute([$uniqueCode, $schoolId]);
            $conflict = $codeChk->fetch(PDO::FETCH_ASSOC);

            if ($conflict) {
                $message = "Unique Code '$uniqueCode' already belongs to '{$conflict['school_name']}' (#{$conflict['id']}).";
                $messageType = 'error';
            } else {
                $upd = $pdo->prepare("UPDATE schools_master SET district = ?, mandal = ?, school_name = ?, unique_code = ?, is_registered = ? WHERE id = ?");
                $upd->execute([$district, $mandal, $schoolName, $uniqueCode, $isRegistered, $schoolId]);

                // Sync with registration if enrolled
                $sync = $pdo->prepare("UPDATE school_registrations_2027 SET district = ?, mandal = ?, school_name = ?, unique_code = ? WHERE school_id = ?");
                $sync->execute([$district, $mandal, $schoolName, $uniqueCode, $schoolId]);

                $message = "School record #$schoolId updated successfully.";
                $messageType = 'success';
            }
        }
    }

    // 9. DELETE SCHOOL
    elseif ($action === 'delete_school') {
        $schoolId = (int)($_POST['school_id'] ?? 0);
        if ($schoolId > 0) {
            $regChk = $pdo->prepare("SELECT id, reference_id FROM school_registrations_2027 WHERE school_id = ?");
            $regChk->execute([$schoolId]);
            $enrolled = $regChk->fetch(PDO::FETCH_ASSOC);

            if ($enrolled) {
                $message = "Cannot delete school: It has active registration '{$enrolled['reference_id']}'.";
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

// KPI Counts
$totalMasterSchools = (int)$pdo->query("SELECT COUNT(*) FROM schools_master")->fetchColumn();
$totalDistrictsCount = (int)$pdo->query("SELECT COUNT(DISTINCT district) FROM schools_master WHERE district != ''")->fetchColumn();
$totalMandalsCount = (int)$pdo->query("SELECT COUNT(DISTINCT mandal) FROM schools_master WHERE mandal != ''")->fetchColumn();
$totalRegisteredSchools = (int)$pdo->query("SELECT COUNT(*) FROM schools_master WHERE is_registered = 1")->fetchColumn();

// Districts List for Selects & Autocomplete
$districtsList = $pdo->query("SELECT DISTINCT district FROM schools_master WHERE district != '' ORDER BY district ASC")->fetchAll(PDO::FETCH_COLUMN);

// Pagination setup
$reqPerPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 25;
$perPage = in_array($reqPerPage, [15, 25, 50, 100, 250], true) ? $reqPerPage : 25;
$page = max(1, (int)($_GET['page'] ?? 1));

$pageTitle = "Directory & Geographic Hierarchy - EEMT 2027";
require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
.hierarchy-tab {
    padding: 10px 20px;
    font-size: 0.92rem;
    font-weight: 700;
    border-radius: 10px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    border: 1.5px solid transparent;
}
.hierarchy-tab.active {
    background: #2563eb;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(37,99,235,0.25);
}
.hierarchy-tab:not(.active) {
    background: #ffffff;
    color: #475569;
    border-color: #e2e8f0;
}
.hierarchy-tab:not(.active):hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    color: #0f172a;
}
.sm-metric-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.15rem 1.4rem;
    display: flex;
    align-items: center;
    gap: 1.15rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    transition: transform 0.2s ease;
}
.sm-metric-card:hover {
    transform: translateY(-2px);
}
.sm-metric-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
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
    max-width: 540px;
    border-radius: 16px;
    box-shadow: 0 20px 45px rgba(0,0,0,0.25);
    overflow: hidden;
    animation: modalSlide 0.18s ease-out forwards;
}
@keyframes modalSlide {
    from { opacity: 0; transform: translateY(10px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
.data-table-wrapper {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}
.data-table-head {
    background: #f8fafc;
    border-bottom: 1.5px solid #e2e8f0;
}
.data-table-head th {
    padding: 0.85rem 1.25rem;
    font-size: 0.75rem;
    text-transform: uppercase;
    color: #64748b;
    font-weight: 700;
    letter-spacing: 0.05em;
}
.data-table-row {
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.15s ease;
}
.data-table-row:hover {
    background: #f8fafc;
}
.data-table-row td {
    padding: 1rem 1.25rem;
    vertical-align: middle;
}
</style>

<!-- Top Breadcrumbs & Heading -->
<div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
    <div>
        <!-- Interactive Breadcrumb Trail -->
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.35rem; font-size: 0.85rem;">
            <a href="dashboard.php" style="color: #64748b; text-decoration: none; font-weight: 600;">Admin</a>
            <span style="color: #cbd5e1;">/</span>
            <a href="schools_master.php?view=districts" style="color: <?php echo $view === 'districts' ? '#2563eb' : '#64748b'; ?>; text-decoration: none; font-weight: 700;">
                Districts (<?php echo $totalDistrictsCount; ?>)
            </a>
            <?php if (!empty($selectedDistrict)): ?>
                <span style="color: #cbd5e1;">/</span>
                <a href="schools_master.php?view=mandals&district=<?php echo urlencode($selectedDistrict); ?>" style="color: <?php echo $view === 'mandals' ? '#2563eb' : '#64748b'; ?>; text-decoration: none; font-weight: 700;">
                    <?php echo htmlspecialchars($selectedDistrict); ?>
                </a>
            <?php endif; ?>
            <?php if (!empty($selectedMandal)): ?>
                <span style="color: #cbd5e1;">/</span>
                <span style="color: #2563eb; font-weight: 700;">
                    <?php echo htmlspecialchars($selectedMandal); ?> (Schools)
                </span>
            <?php endif; ?>
        </div>

        <h1 style="font-size: 1.65rem; font-weight: 800; color: #0f172a; margin: 0 0 0.3rem 0;">
            Geographic Hierarchy &amp; School Directory
        </h1>
        <p style="margin: 0; font-size: 0.88rem; color: #64748b;">
            Manage revenue districts, taluks/mandals, school master records, and unique codes with full add/edit/delete control.
        </p>
    </div>

    <!-- Top Action Buttons -->
    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <a href="schools_master.php?view=<?php echo $view; ?>&action=export&district=<?php echo urlencode($selectedDistrict); ?>&mandal=<?php echo urlencode($selectedMandal); ?>" 
           class="btn" style="background: #ffffff; border: 1.5px solid #cbd5e1; color: #334155; padding: 0 1.15rem; height: 42px; font-size: 0.88rem; font-weight: 700; border-radius: 9px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Export (CSV)
        </a>

        <?php if ($view === 'districts'): ?>
            <button type="button" onclick="openAddDistrictModal()" 
                    class="btn" style="background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; border: none; padding: 0 1.35rem; height: 42px; font-size: 0.88rem; font-weight: 700; border-radius: 9px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(37,99,235,0.25);">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                + Add District
            </button>
        <?php elseif ($view === 'mandals'): ?>
            <button type="button" onclick="openAddMandalModal('<?php echo htmlspecialchars(addslashes($selectedDistrict)); ?>')" 
                    class="btn" style="background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; border: none; padding: 0 1.35rem; height: 42px; font-size: 0.88rem; font-weight: 700; border-radius: 9px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(37,99,235,0.25);">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                + Add Mandal
            </button>
        <?php else: ?>
            <button type="button" onclick="openAddSchoolModal('<?php echo htmlspecialchars(addslashes($selectedDistrict)); ?>', '<?php echo htmlspecialchars(addslashes($selectedMandal)); ?>')" 
                    class="btn" style="background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; border: none; padding: 0 1.35rem; height: 42px; font-size: 0.88rem; font-weight: 700; border-radius: 9px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(37,99,235,0.25);">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                + Add School / UDISE
            </button>
        <?php endif; ?>
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
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 1.15rem; margin-bottom: 1.5rem;">
    <div class="sm-metric-card">
        <div class="sm-metric-icon" style="background: #fdf2f8; color: #db2777;">🏛️</div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">1. Districts</div>
            <div style="font-size: 1.65rem; font-weight: 800; color: #0f172a; line-height: 1.2; margin-top: 2px;">
                <?php echo $totalDistrictsCount; ?>
            </div>
            <div style="font-size: 0.75rem; color: #db2777; font-weight: 600; margin-top: 2px;">Revenue Districts Covered</div>
        </div>
    </div>

    <div class="sm-metric-card">
        <div class="sm-metric-icon" style="background: #f0fdf4; color: #16a34a;">🗺️</div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">2. Taluks / Mandals</div>
            <div style="font-size: 1.65rem; font-weight: 800; color: #0f172a; line-height: 1.2; margin-top: 2px;">
                <?php echo $totalMandalsCount; ?>
            </div>
            <div style="font-size: 0.75rem; color: #16a34a; font-weight: 600; margin-top: 2px;">Block / Mandal Clusters</div>
        </div>
    </div>

    <div class="sm-metric-card">
        <div class="sm-metric-icon" style="background: #eff6ff; color: #2563eb;">🏫</div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">3. Master Schools</div>
            <div style="font-size: 1.65rem; font-weight: 800; color: #0f172a; line-height: 1.2; margin-top: 2px;">
                <?php echo number_format($totalMasterSchools); ?>
            </div>
            <div style="font-size: 0.75rem; color: #2563eb; font-weight: 600; margin-top: 2px;">Total AP Govt Schools</div>
        </div>
    </div>

    <div class="sm-metric-card">
        <div class="sm-metric-icon" style="background: #faf5ff; color: #9333ea;">🎓</div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">EEMT 2027 Enrolled</div>
            <div style="font-size: 1.65rem; font-weight: 800; color: #0f172a; line-height: 1.2; margin-top: 2px;">
                <?php echo number_format($totalRegisteredSchools); ?>
            </div>
            <div style="font-size: 0.75rem; color: #9333ea; font-weight: 600; margin-top: 2px;">
                <?php echo number_format($totalMasterSchools - $totalRegisteredSchools); ?> Open for Registration
            </div>
        </div>
    </div>
</div>

<!-- 3-TIER HIERARCHY NAVIGATION TABS -->
<div style="display: flex; gap: 10px; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap;">
    <a href="schools_master.php?view=districts" class="hierarchy-tab <?php echo $view === 'districts' ? 'active' : ''; ?>">
        <span>🏛️</span>
        <span>1. Districts (<?php echo $totalDistrictsCount; ?>)</span>
    </a>
    <a href="schools_master.php?view=mandals<?php echo $selectedDistrict ? '&district=' . urlencode($selectedDistrict) : ''; ?>" class="hierarchy-tab <?php echo $view === 'mandals' ? 'active' : ''; ?>">
        <span>🗺️</span>
        <span>2. Mandals <?php echo $selectedDistrict ? 'in ' . htmlspecialchars($selectedDistrict) : '(' . $totalMandalsCount . ')'; ?></span>
    </a>
    <a href="schools_master.php?view=schools<?php echo $selectedDistrict ? '&district=' . urlencode($selectedDistrict) : ''; ?><?php echo $selectedMandal ? '&mandal=' . urlencode($selectedMandal) : ''; ?>" class="hierarchy-tab <?php echo $view === 'schools' ? 'active' : ''; ?>">
        <span>🏫</span>
        <span>3. Schools Directory <?php echo $selectedMandal ? 'in ' . htmlspecialchars($selectedMandal) : ''; ?></span>
    </a>
</div>

<!-- ============================================================== -->
<!-- VIEW 1: DISTRICTS MASTER LIST (28 DISTRICTS FIRST)             -->
<!-- ============================================================== -->
<?php if ($view === 'districts'): ?>
    <?php
    $districtSearch = trim($_GET['search'] ?? '');
    $whereD = $districtSearch ? "WHERE district LIKE " . $pdo->quote("%$districtSearch%") : "WHERE district != ''";
    $dSql = "
        SELECT 
            district,
            COUNT(DISTINCT mandal) AS total_mandals,
            COUNT(*) AS total_schools,
            SUM(CASE WHEN is_registered = 1 THEN 1 ELSE 0 END) AS registered_schools
        FROM schools_master 
        {$whereD}
        GROUP BY district 
        ORDER BY district ASC
    ";
    $districtsData = $pdo->query($dSql)->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <!-- District Filter Toolbar -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.15rem 1.5rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap;">
        <form method="GET" action="schools_master.php" style="display: flex; gap: 10px; align-items: center; flex: 1; max-width: 500px;">
            <input type="hidden" name="view" value="districts">
            <input type="text" name="search" class="sm-input" placeholder="Search District by name..." value="<?php echo htmlspecialchars($districtSearch); ?>">
            <button type="submit" class="btn" style="background: #2563eb; color: white; border: none; padding: 0 1.25rem; height: 42px; font-weight: 700; border-radius: 9px; cursor: pointer; white-space: nowrap;">
                Search
            </button>
            <?php if ($districtSearch): ?>
                <a href="schools_master.php?view=districts" class="btn" style="background: #f1f5f9; color: #475569; border: 1.5px solid #cbd5e1; padding: 0 1rem; height: 42px; font-weight: 700; border-radius: 9px; text-decoration: none; display: inline-flex; align-items: center;">Reset</a>
            <?php endif; ?>
        </form>

        <div style="font-size: 0.88rem; color: #64748b; font-weight: 600;">
            Showing <strong><?php echo count($districtsData); ?></strong> Revenue Districts
        </div>
    </div>

    <!-- Districts Table -->
    <div class="data-table-wrapper">
        <div style="padding: 1.15rem 1.5rem; border-bottom: 1px solid #e2e8f0; background: #f8fafc; display: flex; justify-content: space-between; align-items: center;">
            <span style="font-weight: 800; color: #0f172a; font-size: 1.05rem;">Andhra Pradesh Revenue Districts (28)</span>
            <span style="font-size: 0.82rem; color: #64748b;">Click <strong>"View Mandals"</strong> to drill down into sub-district blocks</span>
        </div>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; margin: 0;">
                <thead class="data-table-head">
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>District Name</th>
                        <th style="text-align: center;">Total Mandals</th>
                        <th style="text-align: center;">Total Schools</th>
                        <th style="text-align: center;">Enrolled in EEMT</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($districtsData)): ?>
                        <tr>
                            <td colspan="6" style="padding: 3rem 1.5rem; text-align: center; color: #64748b;">
                                <div style="font-size: 2rem;">🔍</div>
                                <div style="font-weight: 700; color: #1e293b; margin-top: 4px;">No districts matching "<?php echo htmlspecialchars($districtSearch); ?>"</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $idx = 1; foreach ($districtsData as $d): ?>
                            <tr class="data-table-row">
                                <td style="color: #94a3b8; font-family: monospace; font-size: 0.82rem;"><?php echo $idx++; ?></td>
                                <td>
                                    <div style="font-weight: 800; color: #0f172a; font-size: 0.95rem;">
                                        <?php echo htmlspecialchars($d['district']); ?>
                                    </div>
                                </td>
                                <td style="text-align: center;">
                                    <span style="background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; font-size: 0.82rem; font-weight: 700; padding: 4px 10px; border-radius: 9999px;">
                                        🗺️ <?php echo number_format($d['total_mandals']); ?> Mandals
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <span style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; font-size: 0.82rem; font-weight: 700; padding: 4px 10px; border-radius: 9999px;">
                                        🏫 <?php echo number_format($d['total_schools']); ?> Schools
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ((int)$d['registered_schools'] > 0): ?>
                                        <span style="background: #faf5ff; color: #7e22ce; border: 1px solid #e9d5ff; font-size: 0.82rem; font-weight: 700; padding: 4px 10px; border-radius: 9999px;">
                                            🎓 <?php echo $d['registered_schools']; ?> Enrolled
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-size: 0.82rem;">0</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <a href="schools_master.php?view=mandals&district=<?php echo urlencode($d['district']); ?>" 
                                       style="height: 32px; padding: 0 12px; background: #2563eb; color: #ffffff; border-radius: 7px; font-size: 0.82rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                        🔍 View Mandals &rarr;
                                    </a>
                                    <button type="button" onclick="openEditDistrictModal('<?php echo htmlspecialchars(addslashes($d['district'])); ?>')"
                                            style="height: 32px; padding: 0 10px; background: #f1f5f9; color: #1e293b; border: 1px solid #cbd5e1; border-radius: 7px; font-size: 0.8rem; font-weight: 700; cursor: pointer; margin-left: 4px;">
                                        ✏️ Rename
                                    </button>
                                    <form method="POST" action="schools_master.php" style="display: inline-block; margin-left: 4px;" onsubmit="return confirm('WARNING: Are you sure you want to delete district <?php echo htmlspecialchars(addslashes($d['district'])); ?> and its schools?');">
                                        <input type="hidden" name="action" value="delete_district">
                                        <input type="hidden" name="district" value="<?php echo htmlspecialchars($d['district']); ?>">
                                        <button type="submit" style="height: 32px; padding: 0 9px; background: #fff1f2; color: #e11d48; border: 1px solid #fecdd3; border-radius: 7px; font-size: 0.8rem; font-weight: 700; cursor: pointer;">
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
    </div>

<!-- ============================================================== -->
<!-- VIEW 2: MANDALS LIST (ASSOCIATED WITH DISTRICT OR ALL)         -->
<!-- ============================================================== -->
<?php elseif ($view === 'mandals'): ?>
    <?php
    $mandalSearch = trim($_GET['search'] ?? '');
    $whereM = [];
    if (!empty($selectedDistrict)) {
        $whereM[] = "district = " . $pdo->quote($selectedDistrict);
    }
    if (!empty($mandalSearch)) {
        $whereM[] = "(mandal LIKE " . $pdo->quote("%$mandalSearch%") . " OR district LIKE " . $pdo->quote("%$mandalSearch%") . ")";
    }
    $whereMSql = !empty($whereM) ? "WHERE " . implode(' AND ', $whereM) : "WHERE mandal != ''";

    $mSql = "
        SELECT 
            mandal,
            district,
            COUNT(*) AS total_schools,
            SUM(CASE WHEN is_registered = 1 THEN 1 ELSE 0 END) AS registered_schools
        FROM schools_master 
        {$whereMSql}
        GROUP BY district, mandal 
        ORDER BY district ASC, mandal ASC
    ";
    $mandalsData = $pdo->query($mSql)->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <!-- Mandals Filter Toolbar -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.15rem 1.5rem; margin-bottom: 1.5rem;">
        <form method="GET" action="schools_master.php" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <input type="hidden" name="view" value="mandals">
            
            <div style="min-width: 220px;">
                <select name="district" class="sm-select" onchange="this.form.submit()">
                    <option value="">All Districts (Statewide)</option>
                    <?php foreach ($districtsList as $d): ?>
                        <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $selectedDistrict === $d ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($d); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="flex: 1; min-width: 220px;">
                <input type="text" name="search" class="sm-input" placeholder="Search Mandal by name..." value="<?php echo htmlspecialchars($mandalSearch); ?>">
            </div>

            <button type="submit" class="btn" style="background: #2563eb; color: white; border: none; padding: 0 1.25rem; height: 42px; font-weight: 700; border-radius: 9px; cursor: pointer;">
                Filter
            </button>

            <?php if ($selectedDistrict || $mandalSearch): ?>
                <a href="schools_master.php?view=mandals" class="btn" style="background: #f1f5f9; color: #475569; border: 1.5px solid #cbd5e1; padding: 0 1rem; height: 42px; font-weight: 700; border-radius: 9px; text-decoration: none; display: inline-flex; align-items: center;">Reset</a>
            <?php endif; ?>

            <a href="schools_master.php?view=districts" class="btn" style="background: #ffffff; color: #475569; border: 1.5px solid #cbd5e1; padding: 0 1rem; height: 42px; font-weight: 700; border-radius: 9px; text-decoration: none; display: inline-flex; align-items: center; margin-left: auto;">
                &larr; Back to Districts
            </a>
        </form>
    </div>

    <!-- Mandals Table -->
    <div class="data-table-wrapper">
        <div style="padding: 1.15rem 1.5rem; border-bottom: 1px solid #e2e8f0; background: #f8fafc; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
            <div>
                <span style="font-weight: 800; color: #0f172a; font-size: 1.05rem;">
                    <?php echo $selectedDistrict ? "Mandals in " . htmlspecialchars($selectedDistrict) : "Statewide Mandals"; ?>
                </span>
                <span style="background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; font-size: 0.78rem; font-weight: 700; padding: 2px 10px; border-radius: 9999px; margin-left: 8px;">
                    <?php echo count($mandalsData); ?> Mandals
                </span>
            </div>
            <span style="font-size: 0.82rem; color: #64748b;">Click <strong>"View Schools"</strong> to see all schools in a mandal</span>
        </div>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; margin: 0;">
                <thead class="data-table-head">
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Mandal Name</th>
                        <th>District</th>
                        <th style="text-align: center;">Total Schools</th>
                        <th style="text-align: center;">Enrolled in EEMT</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($mandalsData)): ?>
                        <tr>
                            <td colspan="6" style="padding: 3rem 1.5rem; text-align: center; color: #64748b;">
                                <div style="font-size: 2rem;">🔍</div>
                                <div style="font-weight: 700; color: #1e293b; margin-top: 4px;">No mandals found</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $idx = 1; foreach ($mandalsData as $m): ?>
                            <tr class="data-table-row">
                                <td style="color: #94a3b8; font-family: monospace; font-size: 0.82rem;"><?php echo $idx++; ?></td>
                                <td>
                                    <div style="font-weight: 800; color: #0f172a; font-size: 0.95rem;">
                                        <?php echo htmlspecialchars($m['mandal']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="background: #fdf2f8; color: #be185d; border: 1px solid #fbcfe8; font-size: 0.78rem; font-weight: 700; padding: 3px 8px; border-radius: 6px;">
                                        <?php echo htmlspecialchars($m['district']); ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <span style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; font-size: 0.82rem; font-weight: 700; padding: 4px 10px; border-radius: 9999px;">
                                        🏫 <?php echo number_format($m['total_schools']); ?> Schools
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ((int)$m['registered_schools'] > 0): ?>
                                        <span style="background: #faf5ff; color: #7e22ce; border: 1px solid #e9d5ff; font-size: 0.82rem; font-weight: 700; padding: 4px 10px; border-radius: 9999px;">
                                            🎓 <?php echo $m['registered_schools']; ?> Enrolled
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-size: 0.82rem;">0</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <a href="schools_master.php?view=schools&district=<?php echo urlencode($m['district']); ?>&mandal=<?php echo urlencode($m['mandal']); ?>" 
                                       style="height: 32px; padding: 0 12px; background: #059669; color: #ffffff; border-radius: 7px; font-size: 0.82rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                        🏫 View Schools &rarr;
                                    </a>
                                    <button type="button" onclick="openEditMandalModal('<?php echo htmlspecialchars(addslashes($m['district'])); ?>', '<?php echo htmlspecialchars(addslashes($m['mandal'])); ?>')"
                                            style="height: 32px; padding: 0 10px; background: #f1f5f9; color: #1e293b; border: 1px solid #cbd5e1; border-radius: 7px; font-size: 0.8rem; font-weight: 700; cursor: pointer; margin-left: 4px;">
                                        ✏️ Rename
                                    </button>
                                    <form method="POST" action="schools_master.php" style="display: inline-block; margin-left: 4px;" onsubmit="return confirm('WARNING: Are you sure you want to delete mandal <?php echo htmlspecialchars(addslashes($m['mandal'])); ?> from <?php echo htmlspecialchars(addslashes($m['district'])); ?>?');">
                                        <input type="hidden" name="action" value="delete_mandal">
                                        <input type="hidden" name="district" value="<?php echo htmlspecialchars($m['district']); ?>">
                                        <input type="hidden" name="mandal" value="<?php echo htmlspecialchars($m['mandal']); ?>">
                                        <button type="submit" style="height: 32px; padding: 0 9px; background: #fff1f2; color: #e11d48; border: 1px solid #fecdd3; border-radius: 7px; font-size: 0.8rem; font-weight: 700; cursor: pointer;">
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
    </div>

<!-- ============================================================== -->
<!-- VIEW 3: SCHOOLS & UDISE MASTER DIRECTORY (ASSOCIATED OR ALL)   -->
<!-- ============================================================== -->
<?php else: ?>
    <?php
    $whereS = [];
    $paramsS = [];

    if (!empty($selectedDistrict)) {
        $whereS[] = "district = :district";
        $paramsS[':district'] = $selectedDistrict;
    }
    if (!empty($selectedMandal)) {
        $whereS[] = "mandal = :mandal";
        $paramsS[':mandal'] = $selectedMandal;
    }
    if ($statusFilter === 'registered') {
        $whereS[] = "is_registered = 1";
    } elseif ($statusFilter === 'unregistered') {
        $whereS[] = "is_registered = 0";
    }
    if (!empty($search)) {
        $whereS[] = "(school_name LIKE :s OR unique_code LIKE :s OR mandal LIKE :s)";
        $paramsS[':s'] = "%$search%";
    }

    $whereSSql = !empty($whereS) ? "WHERE " . implode(' AND ', $whereS) : "";

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM schools_master {$whereSSql}");
    $countStmt->execute($paramsS);
    $totalFiltered = (int)$countStmt->fetchColumn();

    $totalPages = max(1, (int)ceil($totalFiltered / $perPage));
    $page = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
    $offset = ($page - 1) * $perPage;

    $q = "SELECT * FROM schools_master {$whereSSql} ORDER BY district ASC, mandal ASC, school_name ASC LIMIT :limit OFFSET :offset";
    $stmtS = $pdo->prepare($q);
    foreach ($paramsS as $k => $v) {
        $stmtS->bindValue($k, $v);
    }
    $stmtS->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmtS->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtS->execute();
    $schoolsData = $stmtS->fetchAll(PDO::FETCH_ASSOC);

    // Mandals for selected district
    $mandalsForDistrict = [];
    if (!empty($selectedDistrict)) {
        $mStmt = $pdo->prepare("SELECT DISTINCT mandal FROM schools_master WHERE district = ? ORDER BY mandal ASC");
        $mStmt->execute([$selectedDistrict]);
        $mandalsForDistrict = $mStmt->fetchAll(PDO::FETCH_COLUMN);
    }
    ?>

    <!-- Schools Filter Toolbar -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.15rem 1.5rem; margin-bottom: 1.5rem;">
        <form method="GET" action="schools_master.php" id="schoolsFilterForm">
            <input type="hidden" name="view" value="schools">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; align-items: flex-end;">
                
                <div>
                    <label style="display: block; font-size: 0.78rem; font-weight: 700; color: #475569; margin-bottom: 4px;">District</label>
                    <select name="district" class="sm-select" onchange="onSchoolsDistrictChange(this.value)">
                        <option value="">All Districts</option>
                        <?php foreach ($districtsList as $d): ?>
                            <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $selectedDistrict === $d ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="display: block; font-size: 0.78rem; font-weight: 700; color: #475569; margin-bottom: 4px;">Mandal</label>
                    <select id="schoolsMandalSelect" name="mandal" class="sm-select">
                        <option value="">All Mandals</option>
                        <?php foreach ($mandalsForDistrict as $m): ?>
                            <option value="<?php echo htmlspecialchars($m); ?>" <?php echo $selectedMandal === $m ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="display: block; font-size: 0.78rem; font-weight: 700; color: #475569; margin-bottom: 4px;">Status</label>
                    <select name="status" class="sm-select">
                        <option value="">All Status</option>
                        <option value="registered" <?php echo $statusFilter === 'registered' ? 'selected' : ''; ?>>Registered</option>
                        <option value="unregistered" <?php echo $statusFilter === 'unregistered' ? 'selected' : ''; ?>>Available</option>
                    </select>
                </div>

                <div>
                    <label style="display: block; font-size: 0.78rem; font-weight: 700; color: #475569; margin-bottom: 4px;">Search Name or UDISE</label>
                    <input type="text" name="search" class="sm-input" placeholder="e.g. 28142500311..." value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="btn" style="height: 42px; background: #2563eb; color: white; border: none; padding: 0 1.25rem; font-weight: 700; border-radius: 9px; cursor: pointer; flex: 1;">
                        Filter
                    </button>
                    <?php if ($selectedDistrict || $selectedMandal || $statusFilter || $search): ?>
                        <a href="schools_master.php?view=schools" class="btn" style="height: 42px; background: #f1f5f9; color: #475569; border: 1.5px solid #cbd5e1; padding: 0 1rem; font-weight: 700; border-radius: 9px; text-decoration: none; display: inline-flex; align-items: center;">Reset</a>
                    <?php endif; ?>
                </div>

            </div>
        </form>
    </div>

    <!-- Schools Table -->
    <div class="data-table-wrapper">
        <div style="padding: 1.15rem 1.5rem; border-bottom: 1px solid #e2e8f0; background: #f8fafc; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <span style="font-weight: 800; color: #0f172a; font-size: 1.05rem;">
                    Schools Master Directory
                </span>
                <span style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; font-size: 0.78rem; font-weight: 700; padding: 2px 10px; border-radius: 9999px; margin-left: 8px;">
                    <?php echo number_format($totalFiltered); ?> Schools Found
                </span>
            </div>
            
            <div style="font-size: 0.85rem; color: #64748b;">
                Showing <?php echo $totalFiltered > 0 ? number_format($offset + 1) : 0; ?> to <?php echo number_format(min($offset + $perPage, $totalFiltered)); ?> of <?php echo number_format($totalFiltered); ?>
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; margin: 0;">
                <thead class="data-table-head">
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th>UDISE / Unique Code</th>
                        <th>School Name</th>
                        <th>District</th>
                        <th>Mandal</th>
                        <th style="text-align: center;">Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($schoolsData)): ?>
                        <tr>
                            <td colspan="7" style="padding: 3rem 1.5rem; text-align: center; color: #64748b;">
                                <div style="font-size: 2rem;">🔍</div>
                                <div style="font-weight: 700; color: #1e293b; margin-top: 4px;">No schools found</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($schoolsData as $s): ?>
                            <tr class="data-table-row">
                                <td style="color: #94a3b8; font-family: monospace; font-size: 0.82rem;">#<?php echo $s['id']; ?></td>
                                <td>
                                    <div style="display: inline-flex; align-items: center; gap: 6px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 4px 8px;">
                                        <code style="font-family: ui-monospace, SFMono-Regular, monospace; font-size: 0.85rem; font-weight: 700; color: #1e40af;">
                                            <?php echo htmlspecialchars($s['unique_code']); ?>
                                        </code>
                                        <button type="button" onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($s['unique_code']); ?>'); this.innerText='✓'; setTimeout(() => this.innerText='📋', 1500);" title="Copy UDISE Code" style="background: none; border: none; cursor: pointer; font-size: 0.85rem; padding: 0; color: #64748b;">
                                            📋
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: #0f172a; font-size: 0.92rem;">
                                        <?php echo htmlspecialchars($s['school_name']); ?>
                                    </div>
                                </td>
                                <td>
                                    <a href="schools_master.php?view=mandals&district=<?php echo urlencode($s['district']); ?>" style="text-decoration: none;">
                                        <span style="background: #fdf2f8; color: #be185d; border: 1px solid #fbcfe8; font-size: 0.78rem; font-weight: 700; padding: 3px 8px; border-radius: 6px;">
                                            <?php echo htmlspecialchars($s['district']); ?>
                                        </span>
                                    </a>
                                </td>
                                <td>
                                    <a href="schools_master.php?view=schools&district=<?php echo urlencode($s['district']); ?>&mandal=<?php echo urlencode($s['mandal']); ?>" style="color: #475569; font-size: 0.88rem; font-weight: 700; text-decoration: none;">
                                        <?php echo htmlspecialchars($s['mandal']); ?>
                                    </a>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ((int)$s['is_registered'] === 1): ?>
                                        <span style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; font-size: 0.76rem; font-weight: 700; padding: 3px 9px; border-radius: 9999px;">
                                            🟢 Enrolled
                                        </span>
                                    <?php else: ?>
                                        <span style="background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; font-size: 0.76rem; font-weight: 600; padding: 3px 9px; border-radius: 9999px;">
                                            Available
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <button type="button" onclick='openEditSchoolModal(<?php echo json_encode($s, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' 
                                            style="height: 32px; padding: 0 10px; background: #f1f5f9; color: #1e293b; border: 1px solid #cbd5e1; border-radius: 7px; font-size: 0.8rem; font-weight: 700; cursor: pointer;">
                                        ✏️ Edit
                                    </button>
                                    <form method="POST" action="schools_master.php" style="display: inline-block; margin-left: 4px;" onsubmit="return confirm('Are you sure you want to delete school <?php echo htmlspecialchars(addslashes($s['school_name'])); ?>?');">
                                        <input type="hidden" name="action" value="delete_school">
                                        <input type="hidden" name="school_id" value="<?php echo $s['id']; ?>">
                                        <button type="submit" style="height: 32px; padding: 0 9px; background: #fff1f2; color: #e11d48; border: 1px solid #fecdd3; border-radius: 7px; font-size: 0.8rem; font-weight: 700; cursor: pointer;">
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

        <!-- Schools Pagination Footer -->
        <?php if ($totalPages > 1): ?>
            <div style="padding: 1rem 1.5rem; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div style="display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: #64748b;">
                    <span>Rows per page:</span>
                    <select onchange="location.href='schools_master.php?view=schools&district=<?php echo urlencode($selectedDistrict); ?>&mandal=<?php echo urlencode($selectedMandal); ?>&status=<?php echo urlencode($statusFilter); ?>&search=<?php echo urlencode($search); ?>&per_page=' + this.value;" style="height: 32px; border: 1px solid #cbd5e1; border-radius: 6px; padding: 0 8px; background: white; font-size: 0.85rem;">
                        <?php foreach ([15, 25, 50, 100, 250] as $opt): ?>
                            <option value="<?php echo $opt; ?>" <?php echo $perPage === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: flex; gap: 4px; align-items: center;">
                    <?php $bQ = "schools_master.php?view=schools&district=" . urlencode($selectedDistrict) . "&mandal=" . urlencode($selectedMandal) . "&status=" . urlencode($statusFilter) . "&search=" . urlencode($search) . "&per_page={$perPage}&page="; ?>
                    <?php if ($page > 1): ?>
                        <a href="<?php echo $bQ . '1'; ?>" style="height: 32px; min-width: 32px; padding: 0 8px; border: 1px solid #cbd5e1; border-radius: 6px; background: white; color: #334155; font-size: 0.85rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">&laquo;</a>
                        <a href="<?php echo $bQ . ($page - 1); ?>" style="height: 32px; min-width: 32px; padding: 0 8px; border: 1px solid #cbd5e1; border-radius: 6px; background: white; color: #334155; font-size: 0.85rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">&lsaquo;</a>
                    <?php endif; ?>

                    <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                        <a href="<?php echo $bQ . $p; ?>" style="height: 32px; min-width: 32px; padding: 0 10px; border: 1px solid <?php echo $p === $page ? '#2563eb' : '#cbd5e1'; ?>; border-radius: 6px; background: <?php echo $p === $page ? '#2563eb' : '#ffffff'; ?>; color: <?php echo $p === $page ? '#ffffff' : '#334155'; ?>; font-size: 0.85rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">
                            <?php echo $p; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="<?php echo $bQ . ($page + 1); ?>" style="height: 32px; min-width: 32px; padding: 0 8px; border: 1px solid #cbd5e1; border-radius: 6px; background: white; color: #334155; font-size: 0.85rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">&rsaquo;</a>
                        <a href="<?php echo $bQ . $totalPages; ?>" style="height: 32px; min-width: 32px; padding: 0 8px; border: 1px solid #cbd5e1; border-radius: 6px; background: white; color: #334155; font-size: 0.85rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">&raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- ============================================================== -->
<!-- MODAL: ADD DISTRICT                                           -->
<!-- ============================================================== -->
<div id="addDistrictModal" class="modal-overlay">
    <div class="modal-card">
        <div style="padding: 1.25rem 1.75rem; border-bottom: 1px solid #edf2f7; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 38px; height: 38px; border-radius: 10px; background: #fdf2f8; color: #db2777; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                    🏛️
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #0f172a;">Add New District</h3>
                    <div style="font-size: 0.78rem; color: #64748b;">Create a new Andhra Pradesh revenue district</div>
                </div>
            </div>
            <button type="button" onclick="closeAddDistrictModal()" style="background: none; border: none; font-size: 1.5rem; line-height: 1; color: #94a3b8; cursor: pointer;">&times;</button>
        </div>
        <form method="POST" action="schools_master.php" style="padding: 1.5rem 1.75rem;">
            <input type="hidden" name="action" value="add_district">
            <div style="margin-bottom: 1.15rem;">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #475569; margin-bottom: 4px;">District Name <span style="color: #ef4444;">*</span></label>
                <input type="text" name="district" class="sm-input" placeholder="e.g. KURNOOL-2831 / TIRUPATI-2826" required>
            </div>
            <div style="margin-bottom: 1.15rem;">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #475569; margin-bottom: 4px;">Initial Mandal Name</label>
                <input type="text" name="initial_mandal" class="sm-input" placeholder="e.g. Headquarters / Urban Mandal" value="Headquarters">
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #edf2f7; padding-top: 1.25rem;">
                <button type="button" onclick="closeAddDistrictModal()" style="height: 42px; padding: 0 1.25rem; background: white; border: 1.5px solid #cbd5e1; font-weight: 700; font-size: 0.88rem; border-radius: 9px; cursor: pointer;">Cancel</button>
                <button type="submit" style="height: 42px; padding: 0 1.5rem; background: #2563eb; color: white; border: none; font-weight: 700; font-size: 0.88rem; border-radius: 9px; cursor: pointer;">Save District</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL: EDIT / RENAME DISTRICT                                  -->
<!-- ============================================================== -->
<div id="editDistrictModal" class="modal-overlay">
    <div class="modal-card">
        <div style="padding: 1.25rem 1.75rem; border-bottom: 1px solid #edf2f7; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 38px; height: 38px; border-radius: 10px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                    ✏️
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #0f172a;">Rename District</h3>
                    <div style="font-size: 0.78rem; color: #64748b;">Updates across master directory and enrolled schools</div>
                </div>
            </div>
            <button type="button" onclick="closeEditDistrictModal()" style="background: none; border: none; font-size: 1.5rem; line-height: 1; color: #94a3b8; cursor: pointer;">&times;</button>
        </div>
        <form method="POST" action="schools_master.php" style="padding: 1.5rem 1.75rem;">
            <input type="hidden" name="action" value="edit_district">
            <input type="hidden" id="edit_old_district" name="old_district" value="">
            <div style="margin-bottom: 1.15rem;">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #475569; margin-bottom: 4px;">District Name <span style="color: #ef4444;">*</span></label>
                <input type="text" id="edit_new_district" name="new_district" class="sm-input" required>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #edf2f7; padding-top: 1.25rem;">
                <button type="button" onclick="closeEditDistrictModal()" style="height: 42px; padding: 0 1.25rem; background: white; border: 1.5px solid #cbd5e1; font-weight: 700; font-size: 0.88rem; border-radius: 9px; cursor: pointer;">Cancel</button>
                <button type="submit" style="height: 42px; padding: 0 1.5rem; background: #2563eb; color: white; border: none; font-weight: 700; font-size: 0.88rem; border-radius: 9px; cursor: pointer;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL: ADD MANDAL                                              -->
<!-- ============================================================== -->
<div id="addMandalModal" class="modal-overlay">
    <div class="modal-card">
        <div style="padding: 1.25rem 1.75rem; border-bottom: 1px solid #edf2f7; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 38px; height: 38px; border-radius: 10px; background: #f0fdf4; color: #16a34a; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                    🗺️
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #0f172a;">Add New Mandal</h3>
                    <div style="font-size: 0.78rem; color: #64748b;">Add a block/taluk cluster to a district</div>
                </div>
            </div>
            <button type="button" onclick="closeAddMandalModal()" style="background: none; border: none; font-size: 1.5rem; line-height: 1; color: #94a3b8; cursor: pointer;">&times;</button>
        </div>
        <form method="POST" action="schools_master.php" style="padding: 1.5rem 1.75rem;">
            <input type="hidden" name="action" value="add_mandal">
            <div style="margin-bottom: 1.15rem;">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #475569; margin-bottom: 4px;">District <span style="color: #ef4444;">*</span></label>
                <select id="add_mandal_district" name="district" class="sm-select" required>
                    <option value="">-- Select District --</option>
                    <?php foreach ($districtsList as $d): ?>
                        <option value="<?php echo htmlspecialchars($d); ?>"><?php echo htmlspecialchars($d); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom: 1.15rem;">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #475569; margin-bottom: 4px;">Mandal Name <span style="color: #ef4444;">*</span></label>
                <input type="text" name="mandal" class="sm-input" placeholder="e.g. THAVANAMPALLE-282353" required>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #edf2f7; padding-top: 1.25rem;">
                <button type="button" onclick="closeAddMandalModal()" style="height: 42px; padding: 0 1.25rem; background: white; border: 1.5px solid #cbd5e1; font-weight: 700; font-size: 0.88rem; border-radius: 9px; cursor: pointer;">Cancel</button>
                <button type="submit" style="height: 42px; padding: 0 1.5rem; background: #2563eb; color: white; border: none; font-weight: 700; font-size: 0.88rem; border-radius: 9px; cursor: pointer;">Save Mandal</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL: EDIT MANDAL                                             -->
<!-- ============================================================== -->
<div id="editMandalModal" class="modal-overlay">
    <div class="modal-card">
        <div style="padding: 1.25rem 1.75rem; border-bottom: 1px solid #edf2f7; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 38px; height: 38px; border-radius: 10px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                    ✏️
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #0f172a;">Rename Mandal</h3>
                    <div id="editMandalSubtitle" style="font-size: 0.78rem; color: #64748b;">District</div>
                </div>
            </div>
            <button type="button" onclick="closeEditMandalModal()" style="background: none; border: none; font-size: 1.5rem; line-height: 1; color: #94a3b8; cursor: pointer;">&times;</button>
        </div>
        <form method="POST" action="schools_master.php" style="padding: 1.5rem 1.75rem;">
            <input type="hidden" name="action" value="edit_mandal">
            <input type="hidden" id="edit_mandal_district" name="district" value="">
            <input type="hidden" id="edit_mandal_old" name="old_mandal" value="">
            <div style="margin-bottom: 1.15rem;">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #475569; margin-bottom: 4px;">New Mandal Name <span style="color: #ef4444;">*</span></label>
                <input type="text" id="edit_mandal_new" name="new_mandal" class="sm-input" required>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #edf2f7; padding-top: 1.25rem;">
                <button type="button" onclick="closeEditMandalModal()" style="height: 42px; padding: 0 1.25rem; background: white; border: 1.5px solid #cbd5e1; font-weight: 700; font-size: 0.88rem; border-radius: 9px; cursor: pointer;">Cancel</button>
                <button type="submit" style="height: 42px; padding: 0 1.5rem; background: #2563eb; color: white; border: none; font-weight: 700; font-size: 0.88rem; border-radius: 9px; cursor: pointer;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL: ADD SCHOOL                                              -->
<!-- ============================================================== -->
<div id="addSchoolModal" class="modal-overlay">
    <div class="modal-card">
        <div style="padding: 1.25rem 1.75rem; border-bottom: 1px solid #edf2f7; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 38px; height: 38px; border-radius: 10px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                    🏫
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #0f172a;">Add School Master Entry</h3>
                    <div style="font-size: 0.78rem; color: #64748b;">Add school and 11-digit UDISE code</div>
                </div>
            </div>
            <button type="button" onclick="closeAddSchoolModal()" style="background: none; border: none; font-size: 1.5rem; line-height: 1; color: #94a3b8; cursor: pointer;">&times;</button>
        </div>
        <form method="POST" action="schools_master.php" style="padding: 1.5rem 1.75rem;">
            <input type="hidden" name="action" value="add_school">
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1.15rem;">
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #475569; margin-bottom: 4px;">District <span style="color: #ef4444;">*</span></label>
                    <select id="modal_add_district" name="district" class="sm-select" required onchange="loadMandalsForAddSchool(this.value)">
                        <option value="">-- Select District --</option>
                        <?php foreach ($districtsList as $d): ?>
                            <option value="<?php echo htmlspecialchars($d); ?>"><?php echo htmlspecialchars($d); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #475569; margin-bottom: 4px;">Mandal <span style="color: #ef4444;">*</span></label>
                    <input list="modal_add_mandals_list" id="modal_add_mandal" name="mandal" class="sm-input" placeholder="Select or type mandal..." required>
                    <datalist id="modal_add_mandals_list"></datalist>
                </div>
            </div>
            <div style="margin-bottom: 1.15rem;">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #475569; margin-bottom: 4px;">School Name <span style="color: #ef4444;">*</span></label>
                <input type="text" name="school_name" class="sm-input" placeholder="e.g. ZPHS VETLAPALEM" required>
            </div>
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #475569; margin-bottom: 4px;">UDISE / Unique Code <span style="color: #ef4444;">*</span></label>
                <input type="text" name="unique_code" class="sm-input" placeholder="e.g. 28142500311" required>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #edf2f7; padding-top: 1.25rem;">
                <button type="button" onclick="closeAddSchoolModal()" style="height: 42px; padding: 0 1.25rem; background: white; border: 1.5px solid #cbd5e1; font-weight: 700; font-size: 0.88rem; border-radius: 9px; cursor: pointer;">Cancel</button>
                <button type="submit" style="height: 42px; padding: 0 1.5rem; background: #2563eb; color: white; border: none; font-weight: 700; font-size: 0.88rem; border-radius: 9px; cursor: pointer;">Save School</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL: EDIT SCHOOL                                             -->
<!-- ============================================================== -->
<div id="editSchoolModal" class="modal-overlay">
    <div class="modal-card">
        <div style="padding: 1.25rem 1.75rem; border-bottom: 1px solid #edf2f7; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 38px; height: 38px; border-radius: 10px; background: #fef3c7; color: #d97706; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                    ✏️
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #0f172a;">Edit School Record</h3>
                    <div id="modalEditSubtitle" style="font-size: 0.78rem; color: #64748b;">#0 • School</div>
                </div>
            </div>
            <button type="button" onclick="closeEditSchoolModal()" style="background: none; border: none; font-size: 1.5rem; line-height: 1; color: #94a3b8; cursor: pointer;">&times;</button>
        </div>
        <form method="POST" action="schools_master.php" style="padding: 1.5rem 1.75rem;">
            <input type="hidden" name="action" value="edit_school">
            <input type="hidden" id="edit_school_id" name="school_id" value="">
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1.15rem;">
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #475569; margin-bottom: 4px;">District <span style="color: #ef4444;">*</span></label>
                    <input type="text" id="edit_school_district" name="district" class="sm-input" required>
                </div>
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #475569; margin-bottom: 4px;">Mandal <span style="color: #ef4444;">*</span></label>
                    <input type="text" id="edit_school_mandal" name="mandal" class="sm-input" required>
                </div>
            </div>
            <div style="margin-bottom: 1.15rem;">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #475569; margin-bottom: 4px;">School Name <span style="color: #ef4444;">*</span></label>
                <input type="text" id="edit_school_name" name="school_name" class="sm-input" required>
            </div>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #475569; margin-bottom: 4px;">UDISE Code <span style="color: #ef4444;">*</span></label>
                    <input type="text" id="edit_school_unique_code" name="unique_code" class="sm-input" required>
                </div>
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #475569; margin-bottom: 4px;">Registration</label>
                    <select id="edit_school_is_registered" name="is_registered" class="sm-select">
                        <option value="0">⚪ Available</option>
                        <option value="1">🟢 Enrolled in EEMT 2027</option>
                    </select>
                </div>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #edf2f7; padding-top: 1.25rem;">
                <button type="button" onclick="closeEditSchoolModal()" style="height: 42px; padding: 0 1.25rem; background: white; border: 1.5px solid #cbd5e1; font-weight: 700; font-size: 0.88rem; border-radius: 9px; cursor: pointer;">Cancel</button>
                <button type="submit" style="height: 42px; padding: 0 1.5rem; background: #2563eb; color: white; border: none; font-weight: 700; font-size: 0.88rem; border-radius: 9px; cursor: pointer;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
// Dynamic Mandals for Schools Filter
function onSchoolsDistrictChange(district) {
    const sel = document.getElementById('schoolsMandalSelect');
    sel.innerHTML = '<option value="">All Mandals</option>';
    if (!district) return;
    fetch('../api/get_mandals.php?district=' + encodeURIComponent(district))
        .then(r => r.json())
        .then(mandals => {
            mandals.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m;
                opt.textContent = m;
                sel.appendChild(opt);
            });
        });
}

function loadMandalsForAddSchool(district) {
    const list = document.getElementById('modal_add_mandals_list');
    list.innerHTML = '';
    if (!district) return;
    fetch('../api/get_mandals.php?district=' + encodeURIComponent(district))
        .then(r => r.json())
        .then(mandals => {
            mandals.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m;
                list.appendChild(opt);
            });
        });
}

// Modal open/close functions
function openAddDistrictModal() {
    document.getElementById('addDistrictModal').style.display = 'flex';
}
function closeAddDistrictModal() {
    document.getElementById('addDistrictModal').style.display = 'none';
}

function openEditDistrictModal(district) {
    document.getElementById('edit_old_district').value = district;
    document.getElementById('edit_new_district').value = district;
    document.getElementById('editDistrictModal').style.display = 'flex';
}
function closeEditDistrictModal() {
    document.getElementById('editDistrictModal').style.display = 'none';
}

function openAddMandalModal(presetDistrict) {
    const sel = document.getElementById('add_mandal_district');
    if (presetDistrict) sel.value = presetDistrict;
    document.getElementById('addMandalModal').style.display = 'flex';
}
function closeAddMandalModal() {
    document.getElementById('addMandalModal').style.display = 'none';
}

function openEditMandalModal(district, mandal) {
    document.getElementById('edit_mandal_district').value = district;
    document.getElementById('edit_mandal_old').value = mandal;
    document.getElementById('edit_mandal_new').value = mandal;
    document.getElementById('editMandalSubtitle').textContent = 'District: ' + district;
    document.getElementById('editMandalModal').style.display = 'flex';
}
function closeEditMandalModal() {
    document.getElementById('editMandalModal').style.display = 'none';
}

function openAddSchoolModal(presetDistrict, presetMandal) {
    const dSel = document.getElementById('modal_add_district');
    const mInp = document.getElementById('modal_add_mandal');
    if (presetDistrict) {
        dSel.value = presetDistrict;
        loadMandalsForAddSchool(presetDistrict);
    }
    if (presetMandal) {
        mInp.value = presetMandal;
    }
    document.getElementById('addSchoolModal').style.display = 'flex';
}
function closeAddSchoolModal() {
    document.getElementById('addSchoolModal').style.display = 'none';
}

function openEditSchoolModal(school) {
    document.getElementById('edit_school_id').value = school.id;
    document.getElementById('edit_school_district').value = school.district || '';
    document.getElementById('edit_school_mandal').value = school.mandal || '';
    document.getElementById('edit_school_name').value = school.school_name || '';
    document.getElementById('edit_school_unique_code').value = school.unique_code || '';
    document.getElementById('edit_school_is_registered').value = school.is_registered || '0';
    document.getElementById('modalEditSubtitle').textContent = '#' + school.id + ' • ' + (school.school_name || 'School');
    document.getElementById('editSchoolModal').style.display = 'flex';
}
function closeEditSchoolModal() {
    document.getElementById('editSchoolModal').style.display = 'none';
}

// Close on backdrop click
window.addEventListener('click', function(e) {
    ['addDistrictModal', 'editDistrictModal', 'addMandalModal', 'editMandalModal', 'addSchoolModal', 'editSchoolModal'].forEach(id => {
        const modal = document.getElementById(id);
        if (e.target === modal) modal.style.display = 'none';
    });
});
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
