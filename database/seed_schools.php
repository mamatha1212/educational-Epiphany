<?php
/**
 * EEMT 2027 - Database Seeder
 * Populates schools_master from mandals.json and creates default Admin/Coordinator accounts.
 */
require_once dirname(__DIR__) . '/config/database.php';

echo "Initializing EEMT 2027 Database...\n";
$pdo = getDB();

// 1. Create Tables
echo "Creating tables...\n";
if (DB_DRIVER === 'sqlite') {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS schools_master (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            district TEXT NOT NULL,
            mandal TEXT NOT NULL,
            school_name TEXT NOT NULL,
            unique_code TEXT NOT NULL,
            is_registered INTEGER DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE INDEX IF NOT EXISTS idx_sm_district ON schools_master (district);
        CREATE INDEX IF NOT EXISTS idx_sm_mandal ON schools_master (mandal);
        CREATE INDEX IF NOT EXISTS idx_sm_code ON schools_master (unique_code);

        CREATE TABLE IF NOT EXISTS school_registrations_2027 (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            reference_id TEXT NOT NULL UNIQUE,
            school_id INTEGER NULL,
            district TEXT NOT NULL,
            mandal TEXT NOT NULL,
            school_name TEXT NOT NULL,
            unique_code TEXT NOT NULL,
            contact_person TEXT NOT NULL,
            designation TEXT NOT NULL,
            mobile TEXT NOT NULL,
            email TEXT NOT NULL,
            ip_address TEXT NULL,
            status TEXT DEFAULT 'submitted',
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE INDEX IF NOT EXISTS idx_sr_ref ON school_registrations_2027 (reference_id);
        CREATE INDEX IF NOT EXISTS idx_sr_dist ON school_registrations_2027 (district);
        CREATE INDEX IF NOT EXISTS idx_sr_code ON school_registrations_2027 (unique_code);

        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            name TEXT NOT NULL,
            role TEXT NOT NULL,
            assigned_district TEXT NULL,
            email TEXT NULL,
            mobile TEXT NULL,
            is_active INTEGER DEFAULT 1,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS question_papers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            class TEXT NOT NULL,
            subject TEXT DEFAULT 'General',
            file_path TEXT NOT NULL,
            file_size INTEGER DEFAULT 0,
            is_published INTEGER DEFAULT 0,
            published_at TEXT NULL,
            uploaded_by TEXT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        );
    ");
}

// 2. Seed Default Admin & Coordinator Users
echo "Checking administrative accounts...\n";
$userStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");

$userStmt->execute(['admin']);
if ($userStmt->fetchColumn() == 0) {
    $insertUser = $pdo->prepare("INSERT INTO users (username, password_hash, name, role, email, mobile) VALUES (?, ?, ?, ?, ?, ?)");
    $insertUser->execute([
        'admin',
        password_hash('admin123', PASSWORD_BCRYPT),
        'State Administrator',
        'admin',
        'admin@educationalepiphany.org',
        '9876543210'
    ]);
    echo "  Created default Admin account (Username: admin, Password: admin123)\n";
}

$userStmt->execute(['coordinator']);
if ($userStmt->fetchColumn() == 0) {
    $insertUser = $pdo->prepare("INSERT INTO users (username, password_hash, name, role, assigned_district, email, mobile) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $insertUser->execute([
        'coordinator',
        password_hash('coord123', PASSWORD_BCRYPT),
        'Staff Coordinator',
        'coordinator',
        'ALL',
        'coordinator@educationalepiphany.org',
        '9876543211'
    ]);
    echo "  Created default Coordinator account (Username: coordinator, Password: coord123)\n";
}

// 3. Import AP Schools from mandals.json
$jsonPath = dirname(__DIR__, 2) . '/eemt2027/eemt2027/mandals.json';
if (!file_exists($jsonPath)) {
    // fallback to current directory check
    $jsonPath = __DIR__ . '/mandals.json';
}

if (file_exists($jsonPath)) {
    echo "Reading AP Schools data from $jsonPath...\n";
    $jsonData = json_decode(file_get_contents($jsonPath), true);

    $countSchools = (int)$pdo->query("SELECT COUNT(*) FROM schools_master")->fetchColumn();
    if ($countSchools == 0 && is_array($jsonData)) {
        echo "Importing schools into schools_master...\n";
        $pdo->beginTransaction();
        
        $insertSchool = $pdo->prepare("INSERT INTO schools_master (district, mandal, school_name, unique_code) VALUES (?, ?, ?, ?)");

        $schoolIndex = 10001;
        $totalImported = 0;

        foreach ($jsonData as $district => $mandals) {
            if (!is_array($mandals)) continue;
            // Get district code prefix
            $distCode = substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($district)), 0, 4);

            foreach ($mandals as $mandal => $schools) {
                if (!is_array($schools)) continue;
                foreach ($schools as $schoolName) {
                    $schoolName = trim($schoolName);
                    if ($schoolName === '') continue;

                    // Generate a standard school unique code (e.g., AP-ANAK-10001)
                    $uniqueCode = 'AP-' . $distCode . '-' . $schoolIndex;
                    $insertSchool->execute([$district, $mandal, $schoolName, $uniqueCode]);
                    $schoolIndex++;
                    $totalImported++;
                }
            }
        }
        $pdo->commit();
        echo "Successfully imported $totalImported schools into schools_master!\n";
    } else {
        echo "schools_master already contains $countSchools schools.\n";
    }
} else {
    echo "Notice: mandals.json not found at $jsonPath. Skipping school list seed.\n";
}

// 4. Seed sample question paper if empty
$qpCount = (int)$pdo->query("SELECT COUNT(*) FROM question_papers")->fetchColumn();
if ($qpCount == 0) {
    $insertQP = $pdo->prepare("INSERT INTO question_papers (title, class, subject, file_path, is_published, published_at, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $insertQP->execute([
        'EEMT 2027 Prelims - Class 10 Model Paper',
        '10th',
        'Mathematics & Science',
        'sample_paper_10th.pdf',
        1,
        date('Y-m-d H:i:s'),
        'admin'
    ]);
    $insertQP->execute([
        'EEMT 2027 Prelims - Class 7 Model Paper',
        '7th',
        'General Knowledge & Mental Ability',
        'sample_paper_7th.pdf',
        1,
        date('Y-m-d H:i:s'),
        'admin'
    ]);
    echo "Created sample model question papers.\n";
}

echo "Database initialization completed successfully!\n";
