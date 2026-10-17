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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $rootPath; ?>assets/css/app.css">
</head>
<body>

<nav class="navbar" id="mainNavbar">
    <div class="navbar-container">
        <a href="<?php echo $rootPath; ?>index.php" class="brand-link">
            <img src="<?php echo $rootPath; ?>assets/images/logo.png" alt="EEMT Logo" class="brand-logo" onerror="this.src='<?php echo $rootPath; ?>assets/images/logo.jpeg';">
            <div class="brand-text">
                <h1>EEMT 2027</h1>
                <span>Government Schools Prelims Portal</span>
            </div>
        </a>

        <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle navigation" onclick="document.getElementById('mainNavbar').classList.toggle('nav-open')">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>

        <div class="nav-links">
            <a href="https://educationalepiphany.org" class="nav-link" target="_blank">Home</a>
            <a href="<?php echo $rootPath; ?>register.php" class="nav-link">School Registration</a>
            <a href="<?php echo $rootPath; ?>prelims_qp.php" class="nav-link" style="font-weight: 700; color: #2563eb;">📄 Prelims QP</a>

            <?php if (isSchoolLoggedIn()): ?>
                <a href="<?php echo $rootPath; ?>school/dashboard.php" class="nav-link" style="font-weight: 700; color: #1e40af;">🏫 School Dashboard</a>
                <a href="<?php echo $rootPath; ?>logout.php" class="nav-link" style="color: #ef4444;">Logout</a>
            <?php elseif (isAdmin()): ?>
                <a href="<?php echo $rootPath; ?>admin/dashboard.php" class="nav-link" style="font-weight: 700; color: #2563eb;">🛡️ Admin Dashboard</a>
                <a href="<?php echo $rootPath; ?>staff/dashboard.php" class="nav-link" style="font-weight: 600; color: #059669;">👔 Staff View</a>
                <a href="<?php echo $rootPath; ?>logout.php" class="nav-link" style="color: #ef4444;">Logout</a>
            <?php elseif (isStaffLoggedIn()): ?>
                <a href="<?php echo $rootPath; ?>staff/dashboard.php" class="nav-link" style="font-weight: 700; color: #059669;">👔 Staff Dashboard</a>
                <a href="<?php echo $rootPath; ?>logout.php" class="nav-link" style="color: #ef4444;">Logout</a>
            <?php else: ?>
                <a href="<?php echo $rootPath; ?>school_login.php" class="nav-btn">School Login</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<main class="main-content">
