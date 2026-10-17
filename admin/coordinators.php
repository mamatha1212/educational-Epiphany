<?php
/**
 * EEMT 2027 - Coordinator & Staff Management (Admin Only)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$pdo = getDB();
$message = '';
$messageType = '';

// Handle Account Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_user') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $role = trim($_POST['role'] ?? 'coordinator');
        $canManageSchools = isset($_POST['can_manage_schools']) ? 1 : 0;

        if (empty($username) || empty($password) || empty($name)) {
            $message = "Username, password, and full name are required.";
            $messageType = 'error';
        } elseif (strlen($password) < 6) {
            $message = "Password must be at least 6 characters long.";
            $messageType = 'error';
        } else {
            // Check if username already exists
            $chkStmt = $pdo->prepare("SELECT id FROM users WHERE username = :u");
            $chkStmt->execute([':u' => $username]);
            if ($chkStmt->fetch()) {
                $message = "Username '$username' is already taken. Please choose another.";
                $messageType = 'error';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $insStmt = $pdo->prepare("
                    INSERT INTO users (username, password_hash, role, name, email, mobile, is_active, can_manage_schools)
                    VALUES (:u, :p, :r, :n, :em, :mob, 1, :cms)
                ");
                $insStmt->execute([
                    ':u' => $username,
                    ':p' => $hash,
                    ':r' => $role,
                    ':n' => $name,
                    ':em' => $email,
                    ':mob' => $mobile,
                    ':cms' => $canManageSchools
                ]);
                $message = "Staff account created successfully for '$username' ($role).";
                $messageType = 'success';
            }
        }
    } elseif ($action === 'edit_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $role = trim($_POST['role'] ?? 'coordinator');
        $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
        $canManageSchools = isset($_POST['can_manage_schools']) ? 1 : 0;

        if (empty($name) || empty($username) || $userId <= 0) {
            $message = "Full name and username are required.";
            $messageType = 'error';
        } else {
            // Check if username is already taken by another user
            $chkStmt = $pdo->prepare("SELECT id FROM users WHERE username = :u AND id != :id");
            $chkStmt->execute([':u' => $username, ':id' => $userId]);
            if ($chkStmt->fetch()) {
                $message = "Username '$username' is already in use by another account.";
                $messageType = 'error';
            } else {
                if (!empty($password)) {
                    if (strlen($password) < 6) {
                        $message = "New password must be at least 6 characters long.";
                        $messageType = 'error';
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $updStmt = $pdo->prepare("
                            UPDATE users 
                            SET name = :n, username = :u, password_hash = :p, role = :r, email = :em, mobile = :mob, is_active = :act, can_manage_schools = :cms
                            WHERE id = :id
                        ");
                        $updStmt->execute([
                            ':n' => $name,
                            ':u' => $username,
                            ':p' => $hash,
                            ':r' => $role,
                            ':em' => $email,
                            ':mob' => $mobile,
                            ':act' => $isActive,
                            ':cms' => $canManageSchools,
                            ':id' => $userId
                        ]);
                        $message = "Account for '$name' (@$username) updated successfully.";
                        $messageType = 'success';
                    }
                } else {
                    $updStmt = $pdo->prepare("
                        UPDATE users 
                        SET name = :n, username = :u, role = :r, email = :em, mobile = :mob, is_active = :act, can_manage_schools = :cms
                        WHERE id = :id
                    ");
                    $updStmt->execute([
                        ':n' => $name,
                        ':u' => $username,
                        ':r' => $role,
                        ':em' => $email,
                        ':mob' => $mobile,
                        ':act' => $isActive,
                        ':cms' => $canManageSchools,
                        ':id' => $userId
                    ]);
                    $message = "Account for '$name' (@$username) updated successfully.";
                    $messageType = 'success';
                }
            }
        }
    } elseif ($action === 'toggle_permission') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId > 0) {
            $tglStmt = $pdo->prepare("UPDATE users SET can_manage_schools = CASE WHEN can_manage_schools = 1 THEN 0 ELSE 1 END WHERE id = ?");
            $tglStmt->execute([$userId]);
            $message = "Directory management permission toggled successfully.";
            $messageType = 'success';
        }
    } elseif ($action === 'delete_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $currUser = currentUser();

        if ($userId === (int)($currUser['id'] ?? 0)) {
            $message = "You cannot delete your own account.";
            $messageType = 'error';
        } else {
            $delStmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
            $delStmt->execute([':id' => $userId]);
            $message = "User account removed.";
            $messageType = 'success';
        }
    }
}

// Fetch all staff users
$stmt = $pdo->query("SELECT id, username, role, name, email, mobile, assigned_district, is_active, can_manage_schools, created_at FROM users ORDER BY id ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Metrics calculation
$totalStaff = count($users);
$totalAdmins = 0;
$totalCoords = 0;
$totalActive = 0;
foreach ($users as $u) {
    if ($u['role'] === 'admin') $totalAdmins++;
    if ($u['role'] === 'coordinator') $totalCoords++;
    if (!empty($u['is_active'])) $totalActive++;
}

$pageTitle = "Staff & Coordinators - EEMT 2027 Admin";
require_once __DIR__ . '/includes/admin_header.php';
?>

<div style="max-width: 1250px; margin: 0 auto; padding-bottom: 2rem;">
    <!-- Page Header -->
    <div style="margin-bottom: 1.75rem; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 1.25rem;">
        <div>
            <div style="display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.04em;">
                🛡️ System Access Management
            </div>
            <h1 style="font-size: 1.85rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.03em; line-height: 1.2;">
                Staff & Coordinator Accounts
            </h1>
            <p style="font-size: 0.9rem; color: #64748b; margin-top: 4px; margin-bottom: 0;">
                Manage administrative access, assign district coordinators, and configure credentials.
            </p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="dashboard.php" class="btn" style="height: 42px; padding: 0 1.25rem; font-weight: 600; font-size: 0.88rem; background: #ffffff; border: 1.5px solid #cbd5e1; color: #475569; border-radius: 10px; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; transition: all 0.2s ease;">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Admin Dashboard
            </a>
            <a href="#add-user-card" class="btn btn-primary" style="height: 42px; padding: 0 1.35rem; font-weight: 700; font-size: 0.88rem; border-radius: 10px; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 14px rgba(37,99,235,0.25); text-decoration: none;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Add Staff Member
            </a>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?>" style="margin-bottom: 1.5rem; border-radius: 12px; display: flex; align-items: center; gap: 12px; padding: 1rem 1.35rem; font-size: 0.92rem; font-weight: 600; box-shadow: 0 2px 10px rgba(0,0,0,0.03);">
            <span style="font-size: 1.25rem;"><?php echo $messageType === 'success' ? '✅' : '⚠️'; ?></span>
            <span><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>

    <!-- KPI Metric Cards Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
        <!-- Card 1: Total Accounts -->
        <div style="background: #ffffff; border-radius: 16px; padding: 1.35rem 1.5rem; border: 1px solid #e2e8f0; box-shadow: 0 2px 12px rgba(0,0,0,0.03); display: flex; align-items: center; justify-content: space-between; transition: transform 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
            <div>
                <span style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; display: block;">Total Staff</span>
                <div style="font-size: 2rem; font-weight: 800; color: #0f172a; line-height: 1.1; margin-top: 4px;">
                    <?php echo $totalStaff; ?>
                </div>
                <span style="font-size: 0.78rem; color: #2563eb; font-weight: 600; margin-top: 4px; display: block;">Dashboard Logins</span>
            </div>
            <div style="width: 48px; height: 48px; border-radius: 14px; background: #eff6ff; border: 1px solid #dbeafe; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; color: #2563eb;">
                👥
            </div>
        </div>

        <!-- Card 2: Administrators -->
        <div style="background: #ffffff; border-radius: 16px; padding: 1.35rem 1.5rem; border: 1px solid #e2e8f0; box-shadow: 0 2px 12px rgba(0,0,0,0.03); display: flex; align-items: center; justify-content: space-between; transition: transform 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
            <div>
                <span style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; display: block;">Administrators</span>
                <div style="font-size: 2rem; font-weight: 800; color: #1e3a8a; line-height: 1.1; margin-top: 4px;">
                    <?php echo $totalAdmins; ?>
                </div>
                <span style="font-size: 0.78rem; color: #4338ca; font-weight: 600; margin-top: 4px; display: block;">Full Root Control</span>
            </div>
            <div style="width: 48px; height: 48px; border-radius: 14px; background: #e0e7ff; border: 1px solid #c7d2fe; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; color: #4338ca;">
                🛡️
            </div>
        </div>

        <!-- Card 3: District Coordinators -->
        <div style="background: #ffffff; border-radius: 16px; padding: 1.35rem 1.5rem; border: 1px solid #e2e8f0; box-shadow: 0 2px 12px rgba(0,0,0,0.03); display: flex; align-items: center; justify-content: space-between; transition: transform 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
            <div>
                <span style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; display: block;">Coordinators</span>
                <div style="font-size: 2rem; font-weight: 800; color: #065f46; line-height: 1.1; margin-top: 4px;">
                    <?php echo $totalCoords; ?>
                </div>
                <span style="font-size: 0.78rem; color: #059669; font-weight: 600; margin-top: 4px; display: block;">Regional Access</span>
            </div>
            <div style="width: 48px; height: 48px; border-radius: 14px; background: #ecfdf5; border: 1px solid #a7f3d0; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; color: #059669;">
                👔
            </div>
        </div>

        <!-- Card 4: Active Status -->
        <div style="background: #ffffff; border-radius: 16px; padding: 1.35rem 1.5rem; border: 1px solid #e2e8f0; box-shadow: 0 2px 12px rgba(0,0,0,0.03); display: flex; align-items: center; justify-content: space-between; transition: transform 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
            <div>
                <span style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; display: block;">Active Logins</span>
                <div style="font-size: 2rem; font-weight: 800; color: #0f172a; line-height: 1.1; margin-top: 4px;">
                    <?php echo $totalActive; ?> <span style="font-size: 1.1rem; color: #64748b; font-weight: 500;">/ <?php echo $totalStaff; ?></span>
                </div>
                <span style="font-size: 0.78rem; color: #10b981; font-weight: 700; margin-top: 4px; display: flex; align-items: center; gap: 4px;">
                    <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981;"></span> Enabled
                </span>
            </div>
            <div style="width: 48px; height: 48px; border-radius: 14px; background: #f0fdf4; border: 1px solid #bbf7d0; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; color: #16a34a;">
                🟢
            </div>
        </div>
    </div>

    <!-- User Accounts Table Card -->
    <div class="card" style="padding: 0; overflow: hidden; margin-bottom: 2.25rem; border: 1px solid #e2e8f0; border-radius: 18px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); background: #ffffff;">
        <!-- Card Top Bar with Search -->
        <div style="padding: 1.25rem 1.75rem; border-bottom: 1px solid #edf2f7; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; background: linear-gradient(to right, #f8fafc, #ffffff);">
            <div>
                <h3 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <span>👥 Authorized Staff & Coordinators</span>
                    <span style="font-size: 0.75rem; font-weight: 700; background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; padding: 2px 8px; border-radius: 20px;">
                        <?php echo count($users); ?> Total
                    </span>
                </h3>
                <span style="font-size: 0.82rem; color: #64748b; margin-top: 2px; display: block;">Authorized personnel with active dashboard credentials</span>
            </div>

            <!-- Instant Search Bar -->
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="position: relative; width: 280px;">
                    <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.9rem; pointer-events: none;">🔍</span>
                    <input type="text" id="staffSearchInput" onkeyup="filterStaffTable()" placeholder="Filter by name, username..." style="width: 100%; height: 38px; padding: 0 12px 0 34px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.85rem; color: #1e293b; background: #ffffff; outline: none; transition: all 0.2s ease;" onfocus="this.style.borderColor='#2563eb'; this.style.boxShadow='0 0 0 3px rgba(37,99,235,0.1)';" onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                </div>
            </div>
        </div>

        <div class="table-responsive" style="overflow-x: auto; width: 100%;">
            <table class="table" id="staffTable" style="width: 100%; min-width: 980px; border-collapse: collapse; margin-bottom: 0;">
                <thead style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                    <tr>
                        <th style="font-weight: 700; text-transform: uppercase; font-size: 0.74rem; letter-spacing: 0.5px; color: #475569; padding: 14px 18px; width: 70px;">ID</th>
                        <th style="font-weight: 700; text-transform: uppercase; font-size: 0.74rem; letter-spacing: 0.5px; color: #475569; padding: 14px 18px; min-width: 250px;">Staff Member</th>
                        <th style="font-weight: 700; text-transform: uppercase; font-size: 0.74rem; letter-spacing: 0.5px; color: #475569; padding: 14px 18px; width: 150px;">Role</th>
                        <th style="font-weight: 700; text-transform: uppercase; font-size: 0.74rem; letter-spacing: 0.5px; color: #475569; padding: 14px 18px; width: 220px;">Directory Permission</th>
                        <th style="font-weight: 700; text-transform: uppercase; font-size: 0.74rem; letter-spacing: 0.5px; color: #475569; padding: 14px 18px; min-width: 210px;">Contact Details</th>
                        <th style="font-weight: 700; text-transform: uppercase; font-size: 0.74rem; letter-spacing: 0.5px; color: #475569; padding: 14px 18px; width: 110px;">Status</th>
                        <th style="font-weight: 700; text-transform: uppercase; font-size: 0.74rem; letter-spacing: 0.5px; color: #475569; padding: 14px 18px; width: 130px;">Created On</th>
                        <th style="font-weight: 700; text-transform: uppercase; font-size: 0.74rem; letter-spacing: 0.5px; color: #475569; padding: 14px 18px; width: 150px; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody id="staffTableBody">
                    <?php foreach ($users as $u): 
                        $words = explode(' ', trim($u['name']));
                        $initials = '';
                        foreach ($words as $w) {
                            if (!empty($w)) $initials .= strtoupper($w[0]);
                            if (strlen($initials) >= 2) break;
                        }
                        if ($initials === '') $initials = 'ST';
                        $isCurrent = ($u['id'] == ($_SESSION['user']['id'] ?? 0));
                        $canManage = !empty($u['can_manage_schools']);
                    ?>
                        <tr class="staff-row" style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                            <td style="padding: 14px 18px; vertical-align: middle; font-weight: 700; color: #94a3b8; font-size: 0.85rem;">
                                #<?php echo $u['id']; ?>
                            </td>
                            <td style="padding: 14px 18px; vertical-align: middle;">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="width: 40px; height: 40px; border-radius: 12px; background: <?php echo $u['role'] === 'admin' ? 'linear-gradient(135deg, #1e3a8a, #2563eb)' : 'linear-gradient(135deg, #065f46, #059669)'; ?>; color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 0.88rem; font-weight: 800; flex-shrink: 0; box-shadow: 0 2px 8px rgba(0,0,0,0.12); border: 2px solid #ffffff;">
                                        <?php echo htmlspecialchars($initials); ?>
                                    </div>
                                    <div>
                                        <strong style="color: #0f172a; font-size: 0.94rem; display: block; line-height: 1.25; font-weight: 700;">
                                            <?php echo htmlspecialchars($u['name']); ?>
                                        </strong>
                                        <span style="font-size: 0.78rem; color: #334155; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: 600; background: #e2e8f0; padding: 2px 7px; border-radius: 5px; display: inline-block; margin-top: 3px;">
                                            @<?php echo htmlspecialchars($u['username']); ?>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 14px 18px; vertical-align: middle;">
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; font-size: 0.78rem; font-weight: 700; padding: 4px 10px; border-radius: 20px; display: inline-flex; align-items: center; gap: 5px;">
                                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                        Administrator
                                    </span>
                                <?php else: ?>
                                    <span style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; font-size: 0.78rem; font-weight: 700; padding: 4px 10px; border-radius: 20px; display: inline-flex; align-items: center; gap: 5px;">
                                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                        Coordinator
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 14px 18px; vertical-align: middle;">
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span style="font-size: 0.78rem; color: #1e40af; font-weight: 700; background: #dbeafe; padding: 3px 9px; border-radius: 6px; border: 1px solid #bfdbfe;">
                                        👑 Root Master (Full Access)
                                    </span>
                                <?php else: ?>
                                    <?php if ($canManage): ?>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; font-size: 0.76rem; font-weight: 700; padding: 3px 8px; border-radius: 20px; display: inline-flex; align-items: center; gap: 5px;">
                                                <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981;"></span>
                                                Permitted
                                            </span>
                                            <form method="POST" style="display:inline; margin:0;">
                                                <input type="hidden" name="action" value="toggle_permission">
                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                <button type="submit" style="padding: 3px 8px; font-size: 0.72rem; font-weight: 700; color: #b91c1c; background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; cursor: pointer; transition: all 0.15s ease;" title="Revoke Directory Access">Revoke</button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span style="background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; font-size: 0.76rem; font-weight: 600; padding: 3px 8px; border-radius: 20px; display: inline-flex; align-items: center; gap: 5px;">
                                                🔒 No Access
                                            </span>
                                            <form method="POST" style="display:inline; margin:0;">
                                                <input type="hidden" name="action" value="toggle_permission">
                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                <button type="submit" style="padding: 3px 8px; font-size: 0.72rem; font-weight: 700; color: #047857; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 6px; cursor: pointer; transition: all 0.15s ease;" title="Grant access to manage Schools, Mandals & Districts">Permit</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 14px 18px; vertical-align: middle;">
                                <?php if (!empty($u['mobile']) && $u['mobile'] !== 'N/A'): ?>
                                    <div style="font-weight: 600; color: #1e293b; font-size: 0.88rem;">
                                        <a href="tel:<?php echo htmlspecialchars($u['mobile']); ?>" style="color: #2563eb; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
                                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                            <?php echo htmlspecialchars($u['mobile']); ?>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div style="color: #94a3b8; font-size: 0.82rem;">No Phone</div>
                                <?php endif; ?>

                                <?php if (!empty($u['email']) && $u['email'] !== 'N/A'): ?>
                                    <div style="color: #475569; font-size: 0.82rem; margin-top: 3px;">
                                        <a href="mailto:<?php echo htmlspecialchars($u['email']); ?>" style="color: #475569; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
                                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                            <?php echo htmlspecialchars($u['email']); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 14px 18px; vertical-align: middle;">
                                <?php if ($u['is_active'] == 1): ?>
                                    <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 20px; font-size: 0.76rem; font-weight: 700; background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0;">
                                        <span style="width: 7px; height: 7px; border-radius: 50%; background: #10b981; box-shadow: 0 0 6px #10b981;"></span>
                                        Active
                                    </span>
                                <?php else: ?>
                                    <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 20px; font-size: 0.76rem; font-weight: 700; background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca;">
                                        <span style="width: 7px; height: 7px; border-radius: 50%; background: #ef4444;"></span>
                                        Disabled
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 14px 18px; vertical-align: middle; font-size: 0.85rem; color: #64748b; white-space: nowrap;">
                                <?php echo date('d M Y', strtotime($u['created_at'])); ?>
                            </td>
                            <td style="padding: 14px 18px; vertical-align: middle; text-align: right; white-space: nowrap;">
                                <div style="display: flex; gap: 8px; justify-content: flex-end; align-items: center;">
                                    <button type="button" class="btn" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8'); ?>)" style="padding: 6px 14px; font-size: 0.8rem; font-weight: 700; background: #ffffff; color: #2563eb; border: 1.5px solid #bfdbfe; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 1px 3px rgba(37,99,235,0.06); transition: all 0.15s ease;" onmouseover="this.style.background='#eff6ff'; this.style.borderColor='#2563eb';" onmouseout="this.style.background='#ffffff'; this.style.borderColor='#bfdbfe';" title="Edit Staff Details">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        Edit
                                    </button>
                                    <?php if (!$isCurrent): ?>
                                        <form method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('Permanently delete staff account @<?php echo htmlspecialchars(addslashes($u['username'])); ?>?');">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <button type="submit" class="btn" style="padding: 6px 14px; font-size: 0.8rem; font-weight: 700; background: #ffffff; color: #dc2626; border: 1.5px solid #fecaca; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 1px 3px rgba(220,38,38,0.06); transition: all 0.15s ease;" onmouseover="this.style.background='#fef2f2'; this.style.borderColor='#dc2626';" onmouseout="this.style.background='#ffffff'; this.style.borderColor='#fecaca';" title="Delete Staff Account">
                                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                Delete
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="font-size: 0.78rem; color: #64748b; font-weight: 700; background: #f1f5f9; padding: 5px 12px; border-radius: 8px; border: 1px solid #e2e8f0;">
                                            (You)
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Staff Card -->
    <div class="card" id="add-user-card" style="padding: 0; border-radius: 18px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); background: #ffffff; border: 1px solid #e2e8f0; overflow: hidden;">
        <div style="height: 4px; background: linear-gradient(90deg, #2563eb, #6366f1, #8b5cf6);"></div>
        
        <div style="padding: 1.5rem 2rem 1.25rem 2rem; border-bottom: 1px solid #edf2f7; display: flex; align-items: center; gap: 14px; background: linear-gradient(to right, #f8fafc, #ffffff);">
            <div style="width: 44px; height: 44px; border-radius: 12px; background: #eff6ff; border: 1px solid #bfdbfe; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; color: #2563eb; flex-shrink: 0; box-shadow: 0 2px 8px rgba(37,99,235,0.12);">
                <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
            </div>
            <div>
                <h3 style="font-size: 1.22rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">Create Staff / Coordinator Account</h3>
                <p style="font-size: 0.85rem; color: #64748b; margin-top: 2px; margin-bottom: 0;">
                    Add system administrators or regional district coordinators who can view school registrations and manage papers.
                </p>
            </div>
        </div>

        <form method="POST" action="coordinators.php" style="padding: 1.75rem 2rem;">
            <input type="hidden" name="action" value="create_user">

            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; margin-bottom: 1.5rem;">
                <!-- Full Name -->
                <div>
                    <label class="modal-field-label" for="name">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Full Name <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" id="name" name="name" class="modal-field-input" placeholder="e.g. R. Suresh Kumar" required>
                </div>

                <!-- Username -->
                <div>
                    <label class="modal-field-label" for="username">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/></svg>
                        Username <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" id="username" name="username" class="modal-field-input" placeholder="e.g. suresh_coord" required>
                </div>

                <!-- Password -->
                <div>
                    <label class="modal-field-label" for="password">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Password <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="password" id="password" name="password" class="modal-field-input" placeholder="Minimum 6 characters" minlength="6" required>
                </div>

                <!-- System Role -->
                <div>
                    <label class="modal-field-label" for="role">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        System Role <span style="color: #ef4444;">*</span>
                    </label>
                    <select id="role" name="role" class="modal-field-select" required>
                        <option value="coordinator">Coordinator (Registrations & Papers)</option>
                        <option value="admin">Administrator (Full System Control)</option>
                    </select>
                </div>

                <!-- Phone Number -->
                <div>
                    <label class="modal-field-label" for="mobile">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        Phone Number
                    </label>
                    <input type="tel" id="mobile" name="mobile" class="modal-field-input" placeholder="10-digit mobile" pattern="[0-9]{10}">
                </div>

                <!-- Email Address -->
                <div>
                    <label class="modal-field-label" for="email">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        Email Address
                    </label>
                    <input type="email" id="email" name="email" class="modal-field-input" placeholder="e.g. suresh@example.com">
                </div>

                <!-- Directory Management Permission Checkbox -->
                <div style="grid-column: span 3; background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: 12px; padding: 14px 18px; margin-top: 4px;">
                    <label style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer; margin: 0;">
                        <input type="checkbox" name="can_manage_schools" value="1" style="width: 20px; height: 20px; accent-color: #2563eb; cursor: pointer; margin-top: 2px;">
                        <div>
                            <strong style="color: #0f172a; font-size: 0.94rem; display: block;">🏛️ Permit Directory Management (Schools, Mandals & Districts)</strong>
                            <span style="color: #64748b; font-size: 0.82rem; line-height: 1.4; display: block; margin-top: 2px;">
                                If checked, this coordinator will be granted access to the master directory dashboard to add, edit, or manage schools, mandals and districts. (Leave unchecked for standard coordinators).
                            </span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; justify-content: flex-end; align-items: center; gap: 12px; padding-top: 1.25rem; border-top: 1px solid #edf2f7;">
                <button type="reset" class="btn" style="height: 44px; padding: 0 1.5rem; background: #ffffff; color: #475569; border: 1.5px solid #cbd5e1; font-weight: 600; font-size: 0.92rem; border-radius: 10px; cursor: pointer; transition: all 0.15s ease;" onmouseover="this.style.background='#f1f5f9';" onmouseout="this.style.background='#ffffff';">
                    Clear Form
                </button>
                <button type="submit" class="btn btn-primary" style="height: 44px; padding: 0 1.85rem; font-weight: 700; font-size: 0.92rem; border-radius: 10px; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 14px rgba(37,99,235,0.3); background: linear-gradient(135deg, #2563eb, #1d4ed8); border: none; cursor: pointer; transition: all 0.2s ease;" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 6px 18px rgba(37,99,235,0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 14px rgba(37,99,235,0.3)';">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Create Staff Account
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function filterStaffTable() {
    const input = document.getElementById('staffSearchInput');
    const filter = input.value.toLowerCase().trim();
    const rows = document.querySelectorAll('#staffTableBody .staff-row');

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(filter)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>

<!-- Edit User Modal -->
<style>
@keyframes modalPop {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(12px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}
.modal-field-input {
    width: 100%;
    height: 44px;
    padding: 0 14px;
    border: 1.5px solid #cbd5e1;
    border-radius: 10px;
    background: #f8fafc;
    font-size: 0.92rem;
    color: #1e293b;
    outline: none;
    transition: all 0.2s ease;
    box-sizing: border-box;
}
.modal-field-input:focus {
    background: #ffffff;
    border-color: #2563eb;
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
}
.modal-field-select {
    width: 100%;
    height: 44px;
    padding: 0 40px 0 14px;
    border: 1.5px solid #cbd5e1;
    border-radius: 10px;
    background-color: #f8fafc;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
    background-size: 16px;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    font-size: 0.92rem;
    color: #1e293b;
    outline: none;
    transition: all 0.2s ease;
    box-sizing: border-box;
    cursor: pointer;
}
.modal-field-select:focus {
    background-color: #ffffff;
    border-color: #2563eb;
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
}
.modal-field-label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
    font-weight: 700;
    color: #334155;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin-bottom: 0.4rem;
}
</style>

<div id="editUserModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.72); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 99999; align-items: center; justify-content: center; padding: 1.25rem;">
    <div style="background: #ffffff; border-radius: 20px; max-width: 680px; width: 100%; box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.35), 0 0 0 1px rgba(226, 232, 240, 0.8); overflow: hidden; animation: modalPop 0.22s cubic-bezier(0.16, 1, 0.3, 1); position: relative;">
        <!-- Top Accent Gradient Line -->
        <div style="height: 4px; background: linear-gradient(90deg, #2563eb, #6366f1, #8b5cf6);"></div>

        <!-- Modal Header -->
        <div style="padding: 1.35rem 2rem; background: linear-gradient(to right, #f8fafc, #ffffff); border-bottom: 1px solid #edf2f7; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 14px;">
                <div style="width: 46px; height: 46px; border-radius: 12px; background: linear-gradient(135deg, #eff6ff, #dbeafe); border: 1px solid #bfdbfe; display: flex; align-items: center; justify-content: center; color: #2563eb; flex-shrink: 0; box-shadow: 0 2px 8px rgba(37,99,235,0.12);">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                </div>
                <div>
                    <h3 style="font-size: 1.22rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">Edit Staff Account</h3>
                    <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                        <span id="modalUsernameBadge" style="display: inline-flex; align-items: center; gap: 4px; background: #e0e7ff; color: #3730a3; font-weight: 700; font-size: 0.76rem; padding: 2px 8px; border-radius: 6px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace;">@username</span>
                        <span id="modalRoleBadge" style="font-size: 0.8rem; color: #64748b; font-weight: 600;">Coordinator</span>
                    </div>
                </div>
            </div>
            <button type="button" onclick="closeEditModal()" style="width: 36px; height: 36px; border-radius: 10px; background: #f1f5f9; border: 1px solid #e2e8f0; color: #64748b; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.15s ease;" onmouseover="this.style.background='#e2e8f0'; this.style.color='#0f172a'" onmouseout="this.style.background='#f1f5f9'; this.style.color='#64748b'" title="Close">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Modal Form -->
        <form method="POST" action="coordinators.php" style="padding: 1.75rem 2rem 1.5rem 2rem;">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="user_id" id="edit_user_id" value="">

            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.25rem; margin-bottom: 1.25rem;">
                <!-- Full Name -->
                <div>
                    <label class="modal-field-label" for="edit_name">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Full Name <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" id="edit_name" name="name" class="modal-field-input" placeholder="e.g. Hem Mohan" required>
                </div>

                <!-- Username -->
                <div>
                    <label class="modal-field-label" for="edit_username">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/></svg>
                        Username <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" id="edit_username" name="username" class="modal-field-input" placeholder="e.g. hema" required>
                </div>

                <!-- New Password -->
                <div>
                    <label class="modal-field-label" for="edit_password">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        New Password <span style="font-size: 0.72rem; color: #64748b; font-weight: normal; text-transform: none;">(Optional)</span>
                    </label>
                    <input type="password" id="edit_password" name="password" class="modal-field-input" placeholder="Leave empty to keep current">
                </div>

                <!-- System Role -->
                <div>
                    <label class="modal-field-label" for="edit_role">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        System Role <span style="color: #ef4444;">*</span>
                    </label>
                    <select id="edit_role" name="role" class="modal-field-select" required>
                        <option value="coordinator">Coordinator (Registrations & Papers)</option>
                        <option value="admin">Administrator (Full System Control)</option>
                    </select>
                </div>

                <!-- Phone Number -->
                <div>
                    <label class="modal-field-label" for="edit_mobile">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        Phone Number
                    </label>
                    <input type="tel" id="edit_mobile" name="mobile" class="modal-field-input" placeholder="10-digit mobile" pattern="[0-9]{10}">
                </div>

                <!-- Account Status -->
                <div>
                    <label class="modal-field-label" for="edit_is_active">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        Account Status <span style="color: #ef4444;">*</span>
                    </label>
                    <select id="edit_is_active" name="is_active" class="modal-field-select" required>
                        <option value="1">🟢 Active (Access Enabled)</option>
                        <option value="0">🔴 Suspended (Access Blocked)</option>
                    </select>
                </div>

                <!-- Email Address (Full Width) -->
                <div style="grid-column: span 2;">
                    <label class="modal-field-label" for="edit_email">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        Email Address
                    </label>
                    <input type="email" id="edit_email" name="email" class="modal-field-input" placeholder="user@example.com">
                </div>

                <!-- Directory Management Permission Checkbox -->
                <div style="grid-column: span 2; background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: 12px; padding: 14px 18px; margin-top: 4px;">
                    <label style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer; margin: 0;">
                        <input type="checkbox" id="edit_can_manage_schools" name="can_manage_schools" value="1" style="width: 20px; height: 20px; accent-color: #2563eb; cursor: pointer; margin-top: 2px;">
                        <div>
                            <strong style="color: #0f172a; font-size: 0.94rem; display: block;">🏛️ Permit Directory Management (Schools, Mandals & Districts)</strong>
                            <span style="color: #64748b; font-size: 0.82rem; line-height: 1.4; display: block; margin-top: 2px;">
                                Allow this coordinator to access the Directory Manager to view, add, edit, or delete schools, mandals and districts across Andhra Pradesh.
                            </span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Modal Action Buttons Footer -->
            <div style="display: flex; justify-content: flex-end; align-items: center; gap: 12px; padding-top: 1.25rem; border-top: 1px solid #edf2f7; margin-top: 0.5rem;">
                <button type="button" onclick="closeEditModal()" style="height: 44px; padding: 0 1.4rem; background: #ffffff; color: #475569; border: 1.5px solid #cbd5e1; font-weight: 600; font-size: 0.92rem; border-radius: 10px; cursor: pointer; transition: all 0.15s ease;" onmouseover="this.style.background='#f1f5f9'; this.style.borderColor='#94a3b8'" onmouseout="this.style.background='#ffffff'; this.style.borderColor='#cbd5e1'">
                    Cancel
                </button>
                <button type="submit" style="height: 44px; padding: 0 1.75rem; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #ffffff; border: none; font-weight: 700; font-size: 0.92rem; border-radius: 10px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35); transition: all 0.2s ease;" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 6px 18px rgba(37,99,235,0.45)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 14px rgba(37,99,235,0.35)'">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_name').value = user.name || '';
    document.getElementById('edit_username').value = user.username || '';
    document.getElementById('edit_password').value = '';
    document.getElementById('edit_role').value = user.role || 'coordinator';
    document.getElementById('edit_email').value = (user.email && user.email !== 'N/A') ? user.email : '';
    document.getElementById('edit_mobile').value = (user.mobile && user.mobile !== 'N/A') ? user.mobile : '';
    document.getElementById('edit_is_active').value = (user.is_active !== undefined) ? user.is_active : 1;
    document.getElementById('edit_can_manage_schools').checked = (parseInt(user.can_manage_schools) === 1);
    
    document.getElementById('modalUsernameBadge').textContent = '@' + (user.username || '');
    document.getElementById('modalRoleBadge').textContent = (user.role === 'admin' ? 'State Administrator' : 'Staff Coordinator');
    
    const modal = document.getElementById('editUserModal');
    modal.style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editUserModal').style.display = 'none';
}

// Close modal when clicking outside of modal box
window.addEventListener('click', function(e) {
    const modal = document.getElementById('editUserModal');
    if (e.target === modal) {
        closeEditModal();
    }
});
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

