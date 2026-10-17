<?php
$pageTitle = "Registration Confirmation - EEMT 2027";
require_once __DIR__ . '/config/database.php';

$refId = trim($_GET['ref'] ?? '');
if (!$refId) {
    header("Location: register.php");
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM school_registrations_2027 WHERE reference_id = ? OR unique_code = ?");
$stmt->execute([$refId, $refId]);
$record = $stmt->fetch();

if (!$record && !empty($_SESSION['school']['reference_id'])) {
    $stmt->execute([$_SESSION['school']['reference_id'], $_SESSION['school']['reference_id']]);
    $record = $stmt->fetch();
}

if (!$record) {
    die("Registration record not found for Reference ID: " . htmlspecialchars($refId));
}

$isExisting = isset($_GET['existing']);

require_once __DIR__ . '/includes/header.php';
?>

<div class="slip-container">
    <div class="slip-header">
        <div style="display: flex; justify-content: center; margin-bottom: 12px;">
            <div
                style="width: 76px; height: 76px; background: #ffffff; border-radius: 16px; padding: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: flex; align-items: center; justify-content: center;">
                <img src="assets/images/logo.png" alt="EEMT Logo"
                    style="width: 100%; height: 100%; object-fit: contain;"
                    onerror="this.src='assets/images/logo.jpeg';">
            </div>
        </div>
        <h2 class="slip-title">Educational Epiphany Merit Test 2027</h2>
        <p class="slip-subtitle">Official School Prelims Registration Confirmation Slip</p>
    </div>

    <div class="slip-body">
        <?php if ($isExisting): ?>
            <div class="alert alert-info">
                <span>â„¹ï¸</span>
                <span>This school is already registered. Here are your official registration details and Reference
                    ID.</span>
            </div>
        <?php else: ?>
            <div class="alert alert-success">

                <span>Registration submitted successfully! A confirmation copy has been dispatched to
                    <strong><?php echo htmlspecialchars($record['email']); ?></strong>.</span>
            </div>
        <?php endif; ?>

        <div class="ref-display-card">
            <div class="ref-display-title">
                EEMT School Registration Code (EEMTSRC / EEMT SR Code)
            </div>
            <div class="ref-display-val"><?php echo htmlspecialchars($record['reference_id']); ?></div>
            <div class="ref-display-hint">
                Official EEMT SR Code Series: <code>26275000001, 26275000002...</code>
            </div>
            <button type="button" class="btn btn-secondary btn-no-print ref-copy-btn"
                onclick="copyRefId('<?php echo htmlspecialchars($record['reference_id']); ?>', this)">
                Copy EEMT SR Code
            </button>
        </div>

        <!-- Mandatory Prelims QP Credentials Notice -->
        <div class="qp-notice-card">
            <div class="qp-notice-header">

                <span>Mandatory Credentials For Prelims Question Papers (QP)</span>
            </div>
            <p class="qp-notice-desc">
                When accessing and downloading the <strong>EEMT 2027 Prelims Question Papers</strong>, teachers are
                required to validate with:
            </p>
            <ul class="qp-notice-list">
                <li><strong>1. School UDISE Code:</strong> <code
                        class="credential-badge"><?php echo htmlspecialchars($record['unique_code']); ?></code></li>
                <li><strong>2. EEMTSRC (EEMT SR Code):</strong> <code
                        class="credential-badge"><?php echo htmlspecialchars($record['reference_id']); ?></code></li>
            </ul>
            <a href="prelims_qp.php?udise=<?php echo urlencode($record['unique_code']); ?>&eemtsrc=<?php echo urlencode($record['reference_id']); ?>"
                class="btn btn-primary qp-access-btn">
                Access Prelims QP Portal &rarr;
            </a>
        </div>

        <table class="slip-table">
            <tbody>
                <tr>
                    <th>School Name</th>
                    <td><?php echo htmlspecialchars($record['school_name']); ?></td>
                </tr>
                <tr>
                    <th>District</th>
                    <td><?php echo htmlspecialchars($record['district']); ?></td>
                </tr>
                <tr>
                    <th>Mandal</th>
                    <td><?php echo htmlspecialchars($record['mandal']); ?></td>
                </tr>
                <tr>
                    <th>School UDISE Code</th>
                    <td><code class="credential-badge"><?php echo htmlspecialchars($record['unique_code']); ?></code>
                    </td>
                </tr>
                <tr>
                    <th>EEMT SR Code (EEMTSRC)</th>
                    <td><strong
                            style="color: #1e40af; font-size: 1.05rem;"><?php echo htmlspecialchars($record['reference_id']); ?></strong>
                    </td>
                </tr>
                <tr>
                    <th>In-Charge Name</th>
                    <td><?php echo htmlspecialchars($record['contact_person']); ?>
                        (<?php echo htmlspecialchars($record['designation']); ?>)</td>
                </tr>
                <tr>
                    <th>Mobile Number</th>
                    <td><a href="tel:<?php echo htmlspecialchars($record['mobile']); ?>"
                            style="color: inherit; text-decoration: none;"><?php echo htmlspecialchars($record['mobile']); ?></a>
                    </td>
                </tr>
                <tr>
                    <th>Email Address</th>
                    <td><a href="mailto:<?php echo htmlspecialchars($record['email']); ?>"
                            style="color: inherit; text-decoration: none; word-break: break-all;"><?php echo htmlspecialchars($record['email']); ?></a>
                    </td>
                </tr>
                <tr>
                    <th>Registration Date</th>
                    <td><?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($record['created_at']))); ?></td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td><span
                            class="badge badge-success"><?php echo strtoupper(htmlspecialchars($record['status'])); ?></span>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="slip-note">
            <strong>Important Note:</strong> Please save or print this official confirmation slip. Teachers must
            retain their <strong>UDISE Code</strong> and <strong>EEMTSRC Code</strong>
            (<code><?php echo htmlspecialchars($record['reference_id']); ?></code>) for Prelims QP verification.
        </div>

        <div class="btn-no-print slip-actions">
            <button onclick="window.print()" class="btn btn-secondary slip-action-btn">
                Print Slip / Save PDF
            </button>
            <a href="prelims_qp.php" class="btn btn-primary slip-action-btn">
                Download Prelims QP &rarr;
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>