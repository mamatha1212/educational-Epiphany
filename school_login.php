<?php
$pageTitle = "School Login Portal - EEMT 2027";
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// If already logged in as school, go directly to school dashboard
if (isSchoolLoggedIn()) {
    header("Location: school/dashboard.php");
    exit;
}

$error = '';
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_school'])) {
    $refId = strtoupper(trim($_POST['reference_id'] ?? ''));
    $uniqueCode = strtoupper(trim($_POST['unique_code'] ?? ''));

    if (!$refId || !$uniqueCode) {
        $error = "Validation Failed: Please enter both your School UDISE Code and EEMTSRC (EEMT School Registration Code).";
    } else {
        $stmt = $pdo->prepare("
            SELECT sr.* 
            FROM school_registrations_2027 sr 
            LEFT JOIN schools_master sm ON sr.school_id = sm.id 
            WHERE sr.reference_id = ? AND (sr.unique_code = ? OR sm.unique_code = ?)
        ");
        $stmt->execute([$refId, $uniqueCode, $uniqueCode]);
        $school = $stmt->fetch();

        if ($school) {
            $_SESSION['school'] = $school;
            $redirect = trim($_GET['redirect'] ?? '');
            if (!empty($redirect) && !str_starts_with($redirect, 'http')) {
                header("Location: " . $redirect);
            } else {
                header("Location: school/dashboard.php");
            }
            exit;
        } else {
            $error = "Validation Failed: Invalid UDISE Code or EEMT School Registration Code (EEMTSRC). Please verify your registered credentials.";
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div style="margin-bottom: 1rem;">
        <img src="assets/images/logo.png" alt="EEMT Logo"
            style="height: 70px; width: auto; background: white; padding: 6px; border-radius: 14px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); display: inline-block;"
            onerror="this.src='assets/images/logo.jpeg';">
    </div>
    <div
        style="display: inline-flex; align-items: center; gap: 8px; background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 6px 14px; border-radius: 9999px; font-size: 0.82rem; font-weight: 700; margin-bottom: 0.75rem;">
        <span>🏫 Official School Access</span>
    </div>
    <h2 class="page-title">School Login Portal</h2>
    <p class="page-subtitle">For Headmasters, Principals & Teachers of Registered Government Schools</p>
</div>

<div class="card"
    style="max-width: 500px; margin: 0 auto; box-shadow: var(--shadow-xl); border-top: 4px solid var(--primary);">



    <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom: 1.25rem;">
            <span>⚠️</span>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <div
        style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; margin-bottom: 1.5rem; font-size: 0.85rem; color: #334155; line-height: 1.5;">
        <strong style="color: #0f172a;">Mandatory Verification:</strong> Enter your school's 11-digit <strong>UDISE
            Code</strong> and your issued <strong>EEMTSRC Code</strong> (from your registration confirmation slip).
    </div>

    <form method="POST"
        action="school_login.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>">
        <div class="form-group">
            <label class="form-label" for="unique_code_login">
                1. School UDISE Code <span class="required">*</span>
            </label>
            <input type="text" id="unique_code_login" name="unique_code" class="form-control"
                placeholder="e.g. 28142500311" required autocomplete="off"
                value="<?php echo htmlspecialchars($_POST['unique_code'] ?? ''); ?>">
            <div class="form-hint">Official 11-digit Government school UDISE code.</div>
        </div>

        <div class="form-group">
            <label class="form-label" for="reference_id">
                2. EEMTSRC (EEMT School Registration Code) <span class="required">*</span>
            </label>
            <input type="text" id="reference_id" name="reference_id" class="form-control" placeholder="e.g. 26275000001"
                required autocomplete="off" value="<?php echo htmlspecialchars($_POST['reference_id'] ?? ''); ?>">
            <div class="form-hint">Registration series code (e.g. <code>26275000001</code>).</div>
        </div>

        <button type="submit" name="login_school" class="btn btn-primary btn-block"
            style="margin-top: 1.75rem; padding: 0.85rem; font-size: 1rem; font-weight: 700;">
            Verify & Open School Dashboard &rarr;
        </button>

        <div
            style="text-align: center; margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px dashed var(--border);">
            <div style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 0.5rem;">
                Need question papers immediately?
                <a href="prelims_qp.php" style="color: #059669; font-weight: 700; text-decoration: none;">Direct Prelims
                    QP Portal &rarr;</a>
            </div>

            <div style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 0.75rem;">
                School not registered yet?
                <a href="register.php" style="color: var(--primary); font-weight: 700; text-decoration: none;">Register
                    School Now &rarr;</a>
            </div>


        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>