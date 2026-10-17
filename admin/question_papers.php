<?php
/**
 * EEMT 2027 - Admin Question Paper Management
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireStaffLogin();

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

        $stmt = $pdo->prepare("UPDATE question_papers SET is_published = :status, published_at = :pub_at WHERE id = :id");
        $stmt->execute([':status' => $newStatus, ':pub_at' => $publishedAt, ':id' => $paperId]);

        $message = "Question paper status updated successfully.";
        $messageType = 'success';
    } elseif ($action === 'delete') {
        $paperId = (int)($_POST['paper_id'] ?? 0);
        
        $stmt = $pdo->prepare("SELECT file_path FROM question_papers WHERE id = :id");
        $stmt->execute([':id' => $paperId]);
        $filePath = $stmt->fetchColumn();

        if ($filePath) {
            $fullPath = __DIR__ . '/../' . ltrim($filePath, '/');
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
            $delStmt = $pdo->prepare("DELETE FROM question_papers WHERE id = :id");
            $delStmt->execute([':id' => $paperId]);
            $message = "Question paper deleted successfully.";
            $messageType = 'success';
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

$pageTitle = "Question Papers Manager - EEMT 2027";
require_once __DIR__ . '/includes/admin_header.php';
?>

<div>
    <!-- Navigation Breadcrumb -->
    <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <a href="dashboard.php" style="color: #2563eb; font-weight: 600; text-decoration: none; font-size: 0.9rem;">
                &larr; Back to Admin Dashboard
            </a>
            <h2 style="font-size: 1.75rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;">Prelims Question Papers</h2>
        </div>
        <a href="#upload-card" class="btn btn-primary">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Upload New Paper
        </a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?>" style="margin-bottom: 1.5rem;">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Papers List -->
    <div class="card" style="padding: 0; overflow: hidden; margin-bottom: 2rem;">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 1.15rem; font-weight: 700; color: #1e293b; margin: 0;">Uploaded Question Papers</h3>
            <span class="badge badge-info"><?php echo count($papers); ?> Total Papers</span>
        </div>

        <?php if (empty($papers)): ?>
            <div style="padding: 3rem; text-align: center; color: #64748b;">
                <svg width="48" height="48" fill="none" stroke="#94a3b8" stroke-width="1.5" viewBox="0 0 24 24" style="margin: 0 auto 1rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                <h4 style="font-weight: 700; color: #334155; margin-bottom: 0.25rem;">No Question Papers Uploaded</h4>
                <p style="font-size: 0.9rem; margin: 0;">Use the form below to upload official EEMT 2027 prelims PDFs.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Paper Title</th>
                            <th>Class / Grade</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Uploaded On</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($papers as $p): ?>
                            <tr>
                                <td style="font-weight: 600; color: #64748b;">#<?php echo $p['id']; ?></td>
                                <td>
                                    <strong style="color: #0f172a; font-size: 0.95rem; display: block;"><?php echo htmlspecialchars($p['title']); ?></strong>
                                    <span style="font-size: 0.8rem; color: #64748b; font-family: monospace;"><?php echo basename($p['file_path']); ?></span>
                                </td>
                                <td>
                                    <span class="badge" style="background: #eff6ff; color: #1d4ed8; font-weight: 700;">
                                        <?php echo htmlspecialchars($p['class']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-weight: 600; color: #334155;"><?php echo htmlspecialchars($p['subject']); ?></span>
                                </td>
                                <td>
                                    <?php if ($p['is_published'] == 1): ?>
                                        <span class="badge badge-success">Published (Visible to Schools)</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Draft (Hidden)</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 0.85rem; color: #64748b; white-space: nowrap;">
                                    <?php echo date('d M Y, h:i A', strtotime($p['created_at'])); ?>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_publish">
                                            <input type="hidden" name="paper_id" value="<?php echo $p['id']; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $p['is_published']; ?>">
                                            <button type="submit" class="btn btn-outline" style="padding: 0.35rem 0.65rem; font-size: 0.8rem;">
                                                <?php echo $p['is_published'] == 1 ? 'Unpublish' : 'Publish'; ?>
                                            </button>
                                        </form>

                                        <a href="<?php echo $rootPath . htmlspecialchars($p['file_path']); ?>" target="_blank" class="btn btn-outline" style="padding: 0.35rem 0.65rem; font-size: 0.8rem;">
                                            Preview
                                        </a>

                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to permanently delete this question paper?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="paper_id" value="<?php echo $p['id']; ?>">
                                            <button type="submit" class="btn" style="padding: 0.35rem 0.65rem; font-size: 0.8rem; background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5;">
                                                Delete
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
    <div class="card" id="upload-card">
        <h3 style="font-size: 1.25rem; font-weight: 700; color: #0f172a; margin-bottom: 0.5rem;">Upload New Prelims Question Paper</h3>
        <p style="font-size: 0.9rem; color: #64748b; margin-bottom: 1.5rem;">Upload official PDF question sets for registered schools to access on their dashboard.</p>

        <form method="POST" action="question_papers.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload">

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
                <div class="form-group">
                    <label class="form-label" for="title">Paper Title <span style="color: #ef4444;">*</span></label>
                    <input type="text" id="title" name="title" class="form-input" placeholder="e.g. EEMT 2027 Prelims - Class 10 Model Paper" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="class">Target Class <span style="color: #ef4444;">*</span></label>
                    <select id="class" name="class" class="form-select" required>
                        <option value="">-- Select Class --</option>
                        <option value="Class 10">Class 10</option>
                        <option value="Class 7">Class 7</option>
                        <option value="Class 6">Class 6</option>
                        <option value="Class 8">Class 8</option>
                        <option value="Class 9">Class 9</option>
                        <option value="General / All Classes">General / All Classes</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="subject">Subject <span style="color: #ef4444;">*</span></label>
                    <input type="text" id="subject" name="subject" class="form-input" placeholder="e.g. Mathematics, Science, Aptitude" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="paper_file">PDF File <span style="color: #ef4444;">*</span></label>
                    <input type="file" id="paper_file" name="paper_file" class="form-input" accept=".pdf" required>
                    <span style="font-size: 0.8rem; color: #64748b;">Only PDF documents up to 25MB accepted</span>
                </div>
            </div>

            <div style="margin-top: 0.5rem; margin-bottom: 1.5rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.95rem; font-weight: 600; color: #1e293b;">
                    <input type="checkbox" name="is_published" value="1" checked style="width: 18px; height: 18px; accent-color: #2563eb;">
                    Publish immediately (Schools will be able to download it upon login)
                </label>
            </div>

            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                Upload & Save Paper
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

