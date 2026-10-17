<?php
/**
 * EEMT 2027 - Authentication & Role Helpers
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if a school is logged in
 */
function isSchoolLoggedIn(): bool
{
    return isset($_SESSION['school']) && !empty($_SESSION['school']['reference_id']);
}

/**
 * Check if any staff/admin user is logged in
 */
function isStaffLoggedIn(): bool
{
    return isset($_SESSION['user']) && !empty($_SESSION['user']['username']);
}

/**
 * Check if current user is an admin
 */
function isAdmin(): bool
{
    return isStaffLoggedIn() && (($_SESSION['user']['role'] ?? '') === 'admin');
}

/**
 * Check if current user is a coordinator/staff
 */
function isCoordinator(): bool
{
    return isStaffLoggedIn() && (($_SESSION['user']['role'] ?? '') === 'coordinator');
}

/**
 * Get current logged in school data
 */
function currentSchool(): ?array
{
    return $_SESSION['school'] ?? null;
}

/**
 * Get current logged in user data
 */
function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * Protect school portal pages
 */
function requireSchoolLogin(): void
{
    if (!isSchoolLoggedIn()) {
        header('Location: ../school_login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

/**
 * Protect coordinator/staff portal pages
 */
function requireStaffLogin(): void
{
    if (!isStaffLoggedIn()) {
        header('Location: ../staff_login.php?type=coordinator&redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

/**
 * Protect admin-only features
 */
function requireAdmin(): void
{
    if (!isStaffLoggedIn()) {
        header('Location: ../staff_login.php?type=admin&redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
    if (!isAdmin()) {
        // Coordinator attempting to access admin page: route to staff dashboard
        header('Location: ../staff/dashboard.php?notice=' . urlencode('Admin privileges required. Redirected to Coordinator Dashboard.'));
        exit;
    }
}

/**
 * Check if current user has permission to VIEW schools, mandals and districts directory
 */
function canViewSchools(): bool
{
    if (!isStaffLoggedIn()) {
        return false;
    }
    if (isAdmin()) {
        return true;
    }
    $user = currentUser();
    if (!empty($user['id'])) {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT can_view_schools FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $val = $stmt->fetchColumn();
            $can = ($val !== false && $val !== null) ? (int) $val : 1;
            $_SESSION['user']['can_view_schools'] = $can;
            return $can === 1;
        } catch (Exception $e) {
            return isset($user['can_view_schools']) ? (int) $user['can_view_schools'] === 1 : true;
        }
    }
    return true;
}

/**
 * Check if current user has permission to EDIT/MANAGE schools, mandals and districts
 */
function canEditSchools(): bool
{
    if (!isStaffLoggedIn()) {
        return false;
    }
    if (isAdmin()) {
        return true;
    }
    $user = currentUser();
    if (!empty($user['id'])) {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT can_manage_schools FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $can = (int) $stmt->fetchColumn();
            $_SESSION['user']['can_manage_schools'] = $can;
            return $can === 1;
        } catch (Exception $e) {
            return !empty($user['can_manage_schools']);
        }
    }
    return !empty($user['can_manage_schools']);
}

/**
 * Check if current user has permission to manage schools (alias for backwards compatibility)
 */
function canManageSchools(): bool
{
    return canEditSchools();
}

/**
 * Protect pages that require school/district/mandal directory access
 */
function requireSchoolMasterAccess(): void
{
    if (!isStaffLoggedIn()) {
        header('Location: ../staff_login.php?type=coordinator&redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
    if (!canViewSchools() && !canManageSchools()) {
        header('Location: ../staff/dashboard.php?notice=' . urlencode('Access Restricted: You do not have permission to access schools, mandals and districts directory. Please contact an Administrator.'));
        exit;
    }
}

/**
 * Check if current user has permission to view/download Question Papers
 */
function canViewPapers(): bool
{
    if (!isStaffLoggedIn()) {
        return false;
    }
    if (isAdmin()) {
        return true;
    }
    $user = currentUser();
    if (!empty($user['id'])) {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT can_view_papers FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $val = $stmt->fetchColumn();
            $can = ($val !== false && $val !== null) ? (int) $val : 1;
            $_SESSION['user']['can_view_papers'] = $can;
            return $can === 1;
        } catch (Exception $e) {
            return isset($user['can_view_papers']) ? (int) $user['can_view_papers'] === 1 : true;
        }
    }
    return true;
}

/**
 * Check if current user has permission to manage Question Papers (upload, publish, delete)
 */
function canManagePapers(): bool
{
    if (!isStaffLoggedIn()) {
        return false;
    }
    if (isAdmin()) {
        return true;
    }
    $user = currentUser();
    if (!empty($user['id'])) {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT can_manage_papers FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $can = (int) $stmt->fetchColumn();
            $_SESSION['user']['can_manage_papers'] = $can;
            return $can === 1;
        } catch (Exception $e) {
            return !empty($user['can_manage_papers']);
        }
    }
    return !empty($user['can_manage_papers']);
}

/**
 * Protect question paper management pages
 */
function requireQuestionPapersAccess(): void
{
    if (!isStaffLoggedIn()) {
        header('Location: ../staff_login.php?type=coordinator&redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
    if (!canManagePapers()) {
        header('Location: ../staff/dashboard.php?notice=' . urlencode('Access Restricted: You do not have permission to upload or manage Question Papers. Please contact an Administrator.'));
        exit;
    }
}

/**
 * Check if current user has permission to view school registrations data
 */
function canViewRegistrations(): bool
{
    if (!isStaffLoggedIn()) {
        return false;
    }
    if (isAdmin()) {
        return true;
    }
    $user = currentUser();
    if (!empty($user['id'])) {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT can_view_registrations FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $can = (int) $stmt->fetchColumn();
            $_SESSION['user']['can_view_registrations'] = $can;
            return $can === 1;
        } catch (Exception $e) {
            return !isset($user['can_view_registrations']) || (int) $user['can_view_registrations'] === 1;
        }
    }
    return !isset($user['can_view_registrations']) || (int) $user['can_view_registrations'] === 1;
}

/**
 * Check if current user has permission to edit/update school registrations status
 */
function canEditRegistrations(): bool
{
    if (!isStaffLoggedIn()) {
        return false;
    }
    if (isAdmin()) {
        return true;
    }
    $user = currentUser();
    if (!empty($user['id'])) {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT can_view_regs_only, can_edit_registrations FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                if (!empty($row['can_view_regs_only'])) {
                    $_SESSION['user']['can_view_regs_only'] = 1;
                    $_SESSION['user']['can_edit_registrations'] = 0;
                    return false;
                }
                $can = isset($row['can_edit_registrations']) ? (int) $row['can_edit_registrations'] : 1;
                $_SESSION['user']['can_edit_registrations'] = $can;
                return $can === 1;
            }
        } catch (Exception $e) {
            if (!empty($user['can_view_regs_only'])) {
                return false;
            }
            return !empty($user['can_edit_registrations']);
        }
    }
    if (!empty($user['can_view_regs_only'])) {
        return false;
    }
    return !empty($user['can_edit_registrations']);
}

/**
 * Check if current user is restricted to View-Only for School Registrations
 */
function isRegistrationsViewOnly(): bool
{
    if (!isStaffLoggedIn() || isAdmin()) {
        return false;
    }
    return !canEditRegistrations();
}

function getLoggedInSchool(): ?array
{
    return currentSchool();
}
function getLoggedInUser(): ?array
{
    return currentUser();
}


