<?php
$pageTitle = "Staff & Admin Login Portal - EEMT 2027";
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect to respective dashboard
if (isAdmin()) {
    header("Location: admin/dashboard.php");
    exit;
} elseif (isStaffLoggedIn()) {
    header("Location: staff/dashboard.php");
    exit;
}

$error = '';
$notice = $_GET['notice'] ?? '';
$activeTab = $_GET['type'] ?? 'admin';
if (!in_array($activeTab, ['admin', 'coordinator'])) {
    $activeTab = 'admin';
}

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_staff'])) {
    $activeTab = $_POST['tab_type'] ?? 'admin';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $error = "Please enter both username and password.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'name' => $user['name'],
                'role' => $user['role'],
                'assigned_district' => $user['assigned_district']
            ];

            // Direct role routing
            if ($user['role'] === 'admin') {
                header("Location: admin/dashboard.php");
                exit;
            } else {
                header("Location: staff/dashboard.php");
                exit;
            }
        } else {
            $error = "Invalid username or password. Please verify your credentials.";
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div style="margin-bottom: 1rem;">
        <img src="assets/images/logo.png" alt="EEMT Logo" style="height: 70px; width: auto; background: white; padding: 6px; border-radius: 14px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); display: inline-block;" onerror="this.src='assets/images/logo.jpeg';">
    </div>
    <div style="display: inline-flex; align-items: center; gap: 8px; background: #f1f5f9; border: 1px solid #cbd5e1; color: #334155; padding: 6px 14px; border-radius: 9999px; font-size: 0.82rem; font-weight: 700; margin-bottom: 0.75rem;">
        <span>🔒 Management & Field Operations</span>
    </div>
    <h2 class="page-title">Staff & Admin Login Portal</h2>
    <p class="page-subtitle">Centralized access for State Administrators and District Coordinators</p>
</div>

<div class="card" style="max-width: 520px; margin: 0 auto; box-shadow: var(--shadow-xl); border-top: 4px solid #6366f1;">
    
    <!-- Tab Switcher -->
    <div class="tab-container" style="margin-bottom: 1.5rem; background: #f1f5f9; padding: 4px; border-radius: 10px;">
        <button type="button" class="tab-btn <?php echo $activeTab === 'admin' ? 'active' : ''; ?>" id="tab-btn-admin" onclick="switchStaffTab('admin')" style="flex: 1; text-align: center; font-weight: 700; padding: 10px 14px; border-radius: 8px;">
            🛡️ Admin Login
        </button>
        <button type="button" class="tab-btn <?php echo $activeTab === 'coordinator' ? 'active' : ''; ?>" id="tab-btn-coord" onclick="switchStaffTab('coordinator')" style="flex: 1; text-align: center; font-weight: 700; padding: 10px 14px; border-radius: 8px;">
            👔 Coordinator Login
        </button>
    </div>

    <?php if ($notice): ?>
        <div class="alert alert-info" style="margin-bottom: 1.25rem;">
            <span>ℹ️</span>
            <span><?php echo htmlspecialchars($notice); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom: 1.25rem;">
            <span>⚠️</span>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <!-- 1. ADMIN FORM -->
    <div id="panel-admin" style="display: <?php echo $activeTab === 'admin' ? 'block' : 'none'; ?>;">
        <div style="display: flex; align-items: center; gap: 10px; background: #eff6ff; border-left: 4px solid #3b82f6; padding: 10px 14px; border-radius: 6px; margin-bottom: 1.5rem; font-size: 0.85rem; color: #1e40af;">
            <div>
                <strong>State Administrator Access:</strong> Full system control, coordinator management, question paper publishing, and statewide data export.
            </div>
        </div>

        <form method="POST" action="staff_login.php">
            <input type="hidden" name="tab_type" value="admin">
            
            <div class="form-group">
                <label class="form-label" for="admin_username">
                    Admin Username <span class="required">*</span>
                </label>
                <input type="text" id="admin_username" name="username" class="form-control" placeholder="e.g. admin" required autocomplete="username" value="<?php echo $activeTab === 'admin' ? htmlspecialchars($_POST['username'] ?? '') : ''; ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="admin_password">
                    Admin Password <span class="required">*</span>
                </label>
                <input type="password" id="admin_password" name="password" class="form-control" placeholder="••••••••" required autocomplete="current-password">
            </div>

            <button type="submit" name="login_staff" class="btn btn-primary btn-block" style="margin-top: 1.5rem; background: #2563eb; padding: 0.85rem; font-size: 1rem; font-weight: 700;">
                Sign In to Admin Dashboard &rarr;
            </button>
        </form>
    </div>

    <!-- 2. COORDINATOR FORM -->
    <div id="panel-coordinator" style="display: <?php echo $activeTab === 'coordinator' ? 'block' : 'none'; ?>;">
        <div style="display: flex; align-items: center; gap: 10px; background: #ecfdf5; border-left: 4px solid #10b981; padding: 10px 14px; border-radius: 6px; margin-bottom: 1.5rem; font-size: 0.85rem; color: #065f46;">
            <div>
                <strong>District Coordinator Access:</strong> Field monitoring, registered school verification, school in-charge follow-up, and district roster management.
            </div>
        </div>

        <form method="POST" action="staff_login.php">
            <input type="hidden" name="tab_type" value="coordinator">
            
            <div class="form-group">
                <label class="form-label" for="coord_username">
                    Coordinator Username <span class="required">*</span>
                </label>
                <input type="text" id="coord_username" name="username" class="form-control" placeholder="e.g. coordinator" required autocomplete="username" value="<?php echo $activeTab === 'coordinator' ? htmlspecialchars($_POST['username'] ?? '') : ''; ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="coord_password">
                    Coordinator Password <span class="required">*</span>
                </label>
                <input type="password" id="coord_password" name="password" class="form-control" placeholder="••••••••" required autocomplete="current-password">
            </div>

            <button type="submit" name="login_staff" class="btn btn-block" style="margin-top: 1.5rem; background: #059669; color: white; padding: 0.85rem; font-size: 1rem; font-weight: 700;">
                Sign In to Staff Dashboard &rarr;
            </button>
        </form>
    </div>

    <!-- Quick Credentials Hint -->
    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; margin-top: 1.5rem; font-size: 0.8rem; color: #64748b;">
        <div style="font-weight: 700; color: #334155; margin-bottom: 4px;">System Credentials Reference:</div>
        <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 6px;">
            <span>Admin: <code>admin</code> / <code>admin123</code></span>
            <span>Coordinator: <code>coordinator</code> / <code>coord123</code></span>
        </div>
    </div>

    <!-- School Link -->
    <div style="text-align: center; margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px dashed var(--border);">
        <div style="font-size: 0.88rem; color: var(--text-muted);">
            Are you a School Headmaster or Teacher?
        </div>
        <a href="school_login.php" style="display: inline-flex; align-items: center; gap: 6px; margin-top: 0.5rem; color: #2563eb; font-weight: 700; text-decoration: none; font-size: 0.92rem;">
            <span>🏫 Go to Dedicated School Login Portal</span> &rarr;
        </a>
    </div>

</div>

<script>
function switchStaffTab(tab) {
    const adminBtn = document.getElementById('tab-btn-admin');
    const coordBtn = document.getElementById('tab-btn-coord');
    const adminPanel = document.getElementById('panel-admin');
    const coordPanel = document.getElementById('panel-coordinator');

    if (tab === 'admin') {
        adminBtn.classList.add('active');
        coordBtn.classList.remove('active');
        adminPanel.style.display = 'block';
        coordPanel.style.display = 'none';
        adminBtn.style.background = '#ffffff';
        adminBtn.style.color = '#1e3a8a';
        adminBtn.style.boxShadow = '0 1px 3px rgba(0,0,0,0.1)';
        coordBtn.style.background = 'transparent';
        coordBtn.style.color = '#64748b';
        coordBtn.style.boxShadow = 'none';
    } else {
        coordBtn.classList.add('active');
        adminBtn.classList.remove('active');
        coordPanel.style.display = 'block';
        adminPanel.style.display = 'none';
        coordBtn.style.background = '#ffffff';
        coordBtn.style.color = '#065f46';
        coordBtn.style.boxShadow = '0 1px 3px rgba(0,0,0,0.1)';
        adminBtn.style.background = 'transparent';
        adminBtn.style.color = '#64748b';
        adminBtn.style.boxShadow = 'none';
    }
}

// Initialize styles on load
document.addEventListener('DOMContentLoaded', () => {
    switchStaffTab('<?php echo $activeTab; ?>');
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

