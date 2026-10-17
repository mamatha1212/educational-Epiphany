<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['school'])) {
    unset($_SESSION['school']);
}
header("Location: ../school_login.php");
exit;
