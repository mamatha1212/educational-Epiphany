<?php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config/database.php';

$district = trim($_GET['district'] ?? '');
if ($district === '') {
    echo json_encode([]);
    exit;
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT DISTINCT mandal FROM schools_master WHERE district = ? ORDER BY mandal ASC");
    $stmt->execute([$district]);
    $mandals = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($mandals);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
