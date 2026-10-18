<?php
$pageTitle = "Login Portals - EEMT 2027";
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Quick redirect if query param is set
$type = $_GET['type'] ?? '';
$redirect = $_GET['redirect'] ?? '';
$redirectQuery = !empty($redirect) ? '&redirect=' . urlencode($redirect) : '';

if ($type === 'school') {
    header("Location: school_login.php" . (!empty($redirect) ? '?redirect=' . urlencode($redirect) : ''));
    exit;
} elseif ($type === 'admin') {
    header("Location: staff_login.php?type=admin" . $redirectQuery);
    exit;
} elseif ($type === 'staff' || $type === 'coordinator') {
    header("Location: staff_login.php?type=coordinator" . $redirectQuery);
    exit;
}

// If already logged in, route to respective dashboard
if (isSchoolLoggedIn()) {
    header("Location: school/dashboard.php");
    exit;
} elseif (isAdmin()) {
    header("Location: admin/dashboard.php");
    exit;
} elseif (isStaffLoggedIn()) {
    header("Location: staff/dashboard.php");
    exit;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div style="display: inline-flex; align-items: center; gap: 8px; background: #ececf7; border: 1px solid #c8c9eb; color: #31328f; padding: 6px 14px; border-radius: 9999px; font-size: 0.82rem; font-weight: 700; margin-bottom: 0.75rem;">
        <span>🔐 Secure Access Gateway</span>
    </div>
    <h2 class="page-title">EEMT 2027 Sign In Portals</h2>
    <p class="page-subtitle">Please select the appropriate portal corresponding to your authorized role</p>
</div>

<div style="max-width: 860px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 3rem;">
    
    <!-- Portal 1: School Login -->
    <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; border-top: 5px solid #31328f; box-shadow: var(--shadow-lg); transition: transform 0.2s, box-shadow 0.2s;">
        <div>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                <div style="width: 50px; height: 50px; border-radius: 12px; background: #ececf7; color: #31328f; display: flex; align-items: center; justify-content: center; font-size: 1.6rem;">
                    🏫
                </div>
                <span class="badge badge-info">Schools Portal</span>
            </div>

            <h3 style="font-size: 1.4rem; font-weight: 800; color: #0f172a; margin-bottom: 0.5rem;">
                School Login Portal
            </h3>
            
            <p style="color: #64748b; font-size: 0.95rem; line-height: 1.5; margin-bottom: 1.25rem;">
                Dedicated portal for <strong>Headmasters, Principals & Teachers</strong> of registered Andhra Pradesh Government Schools.
            </p>

            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; font-size: 0.82rem; color: #334155; margin-bottom: 1.5rem;">
                <div style="font-weight: 700; margin-bottom: 4px; color: #31328f;">Required Credentials:</div>
                <ul style="margin: 0; padding-left: 1.2rem; line-height: 1.4;">
                    <li>11-Digit School UDISE Code</li>
                    <li>EEMTSR Code (Format: <code>26275XXXXXX</code>)</li>
                </ul>
            </div>
        </div>

        <div>
            <a href="school_login.php<?php echo !empty($redirect) ? '?redirect=' . urlencode($redirect) : ''; ?>" class="btn btn-primary btn-block" style="padding: 0.85rem; font-size: 1rem; font-weight: 700; justify-content: center;">
                Open School Login &rarr;
            </a>
            
            <div style="text-align: center; margin-top: 0.75rem; font-size: 0.82rem; color: var(--text-muted);">
                Need question papers? <a href="prelims_qp.php" style="color: #059669; font-weight: 700;">Direct Prelims QP</a>
            </div>
        </div>
    </div>

    <!-- Portal 2: Staff & Admin Login -->
    <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; border-top: 5px solid #6366f1; box-shadow: var(--shadow-lg); transition: transform 0.2s, box-shadow 0.2s;">
        <div>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                <div style="width: 50px; height: 50px; border-radius: 12px; background: #eef2ff; color: #6366f1; display: flex; align-items: center; justify-content: center; font-size: 1.6rem;">
                    🛡️
                </div>
                <span class="badge" style="background: #eef2ff; color: #4338ca; font-weight: 700;">Staff & Admin</span>
            </div>

            <h3 style="font-size: 1.4rem; font-weight: 800; color: #0f172a; margin-bottom: 0.5rem;">
                Staff & Admin Portal
            </h3>
            
            <p style="color: #64748b; font-size: 0.95rem; line-height: 1.5; margin-bottom: 1.25rem;">
                Authorized portal with tabbed access for <strong>State Administrators</strong> and <strong>District & Field Coordinators</strong>.
            </p>

            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; font-size: 0.82rem; color: #334155; margin-bottom: 1.5rem;">
                <div style="font-weight: 700; margin-bottom: 4px; color: #312e81;">Available Tabs:</div>
                <ul style="margin: 0; padding-left: 1.2rem; line-height: 1.4;">
                    <li><strong>Admin Tab:</strong> Central monitoring & state controls</li>
                    <li><strong>Coordinator Tab:</strong> District field tracking & school follow-up</li>
                </ul>
            </div>
        </div>

        <div>
            <a href="staff_login.php<?php echo !empty($redirect) ? '?redirect=' . urlencode($redirect) : ''; ?>" class="btn btn-block" style="background: #4f46e5; color: white; padding: 0.85rem; font-size: 1rem; font-weight: 700; justify-content: center;">
                Open Staff & Admin Portal &rarr;
            </a>

            <div style="text-align: center; margin-top: 0.75rem; font-size: 0.82rem; color: var(--text-muted);">
                Default credentials available on login screen
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

