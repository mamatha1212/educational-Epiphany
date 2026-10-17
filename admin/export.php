<?php
/**
 * EEMT 2027 - Export Registrations to CSV
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireStaffLogin();

$pdo = getDB();

$districtFilter = trim($_GET['district'] ?? '');
$mandalFilter = trim($_GET['mandal'] ?? '');
$search = trim($_GET['search'] ?? '');

$where = [];
$params = [];

if ($districtFilter !== '') {
    $where[] = "sr.district = :district";
    $params[':district'] = $districtFilter;
}

if ($mandalFilter !== '') {
    $where[] = "sr.mandal = :mandal";
    $params[':mandal'] = $mandalFilter;
}

if ($search !== '') {
    $where[] = "(sr.reference_id LIKE :s OR sr.school_name LIKE :s OR sr.unique_code LIKE :s OR sr.contact_person LIKE :s OR sr.mobile LIKE :s OR sr.email LIKE :s)";
    $params[':s'] = "%$search%";
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$stmt = $pdo->prepare("SELECT sr.* FROM school_registrations_2027 sr {$whereSql} ORDER BY sr.id DESC");
$stmt->execute($params);
$registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Output CSV headers
$filename = "EEMT_2027_Registered_Schools_" . date('Y-m-d_His') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Open output stream
$output = fopen('php://output', 'w');

// UTF-8 BOM for Microsoft Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header Row
fputcsv($output, [
    'S.No',
    'Reference ID',
    'School Name',
    'District',
    'Mandal',
    'School Unique Code',
    'In-Charge Name',
    'Designation',
    'Mobile Number',
    'Email Address',
    'Registration Date',
    'IP Address'
]);

$sno = 1;
foreach ($registrations as $r) {
    fputcsv($output, [
        $sno++,
        $r['reference_id'],
        $r['school_name'],
        $r['district'],
        $r['mandal'],
        $r['unique_code'],
        $r['contact_person'],
        $r['designation'],
        $r['mobile'],
        $r['email'],
        $r['created_at'],
        $r['ip_address'] ?? ''
    ]);
}

fclose($output);
exit;
