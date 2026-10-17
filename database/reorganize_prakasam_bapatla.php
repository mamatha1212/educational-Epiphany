<?php
require_once __DIR__ . '/../config/database.php';
$pdo = getDB();

echo "Starting Safe Data Duplication & Reorganization...\n";

// 1. Create a physical backup of the sqlite file
$origFile = SQLITE_PATH;
$bakFile = dirname($origFile) . '/eemt2027_original_backup_' . date('Ymd_His') . '.sqlite';
if (file_exists($origFile)) {
    copy($origFile, $bakFile);
    echo "1. Created physical backup copy at: $bakFile\n";
}

// 2. Create snapshot backup table inside the database
$pdo->exec("DROP TABLE IF EXISTS schools_master_backup_original;");
$pdo->exec("CREATE TABLE schools_master_backup_original AS SELECT * FROM schools_master;");
$bakCount = (int)$pdo->query("SELECT COUNT(*) FROM schools_master_backup_original")->fetchColumn();
echo "2. Preserved exact original snapshot table `schools_master_backup_original` with $bakCount records.\n";

// 3. Duplicate Ballikurava and Santhamaguluru into Bapatla-2828
$bapatlaDistrict = 'BAPATLA-2828';
$bapatlaMandalsToCopy = ['BALLIKURAVA-281817', 'SANTHAMAGULURU-281818'];

foreach ($bapatlaMandalsToCopy as $mandalName) {
    // Check if already in Bapatla
    $chk = $pdo->prepare("SELECT COUNT(*) FROM schools_master WHERE district = ? AND mandal = ?");
    $chk->execute([$bapatlaDistrict, $mandalName]);
    if ($chk->fetchColumn() == 0) {
        $copyStmt = $pdo->prepare("
            INSERT INTO schools_master (district, mandal, school_name, unique_code, is_registered, created_at)
            SELECT ?, mandal, school_name, unique_code, is_registered, CURRENT_TIMESTAMP
            FROM schools_master 
            WHERE district = 'PRAKASAM-2818' AND mandal = ?
        ");
        $copyStmt->execute([$bapatlaDistrict, $mandalName]);
        $copied = $copyStmt->rowCount();
        echo "3. Duplicated $copied schools from $mandalName into $bapatlaDistrict.\n";
    }
}

// 4. Duplicate Addanki, J.Panguluru, Korisapadu into Prakasam-2818 (from Bapatla-2828)
$prakasamDistrict = 'PRAKASAM-2818';
$prakasamMandalsToCopy = ['ADDANKI-281816', 'J.PANGULURU-281826', 'KORISAPADU-281827'];

foreach ($prakasamMandalsToCopy as $mandalName) {
    $chk = $pdo->prepare("SELECT COUNT(*) FROM schools_master WHERE district = ? AND mandal = ?");
    $chk->execute([$prakasamDistrict, $mandalName]);
    if ($chk->fetchColumn() == 0) {
        $copyStmt = $pdo->prepare("
            INSERT INTO schools_master (district, mandal, school_name, unique_code, is_registered, created_at)
            SELECT ?, mandal, school_name, unique_code, is_registered, CURRENT_TIMESTAMP
            FROM schools_master 
            WHERE district = 'BAPATLA-2828' AND mandal = ?
        ");
        $copyStmt->execute([$prakasamDistrict, $mandalName]);
        $copied = $copyStmt->rowCount();
        echo "4. Duplicated $copied schools from $mandalName into $prakasamDistrict.\n";
    }
}

// 5. Duplicate Ulavapadu into Prakasam-2818 (from Nellore-2819)
$chkUlavapadu = $pdo->prepare("SELECT COUNT(*) FROM schools_master WHERE district = ? AND mandal = 'ULAVAPADU-281855'");
$chkUlavapadu->execute([$prakasamDistrict]);
if ($chkUlavapadu->fetchColumn() == 0) {
    $copyStmt = $pdo->prepare("
        INSERT INTO schools_master (district, mandal, school_name, unique_code, is_registered, created_at)
        SELECT ?, mandal, school_name, unique_code, is_registered, CURRENT_TIMESTAMP
        FROM schools_master 
        WHERE district = 'NELLORE-2819' AND mandal = 'ULAVAPADU-281855'
    ");
    $copyStmt->execute([$prakasamDistrict]);
    $copied = $copyStmt->rowCount();
    echo "5. Duplicated $copied schools from ULAVAPADU-281855 into $prakasamDistrict.\n";
}

// 6. Provide Ongole Urban & Ongole Rural in Prakasam
$chkUrban = $pdo->prepare("SELECT COUNT(*) FROM schools_master WHERE district = ? AND mandal = 'ONGOLE URBAN-281844'");
$chkUrban->execute([$prakasamDistrict]);
if ($chkUrban->fetchColumn() == 0) {
    $copyUrban = $pdo->prepare("
        INSERT INTO schools_master (district, mandal, school_name, unique_code, is_registered, created_at)
        SELECT ?, 'ONGOLE URBAN-281844', school_name, unique_code, is_registered, CURRENT_TIMESTAMP
        FROM schools_master 
        WHERE district = 'PRAKASAM-2818' AND mandal = 'ONGOLE-281844'
    ");
    $copyUrban->execute([$prakasamDistrict]);
    echo "6. Created ONGOLE URBAN-281844 with " . $copyUrban->rowCount() . " schools in $prakasamDistrict.\n";
}

$chkRural = $pdo->prepare("SELECT COUNT(*) FROM schools_master WHERE district = ? AND mandal = 'ONGOLE RURAL-281844'");
$chkRural->execute([$prakasamDistrict]);
if ($chkRural->fetchColumn() == 0) {
    $copyRural = $pdo->prepare("
        INSERT INTO schools_master (district, mandal, school_name, unique_code, is_registered, created_at)
        SELECT ?, 'ONGOLE RURAL-281844', school_name, unique_code, is_registered, CURRENT_TIMESTAMP
        FROM schools_master 
        WHERE district = 'PRAKASAM-2818' AND mandal = 'ONGOLE-281844'
    ");
    $copyRural->execute([$prakasamDistrict]);
    echo "7. Created ONGOLE RURAL-281844 with " . $copyRural->rowCount() . " schools in $prakasamDistrict.\n";
}

echo "Duplicate reorganization completed successfully!\n";
