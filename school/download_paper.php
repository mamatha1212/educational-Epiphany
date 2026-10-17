<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/auth.php';

// Allow authenticated schools as well as staff/coordinators/admins
if (!isSchoolLoggedIn() && !isStaffLoggedIn()) {
    header('Location: ../school_login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Invalid question paper requested.");
}

$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM question_papers WHERE id = ? AND is_published = 1");
$stmt->execute([$id]);
$paper = $stmt->fetch();

if (!$paper) {
    die("Question paper not found or not yet published.");
}

$filePath = dirname(__DIR__) . '/uploads/question_papers/' . basename($paper['file_path']);
if (!file_exists($filePath)) {
    $altPath = dirname(__DIR__) . '/' . ltrim($paper['file_path'], '/');
    if (file_exists($altPath)) {
        $filePath = $altPath;
    }
}

if (!file_exists($filePath)) {
    die("File not found on server storage: " . htmlspecialchars($paper['file_path']));
}

$downloadName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $paper['title']) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($filePath);
exit;
