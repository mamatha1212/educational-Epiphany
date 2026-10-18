<?php
$pageTitle = "Find your Login credentials - EEMT 2027";
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect to school dashboard
if (isSchoolLoggedIn()) {
    header("Location: school/dashboard.php");
    exit;
}

$pdo = getDB();
$error = '';
$result = null;
$notRegistered = false;

// Preload districts
$districts = [];
try {
    $distStmt = $pdo->query("SELECT DISTINCT district FROM schools_master ORDER BY district ASC");
    $districts = $distStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $error = "Unable to load districts: " . $e->getMessage();
}

$selectedDistrict = trim($_POST['district'] ?? '');
$selectedMandal   = trim($_POST['mandal'] ?? '');
$selectedSchool   = trim($_POST['school_name'] ?? '');
$enteredCode      = strtoupper(trim($_POST['unique_code'] ?? ''));

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['find_ref_id'])) {
    if (!$selectedDistrict || !$selectedMandal || !$selectedSchool || !$enteredCode) {
        $error = "Please select District, Mandal, School and enter the School Code.";
    } elseif (!preg_match('/^[0-9]{11}$/', $enteredCode)) {
        $error = "School Code / UDISE Code must be exactly 11 numeric digits.";
    } else {
        try {
            // 1. Look up school in master registry
            $stmtMaster = $pdo->prepare("SELECT * FROM schools_master WHERE district = ? AND mandal = ? AND (school_name = ? OR REPLACE(school_name, '  ', ' ') = REPLACE(?, '  ', ' '))");
            $stmtMaster->execute([$selectedDistrict, $selectedMandal, $selectedSchool, $selectedSchool]);
            $masterRow = $stmtMaster->fetch();

            if (!$masterRow) {
                $error = "Selected school could not be verified in the state master registry. Please re-select your school.";
            } elseif (strtoupper(trim($masterRow['unique_code'])) !== $enteredCode) {
                $error = "Invalid School Code: The code entered does not match our official records for " . htmlspecialchars($masterRow['school_name']) . ". Please verify your 11-digit UDISE code.";
            } else {
                // 2. Check if registered in school_registrations_2027
                $stmtReg = $pdo->prepare("
                    SELECT * 
                    FROM school_registrations_2027 
                    WHERE unique_code = ? OR school_id = ?
                ");
                $stmtReg->execute([$enteredCode, $masterRow['id']]);
                $regRecord = $stmtReg->fetch();

                if ($regRecord) {
                    $result = $regRecord;
                } else {
                    $notRegistered = true;
                }
            }
        } catch (Exception $e) {
            $error = "Lookup error: " . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header" style="text-align: center; margin-bottom: 2rem;">
    <h2 class="page-title" style="font-size: 1.85rem; font-weight: 800; color: #31328f;">Find your Login credentials</h2>
    <p class="page-subtitle" style="color: #64748b; font-size: 0.95rem; max-width: 650px; margin: 0.5rem auto 0;">
        Retrieve your official School Reference ID (EEMTSRC) by verifying your school location and UDISE Code.
    </p>
</div>

<div class="card" style="max-width: 680px; margin: 0 auto 2.5rem; box-shadow: var(--shadow-xl); border-top: 4px solid #31328f; border-radius: 14px;">

    <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
            <span>⚠️</span>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($result): ?>
        <!-- SUCCESS CARD: EEMTSRC FOUND -->
        <div style="background: #f0fdf4; border: 1.5px solid #86efac; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.75rem;">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 1rem;">
                <div style="width: 38px; height: 38px; border-radius: 50%; background: #dcfce7; color: #15803d; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; font-weight: 800;">
                    ✓
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #14532d;">School Registration Record Found!</h3>
                    <div style="font-size: 0.82rem; color: #166534;">Your official EEMT 2027 credentials are shown below.</div>
                </div>
            </div>

            <!-- Prominent Reference ID Box -->
            <div style="background: #ffffff; border: 2px dashed #22c55e; border-radius: 10px; padding: 1.25rem; text-align: center; margin-bottom: 1.25rem;">
                <div style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; margin-bottom: 4px;">
                    Your School Reference ID (EEMTSRC)
                </div>
                <div id="foundRefId" style="font-family: monospace; font-size: 1.9rem; font-weight: 800; color: #31328f; letter-spacing: 0.05em; margin: 4px 0 8px;">
                    <?php echo htmlspecialchars($result['reference_id']); ?>
                </div>
                <button type="button" class="btn" onclick="copyFoundRef()" id="btnCopyRef"
                    style="background: #ececf7; color: #31328f; border: 1px solid #c8c9eb; padding: 6px 14px; font-size: 0.8rem; font-weight: 700; border-radius: 6px; cursor: pointer;">
                    📋 Copy Ref ID
                </button>
            </div>

            <!-- Detail Grid -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1.25rem; font-size: 0.88rem;">
                <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f1f5f9;">
                    <span style="color: #64748b; font-weight: 600;">School Name:</span>
                    <strong style="color: #0f172a; text-align: right;"><?php echo htmlspecialchars($result['school_name']); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f1f5f9;">
                    <span style="color: #64748b; font-weight: 600;">UDISE Code:</span>
                    <strong style="font-family: monospace; color: #31328f;"><?php echo htmlspecialchars($result['unique_code']); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f1f5f9;">
                    <span style="color: #64748b; font-weight: 600;">District / Mandal:</span>
                    <span style="color: #0f172a; font-weight: 700;"><?php echo htmlspecialchars($result['district'] . ' / ' . $result['mandal']); ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f1f5f9;">
                    <span style="color: #64748b; font-weight: 600;">In-Charge Coordinator:</span>
                    <span style="color: #0f172a; font-weight: 700;"><?php echo htmlspecialchars($result['contact_person']); ?> (<?php echo htmlspecialchars($result['designation'] ?? 'In-Charge'); ?>)</span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f1f5f9;">
                    <span style="color: #64748b; font-weight: 600;">Contact Mobile:</span>
                    <span style="color: #0f172a; font-weight: 700;"><?php echo htmlspecialchars($result['mobile']); ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 6px 0;">
                    <span style="color: #64748b; font-weight: 600;">Registration Date:</span>
                    <span style="color: #0f172a; font-weight: 700;"><?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($result['created_at'] ?? 'now'))); ?></span>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="school_login.php?code=<?php echo urlencode($result['unique_code']); ?>&ref=<?php echo urlencode($result['reference_id']); ?>"
                    class="btn btn-primary"
                    style="flex: 1; min-width: 200px; padding: 0.85rem; text-align: center; text-decoration: none; font-weight: 700; border-radius: 8px;">
                    Login with this Ref ID &rarr;
                </a>
                <a href="register_success.php?ref=<?php echo urlencode($result['reference_id']); ?>&existing=1" target="_blank"
                    class="btn"
                    style="flex: 1; min-width: 200px; background: #ffffff; color: #31328f; border: 1.5px solid #31328f; padding: 0.85rem; text-align: center; text-decoration: none; font-weight: 700; border-radius: 8px;">
                    View &amp; Print Slip ↗
                </a>
            </div>
        </div>
    <?php elseif ($notRegistered): ?>
        <!-- NOT REGISTERED WARNING -->
        <div style="background: #fffbeb; border: 1.5px solid #fde68a; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.75rem;">
            <div style="display: flex; align-items: flex-start; gap: 12px;">
                <div style="font-size: 1.8rem; line-height: 1;">ℹ️</div>
                <div>
                    <h3 style="margin: 0 0 0.4rem 0; font-size: 1.15rem; font-weight: 800; color: #92400e;">
                        School Verified, But Not Yet Registered!
                    </h3>
                    <p style="margin: 0 0 1rem 0; font-size: 0.88rem; color: #78350f; line-height: 1.5;">
                        Official records for <strong><?php echo htmlspecialchars($selectedSchool); ?></strong> (UDISE: <code><?php echo htmlspecialchars($enteredCode); ?></code>) were found, but this school has <strong>not yet submitted registration</strong> for EEMT 2027.
                    </p>
                    <a href="register.php" class="btn btn-primary"
                        style="display: inline-block; padding: 0.75rem 1.5rem; font-weight: 700; text-decoration: none; border-radius: 8px;">
                        Register School for EEMT 2027 Now &rarr;
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Form Section -->
    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 14px; margin-bottom: 1.5rem; font-size: 0.85rem; color: #334155; line-height: 1.5;">
        <strong style="color: #0f172a;">Instructions:</strong> Select your school's <strong>District</strong>, <strong>Mandal</strong>, and <strong>School Name</strong>, then enter your 11-digit <strong>UDISE Code</strong>. When verified, your EEMTSRC code will be retrieved.
    </div>

    <form method="POST" action="forgot_password.php" id="forgot_form">
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label" for="forgot_district">
                    Select District <span class="required">*</span>
                </label>
                <select id="forgot_district" name="district" class="form-control" required>
                    <option value="">-- Choose District --</option>
                    <?php foreach ($districts as $d): ?>
                        <option value="<?php echo htmlspecialchars($d); ?>" <?php echo ($selectedDistrict === $d) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($d); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="forgot_mandal">
                    Select Mandal <span class="required">*</span>
                </label>
                <select id="forgot_mandal" name="mandal" class="form-control" required <?php echo empty($selectedMandal) ? 'disabled' : ''; ?>>
                    <?php if (!empty($selectedMandal)): ?>
                        <option value="<?php echo htmlspecialchars($selectedMandal); ?>" selected>
                            <?php echo htmlspecialchars($selectedMandal); ?>
                        </option>
                    <?php else: ?>
                        <option value="">Select District first</option>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="forgot_school">
                Select School <span class="required">*</span>
            </label>
            <select id="forgot_school" name="school_name" class="form-control" required <?php echo empty($selectedSchool) ? 'disabled' : ''; ?>>
                <?php if (!empty($selectedSchool)): ?>
                    <option value="<?php echo htmlspecialchars($selectedSchool); ?>" selected>
                        <?php echo htmlspecialchars($selectedSchool); ?>
                    </option>
                <?php else: ?>
                    <option value="">Select Mandal first</option>
                <?php endif; ?>
            </select>
            <div class="form-hint">Choose your official Government High School or Upper Primary School.</div>
        </div>

        <div class="form-group">
            <label class="form-label" for="forgot_code">
                School Unique Code / UDISE Code <span class="required">*</span>
            </label>
            <input type="text" id="forgot_code" name="unique_code" class="form-control"
                placeholder="Enter 11-digit School UDISE Code"
                inputmode="numeric" minlength="11" maxlength="11" pattern="[0-9]{11}"
                oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)"
                value="<?php echo htmlspecialchars($enteredCode); ?>" required autocomplete="off">
            <div class="form-hint">Official 11-digit school code from government records.</div>
        </div>

        <button type="submit" name="find_ref_id" class="btn btn-primary btn-block"
            style="margin-top: 1.5rem; padding: 0.85rem; font-size: 1rem; font-weight: 700; border-radius: 8px;">
            🔍 Find &amp; Retrieve Ref ID (EEMTSRC) &rarr;
        </button>

        <div style="text-align: center; margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px dashed var(--border);">
            <div style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 0.5rem;">
                Remember your credentials?
                <a href="school_login.php" style="color: #31328f; font-weight: 700; text-decoration: none;">Back to School Login &rarr;</a>
            </div>
            <div style="font-size: 0.88rem; color: var(--text-muted);">
                Need to register your school?
                <a href="register.php" style="color: #31328f; font-weight: 700; text-decoration: none;">Register School Now &rarr;</a>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dSelect = document.getElementById('forgot_district');
    const mSelect = document.getElementById('forgot_mandal');
    const sSelect = document.getElementById('forgot_school');
    const codeInput = document.getElementById('forgot_code');

    if (dSelect && mSelect) {
        dSelect.addEventListener('change', async function() {
            const district = dSelect.value;
            mSelect.innerHTML = '<option value="">Loading Mandals...</option>';
            mSelect.disabled = true;

            if (sSelect) {
                sSelect.innerHTML = '<option value="">Select Mandal first</option>';
                sSelect.disabled = true;
            }

            if (!district) {
                mSelect.innerHTML = '<option value="">Select District first</option>';
                return;
            }

            try {
                const res = await fetch(`api/get_mandals.php?district=${encodeURIComponent(district)}`);
                const mandals = await res.json();
                
                mSelect.innerHTML = '<option value="">-- Select Mandal --</option>';
                mandals.forEach(m => {
                    const opt = document.createElement('option');
                    opt.value = m;
                    opt.textContent = m;
                    mSelect.appendChild(opt);
                });
                mSelect.disabled = false;
            } catch (err) {
                console.error(err);
                mSelect.innerHTML = '<option value="">Error loading mandals</option>';
            }
        });
    }

    if (mSelect && sSelect) {
        mSelect.addEventListener('change', async function() {
            const district = dSelect.value;
            const mandal = mSelect.value;

            sSelect.innerHTML = '<option value="">Loading Schools...</option>';
            sSelect.disabled = true;

            if (!mandal) {
                sSelect.innerHTML = '<option value="">Select Mandal first</option>';
                return;
            }

            try {
                const res = await fetch(`api/get_schools.php?district=${encodeURIComponent(district)}&mandal=${encodeURIComponent(mandal)}`);
                const schools = await res.json();
                
                sSelect.innerHTML = '<option value="">-- Select School --</option>';
                schools.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.school_name;
                    opt.textContent = s.school_name;
                    sSelect.appendChild(opt);
                });
                sSelect.disabled = false;
            } catch (err) {
                console.error(err);
                sSelect.innerHTML = '<option value="">Error loading schools</option>';
            }
        });
    }
});

function copyFoundRef() {
    const el = document.getElementById('foundRefId');
    const btn = document.getElementById('btnCopyRef');
    if (!el) return;
    const text = el.innerText.trim();
    navigator.clipboard.writeText(text).then(() => {
        if (btn) {
            btn.innerHTML = '✓ Copied!';
            btn.style.background = '#dcfce7';
            btn.style.color = '#15803d';
            btn.style.borderColor = '#86efac';
            setTimeout(() => {
                btn.innerHTML = '📋 Copy Ref ID';
                btn.style.background = '#ececf7';
                btn.style.color = '#31328f';
                btn.style.borderColor = '#c8c9eb';
            }, 2500);
        }
    }).catch(() => {
        alert('Copied Ref ID: ' + text);
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
