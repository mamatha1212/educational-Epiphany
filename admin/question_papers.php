<?php
/**
 * EEMT 2027 - Admin Question Paper Management
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_logger.php';

requireQuestionPapersAccess();

// If coordinator visits admin/question_papers.php, route to coordinator dashboard prelims tab
if (!isAdmin()) {
    header('Location: ../staff/dashboard.php?tab=prelims');
    exit;
}

$pdo = getDB();
$message = '';
$messageType = '';

// Handle Actions (Toggle, Delete, Upload)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_publish') {
        $paperId = (int)($_POST['paper_id'] ?? 0);
        $currentStatus = (int)($_POST['current_status'] ?? 0);
        $newStatus = $currentStatus === 1 ? 0 : 1;
        $publishedAt = $newStatus === 1 ? date('Y-m-d H:i:s') : null;

        $pInfoStmt = $pdo->prepare("SELECT title, class, subject FROM question_papers WHERE id = ?");
        $pInfoStmt->execute([$paperId]);
        $pInfo = $pInfoStmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("UPDATE question_papers SET is_published = :status, published_at = :pub_at WHERE id = :id");
        $stmt->execute([':status' => $newStatus, ':pub_at' => $publishedAt, ':id' => $paperId]);

        logCoordinatorActivity(
            'PAPER_STATUS',
            ($newStatus === 1 ? "Published" : "Unpublished") . " Question Paper: '" . ($pInfo['title'] ?? "#$paperId") . "'",
            "Target Class: Grade " . ($pInfo['class'] ?? 'N/A') . ", Subject: " . ($pInfo['subject'] ?? 'General')
        );

        $message = "Question paper status updated successfully.";
        $messageType = 'success';
    } elseif ($action === 'delete') {
        $paperId = (int)($_POST['paper_id'] ?? 0);
        
        $stmt = $pdo->prepare("SELECT title, class, subject, file_path FROM question_papers WHERE id = :id");
        $stmt->execute([':id' => $paperId]);
        $paperRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($paperRow) {
            $fullPath = __DIR__ . '/../' . ltrim($paperRow['file_path'], '/');
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
            $delStmt = $pdo->prepare("DELETE FROM question_papers WHERE id = :id");
            $delStmt->execute([':id' => $paperId]);

            logCoordinatorActivity(
                'PAPER_DELETE',
                "Deleted Question Paper: '{$paperRow['title']}'",
                "Class: Grade {$paperRow['class']}, Subject: {$paperRow['subject']}"
            );

            $message = "Question paper deleted successfully.";
            $messageType = 'success';
        }
    } elseif ($action === 'edit') {
        $paperId = (int)($_POST['paper_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $classGrade = trim($_POST['class'] ?? '');
        $subject = trim($_POST['subject'] ?? 'General');
        $isPublished = isset($_POST['is_published']) ? (int)$_POST['is_published'] : 0;

        if ($paperId <= 0 || empty($title) || empty($classGrade) || empty($subject)) {
            $message = "Please fill in all required question paper fields.";
            $messageType = 'error';
        } else {
            $newFilePath = null;
            $newFileSize = null;

            if (isset($_FILES['paper_file']) && $_FILES['paper_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['paper_file'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($ext !== 'pdf') {
                    $message = "Replacement file must be a PDF document.";
                    $messageType = 'error';
                } else {
                    $uploadDir = __DIR__ . '/../uploads/question_papers/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
                    $newFileName = 'paper_' . time() . '_' . $safeName . '.pdf';
                    $destination = $uploadDir . $newFileName;
                    if (move_uploaded_file($file['tmp_name'], $destination)) {
                        $newFilePath = 'uploads/question_papers/' . $newFileName;
                        $newFileSize = (int)($file['size'] ?? 0);
                    }
                }
            }

            if ($messageType !== 'error') {
                if ($newFilePath) {
                    $oldStmt = $pdo->prepare("SELECT file_path FROM question_papers WHERE id = :id");
                    $oldStmt->execute([':id' => $paperId]);
                    $oldPath = $oldStmt->fetchColumn();
                    if ($oldPath) {
                        $fullOld = __DIR__ . '/../' . ltrim($oldPath, '/');
                        if (file_exists($fullOld)) @unlink($fullOld);
                    }

                    $updStmt = $pdo->prepare("
                        UPDATE question_papers 
                        SET title = :title, class = :class, subject = :subject, 
                            file_path = :file_path, file_size = :file_size,
                            is_published = :is_published,
                            published_at = CASE WHEN :is_published = 1 AND published_at IS NULL THEN CURRENT_TIMESTAMP ELSE published_at END
                        WHERE id = :id
                    ");
                    $updStmt->execute([
                        ':title' => $title,
                        ':class' => $classGrade,
                        ':subject' => $subject,
                        ':file_path' => $newFilePath,
                        ':file_size' => $newFileSize,
                        ':is_published' => $isPublished,
                        ':id' => $paperId
                    ]);
                } else {
                    $updStmt = $pdo->prepare("
                        UPDATE question_papers 
                        SET title = :title, class = :class, subject = :subject, 
                            is_published = :is_published,
                            published_at = CASE WHEN :is_published = 1 AND published_at IS NULL THEN CURRENT_TIMESTAMP ELSE published_at END
                        WHERE id = :id
                    ");
                    $updStmt->execute([
                        ':title' => $title,
                        ':class' => $classGrade,
                        ':subject' => $subject,
                        ':is_published' => $isPublished,
                        ':id' => $paperId
                    ]);
                }

                $message = "Question paper details updated successfully!";
                $messageType = 'success';
            }
        }
    } elseif ($action === 'upload') {
        $title = trim($_POST['title'] ?? '');
        $classGrade = trim($_POST['class'] ?? '');
        $subject = trim($_POST['subject'] ?? 'General');
        $isPublished = isset($_POST['is_published']) ? 1 : 0;
        $currentUser = currentUser();
        $uploaderName = $currentUser['name'] ?? $currentUser['username'] ?? 'Staff';

        if (empty($title) || empty($classGrade) || empty($subject)) {
            $message = "Please fill in all required paper details.";
            $messageType = 'error';
        } elseif (!isset($_FILES['paper_file']) || $_FILES['paper_file']['error'] !== UPLOAD_ERR_OK) {
            $message = "Please upload a valid PDF file.";
            $messageType = 'error';
        } else {
            $file = $_FILES['paper_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if ($ext !== 'pdf') {
                $message = "Only PDF documents are allowed for question papers.";
                $messageType = 'error';
            } else {
                $uploadDir = __DIR__ . '/../uploads/question_papers/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
                $newFileName = 'paper_' . time() . '_' . $safeName . '.pdf';
                $destination = $uploadDir . $newFileName;

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $relPath = 'uploads/question_papers/' . $newFileName;
                    $fileSizeBytes = (int)($file['size'] ?? 0);
                    $publishedAt = $isPublished ? date('Y-m-d H:i:s') : null;

                    $stmt = $pdo->prepare("
                        INSERT INTO question_papers (title, class, subject, file_path, file_size, is_published, published_at, uploaded_by)
                        VALUES (:title, :class, :subject, :file_path, :file_size, :is_published, :published_at, :uploaded_by)
                    ");
                    $stmt->execute([
                        ':title' => $title,
                        ':class' => $classGrade,
                        ':subject' => $subject,
                        ':file_path' => $relPath,
                        ':file_size' => $fileSizeBytes,
                        ':is_published' => $isPublished,
                        ':published_at' => $publishedAt,
                        ':uploaded_by' => $uploaderName
                    ]);

                    logCoordinatorActivity(
                        'PAPER_UPLOAD',
                        "Uploaded Question Paper: '{$title}'",
                        "Class: Grade {$classGrade}, Subject: {$subject}, File: {$file['name']} (" . round($fileSizeBytes / 1024) . " KB)"
                    );

                    $message = "Question paper uploaded and saved successfully!";
                    $messageType = 'success';
                } else {
                    $message = "Failed to save the uploaded file. Check directory permissions.";
                    $messageType = 'error';
                }
            }
        }
    }
}

// Fetch all papers
$stmt = $pdo->query("SELECT * FROM question_papers ORDER BY id DESC");
$papers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Metrics calculation
$totalPapers = count($papers);
$publishedCount = 0;
$draftCount = 0;
$classesCovered = [];
foreach ($papers as $p) {
    if ((int)$p['is_published'] === 1) {
        $publishedCount++;
    } else {
        $draftCount++;
    }
    if (!empty($p['class'])) {
        $classesCovered[$p['class']] = true;
    }
}
$totalClasses = count($classesCovered);

$pageTitle = "Question Papers Manager - EEMT 2027";
require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
.qp-input {
    width: 100%;
    height: 44px;
    padding: 0 14px;
    border: 1.5px solid #cbd5e1;
    border-radius: 10px;
    background: #f8fafc;
    font-size: 0.92rem;
    color: #1e293b;
    outline: none;
    transition: all 0.2s ease;
    box-sizing: border-box;
}
.qp-input:focus {
    background: #ffffff;
    border-color: #31328f;
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
}
.qp-select {
    width: 100%;
    height: 44px;
    padding: 0 40px 0 14px;
    border: 1.5px solid #cbd5e1;
    border-radius: 10px;
    background-color: #f8fafc;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
    background-size: 16px;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    font-size: 0.92rem;
    color: #1e293b;
    outline: none;
    transition: all 0.2s ease;
    box-sizing: border-box;
    cursor: pointer;
}
.qp-select:focus {
    background-color: #ffffff;
    border-color: #31328f;
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
}
.qp-label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
    font-weight: 700;
    color: #334155;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin-bottom: 0.4rem;
}
</style>

<div style="max-width: 1250px; margin: 0 auto; padding-bottom: 2rem;">
    <!-- Page Header -->
    <div style="margin-bottom: 1.75rem; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 1.25rem;">
        <div>
            <div style="display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; background: #ececf7; color: #31328f; border: 1px solid #c8c9eb; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.04em;">
                📚 Examination Materials Portal
            </div>
            <h1 style="font-size: 1.85rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.03em; line-height: 1.2;">
                Prelims Question Papers
            </h1>
            <p style="font-size: 0.9rem; color: #64748b; margin-top: 4px; margin-bottom: 0;">
                Manage official PDF question sets and model papers for registered schools statewide.
            </p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="dashboard.php" class="btn" style="height: 42px; padding: 0 1.25rem; font-weight: 600; font-size: 0.88rem; background: #ffffff; border: 1.5px solid #cbd5e1; color: #475569; border-radius: 10px; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; transition: all 0.2s ease;">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Admin Dashboard
            </a>
            <a href="#upload-card" class="btn btn-primary" style="height: 42px; padding: 0 1.35rem; font-weight: 700; font-size: 0.88rem; border-radius: 10px; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 14px rgba(37,99,235,0.25); text-decoration: none;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Upload New Paper
            </a>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?>" style="margin-bottom: 1.5rem; border-radius: 12px; display: flex; align-items: center; gap: 12px; padding: 1rem 1.35rem; font-size: 0.92rem; font-weight: 600; box-shadow: 0 2px 10px rgba(0,0,0,0.03);">
            <span style="font-size: 1.25rem;"><?php echo $messageType === 'success' ? '✅' : '⚠️'; ?></span>
            <span><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>

    <!-- KPI Metric Cards Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
        <!-- Card 1: Total Question Papers -->
        <div style="background: #ffffff; border-radius: 16px; padding: 1.35rem 1.5rem; border: 1px solid #e2e8f0; box-shadow: 0 2px 12px rgba(0,0,0,0.03); display: flex; align-items: center; justify-content: space-between; transition: transform 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
            <div>
                <span style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; display: block;">Total Papers</span>
                <div style="font-size: 2rem; font-weight: 800; color: #0f172a; line-height: 1.1; margin-top: 4px;">
                    <?php echo $totalPapers; ?>
                </div>
                <span style="font-size: 0.78rem; color: #31328f; font-weight: 600; margin-top: 4px; display: block;">In Repository</span>
            </div>
            <div style="width: 48px; height: 48px; border-radius: 14px; background: #ececf7; border: 1px solid #dbeafe; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; color: #31328f;">
                📄
            </div>
        </div>

        <!-- Card 2: Published / Live -->
        <div style="background: #ffffff; border-radius: 16px; padding: 1.35rem 1.5rem; border: 1px solid #e2e8f0; box-shadow: 0 2px 12px rgba(0,0,0,0.03); display: flex; align-items: center; justify-content: space-between; transition: transform 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
            <div>
                <span style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; display: block;">Published</span>
                <div style="font-size: 2rem; font-weight: 800; color: #065f46; line-height: 1.1; margin-top: 4px;">
                    <?php echo $publishedCount; ?>
                </div>
                <span style="font-size: 0.78rem; color: #10b981; font-weight: 700; margin-top: 4px; display: flex; align-items: center; gap: 4px;">
                    <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981; box-shadow: 0 0 6px #10b981;"></span> Live for Schools
                </span>
            </div>
            <div style="width: 48px; height: 48px; border-radius: 14px; background: #ecfdf5; border: 1px solid #a7f3d0; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; color: #059669;">
                🟢
            </div>
        </div>

        <!-- Card 3: Draft Mode -->
        <div style="background: #ffffff; border-radius: 16px; padding: 1.35rem 1.5rem; border: 1px solid #e2e8f0; box-shadow: 0 2px 12px rgba(0,0,0,0.03); display: flex; align-items: center; justify-content: space-between; transition: transform 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
            <div>
                <span style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; display: block;">Draft Mode</span>
                <div style="font-size: 2rem; font-weight: 800; color: #92400e; line-height: 1.1; margin-top: 4px;">
                    <?php echo $draftCount; ?>
                </div>
                <span style="font-size: 0.78rem; color: #d97706; font-weight: 600; margin-top: 4px; display: block;">Hidden from Schools</span>
            </div>
            <div style="width: 48px; height: 48px; border-radius: 14px; background: #fffbeb; border: 1px solid #fde68a; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; color: #d97706;">
                ⏳
            </div>
        </div>

        <!-- Card 4: Classes Covered -->
        <div style="background: #ffffff; border-radius: 16px; padding: 1.35rem 1.5rem; border: 1px solid #e2e8f0; box-shadow: 0 2px 12px rgba(0,0,0,0.03); display: flex; align-items: center; justify-content: space-between; transition: transform 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
            <div>
                <span style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; display: block;">Classes Covered</span>
                <div style="font-size: 2rem; font-weight: 800; color: #3730a3; line-height: 1.1; margin-top: 4px;">
                    <?php echo $totalClasses; ?>
                </div>
                <span style="font-size: 0.78rem; color: #4f46e5; font-weight: 600; margin-top: 4px; display: block;">Grade Standards</span>
            </div>
            <div style="width: 48px; height: 48px; border-radius: 14px; background: #e0e7ff; border: 1px solid #c7d2fe; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; color: #4338ca;">
                🎓
            </div>
        </div>
    </div>

    <!-- Uploaded Papers Table Card -->
    <div class="card" style="padding: 0; overflow: hidden; margin-bottom: 2.25rem; border: 1px solid #e2e8f0; border-radius: 18px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); background: #ffffff;">
        <!-- Top Bar with Search -->
        <div style="padding: 1.25rem 1.75rem; border-bottom: 1px solid #edf2f7; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; background: linear-gradient(to right, #f8fafc, #ffffff);">
            <div>
                <h3 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <span>📄 Question Paper Repository</span>
                    <span style="font-size: 0.75rem; font-weight: 700; background: #ececf7; color: #31328f; border: 1px solid #c8c9eb; padding: 2px 8px; border-radius: 20px;">
                        <?php echo count($papers); ?> Total
                    </span>
                </h3>
                <span style="font-size: 0.82rem; color: #64748b; margin-top: 2px; display: block;">Active PDF files accessible to authenticated schools</span>
            </div>

            <!-- Instant Search Bar & Add Shortcut -->
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="position: relative; width: 260px;">
                    <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.9rem; pointer-events: none;">🔍</span>
                    <input type="text" id="paperSearchInput" onkeyup="filterPaperTable()" placeholder="Filter by title, class, subject..." style="width: 100%; height: 38px; padding: 0 12px 0 34px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.85rem; color: #1e293b; background: #ffffff; outline: none; transition: all 0.2s ease;" onfocus="this.style.borderColor='#31328f'; this.style.boxShadow='0 0 0 3px rgba(37,99,235,0.1)';" onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                </div>
                <a href="#upload-card" class="btn btn-primary" style="height: 38px; padding: 0 1rem; font-weight: 700; font-size: 0.82rem; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 8px rgba(37,99,235,0.2); text-decoration: none; white-space: nowrap;">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    + Add Paper
                </a>
            </div>
        </div>

        <?php if (empty($papers)): ?>
            <div style="padding: 3.5rem 2rem; text-align: center; color: #64748b;">
                <div style="width: 64px; height: 64px; border-radius: 18px; background: #ececf7; border: 1px solid #c8c9eb; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; font-size: 2rem; color: #31328f;">
                    📄
                </div>
                <h4 style="font-weight: 800; color: #0f172a; margin-bottom: 0.4rem; font-size: 1.15rem;">No Question Papers Uploaded</h4>
                <p style="font-size: 0.9rem; margin: 0 auto; max-width: 420px; color: #64748b;">Use the form below to upload official EEMT 2027 prelims question papers in PDF format.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive" style="overflow-x: auto; width: 100%;">
                <table class="table" id="paperTable" style="width: 100%; min-width: 1000px; border-collapse: collapse; margin-bottom: 0;">
                    <thead style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                        <tr>
                            <th style="font-weight: 700; text-transform: uppercase; font-size: 0.74rem; letter-spacing: 0.5px; color: #475569; padding: 14px 18px; width: 70px;">ID</th>
                            <th style="font-weight: 700; text-transform: uppercase; font-size: 0.74rem; letter-spacing: 0.5px; color: #475569; padding: 14px 18px; min-width: 250px;">Paper Title</th>
                            <th style="font-weight: 700; text-transform: uppercase; font-size: 0.74rem; letter-spacing: 0.5px; color: #475569; padding: 14px 18px; width: 140px;">Class / Grade</th>
                            <th style="font-weight: 700; text-transform: uppercase; font-size: 0.74rem; letter-spacing: 0.5px; color: #475569; padding: 14px 18px; min-width: 180px;">Subject</th>
                            <th style="font-weight: 700; text-transform: uppercase; font-size: 0.74rem; letter-spacing: 0.5px; color: #475569; padding: 14px 18px; width: 130px;">Status</th>
                            <th style="font-weight: 700; text-transform: uppercase; font-size: 0.74rem; letter-spacing: 0.5px; color: #475569; padding: 14px 18px; width: 160px;">Uploaded On</th>
                            <th style="font-weight: 700; text-transform: uppercase; font-size: 0.74rem; letter-spacing: 0.5px; color: #475569; padding: 14px 18px; width: 270px; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="paperTableBody">
                        <?php foreach ($papers as $p): ?>
                            <tr class="paper-row" style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                                <td style="padding: 14px 18px; vertical-align: middle; font-weight: 700; color: #94a3b8; font-size: 0.85rem;">
                                    #<?php echo $p['id']; ?>
                                </td>
                                <td style="padding: 14px 18px; vertical-align: middle;">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div style="width: 40px; height: 40px; border-radius: 10px; background: #ececf7; border: 1px solid #c8c9eb; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; color: #31328f; box-shadow: 0 2px 6px rgba(37,99,235,0.1);">
                                            📄
                                        </div>
                                        <div>
                                            <strong style="color: #0f172a; font-size: 0.94rem; display: block; line-height: 1.25; font-weight: 700;">
                                                <?php echo htmlspecialchars($p['title']); ?>
                                            </strong>
                                            <span style="font-size: 0.78rem; color: #475569; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: 600; background: #f1f5f9; padding: 2px 7px; border-radius: 5px; display: inline-block; margin-top: 3px; border: 1px solid #e2e8f0;">
                                                <?php echo basename($p['file_path']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 14px 18px; vertical-align: middle;">
                                    <span style="background: #ececf7; color: #1d4ed8; font-weight: 700; border: 1px solid #c8c9eb; font-size: 0.78rem; padding: 4px 10px; border-radius: 20px; display: inline-flex; align-items: center; gap: 4px;">
                                        🎓 <?php echo htmlspecialchars($p['class']); ?>
                                    </span>
                                </td>
                                <td style="padding: 14px 18px; vertical-align: middle;">
                                    <span style="font-weight: 600; color: #1e293b; font-size: 0.88rem; display: inline-flex; align-items: center; gap: 6px;">
                                        📖 <?php echo htmlspecialchars($p['subject']); ?>
                                    </span>
                                </td>
                                <td style="padding: 14px 18px; vertical-align: middle;">
                                    <?php if ($p['is_published'] == 1): ?>
                                        <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 20px; font-size: 0.76rem; font-weight: 700; background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0;">
                                            <span style="width: 7px; height: 7px; border-radius: 50%; background: #10b981; box-shadow: 0 0 6px #10b981;"></span>
                                            Published
                                        </span>
                                    <?php else: ?>
                                        <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 20px; font-size: 0.76rem; font-weight: 700; background: #fffbeb; color: #b45309; border: 1px solid #fde68a;">
                                            <span style="width: 7px; height: 7px; border-radius: 50%; background: #f59e0b;"></span>
                                            Draft
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 14px 18px; vertical-align: middle; font-size: 0.85rem; color: #64748b; white-space: nowrap;">
                                    <?php echo date('d M Y, h:i A', strtotime($p['created_at'])); ?>
                                </td>
                                <td style="padding: 14px 18px; vertical-align: middle; text-align: right; white-space: nowrap;">
                                    <div style="display: flex; gap: 6px; justify-content: flex-end; align-items: center;">
                                        <!-- Toggle Publish / Hide -->
                                        <form method="POST" style="display: inline; margin: 0;">
                                            <input type="hidden" name="action" value="toggle_publish">
                                            <input type="hidden" name="paper_id" value="<?php echo $p['id']; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $p['is_published']; ?>">
                                            <?php if ($p['is_published'] == 1): ?>
                                                <button type="submit" class="btn" style="padding: 6px 11px; font-size: 0.78rem; font-weight: 700; border-radius: 8px; background: #ffffff; color: #b45309; border: 1.5px solid #fde68a; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; transition: all 0.15s ease;" onmouseover="this.style.background='#fffbeb';" onmouseout="this.style.background='#ffffff';" title="Hide paper from schools">
                                                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                                                    Hide
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" class="btn" style="padding: 6px 11px; font-size: 0.78rem; font-weight: 700; border-radius: 8px; background: #ffffff; color: #047857; border: 1.5px solid #a7f3d0; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; transition: all 0.15s ease;" onmouseover="this.style.background='#ecfdf5';" onmouseout="this.style.background='#ffffff';" title="Publish paper to schools">
                                                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                    Publish
                                                </button>
                                            <?php endif; ?>
                                        </form>

                                        <!-- Edit Paper Button -->
                                        <button type="button" class="btn" onclick="openEditPaperModal(<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8'); ?>)" style="padding: 6px 11px; font-size: 0.78rem; font-weight: 700; border-radius: 8px; background: #ffffff; color: #31328f; border: 1.5px solid #c8c9eb; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; transition: all 0.15s ease;" onmouseover="this.style.background='#ececf7'; this.style.borderColor='#31328f';" onmouseout="this.style.background='#ffffff'; this.style.borderColor='#c8c9eb';" title="Edit Question Paper Details">
                                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                            Edit
                                        </button>

                                        <!-- Preview PDF -->
                                        <a href="<?php echo htmlspecialchars('../' . ltrim($p['file_path'], '/')); ?>" target="_blank" class="btn" style="padding: 6px 11px; font-size: 0.78rem; font-weight: 700; border-radius: 8px; background: #ffffff; color: #475569; border: 1.5px solid #cbd5e1; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; transition: all 0.15s ease;" onmouseover="this.style.background='#f1f5f9';" onmouseout="this.style.background='#ffffff';" title="Preview PDF document">
                                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            Preview
                                        </a>

                                        <!-- Delete Paper -->
                                        <form method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('Permanently delete this question paper?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="paper_id" value="<?php echo $p['id']; ?>">
                                            <button type="submit" class="btn" style="padding: 6px 10px; font-size: 0.78rem; font-weight: 700; border-radius: 8px; background: #ffffff; color: #d92d20; border: 1.5px solid #fed7d5; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; transition: all 0.15s ease;" onmouseover="this.style.background='#fef2f1';" onmouseout="this.style.background='#ffffff';" title="Delete Paper">
                                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Upload Form Card -->
    <div class="card" id="upload-card" style="padding: 0; border-radius: 18px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); background: #ffffff; border: 1px solid #e2e8f0; overflow: hidden;">
        <div style="height: 4px; background: linear-gradient(90deg, #31328f, #6366f1, #8b5cf6);"></div>

        <div style="padding: 1.5rem 2rem 1.25rem 2rem; border-bottom: 1px solid #edf2f7; display: flex; align-items: center; gap: 14px; background: linear-gradient(to right, #f8fafc, #ffffff);">
            <div style="width: 44px; height: 44px; border-radius: 12px; background: #ececf7; border: 1px solid #c8c9eb; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; color: #31328f; flex-shrink: 0; box-shadow: 0 2px 8px rgba(37,99,235,0.12);">
                <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            </div>
            <div>
                <h3 style="font-size: 1.22rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">Upload New Prelims Question Paper</h3>
                <p style="font-size: 0.85rem; color: #64748b; margin-top: 2px; margin-bottom: 0;">
                    Upload official PDF question sets for registered schools to access directly from their dashboard.
                </p>
            </div>
        </div>

        <form method="POST" action="question_papers.php" enctype="multipart/form-data" style="padding: 1.75rem 2rem;">
            <input type="hidden" name="action" value="upload">

            <!-- Row 1: Paper Title -->
            <div style="margin-bottom: 1.25rem;">
                <label class="qp-label" for="title">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Paper Title <span style="color: #ef3b2d;">*</span>
                </label>
                <input type="text" id="title" name="title" class="qp-input" placeholder="e.g. EEMT 2027 Prelims - Class 10 State Model Paper & Answer Key" required>
            </div>

            <!-- Row 2: Target Class & Subject -->
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.25rem; margin-bottom: 1.25rem;">
                <div>
                    <label class="qp-label" for="class">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
                        Target Class <span style="color: #ef3b2d;">*</span>
                    </label>
                    <select id="class" name="class" class="qp-select" required>
                        <option value="">-- Select Class --</option>
                        <option value="Class 7">Class 7</option>
                        <option value="Class 10">Class 10</option>
                    </select>
                </div>

                <div>
                    <label class="qp-label" for="subject">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        Subject / Domain <span style="color: #ef3b2d;">*</span>
                    </label>
                    <input type="text" id="subject" name="subject" class="qp-input" placeholder="e.g. Mathematics, Science, Mental Ability" required>
                </div>
            </div>

            <!-- Row 3: PDF File Picker -->
            <div style="margin-bottom: 1.25rem;">
                <label class="qp-label" for="paper_file">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                    PDF Document File <span style="color: #ef3b2d;">*</span>
                </label>
                <div style="background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: 12px; padding: 1.25rem; text-align: center; transition: all 0.2s ease;">
                    <input type="file" id="paper_file" name="paper_file" accept=".pdf" required style="display: block; width: 100%; font-size: 0.9rem; color: #475569; cursor: pointer;">
                    <div style="font-size: 0.78rem; color: #64748b; margin-top: 6px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <span style="background: #e2e8f0; color: #1e293b; padding: 1px 6px; border-radius: 4px; font-weight: 700;">.PDF ONLY</span>
                        <span>Maximum file size allowed: 25 MB</span>
                    </div>
                </div>
            </div>

            <!-- Row 4: Publish Status Switch Box -->
            <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between;">
                <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; user-select: none;">
                    <input type="checkbox" name="is_published" value="1" checked style="width: 20px; height: 20px; accent-color: #31328f; cursor: pointer;">
                    <div>
                        <strong style="color: #0f172a; font-size: 0.94rem; display: block;">Publish immediately to school dashboards</strong>
                        <span style="font-size: 0.8rem; color: #64748b;">Schools will be able to view and download this question paper as soon as it is uploaded</span>
                    </div>
                </label>
            </div>

            <!-- Action Buttons Footer -->
            <div style="display: flex; justify-content: flex-end; align-items: center; gap: 12px; padding-top: 1.25rem; border-top: 1px solid #edf2f7;">
                <button type="reset" class="btn" style="height: 44px; padding: 0 1.5rem; background: #ffffff; color: #475569; border: 1.5px solid #cbd5e1; font-weight: 600; font-size: 0.92rem; border-radius: 10px; cursor: pointer; transition: all 0.15s ease;" onmouseover="this.style.background='#f1f5f9';" onmouseout="this.style.background='#ffffff';">
                    Clear Form
                </button>
                <button type="submit" class="btn btn-primary" style="height: 44px; padding: 0 1.85rem; font-weight: 700; font-size: 0.92rem; border-radius: 10px; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 14px rgba(37,99,235,0.3); background: linear-gradient(135deg, #31328f, #1d4ed8); border: none; cursor: pointer; transition: all 0.2s ease;" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 6px 18px rgba(37,99,235,0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 14px rgba(37,99,235,0.3)';">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Upload & Save Paper
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Question Paper Modal -->
<div id="editPaperModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.72); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 99999; align-items: center; justify-content: center; padding: 1.25rem;">
    <div style="background: #ffffff; border-radius: 20px; max-width: 680px; width: 100%; box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.35), 0 0 0 1px rgba(226, 232, 240, 0.8); overflow: hidden; animation: modalPop 0.22s cubic-bezier(0.16, 1, 0.3, 1); position: relative;">
        <!-- Top Accent Gradient Line -->
        <div style="height: 4px; background: linear-gradient(90deg, #31328f, #6366f1, #8b5cf6);"></div>

        <!-- Modal Header -->
        <div style="padding: 1.35rem 2rem; background: linear-gradient(to right, #f8fafc, #ffffff); border-bottom: 1px solid #edf2f7; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 14px;">
                <div style="width: 46px; height: 46px; border-radius: 12px; background: linear-gradient(135deg, #ececf7, #dbeafe); border: 1px solid #c8c9eb; display: flex; align-items: center; justify-content: center; color: #31328f; flex-shrink: 0; box-shadow: 0 2px 8px rgba(37,99,235,0.12);">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                </div>
                <div>
                    <h3 style="font-size: 1.22rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">Edit Question Paper</h3>
                    <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                        <span id="modalPaperSubtitle" style="display: inline-flex; align-items: center; gap: 4px; background: #e0e7ff; color: #3730a3; font-weight: 700; font-size: 0.76rem; padding: 2px 8px; border-radius: 6px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace;">#0</span>
                        <span style="font-size: 0.8rem; color: #64748b; font-weight: 500;">Update academic paper parameters</span>
                    </div>
                </div>
            </div>
            <button type="button" onclick="closeEditPaperModal()" style="width: 36px; height: 36px; border-radius: 10px; background: #f1f5f9; border: 1px solid #e2e8f0; color: #64748b; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.15s ease;" onmouseover="this.style.background='#e2e8f0'; this.style.color='#0f172a'" onmouseout="this.style.background='#f1f5f9'; this.style.color='#64748b'" title="Close">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Modal Form -->
        <form method="POST" action="question_papers.php" enctype="multipart/form-data" style="padding: 1.75rem 2rem 1.5rem 2rem;">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="paper_id" id="edit_paper_id" value="">

            <!-- Paper Title -->
            <div style="margin-bottom: 1.25rem;">
                <label class="qp-label" for="edit_title">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Paper Title <span style="color: #ef3b2d;">*</span>
                </label>
                <input type="text" id="edit_title" name="title" class="qp-input" required>
            </div>

            <!-- Target Class & Subject -->
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.25rem; margin-bottom: 1.25rem;">
                <div>
                    <label class="qp-label" for="edit_class">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
                        Target Class <span style="color: #ef3b2d;">*</span>
                    </label>
                    <select id="edit_class" name="class" class="qp-select" required>
                        <option value="">-- Select Class --</option>
                        <option value="Class 7">Class 7</option>
                        <option value="Class 10">Class 10</option>
                    </select>
                </div>

                <div>
                    <label class="qp-label" for="edit_subject">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        Subject / Domain <span style="color: #ef3b2d;">*</span>
                    </label>
                    <input type="text" id="edit_subject" name="subject" class="qp-input" required>
                </div>
            </div>

            <!-- Status & Replace File -->
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.25rem; margin-bottom: 1.25rem;">
                <div>
                    <label class="qp-label" for="edit_is_published">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        Publication Status <span style="color: #ef3b2d;">*</span>
                    </label>
                    <select id="edit_is_published" name="is_published" class="qp-select" required>
                        <option value="1">🟢 Published (Live for Schools)</option>
                        <option value="0">🟠 Draft (Hidden from Schools)</option>
                    </select>
                </div>

                <div>
                    <label class="qp-label" for="edit_paper_file">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                        Replace PDF Document <span style="font-size: 0.72rem; color: #64748b; font-weight: normal; text-transform: none;">(Optional)</span>
                    </label>
                    <input type="file" id="edit_paper_file" name="paper_file" accept=".pdf" class="qp-input" style="padding-top: 8px;">
                </div>
            </div>

            <!-- Current File Indicator -->
            <div style="background: #f1f5f9; border-radius: 8px; padding: 8px 12px; margin-bottom: 1.25rem; font-size: 0.8rem; color: #475569; display: flex; align-items: center; gap: 8px;">
                <span style="font-weight: 700; color: #1e293b;">Active File:</span>
                <span id="edit_current_filename" style="font-family: ui-monospace, SFMono-Regular, monospace; background: #ffffff; padding: 2px 6px; border-radius: 4px; border: 1px solid #cbd5e1;"></span>
                <span style="color: #64748b; font-size: 0.75rem;">(Leave file input empty to keep current file)</span>
            </div>

            <!-- Modal Action Buttons Footer -->
            <div style="display: flex; justify-content: flex-end; align-items: center; gap: 12px; padding-top: 1.25rem; border-top: 1px solid #edf2f7;">
                <button type="button" onclick="closeEditPaperModal()" style="height: 44px; padding: 0 1.4rem; background: #ffffff; color: #475569; border: 1.5px solid #cbd5e1; font-weight: 600; font-size: 0.92rem; border-radius: 10px; cursor: pointer; transition: all 0.15s ease;" onmouseover="this.style.background='#f1f5f9';" onmouseout="this.style.background='#ffffff';">
                    Cancel
                </button>
                <button type="submit" style="height: 44px; padding: 0 1.75rem; background: linear-gradient(135deg, #31328f, #1d4ed8); color: #ffffff; border: none; font-weight: 700; font-size: 0.92rem; border-radius: 10px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35); transition: all 0.2s ease;" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 6px 18px rgba(37,99,235,0.45)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 14px rgba(37,99,235,0.35)'">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function filterPaperTable() {
    const input = document.getElementById('paperSearchInput');
    const filter = input.value.toLowerCase().trim();
    const rows = document.querySelectorAll('#paperTableBody .paper-row');

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(filter)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function openEditPaperModal(paper) {
    document.getElementById('edit_paper_id').value = paper.id;
    document.getElementById('edit_title').value = paper.title || '';
    
    // Normalize target class so both '7th'/'Class 7' and '10th'/'Class 10' are selected
    let c = (paper.class || '').toString().trim();
    if (c === '7th' || c === 'Class 7' || c === '7') {
        document.getElementById('edit_class').value = 'Class 7';
    } else if (c === '10th' || c === 'Class 10' || c === '10') {
        document.getElementById('edit_class').value = 'Class 10';
    } else {
        document.getElementById('edit_class').value = c;
    }

    document.getElementById('edit_subject').value = paper.subject || '';
    document.getElementById('edit_is_published').value = paper.is_published;
    document.getElementById('edit_paper_file').value = '';
    
    const fileName = paper.file_path ? paper.file_path.split('/').pop() : 'None';
    document.getElementById('edit_current_filename').textContent = fileName;
    document.getElementById('modalPaperSubtitle').textContent = '#' + paper.id + ' • ' + (document.getElementById('edit_class').value || paper.class || 'Paper');
    
    document.getElementById('editPaperModal').style.display = 'flex';
}

function closeEditPaperModal() {
    document.getElementById('editPaperModal').style.display = 'none';
}

// Close modal when clicking outside of modal box
window.addEventListener('click', function(e) {
    const modal = document.getElementById('editPaperModal');
    if (e.target === modal) {
        closeEditPaperModal();
    }
});
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

