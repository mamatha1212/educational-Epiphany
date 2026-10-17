<?php
/**
 * EEMT 2027 - Coordinator Schools Directory
 * Seamlessly routes to the unified Coordinator Dashboard schools tab.
 */
require_once __DIR__ . '/../includes/auth.php';
requireStaffLogin();

$qs = !empty($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : '';
header('Location: dashboard.php?tab=schools' . $qs);
exit;
