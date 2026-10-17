<?php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config/database.php';

$district = trim($_GET['district'] ?? '');
$mandal   = trim($_GET['mandal'] ?? '');

if ($district === '' || $mandal === '') {
    echo json_encode([]);
    exit;
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT id, school_name, is_registered FROM schools_master WHERE district = ? AND mandal = ? ORDER BY school_name ASC");
    $stmt->execute([$district, $mandal]);
    $schools = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($schools);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
