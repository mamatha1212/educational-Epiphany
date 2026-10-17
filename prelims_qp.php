<?php
/**
 * EEMT 2027 - Prelims Question Papers (QP) Verification & Download Portal
 * Mandatory validation: 1. UDISE Code, 2. EEMTSRC (EEMT School Registration Code - Format: 26275XXXXXX)
 */
$pageTitle = "Prelims QP - EEMT 2027 Question Papers Portal";
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = getDB();
$error = '';
$validatedSchool = null;
$questionPapers = [];

// Pre-fill from query params if coming from registration success slip
$udiseInput = strtoupper(trim($_GET['udise'] ?? $_POST['udise_code'] ?? ''));
$eemtsrcInput = strtoupper(trim($_GET['eemtsrc'] ?? $_POST['eemtsrc'] ?? ''));

// If already logged in as school, load session school
if (isSchoolLoggedIn()) {
    $sessionSchool = currentSchool();
    $udiseInput = $sessionSchool['unique_code'] ?? $udiseInput;
    $eemtsrcInput = $sessionSchool['reference_id'] ?? $eemtsrcInput;
}

// Handle Verification
if (($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['validate_qp'])) || (!empty($udiseInput) && !empty($eemtsrcInput))) {
    if (!$udiseInput || !$eemtsrcInput) {
        $error = "Validation check is mandatory. Please enter both your School UDISE Code and EEMTSRC (EEMT School Registration Code).";
    } else {
        $stmt = $pdo->prepare("
            SELECT sr.*, sm.unique_code AS master_udise 
            FROM school_registrations_2027 sr 
            LEFT JOIN schools_master sm ON sr.school_id = sm.id 
            WHERE sr.reference_id = ? AND (sr.unique_code = ? OR sm.unique_code = ?)
        ");
        $stmt->execute([$eemtsrcInput, $udiseInput, $udiseInput]);
        $validatedSchool = $stmt->fetch();

        if ($validatedSchool) {
            // Set session for download authorization
            $_SESSION['school'] = $validatedSchool;

            // Fetch published prelims question papers
            $qpStmt = $pdo->prepare("SELECT * FROM question_papers WHERE is_published = 1 ORDER BY id DESC");
            $qpStmt->execute();
            $questionPapers = $qpStmt->fetchAll();
        } else {
            $error = "Validation Failed: The entered UDISE Code and EEMT School Registration Code (EEMTSRC) do not match school records. Please check your credentials.";
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header" style="max-width: 900px; margin: 0 auto 2rem;">
    <span class="badge badge-primary" style="font-size: 0.85rem; padding: 4px 12px; margin-bottom: 0.75rem;">
        Official Examination Portal
    </span>
    <h2 class="page-title" style="font-size: 2rem; font-weight: 800;">
        EEMT 2027 Prelims Question Papers (QP)
    </h2>
    <p class="page-subtitle" style="font-size: 1rem; max-width: 680px; margin: 0 auto;">
        Secure portal for Headmasters and Teachers to validate registered credentials and download official prelims
        question papers.
    </p>
</div>

<!-- Mandatory Validation Notice Box -->
<div class="card" style="max-width: 900px; margin: 0 auto 2rem; border-left: 5px solid #2563eb; background: #f8fafc;">
    <div style="display: flex; gap: 14px; align-items: flex-start;">
        <span style="font-size: 1.75rem; line-height: 1;"></span>
        <div>
            <h3 style="font-size: 1.05rem; font-weight: 700; color: #1e3a8a; margin-bottom: 0.35rem;">
                Validation Check is Mandatory for Prelims QP Access
            </h3>
            <p style="font-size: 0.9rem; color: #334155; margin: 0 0 0.5rem; line-height: 1.5;">
                To access and download question papers, teachers are required to enter:
            </p>
            <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; font-size: 0.875rem;">
                <div style="background: white; border: 1px solid #cbd5e1; padding: 6px 14px; border-radius: 8px;">
                    <strong>1. UDISE Code:</strong> <span style="color: #64748b;">(11-digit School UDISE Code)</span>
                </div>
                <div style="background: white; border: 1px solid #cbd5e1; padding: 6px 14px; border-radius: 8px;">
                    <strong>2. EEMTSRC:</strong> <span style="color: #64748b;">EEMT SR Code Series:
                        <code>26275000001, 26275000002...</code></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card" style="max-width: 900px; margin: 0 auto;">
    <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
            <span>âš ï¸</span>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <?php if (!$validatedSchool): ?>
        <!-- VALIDATION FORM -->
        <div class="card-title" style="font-size: 1.15rem; margin-bottom: 1.25rem;">

            <span>Enter Teacher / School Credentials</span>
        </div>

        <form method="POST" action="prelims_qp.php">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="udise_code">
                        1. School UDISE Code <span class="required">*</span>
                    </label>
                    <input type="text" id="udise_code" name="udise_code" class="form-control"
                        placeholder="11-digit UDISE Code (e.g. 28142500311)"
                        value="<?php echo htmlspecialchars($udiseInput); ?>" required autocomplete="off"
                        style="font-size: 1rem; font-weight: 600;">
                    <div class="form-hint">Official 11-digit school UDISE code as per Government records.</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="eemtsrc">
                        2. EEMTSRC (EEMT School Registration Code) <span class="required">*</span>
                    </label>
                    <input type="text" id="eemtsrc" name="eemtsrc" class="form-control" placeholder="e.g. 26275000001"
                        value="<?php echo htmlspecialchars($eemtsrcInput); ?>" required autocomplete="off"
                        style="font-size: 1rem; font-weight: 600;">
                    <div class="form-hint">Generated upon successful registration (Series:
                        <code>26275000001, 26275000002...</code>).
                    </div>
                </div>
            </div>

            <div style="margin-top: 1.5rem;">
                <button type="submit" name="validate_qp" class="btn btn-primary btn-block"
                    style="padding: 0.95rem; font-size: 1.05rem;">
                    Validate & Access Prelims Question Papers &rarr;
                </button>
            </div>

            <div style="text-align: center; margin-top: 1.25rem; font-size: 0.85rem; color: var(--text-muted);">
                School not registered yet? <a href="register.php" style="color: var(--primary); font-weight: 700;">Complete
                    School Prelims Registration</a>
            </div>
        </form>

    <?php else: ?>
        <!-- VALIDATED SUCCESS STATE: DISPLAY PAPERS -->
        <div class="alert alert-success"
            style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <strong style="font-size: 1rem;">Validation Successful!</strong>
                <div style="font-size: 0.85rem; margin-top: 2px;">
                    Access granted for <strong><?php echo htmlspecialchars($validatedSchool['school_name']); ?></strong>
                    (<?php echo htmlspecialchars($validatedSchool['mandal']); ?>,
                    <?php echo htmlspecialchars($validatedSchool['district']); ?>).
                </div>
            </div>
            <a href="prelims_qp.php" class="btn btn-secondary" style="padding: 4px 10px; font-size: 0.75rem;">
                Change School
            </a>
        </div>

        <div
            style="background: #f8fafc; border: 1px solid var(--border); border-radius: 10px; padding: 1.25rem; margin-bottom: 2rem;">
            <div
                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; font-size: 0.85rem;">
                <div>
                    <span style="color: var(--text-muted); display: block;">UDISE Code:</span>
                    <code
                        style="font-weight: 700; font-size: 0.95rem;"><?php echo htmlspecialchars($validatedSchool['unique_code']); ?></code>
                </div>
                <div>
                    <span style="color: var(--text-muted); display: block;">EEMT SR Code (EEMTSRC):</span>
                    <strong
                        style="color: #1e40af; font-size: 0.95rem;"><?php echo htmlspecialchars($validatedSchool['reference_id']); ?></strong>
                </div>
                <div>
                    <span style="color: var(--text-muted); display: block;">In-Charge / HM:</span>
                    <strong><?php echo htmlspecialchars($validatedSchool['contact_person']); ?></strong>
                    (<?php echo htmlspecialchars($validatedSchool['designation']); ?>)
                </div>
            </div>
        </div>

        <div class="card-title" style="justify-content: space-between; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 8px;">

                <span>Available Prelims Question Papers</span>
            </div>
            <span class="badge badge-primary"><?php echo count($questionPapers); ?> Available for Download</span>
        </div>

        <?php if (empty($questionPapers)): ?>
            <div
                style="background: #f8fafc; border: 2px dashed var(--border); border-radius: 12px; padding: 2.5rem; text-align: center;">
                <h4 style="color: var(--secondary); margin-bottom: 0.25rem;">Question Papers Scheduled Soon</h4>
                <p style="color: var(--text-muted); font-size: 0.875rem; max-width: 500px; margin: 0 auto;">
                    Prelims Question Papers are currently being compiled and will be available for download here ahead of the
                    exam date.
                </p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Title / Subject</th>
                            <th>Class</th>
                            <th>Published Date</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($questionPapers as $qp): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($qp['title']); ?></strong>
                                    <div style="font-size: 0.78rem; color: var(--text-muted);">
                                        Subject: <?php echo htmlspecialchars($qp['subject']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-primary"><?php echo htmlspecialchars($qp['class']); ?></span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars(date('d M Y', strtotime($qp['published_at'] ?? $qp['created_at']))); ?>
                                </td>
                                <td style="text-align: right;">
                                    <a href="school/download_paper.php?id=<?php echo (int) $qp['id']; ?>" class="btn btn-primary"
                                        style="padding: 6px 14px; font-size: 0.825rem;">
                                        Download Prelims QP
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div style="margin-top: 2rem; display: flex; gap: 1rem; flex-wrap: wrap;">
            <a href="school/dashboard.php" class="btn btn-secondary" style="flex: 1; text-align: center;">
                Go to School Dashboard &rarr;
            </a>
            <a href="register_success.php?ref=<?php echo urlencode($validatedSchool['reference_id']); ?>" target="_blank"
                class="btn btn-secondary" style="flex: 1; text-align: center;">
                View Official Registration Slip
            </a>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>