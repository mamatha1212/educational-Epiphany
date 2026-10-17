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
function isSchoolLoggedIn(): bool {
    return isset($_SESSION['school']) && !empty($_SESSION['school']['reference_id']);
}

/**
 * Check if any staff/admin user is logged in
 */
function isStaffLoggedIn(): bool {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['username']);
}

/**
 * Check if current user is an admin
 */
function isAdmin(): bool {
    return isStaffLoggedIn() && (($_SESSION['user']['role'] ?? '') === 'admin');
}

/**
 * Check if current user is a coordinator/staff
 */
function isCoordinator(): bool {
    return isStaffLoggedIn() && (($_SESSION['user']['role'] ?? '') === 'coordinator');
}

/**
 * Get current logged in school data
 */
function currentSchool(): ?array {
    return $_SESSION['school'] ?? null;
}

/**
 * Get current logged in user data
 */
function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * Protect school portal pages
 */
function requireSchoolLogin(): void {
    if (!isSchoolLoggedIn()) {
        header('Location: ../school_login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

/**
 * Protect coordinator/staff portal pages
 */
function requireStaffLogin(): void {
    if (!isStaffLoggedIn()) {
        header('Location: ../staff_login.php?type=coordinator&redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

/**
 * Protect admin-only features
 */
function requireAdmin(): void {
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
 * Check if current user has permission to manage schools, mandals and districts
 */
function canManageSchools(): bool {
    if (!isStaffLoggedIn()) {
        return false;
    }
    if (isAdmin()) {
        return true;
    }
    $user = currentUser();
    // Live lookup from database to ensure admin changes take effect immediately
    if (!empty($user['id'])) {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT can_manage_schools FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $can = (int)$stmt->fetchColumn();
            $_SESSION['user']['can_manage_schools'] = $can;
            return $can === 1;
        } catch (Exception $e) {
            return !empty($user['can_manage_schools']);
        }
    }
    return !empty($user['can_manage_schools']);
}

/**
 * Protect pages that require school/district/mandal directory management access
 */
function requireSchoolMasterAccess(): void {
    if (!isStaffLoggedIn()) {
        header('Location: ../staff_login.php?type=coordinator&redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
    if (!canManageSchools()) {
        header('Location: ../staff/dashboard.php?notice=' . urlencode('Access Restricted: You do not have permission to manage schools, mandals and districts. Please contact the administrator.'));
        exit;
    }
}

function getLoggedInSchool(): ?array { return currentSchool(); }
function getLoggedInUser(): ?array { return currentUser(); }

