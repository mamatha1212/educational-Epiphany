<?php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config/database.php';

$code     = strtoupper(trim($_POST['code'] ?? ''));
$district = trim($_POST['district'] ?? '');
$mandal   = trim($_POST['mandal'] ?? '');
$school   = trim($_POST['school'] ?? '');

if ($code === '') {
    echo json_encode(['valid' => false, 'message' => 'Please enter the School Unique Code or 11-digit UDISE Code.']);
    exit;
}

if (!preg_match('/^[0-9]{11}$/', $code)) {
    echo json_encode(['valid' => false, 'message' => 'School UDISE Code must be exactly 11 numeric digits (no alphabets or symbols).']);
    exit;
}

try {
    $pdo = getDB();
    $row = null;
    
    if ($school !== '') {
        // 1. Look up the selected school in registry
        $stmtSchool = $pdo->prepare("SELECT * FROM schools_master WHERE district = ? AND mandal = ? AND school_name = ?");
        $stmtSchool->execute([$district, $mandal, $school]);
        $row = $stmtSchool->fetch();

        if (!$row) {
            echo json_encode(['valid' => false, 'message' => 'Selected school not found in state registry.']);
            exit;
        }

        // 2. Strictly check if the entered code matches the school's unique_code
        if (strtoupper(trim($row['unique_code'])) !== $code) {
            echo json_encode([
                'valid' => false, 
                'message' => 'Invalid School Code. The code entered does not match this school.'
            ]);
            exit;
        }
    } else {
        // Look up by code directly
        $stmt = $pdo->prepare("SELECT * FROM schools_master WHERE unique_code = ?");
        $stmt->execute([$code]);
        $row = $stmt->fetch();

        if (!$row) {
            echo json_encode(['valid' => false, 'message' => 'School Code not found in school registry.']);
            exit;
        }
    }

    // 3. Check if already registered in 2027
    $checkReg = $pdo->prepare("SELECT reference_id, contact_person, mobile, created_at FROM school_registrations_2027 WHERE unique_code = ? OR school_id = ?");
    $checkReg->execute([$code, $row['id']]);
    $existing = $checkReg->fetch();

    if ($existing) {
        echo json_encode([
            'valid' => true,
            'already_registered' => true,
            'reference_id' => $existing['reference_id'],
            'contact_person' => $existing['contact_person'],
            'created_at' => $existing['created_at'],
            'school_name' => $row['school_name'],
            'district' => $row['district'],
            'mandal' => $row['mandal'],
            'message' => 'This school has already completed EEMT 2027 registration with Reference ID: ' . $existing['reference_id']
        ]);
        exit;
    }

    echo json_encode([
        'valid' => true,
        'already_registered' => false,
        'school_name' => $row['school_name'],
        'district' => $row['district'],
        'mandal' => $row['mandal'],
        'unique_code' => $row['unique_code'],
        'message' => 'School code (' . $row['unique_code'] . ') verified successfully!'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['valid' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
