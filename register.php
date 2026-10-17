<?php
$pageTitle = "School Registration - EEMT 2027";
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/mailer.php';

$pdo = getDB();
$error = '';
$districts = [];

try {
    $distStmt = $pdo->query("SELECT DISTINCT district FROM schools_master ORDER BY district ASC");
    $districts = $distStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $error = "Unable to load districts: " . $e->getMessage();
}

$error = '';
$isUnlocked = false;

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_registration'])) {
    $district = trim($_POST['district'] ?? '');
    $mandal = trim($_POST['mandal'] ?? '');
    $school_name = trim($_POST['school_name'] ?? '');
    $unique_code = strtoupper(trim($_POST['unique_code'] ?? ''));
    $contact_person = trim($_POST['contact_person'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

    // Strict Validation
    if (!$district || !$mandal || !$school_name || !$unique_code || !$contact_person || !$designation || !$mobile || !$email) {
        $error = "All fields are required. Please fill in the complete form.";
    } elseif (!preg_match('/^[0-9]{11}$/', $unique_code)) {
        $error = "School UDISE Code must be exactly 11 digits (numbers only, no alphabets or special characters).";
    } elseif (!preg_match('/^[0-9]{10}$/', $mobile)) {
        $error = "Contact Mobile Number must be exactly 10 digits (numbers only, no alphabets or special characters).";
    } elseif (!str_contains($email, '@') || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address containing '@' and a valid domain.";
    } else {
        // Check if mobile number has already been registered (Mobile number should be accepted only 1 time)
        $mobileCheck = $pdo->prepare("SELECT id, school_name, reference_id FROM school_registrations_2027 WHERE mobile = ?");
        $mobileCheck->execute([$mobile]);
        $existingMobile = $mobileCheck->fetch();

        if ($existingMobile) {
            $error = "This mobile number (" . htmlspecialchars($mobile) . ") is already registered for " . htmlspecialchars($existingMobile['school_name']) . " (EEMTSRC: " . htmlspecialchars($existingMobile['reference_id']) . "). Each mobile number can only be used once.";
        } else {
            // Validate School and Unique Code / UDISE Code against schools_master
        $checkSchoolStmt = $pdo->prepare("SELECT id, school_name, unique_code FROM schools_master WHERE district = ? AND mandal = ? AND school_name = ?");
        $checkSchoolStmt->execute([$district, $mandal, $school_name]);
        $schoolRecord = $checkSchoolStmt->fetch();

        if (!$schoolRecord) {
            $error = "Selected school could not be verified in state registry. Please re-select your school.";
        } elseif (strtoupper(trim($schoolRecord['unique_code'])) !== $unique_code) {
            $error = "Invalid School Code. The entered code does not match the records for {$schoolRecord['school_name']}.";
        } else {
            $isUnlocked = true;
            // Check if school is already registered (School should be registered only 1 time - no duplicates)
            $checkExisting = $pdo->prepare("SELECT reference_id, school_name FROM school_registrations_2027 WHERE unique_code = ? OR school_id = ?");
            $checkExisting->execute([$unique_code, $schoolRecord['id']]);
            $existing = $checkExisting->fetch();

            if ($existing) {
                $error = "This school (" . htmlspecialchars($schoolRecord['school_name']) . " - UDISE: " . htmlspecialchars($unique_code) . ") is already registered with Reference ID (EEMTSRC): " . htmlspecialchars($existing['reference_id']) . ". Duplicate registration is not permitted.";
            } else {
                // Generate Guaranteed Unique EEMT School Registration Code (Series starting at 26275000001)
                $seqOffset = 0;
                do {
                    $stmtMax = $pdo->query("SELECT MAX(reference_id) FROM school_registrations_2027 WHERE reference_id LIKE '26275%' AND LENGTH(reference_id) = 11");
                    $maxRef = $stmtMax->fetchColumn();

                    if ($maxRef && preg_match('/^26275([0-9]{6})$/', $maxRef, $matches)) {
                        $nextSeq = (int) $matches[1] + 1 + $seqOffset;
                    } else {
                        $nextSeq = 1 + $seqOffset;
                    }

                    $reference_id = '26275' . str_pad((string) $nextSeq, 6, '0', STR_PAD_LEFT);

                    $refCheck = $pdo->prepare("SELECT COUNT(*) FROM school_registrations_2027 WHERE reference_id = ?");
                    $refCheck->execute([$reference_id]);
                    if ($refCheck->fetchColumn() == 0) {
                        break;
                    }
                    $seqOffset++;
                } while (true);

                try {
                    $insertStmt = $pdo->prepare("
                        INSERT INTO school_registrations_2027 
                        (reference_id, school_id, district, mandal, school_name, unique_code, contact_person, designation, mobile, email, ip_address, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted')
                    ");
                    $insertStmt->execute([
                        $reference_id,
                        $schoolRecord['id'],
                        $district,
                        $mandal,
                        $school_name,
                        $unique_code,
                        $contact_person,
                        $designation,
                        $mobile,
                        $email,
                        $ip_address
                    ]);

                    // Mark school as registered (sync across unique_code aliases)
                    $pdo->prepare("UPDATE schools_master SET is_registered = 1 WHERE unique_code = ? OR id = ?")->execute([$schoolRecord['unique_code'], $schoolRecord['id']]);

                    // Send Confirmation Email
                    sendRegistrationEmail($email, [
                        'reference_id' => $reference_id,
                        'school_name' => $school_name,
                        'contact_person' => $contact_person,
                        'designation' => $designation,
                        'district' => $district,
                        'mandal' => $mandal,
                        'unique_code' => $unique_code,
                    ]);

                    // Redirect to confirmation
                    header("Location: register_success.php?ref=" . urlencode($reference_id));
                    exit;

                } catch (Exception $e) {
                    $errText = $e->getMessage();
                    if (str_contains($errText, 'mobile') || str_contains($errText, 'uq_sr_mobile')) {
                        $error = "This mobile number is already registered. Duplicate mobile numbers are not allowed.";
                    } elseif (str_contains($errText, 'unique_code') || str_contains($errText, 'uq_sr_unique_code') || str_contains($errText, 'school_id') || str_contains($errText, 'uq_sr_school_id')) {
                        $error = "This School / UDISE Code is already registered. Duplicate registrations are strictly not permitted.";
                    } elseif (str_contains($errText, 'reference_id') || str_contains($errText, 'idx_sr_ref')) {
                        $error = "Reference code generation conflict. Please submit again to generate a new unique reference code.";
                    } else {
                        $error = "Registration failed: " . $errText;
                    }
                }
            }
        }
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
    <h2 class="page-title">AP Government Schools Registration</h2>
    <p class="page-subtitle">Educational Epiphany Merit Test (EEMT) 2027 â€” School Prelims Enrollment Portal</p>
</div>

<div class="card" style="max-width: 850px; margin: 0 auto;">
    <div class="card-title">

        <span>School & In-Charge Teacher Details</span>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <span>âš ï¸</span>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="register.php" id="registration_form">
        <!-- 1. School Location Details -->
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label" for="district_select">
                    Select District <span class="required">*</span>
                </label>
                <select id="district_select" name="district" class="form-control" required>
                    <option value="">-- Choose District --</option>
                    <?php foreach ($districts as $d): ?>
                        <option value="<?php echo htmlspecialchars($d); ?>" <?php echo (isset($_POST['district']) && $_POST['district'] === $d) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($d); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="mandal_select">
                    Select Mandal <span class="required">*</span>
                </label>
                <select id="mandal_select" name="mandal" class="form-control" required disabled>
                    <option value="">Select District first</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="school_select">
                Select School <span class="required">*</span>
            </label>
            <select id="school_select" name="school_name" class="form-control" required disabled>
                <option value="">Select Mandal first</option>
            </select>
            <div class="form-hint">All AP Government High Schools and Upper Primary Schools are listed.</div>
        </div>

        <div class="form-group">
            <label class="form-label" for="unique_code">
                School Unique Code / UDISE Code <span class="required">*</span>
            </label>
            <div class="code-verify-wrapper">
                <input type="text" id="unique_code" name="unique_code" class="form-control"
                    placeholder="Enter 11-digit School UDISE Code (digits only)"
                    inputmode="numeric" minlength="11" maxlength="11" pattern="[0-9]{11}"
                    oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)"
                    value="<?php echo htmlspecialchars($_POST['unique_code'] ?? ''); ?>" required autocomplete="off">
                <button type="button" id="btn_verify_code" class="btn-verify">Verify Code</button>
            </div>
            <div id="code_verify_status" class="code-status"></div>
            <div class="form-hint">Enter your school's 11-digit UDISE Code (or assigned code) and click 'Verify Code' to
                validate.</div>
        </div>

        <hr style="margin: 2rem 0; border: none; border-top: 1px solid var(--border);">

        <!-- 2. Contact Person Information (Locked until code is verified) -->
        <?php $contactDisabled = !$isUnlocked ? 'disabled' : ''; ?>
        <div id="contact_info_section"
            class="<?php echo $isUnlocked ? 'contact-section-unlocked' : 'contact-section-locked'; ?>">
            <div class="card-title"
                style="font-size: 1.1rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span>👤</span>
                    <span>Contact Information (HM / Principal / Teacher)</span>
                </div>
                <span id="contact_lock_badge"
                    style="font-size: 0.8rem; font-weight: 600; padding: 4px 12px; border-radius: 999px; <?php echo $isUnlocked ? 'background: #dcfce7; color: #15803d; border: 1px solid #86efac;' : 'background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1;'; ?>">
                    <?php echo $isUnlocked ? 'Verified & Unlocked' : 'Locked Verify School Code First'; ?>
                </span>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="contact_person">
                        In-Charge / HM Full Name <span class="required">*</span>
                    </label>
                    <input type="text" id="contact_person" name="contact_person" class="form-control"
                        placeholder="Full Name as per records"
                        value="<?php echo htmlspecialchars($_POST['contact_person'] ?? ''); ?>" required <?php echo $contactDisabled; ?>>
                </div>

                <div class="form-group">
                    <label class="form-label" for="designation">
                        Designation <span class="required">*</span>
                    </label>
                    <select id="designation" name="designation" class="form-control" required <?php echo $contactDisabled; ?>>
                        <option value="">-- Choose Designation --</option>
                        <option value="Head Master (HM)" <?php echo (isset($_POST['designation']) && $_POST['designation'] === 'Head Master (HM)') ? 'selected' : ''; ?>>Head Master (HM)</option>
                        <option value="Principal" <?php echo (isset($_POST['designation']) && $_POST['designation'] === 'Principal') ? 'selected' : ''; ?>>Principal</option>
                        <option value="School Teacher" <?php echo (isset($_POST['designation']) && $_POST['designation'] === 'School Teacher') ? 'selected' : ''; ?>>School Teacher</option>
                        <option value="In-charge Teacher" <?php echo (isset($_POST['designation']) && $_POST['designation'] === 'In-charge Teacher') ? 'selected' : ''; ?>>In-charge Teacher
                        </option>
                    </select>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="mobile">
                        Contact Mobile Number <span class="required">*</span>
                    </label>
                    <input type="tel" id="mobile" name="mobile" class="form-control"
                        placeholder="Enter 10-digit mobile number (digits only)"
                        inputmode="numeric" minlength="10" maxlength="10" pattern="[0-9]{10}"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)"
                        value="<?php echo htmlspecialchars($_POST['mobile'] ?? ''); ?>" required <?php echo $contactDisabled; ?>>" required <?php echo $contactDisabled; ?>>
                    <div class="form-hint">Reference ID and exam updates will be communicated to this number.</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">
                        School / In-Charge Email Address <span class="required">*</span>
                    </label>
                    <input type="email" id="email" name="email" class="form-control"
                        placeholder="e.g. school@gmail.com"
                        pattern="[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}"
                        title="Please enter a valid email address containing @ and domain"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required <?php echo $contactDisabled; ?>>" required <?php echo $contactDisabled; ?>>
                    <div class="form-hint">Reference ID confirmation slip is sent immediately to this email.</div>
                </div>
            </div>

            <div style="margin-top: 2rem;">
                <button type="submit" id="btn_submit_registration" name="submit_registration"
                    class="btn btn-primary btn-block" style="padding: 1rem; font-size: 1.05rem;" <?php echo $contactDisabled; ?>>
                    Submit EEMT 2027 Registration & Generate EEMT SR Code &rarr;
                </button>
            </div>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>