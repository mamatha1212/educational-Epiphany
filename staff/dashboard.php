<?php
/**
 * EEMT 2027 - Staff & Coordinator Dashboard
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

$pageTitle = "Staff & Coordinator Dashboard - EEMT 2027";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="max-width: 1200px;">
    
    <!-- Header Notification Banner -->
    <div class="card" style="margin-bottom: 1.5rem; background: linear-gradient(135deg, #064e3b, #0f172a); color: white; border: none; box-shadow: var(--shadow-lg);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div style="background: white; border-radius: 12px; padding: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.25); flex-shrink: 0;">
                    <img src="../assets/images/logo.png" alt="EEMT Logo" style="height: 52px; width: 52px; object-fit: contain; display: block;" onerror="this.src='../assets/images/logo.jpeg';">
                </div>
                <div>
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.5rem;">
                    <span class="badge" style="background: #10b981; color: white; font-weight: 700;">
                        👔 ROLE: <?php echo htmlspecialchars(strtoupper($user['role'] ?? 'COORDINATOR')); ?>
                    </span>
                    <?php if (!empty($user['assigned_district'])): ?>
                        <span class="badge" style="background: rgba(255, 255, 255, 0.2); color: white;">
                            District Scope: <?php echo htmlspecialchars($user['assigned_district']); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <h2 style="font-size: 1.75rem; font-weight: 800; color: white; margin: 0 0 0.35rem;">
                    Welcome, <?php echo htmlspecialchars($user['name'] ?? $user['username']); ?>
                </h2>
                <p style="color: #a7f3d0; font-size: 0.92rem; margin: 0;">
                    EEMT 2027 District Field Operations & School Verification Workspace
                </p>
            </div>

            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                <?php if (isAdmin()): ?>
                    <a href="../admin/dashboard.php" class="btn" style="background: #3b82f6; color: white;">
                        🛡️ Switch to Admin Dashboard
                    </a>
                <?php endif; ?>
                <a href="#papers-section" class="btn" style="background: rgba(255, 255, 255, 0.15); color: white; border: 1px solid rgba(255, 255, 255, 0.3);">
                    📄 Prelims Papers (<?php echo count($questionPapers); ?>)
                </a>
                <a href="../logout.php" class="btn" style="background: rgba(239, 68, 68, 0.25); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.4);">
                    Logout
                </a>
            </div>
        </div>
    </div>

    <?php if ($statusMessage): ?>
        <div class="alert alert-success" style="margin-bottom: 1.5rem;">
            <span>✅</span>
            <span><?php echo htmlspecialchars($statusMessage); ?></span>
        </div>
    <?php endif; ?>

    <!-- KPI Metric Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
        <div class="card" style="text-align: center; border-left: 4px solid #059669;">
            <div style="font-size: 0.85rem; font-weight: 600; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">Registered Schools</div>
            <div style="font-size: 2.25rem; font-weight: 800; color: #064e3b; margin-top: 0.25rem;"><?php echo number_format($totalSchools); ?></div>
            <div style="font-size: 0.8rem; color: #059669; margin-top: 0.25rem;">In <?php echo $assignedDistrict ?: 'All Districts'; ?></div>
        </div>

        <div class="card" style="text-align: center; border-left: 4px solid #2563eb;">
            <div style="font-size: 0.85rem; font-weight: 600; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">Active Mandals</div>
            <div style="font-size: 2.25rem; font-weight: 800; color: #1e3a8a; margin-top: 0.25rem;"><?php echo number_format($totalMandals); ?></div>
            <div style="font-size: 0.8rem; color: #2563eb; margin-top: 0.25rem;">Mandals Covered</div>
        </div>

        <div class="card" style="text-align: center; border-left: 4px solid #f59e0b;">
            <div style="font-size: 0.85rem; font-weight: 600; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">Verified / Contacted</div>
            <div style="font-size: 2.25rem; font-weight: 800; color: #92400e; margin-top: 0.25rem;"><?php echo number_format($verifiedCount); ?></div>
            <div style="font-size: 0.8rem; color: #f59e0b; margin-top: 0.25rem;">Followed-up Schools</div>
        </div>

        <div class="card" style="text-align: center; border-left: 4px solid #8b5cf6;">
            <div style="font-size: 0.85rem; font-weight: 600; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">Prelims Question Papers</div>
            <div style="font-size: 2.25rem; font-weight: 800; color: #5b21b6; margin-top: 0.25rem;"><?php echo count($questionPapers); ?></div>
            <div style="font-size: 0.8rem; color: #8b5cf6; margin-top: 0.25rem;">Published for Schools</div>
        </div>
    </div>

    <!-- Filter & Search Controls -->
    <div class="card" style="margin-bottom: 1.5rem;">
        <form method="GET" action="dashboard.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) 120px 140px; gap: 1rem; align-items: flex-end;">
            
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="district">District</label>
                <select name="district" id="district" class="form-select">
                    <option value="">All Districts</option>
                    <?php foreach ($allDistricts as $d): ?>
                        <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $districtFilter === $d ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($d); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="mandal">Mandal</label>
                <input type="text" name="mandal" id="mandal" class="form-input" placeholder="Type Mandal name..." value="<?php echo htmlspecialchars($mandalFilter); ?>">
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="status">Status</label>
                <select name="status" id="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="submitted" <?php echo $statusFilter === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                    <option value="contacted" <?php echo $statusFilter === 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                    <option value="verified" <?php echo $statusFilter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="search">Keyword Search</label>
                <input type="text" name="search" id="search" class="form-input" placeholder="Name, UDISE, Mobile, Code..." value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <div>
                <button type="submit" class="btn btn-primary" style="width: 100%; background: #059669; border: none;">
                    Filter
                </button>
            </div>

            <div>
                <a href="../admin/export.php?district=<?php echo urlencode($districtFilter); ?>&mandal=<?php echo urlencode($mandalFilter); ?>&search=<?php echo urlencode($search); ?>" class="btn btn-outline" style="width: 100%; justify-content: center;">
                    📥 Export CSV
                </a>
            </div>
        </form>

        <?php if ($districtFilter !== '' || $mandalFilter !== '' || $statusFilter !== '' || $search !== ''): ?>
            <div style="margin-top: 1rem; font-size: 0.9rem; color: #64748b;">
                Found <strong><?php echo $filteredCount; ?></strong> matching registrations. 
                <a href="dashboard.php" style="color: #059669; text-decoration: underline; margin-left: 0.5rem; font-weight: 600;">Reset Filters</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Registered Schools Field Operations Table -->
    <div class="card" style="padding: 0; overflow: hidden; margin-bottom: 2.5rem;">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
            <div>
                <h3 style="font-size: 1.15rem; font-weight: 700; color: #1e293b; margin: 0;">
                    Registered Schools Verification Roster
                </h3>
                <span style="font-size: 0.8rem; color: var(--text-muted);">
                    Direct WhatsApp & phone outreach tools for school coordinators
                </span>
            </div>
            <span class="badge badge-success"><?php echo $filteredCount; ?> Schools in View</span>
        </div>

        <?php if (empty($registrations)): ?>
            <div style="padding: 3rem; text-align: center; color: #64748b;">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🏫</div>
                <h4 style="font-weight: 700; color: #334155; margin-bottom: 0.25rem;">No School Registrations Found</h4>
                <p style="font-size: 0.9rem; margin: 0;">Try adjusting your search or filters.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>EEMTSRC (Ref ID)</th>
                            <th>School Details</th>
                            <th>Location</th>
                            <th>In-Charge Person</th>
                            <th>Outreach / Actions</th>
                            <th>Status Tracking</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registrations as $row): 
                            $rawPhone = preg_replace('/[^0-9]/', '', $row['mobile']);
                            $cleanPhone = strlen($rawPhone) === 10 ? '91' . $rawPhone : $rawPhone;
                            $waText = rawurlencode("Respected Headmaster, Greetings from EEMT 2027 Coordinator Team.\nRegarding your registration for " . $row['school_name'] . " (EEMTSRC: " . $row['reference_id'] . "). Please let us know if you have received the Prelims Question Papers.");
                        ?>
                            <tr>
                                <td>
                                    <span class="badge" style="background: #eff6ff; color: #1d4ed8; font-family: monospace; font-size: 0.9rem; font-weight: 700; border: 1px solid #bfdbfe;">
                                        <?php echo htmlspecialchars($row['reference_id']); ?>
                                    </span>
                                    <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 4px;">
                                        Reg: <?php echo date('d M, h:i A', strtotime($row['created_at'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <strong style="color: #0f172a; display: block; max-width: 260px; font-size: 0.92rem;">
                                        <?php echo htmlspecialchars($row['school_name']); ?>
                                    </strong>
                                    <span style="font-size: 0.78rem; color: #64748b;">
                                        UDISE: <code style="color: #0284c7;"><?php echo htmlspecialchars($row['unique_code']); ?></code>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: #334155; font-size: 0.88rem;"><?php echo htmlspecialchars($row['district']); ?></div>
                                    <div style="font-size: 0.8rem; color: #64748b;">Mandal: <?php echo htmlspecialchars($row['mandal']); ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($row['contact_person']); ?></div>
                                    <span class="badge badge-info" style="font-size: 0.7rem;"><?php echo htmlspecialchars($row['designation']); ?></span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                                        <!-- WhatsApp Chat Link -->
                                        <?php if (!empty($cleanPhone)): ?>
                                            <a href="https://api.whatsapp.com/send?phone=<?php echo $cleanPhone; ?>&text=<?php echo $waText; ?>" target="_blank" rel="noopener noreferrer" class="btn" style="background: #25d366; color: white; padding: 4px 8px; font-size: 0.75rem; border-radius: 6px; text-decoration: none; font-weight: 700;" title="Send WhatsApp Message">
                                                💬 WhatsApp
                                            </a>
                                            <!-- Call Link -->
                                            <a href="tel:<?php echo htmlspecialchars($row['mobile']); ?>" class="btn btn-outline" style="padding: 4px 8px; font-size: 0.75rem; border-radius: 6px; color: #2563eb;" title="Call Headmaster">
                                                📞 Call
                                            </a>
                                        <?php else: ?>
                                            <span style="font-size: 0.75rem; color: var(--text-muted);">No phone</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 4px;">
                                        <?php echo htmlspecialchars($row['mobile']); ?>
                                    </div>
                                </td>
                                <td>
                                    <form method="POST" action="dashboard.php" style="display: flex; gap: 4px; align-items: center;">
                                        <input type="hidden" name="reg_id" value="<?php echo $row['id']; ?>">
                                        <input type="hidden" name="update_status" value="1">
                                        
                                        <select name="status" class="form-select" style="font-size: 0.78rem; padding: 4px 6px; width: auto; font-weight: 600;" onchange="this.form.submit()">
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
    <div id="papers-section" class="card" style="border-top: 4px solid #8b5cf6;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <div>
                <h3 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0 0 0.25rem;">
                    📄 Published Prelims Question Papers
                </h3>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">
                    Reference repository for coordinators to assist school teachers and check syllabus files
                </p>
            </div>
            <span class="badge" style="background: #f5f3ff; color: #7c3aed; font-weight: 700; border: 1px solid #ddd6fe;">
                <?php echo count($questionPapers); ?> Available
            </span>
        </div>

        <?php if (empty($questionPapers)): ?>
            <div style="padding: 1.5rem; text-align: center; color: var(--text-muted); font-size: 0.9rem;">
                No question papers have been published yet by administrators.
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
                <?php foreach ($questionPapers as $paper): ?>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-weight: 700; color: #1e293b; font-size: 0.95rem;">
                                <?php echo htmlspecialchars($paper['title']); ?>
                            </div>
                            <div style="font-size: 0.78rem; color: #64748b; margin-top: 2px;">
                                Class: <strong><?php echo htmlspecialchars($paper['class']); ?></strong> &bull; Subject: <?php echo htmlspecialchars($paper['subject'] ?? 'General'); ?>
                            </div>
                        </div>
                        <a href="../school/download_paper.php?id=<?php echo $paper['id']; ?>" class="btn" style="background: #8b5cf6; color: white; padding: 6px 12px; font-size: 0.8rem; border-radius: 6px; text-decoration: none; font-weight: 700;">
                            ⬇️ Download
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

