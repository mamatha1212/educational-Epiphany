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

<div>
    <!-- Breadcrumb -->
    <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <a href="dashboard.php" style="color: #2563eb; font-weight: 600; text-decoration: none; font-size: 0.9rem;">
                &larr; Back to Admin Dashboard
            </a>
            <h2 style="font-size: 1.75rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;">Staff & Coordinator Accounts</h2>
        </div>
        <a href="#add-user-card" class="btn btn-primary">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Add Staff Member
        </a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?>" style="margin-bottom: 1.5rem;">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- User Accounts Table -->
    <div class="card" style="padding: 0; overflow: hidden; margin-bottom: 2rem;">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 1.15rem; font-weight: 700; color: #1e293b; margin: 0;">System Access Accounts</h3>
            <span class="badge badge-info"><?php echo count($users); ?> Total Accounts</span>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name / Username</th>
                        <th>Role</th>
                        <th>Contact Info</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td style="font-weight: 600; color: #64748b;">#<?php echo $u['id']; ?></td>
                            <td>
                                <strong style="color: #0f172a;"><?php echo htmlspecialchars($u['name']); ?></strong>
                                <div style="font-size: 0.82rem; color: #64748b; font-family: monospace;">@<?php echo htmlspecialchars($u['username']); ?></div>
                            </td>
                            <td>
                                <span class="badge" style="background: <?php echo $u['role'] === 'admin' ? '#eff6ff' : '#ecfdf5'; ?>; color: <?php echo $u['role'] === 'admin' ? '#1d4ed8' : '#047857'; ?>; font-weight: 700;">
                                    <?php echo strtoupper(htmlspecialchars($u['role'])); ?>
                                </span>
                            </td>
                            <td style="font-size: 0.85rem;">
                                <div><?php echo htmlspecialchars($u['mobile'] ?: 'N/A'); ?></div>
                                <div style="color: #64748b;"><?php echo htmlspecialchars($u['email'] ?: 'N/A'); ?></div>
                            </td>
                            <td>
                                <?php if ($u['is_active'] == 1): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.82rem; color: #64748b; white-space: nowrap;">
                                <?php echo date('d M Y', strtotime($u['created_at'])); ?>
                            </td>
                            <td style="text-align: right;">
                                <?php if ($u['id'] != ($_SESSION['user']['id'] ?? 0)): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this staff account?');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <button type="submit" class="btn" style="padding: 0.35rem 0.65rem; font-size: 0.8rem; background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5;">
                                            Delete
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span style="font-size: 0.8rem; color: #64748b; font-style: italic;">Current User</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Staff Card -->
    <div class="card" id="add-user-card">
        <h3 style="font-size: 1.25rem; font-weight: 700; color: #0f172a; margin-bottom: 0.5rem;">Create Staff / Coordinator Account</h3>
        <p style="font-size: 0.9rem; color: #64748b; margin-bottom: 1.5rem;">Add administrators or regional coordinators who can view school registrations and manage papers.</p>

        <form method="POST" action="coordinators.php">
            <input type="hidden" name="action" value="create_user">

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
                <div class="form-group">
                    <label class="form-label" for="name">Full Name <span style="color: #ef4444;">*</span></label>
                    <input type="text" id="name" name="name" class="form-input" placeholder="e.g. R. Suresh Kumar" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="username">Username <span style="color: #ef4444;">*</span></label>
                    <input type="text" id="username" name="username" class="form-input" placeholder="e.g. suresh_coord" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password <span style="color: #ef4444;">*</span></label>
                    <input type="password" id="password" name="password" class="form-input" placeholder="Minimum 6 characters" minlength="6" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="role">System Role <span style="color: #ef4444;">*</span></label>
                    <select id="role" name="role" class="form-select" required>
                        <option value="coordinator">Coordinator (View registrations, papers)</option>
                        <option value="admin">Administrator (Full Access)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-input" placeholder="e.g. suresh@example.com">
                </div>

                <div class="form-group">
                    <label class="form-label" for="mobile">Phone Number</label>
                    <input type="tel" id="mobile" name="mobile" class="form-input" placeholder="e.g. 9876543210" pattern="[0-9]{10}">
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem; margin-top: 0.5rem;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                Create Staff Account
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

