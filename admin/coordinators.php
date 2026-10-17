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

// Handle Account Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_user') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $role = trim($_POST['role'] ?? 'coordinator');

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
                    INSERT INTO users (username, password_hash, role, name, email, mobile, is_active)
                    VALUES (:u, :p, :r, :n, :em, :mob, 1)
                ");
                $insStmt->execute([
                    ':u' => $username,
                    ':p' => $hash,
                    ':r' => $role,
                    ':n' => $name,
                    ':em' => $email,
                    ':mob' => $mobile
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
                            SET name = :n, username = :u, password_hash = :p, role = :r, email = :em, mobile = :mob, is_active = :act
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
                            ':id' => $userId
                        ]);
                        $message = "Account for '$name' (@$username) updated successfully.";
                        $messageType = 'success';
                    }
                } else {
                    $updStmt = $pdo->prepare("
                        UPDATE users 
                        SET name = :n, username = :u, role = :r, email = :em, mobile = :mob, is_active = :act
                        WHERE id = :id
                    ");
                    $updStmt->execute([
                        ':n' => $name,
                        ':u' => $username,
                        ':r' => $role,
                        ':em' => $email,
                        ':mob' => $mobile,
                        ':act' => $isActive,
                        ':id' => $userId
                    ]);
                    $message = "Account for '$name' (@$username) updated successfully.";
                    $messageType = 'success';
                }
            }
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
$stmt = $pdo->query("SELECT id, username, role, name, email, mobile, is_active, created_at FROM users ORDER BY id ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Staff & Coordinators - EEMT 2027 Admin";
require_once __DIR__ . '/includes/admin_header.php';
?>

<div style="max-width: 1200px; margin: 0 auto;">
    <!-- Breadcrumb & Top Bar -->
    <div style="margin-bottom: 1.75rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <a href="dashboard.php" style="color: #2563eb; font-weight: 600; text-decoration: none; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 4px;">
                &larr; Back to Admin Dashboard
            </a>
            <h2 style="font-size: 1.85rem; font-weight: 800; color: #0f172a; margin-top: 0.35rem; letter-spacing: -0.5px;">
                Staff & Coordinator Accounts
            </h2>
            <p style="font-size: 0.88rem; color: #64748b; margin-top: 2px;">
                Manage administrative access and regional district coordinator credentials.
            </p>
        </div>
        <a href="#add-user-card" class="btn btn-primary" style="height: 42px; padding: 0 1.25rem; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(37,99,235,0.25);">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Add New Staff Member
        </a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?>" style="margin-bottom: 1.5rem; border-radius: 10px; display: flex; align-items: center; gap: 10px; padding: 1rem 1.25rem;">
            <span><?php echo $messageType === 'success' ? '✅' : '⚠️'; ?></span>
            <span><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>

    <!-- User Accounts Table Card -->
    <div class="card" style="padding: 0; overflow: hidden; margin-bottom: 2.25rem; border: 1px solid #e2e8f0; border-radius: 14px; box-shadow: 0 2px 10px rgba(0,0,0,0.03);">
        <div style="padding: 1.25rem 1.75rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
            <div>
                <h3 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <span>👥 System Access Accounts</span>
                </h3>
                <span style="font-size: 0.8rem; color: #64748b; margin-top: 2px; display: block;">Authorized personnel with dashboard credentials</span>
            </div>
            <span class="badge" style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; font-size: 0.78rem; font-weight: 700; padding: 4px 10px;">
                <?php echo count($users); ?> Total Accounts
            </span>
        </div>

        <div class="table-responsive" style="overflow-x: auto; width: 100%;">
            <table class="table" style="width: 100%; min-width: 950px; border-collapse: collapse; margin-bottom: 0;">
                <thead style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                    <tr>
                        <th style="font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; color: #475569; padding: 14px 18px; width: 70px;">ID</th>
                        <th style="font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; color: #475569; padding: 14px 18px; min-width: 240px;">Staff Member</th>
                        <th style="font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; color: #475569; padding: 14px 18px; width: 160px;">Role</th>
                        <th style="font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; color: #475569; padding: 14px 18px; min-width: 240px;">Contact Details</th>
                        <th style="font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; color: #475569; padding: 14px 18px; width: 120px;">Status</th>
                        <th style="font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; color: #475569; padding: 14px 18px; width: 140px;">Created On</th>
                        <th style="font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; color: #475569; padding: 14px 18px; width: 120px; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): 
                        // Generate initials for avatar
                        $words = explode(' ', trim($u['name']));
                        $initials = '';
                        foreach ($words as $w) {
                            if (!empty($w)) $initials .= strtoupper($w[0]);
                            if (strlen($initials) >= 2) break;
                        }
                        if ($initials === '') $initials = 'ST';
                        $isCurrent = ($u['id'] == ($_SESSION['user']['id'] ?? 0));
                    ?>
                        <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;">
                            <td style="padding: 14px 18px; vertical-align: middle; font-weight: 700; color: #94a3b8; font-size: 0.85rem;">
                                #<?php echo $u['id']; ?>
                            </td>
                            <td style="padding: 14px 18px; vertical-align: middle;">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="width: 38px; height: 38px; border-radius: 10px; background: <?php echo $u['role'] === 'admin' ? 'linear-gradient(135deg, #1e3a8a, #3b82f6)' : 'linear-gradient(135deg, #065f46, #10b981)'; ?>; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 800; flex-shrink: 0; box-shadow: 0 2px 6px rgba(0,0,0,0.1);">
                                        <?php echo htmlspecialchars($initials); ?>
                                    </div>
                                    <div>
                                        <strong style="color: #0f172a; font-size: 0.94rem; display: block; line-height: 1.2;">
                                            <?php echo htmlspecialchars($u['name']); ?>
                                        </strong>
                                        <span style="font-size: 0.78rem; color: #64748b; font-family: monospace; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top: 3px;">
                                            @<?php echo htmlspecialchars($u['username']); ?>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 14px 18px; vertical-align: middle;">
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span class="badge" style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; font-size: 0.78rem; font-weight: 700; padding: 4px 10px; display: inline-flex; align-items: center; gap: 4px;">
                                        🛡️ Administrator
                                    </span>
                                <?php else: ?>
                                    <span class="badge" style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; font-size: 0.78rem; font-weight: 700; padding: 4px 10px; display: inline-flex; align-items: center; gap: 4px;">
                                        👔 Coordinator
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 14px 18px; vertical-align: middle;">
                                <?php if (!empty($u['mobile']) && $u['mobile'] !== 'N/A'): ?>
                                    <div style="font-weight: 600; color: #1e293b; font-size: 0.88rem;">
                                        <a href="tel:<?php echo htmlspecialchars($u['mobile']); ?>" style="color: #2563eb; text-decoration: none;">
                                            📞 <?php echo htmlspecialchars($u['mobile']); ?>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div style="color: #94a3b8; font-size: 0.82rem;">No Phone</div>
                                <?php endif; ?>

                                <?php if (!empty($u['email']) && $u['email'] !== 'N/A'): ?>
                                    <div style="color: #64748b; font-size: 0.8rem; margin-top: 2px;">
                                        ✉️ <?php echo htmlspecialchars($u['email']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 14px 18px; vertical-align: middle;">
                                <?php if ($u['is_active'] == 1): ?>
                                    <span class="badge" style="background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; font-size: 0.75rem; font-weight: 700; padding: 3px 8px; display: inline-flex; align-items: center; gap: 5px;">
                                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981;"></span> Active
                                    </span>
                                <?php else: ?>
                                    <span class="badge" style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; font-size: 0.75rem; font-weight: 700; padding: 3px 8px; display: inline-flex; align-items: center; gap: 5px;">
                                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #ef4444;"></span> Disabled
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 14px 18px; vertical-align: middle; font-size: 0.82rem; color: #64748b; white-space: nowrap;">
                                <?php echo date('d M Y', strtotime($u['created_at'])); ?>
                            </td>
                            <td style="padding: 14px 18px; vertical-align: middle; text-align: right; white-space: nowrap;">
                                <div style="display: flex; gap: 6px; justify-content: flex-end; align-items: center;">
                                    <button type="button" class="btn" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8'); ?>)" style="padding: 6px 12px; font-size: 0.78rem; font-weight: 600; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;" title="Edit Staff Details">
                                        ✏️ Edit
                                    </button>
                                    <?php if (!$isCurrent): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Permanently delete staff account @<?php echo htmlspecialchars(addslashes($u['username'])); ?>?');">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <button type="submit" class="btn" style="padding: 6px 12px; font-size: 0.78rem; font-weight: 600; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;" title="Delete Staff Account">
                                                🗑️ Delete
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="font-size: 0.75rem; color: #64748b; font-weight: 600; background: #f1f5f9; padding: 4px 8px; border-radius: 6px; border: 1px solid #e2e8f0;">
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
    <div class="card" id="add-user-card" style="padding: 2rem; border-top: 4px solid #2563eb; border-radius: 14px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); background: #ffffff;">
        <div style="margin-bottom: 1.5rem; display: flex; align-items: flex-start; gap: 14px;">
            <div style="width: 44px; height: 44px; border-radius: 12px; background: #eff6ff; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; color: #2563eb; flex-shrink: 0;">
                ➕
            </div>
            <div>
                <h3 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0;">Create Staff / Coordinator Account</h3>
                <p style="font-size: 0.88rem; color: #64748b; margin-top: 3px; margin-bottom: 0;">
                    Add system administrators or regional district coordinators who can view school registrations and manage papers.
                </p>
            </div>
        </div>

        <form method="POST" action="coordinators.php">
            <input type="hidden" name="action" value="create_user">

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="name" style="font-weight: 700; font-size: 0.82rem; color: #334155; margin-bottom: 0.4rem; display: block;">
                        Full Name <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" id="name" name="name" class="form-input" placeholder="e.g. R. Suresh Kumar" style="height: 42px; border-radius: 8px; font-size: 0.9rem;" required>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="username" style="font-weight: 700; font-size: 0.82rem; color: #334155; margin-bottom: 0.4rem; display: block;">
                        Username <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" id="username" name="username" class="form-input" placeholder="e.g. suresh_coord" style="height: 42px; border-radius: 8px; font-size: 0.9rem;" required>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="password" style="font-weight: 700; font-size: 0.82rem; color: #334155; margin-bottom: 0.4rem; display: block;">
                        Password <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="password" id="password" name="password" class="form-input" placeholder="Minimum 6 characters" minlength="6" style="height: 42px; border-radius: 8px; font-size: 0.9rem;" required>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="role" style="font-weight: 700; font-size: 0.82rem; color: #334155; margin-bottom: 0.4rem; display: block;">
                        System Role <span style="color: #ef4444;">*</span>
                    </label>
                    <select id="role" name="role" class="form-select" style="height: 42px; border-radius: 8px; font-size: 0.9rem;" required>
                        <option value="coordinator">Coordinator (View registrations, papers)</option>
                        <option value="admin">Administrator (Full Access)</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="email" style="font-weight: 700; font-size: 0.82rem; color: #334155; margin-bottom: 0.4rem; display: block;">
                        Email Address
                    </label>
                    <input type="email" id="email" name="email" class="form-input" placeholder="e.g. suresh@example.com" style="height: 42px; border-radius: 8px; font-size: 0.9rem;">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="mobile" style="font-weight: 700; font-size: 0.82rem; color: #334155; margin-bottom: 0.4rem; display: block;">
                        Phone Number
                    </label>
                    <input type="tel" id="mobile" name="mobile" class="form-input" placeholder="e.g. 9876543210 (10 digits)" pattern="[0-9]{10}" style="height: 42px; border-radius: 8px; font-size: 0.9rem;">
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="height: 44px; padding: 0 2rem; font-weight: 700; font-size: 0.95rem; border-radius: 8px; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(37,99,235,0.25);">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                Create Staff Account
            </button>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); z-index: 99999; align-items: center; justify-content: center; padding: 1.25rem;">
    <div style="background: #ffffff; border-radius: 16px; max-width: 580px; width: 100%; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3); border: 1px solid #e2e8f0; overflow: hidden;">
        <!-- Modal Header -->
        <div style="padding: 1.25rem 1.75rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 1.35rem;">✏️</span>
                <div>
                    <h3 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0;">Edit Staff Account</h3>
                    <span id="modalUserSubtitle" style="font-size: 0.8rem; color: #64748b;">Update account details and permissions</span>
                </div>
            </div>
            <button type="button" onclick="closeEditModal()" style="background: none; border: none; font-size: 1.25rem; color: #64748b; cursor: pointer; padding: 4px 8px; border-radius: 6px;" title="Close">✕</button>
        </div>

        <!-- Modal Form -->
        <form method="POST" action="coordinators.php" style="padding: 1.5rem 1.75rem;">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="user_id" id="edit_user_id" value="">

            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.15rem; margin-bottom: 1.25rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="edit_name" style="font-weight: 700; font-size: 0.82rem; color: #334155; margin-bottom: 0.35rem; display: block;">
                        Full Name <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" id="edit_name" name="name" class="form-input" style="height: 40px; border-radius: 8px; font-size: 0.9rem;" required>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="edit_username" style="font-weight: 700; font-size: 0.82rem; color: #334155; margin-bottom: 0.35rem; display: block;">
                        Username <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" id="edit_username" name="username" class="form-input" style="height: 40px; border-radius: 8px; font-size: 0.9rem;" required>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="edit_password" style="font-weight: 700; font-size: 0.82rem; color: #334155; margin-bottom: 0.35rem; display: block;">
                        New Password <span style="font-size: 0.72rem; color: #64748b; font-weight: normal;">(Optional)</span>
                    </label>
                    <input type="password" id="edit_password" name="password" class="form-input" placeholder="Leave blank to keep unchanged" style="height: 40px; border-radius: 8px; font-size: 0.85rem;">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="edit_role" style="font-weight: 700; font-size: 0.82rem; color: #334155; margin-bottom: 0.35rem; display: block;">
                        System Role <span style="color: #ef4444;">*</span>
                    </label>
                    <select id="edit_role" name="role" class="form-select" style="height: 40px; border-radius: 8px; font-size: 0.9rem;" required>
                        <option value="coordinator">Coordinator (View registrations, papers)</option>
                        <option value="admin">Administrator (Full Access)</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="edit_mobile" style="font-weight: 700; font-size: 0.82rem; color: #334155; margin-bottom: 0.35rem; display: block;">
                        Phone Number
                    </label>
                    <input type="tel" id="edit_mobile" name="mobile" class="form-input" placeholder="10-digit mobile" pattern="[0-9]{10}" style="height: 40px; border-radius: 8px; font-size: 0.9rem;">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="edit_is_active" style="font-weight: 700; font-size: 0.82rem; color: #334155; margin-bottom: 0.35rem; display: block;">
                        Account Status <span style="color: #ef4444;">*</span>
                    </label>
                    <select id="edit_is_active" name="is_active" class="form-select" style="height: 40px; border-radius: 8px; font-size: 0.9rem;" required>
                        <option value="1">Active (Can Login)</option>
                        <option value="0">Disabled (Access Blocked)</option>
                    </select>
                </div>

                <div class="form-group" style="grid-column: span 2; margin-bottom: 0;">
                    <label class="form-label" for="edit_email" style="font-weight: 700; font-size: 0.82rem; color: #334155; margin-bottom: 0.35rem; display: block;">
                        Email Address
                    </label>
                    <input type="email" id="edit_email" name="email" class="form-input" placeholder="user@example.com" style="height: 40px; border-radius: 8px; font-size: 0.9rem;">
                </div>
            </div>

            <!-- Modal Action Buttons -->
            <div style="display: flex; justify-content: flex-end; gap: 10px; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
                <button type="button" onclick="closeEditModal()" class="btn" style="height: 40px; padding: 0 1.25rem; background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; font-weight: 600; border-radius: 8px; cursor: pointer;">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary" style="height: 40px; padding: 0 1.5rem; background: #2563eb; color: #ffffff; border: none; font-weight: 700; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(37,99,235,0.25);">
                    💾 Save Changes
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
    document.getElementById('modalUserSubtitle').textContent = 'Editing @' + user.username + ' (' + user.role + ')';
    
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

