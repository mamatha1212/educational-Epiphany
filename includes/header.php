<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/auth.php';

// Calculate relative path to root
$inSubdir = in_array(basename(dirname($_SERVER['PHP_SELF'])), ['school', 'admin', 'staff']);
$rootPath = $inSubdir ? '../' : './';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? 'EEMT 2027 - Educational Epiphany Merit Test'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $rootPath; ?>assets/css/app.css">
</head>

<body>

    <nav class="navbar" id="mainNavbar">
        <div class="navbar-container">
            <a href="https://educationalepiphany.org/" class="brand-link" title="EEMT 2027 Home">
                <img src="<?php echo $rootPath; ?>assets/images/logo.png" alt="EEMT Logo" class="brand-logo"
                    onerror="this.src='<?php echo $rootPath; ?>assets/images/logo.jpeg';">
            </a>

            <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open navigation menu"
                onclick="openMobileNav()">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <div class="nav-links" id="navLinksMenu">
                <!-- Full-Screen Mobile Header with Close Option -->
                <div class="mobile-menu-header">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <img src="<?php echo $rootPath; ?>assets/images/logo.png" alt="EEMT Logo"
                            style="height: 48px; width: auto;"
                            onerror="this.src='<?php echo $rootPath; ?>assets/images/logo.jpeg';">
                        <span style="font-weight: 800; font-size: 1.15rem; color: #31328f;">EEMT 2027</span>
                    </div>
                    <button type="button" class="mobile-close-btn" id="mobileCloseBtn"
                        aria-label="Close navigation menu" onclick="closeMobileNav()">
                        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <a href="https://educationalepiphany.org" class="nav-link" target="_blank"
                    onclick="closeMobileNav()">Home</a>
                <a href="<?php echo $rootPath; ?>register.php" class="nav-link" onclick="closeMobileNav()">School
                    Registration</a>
                <a href="<?php echo $rootPath; ?>prelims_qp.php" class="nav-link nav-link-highlight"
                    onclick="closeMobileNav()">📄 Prelims QP</a>
                <a href="<?php echo $rootPath; ?>forgot_password.php" class="nav-link"
                    onclick="closeMobileNav()">Find your Login credentials</a>

                <?php if (isSchoolLoggedIn()): ?>
                    <a href="<?php echo $rootPath; ?>school/dashboard.php" class="nav-link"
                        style="font-weight: 700; color: #31328f;" onclick="closeMobileNav()">🏫 School Dashboard</a>
                    <a href="<?php echo $rootPath; ?>logout.php" class="nav-link" style="color: #ef3b2d;"
                        onclick="closeMobileNav()">Logout</a>
                <?php elseif (isAdmin()): ?>
                    <a href="<?php echo $rootPath; ?>admin/dashboard.php" class="nav-link"
                        style="font-weight: 700; color: #31328f;" onclick="closeMobileNav()">🛡️ Admin Dashboard</a>
                    <a href="<?php echo $rootPath; ?>staff/dashboard.php" class="nav-link"
                        style="font-weight: 600; color: #059669;" onclick="closeMobileNav()">👔 Staff View</a>
                    <a href="<?php echo $rootPath; ?>logout.php" class="nav-link" style="color: #ef3b2d;"
                        onclick="closeMobileNav()">Logout</a>
                <?php elseif (isStaffLoggedIn()): ?>
                    <a href="<?php echo $rootPath; ?>staff/dashboard.php" class="nav-link"
                        style="font-weight: 700; color: #059669;" onclick="closeMobileNav()">👔 Staff Dashboard</a>
                    <a href="<?php echo $rootPath; ?>logout.php" class="nav-link" style="color: #ef3b2d;"
                        onclick="closeMobileNav()">Logout</a>
                <?php else: ?>
                    <a href="<?php echo $rootPath; ?>school_login.php" class="nav-btn" onclick="closeMobileNav()">School
                        Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <script>
        function openMobileNav() {
            var nb = document.getElementById('mainNavbar');
            if (nb) nb.classList.add('nav-open');
            document.body.style.overflow = 'hidden';
        }
        function closeMobileNav() {
            var nb = document.getElementById('mainNavbar');
            if (nb) nb.classList.remove('nav-open');
            document.body.style.overflow = '';
        }
    </script>

    <main class="main-content">