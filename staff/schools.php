<?php
/**
 * EEMT 2027 - Coordinator Schools Directory Alias
 */
require_once __DIR__ . '/../includes/auth.php';
requireStaffLogin();

$qs = !empty($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : '';
header('Location: dashboard.php?tab=schools' . $qs);
exit;
