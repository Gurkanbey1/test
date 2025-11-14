<?php
require_once __DIR__ . '/config.php';
require_login();

$user = current_user();
$users = fetch_users();
$tags = fetch_tags();
$statusOptions = status_options();
$priorityOptions = priority_options();

$success = null;
$error = null;
$createdTaskId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $assigned = (int) ($_POST['assigned_to'] ?? 0);
    $priority = $_POST['priority'] ?? 'medium';
    $status = $_POST['status'] ?? 'new';
    $deadline = $_POST['deadline'] ?? null;
    $selectedTags = array_map('intval', $_POST['tags'] ?? []);

    if (!$title) {
        $error = 'Başlık zorunludur.';
    } elseif (!isset($priorityOptions[$priority]) || !isset($statusOptions[$status])) {
        $error = 'Geçersiz durum veya öncelik.';
    } else {
        $stmt = db()->prepare('INSERT INTO tasks (title, description, priority, status, deadline, created_by, assigned_to, created_at, updated_at)
            VALUES (:title, :description, :priority, :status, :deadline, :created_by, :assigned_to, NOW(), NOW())');
        $stmt->execute([
            'title' => $title,
            'description' => $description,
            'priority' => $priority,
            'status' => $status,
            'deadline' => $deadline ?: null,
            'created_by' => $user['id'],
            'assigned_to' => $assigned ?: null,
        ]);
        $taskId = (int) db()->lastInsertId();

        if ($selectedTags) {
            $stmt = db()->prepare('INSERT INTO task_tag_map (task_id, tag_id) VALUES (:task_id, :tag_id)');
            foreach ($selectedTags as $tagId) {
                $stmt->execute(['task_id' => $taskId, 'tag_id' => $tagId]);
            }
        }

        if (!empty($_FILES['attachments'])) {
            handle_file_uploads($_FILES['attachments'], $taskId, null, (int) $user['id']);
        }

        if ($assigned && $assigned !== (int) $user['id']) {
            create_notification($assigned, 'Yeni görev', "{$user['name']} size {$title} görevini atadı.", base_url("task.php?id={$taskId}"));
        }

        $createdTaskId = $taskId;
        $success = 'Görev başarıyla oluşturuldu.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
    header('Content-Type: application/json');
    if ($error) {
        echo json_encode(['success' => false, 'error' => $error]);
    } else {
        echo json_encode(['success' => true, 'message' => $success, 'task_id' => $createdTaskId]);
    }
    exit;
}

include __DIR__ . '/includes/header.php';
?>

<div class="row">
    <div class="col-xl-8">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Görev Oluştur</strong>
                <a href="<?php echo e(base_url('index.php')); ?>" class="btn btn-sm btn-outline-secondary">Dashboards</a>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo e($error); ?></div>
                <?php elseif ($success): ?>
                    <div class="alert alert-success"><?php echo e($success); ?></div>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data" id="createTaskForm">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <div class="mb-3">
                        <label class="form-label">Başlık</label>
                        <input type="text" class="form-control" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Açıklama</label>
                        <textarea class="form-control" name="description" rows="4" required></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Öncelik</label>
                            <select class="form-select" name="priority">
                                <?php foreach ($priorityOptions as $value => $label): ?>
                                    <option value="<?php echo e($value); ?>"><?php echo e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Durum</label>
                            <select class="form-select" name="status">
                                <?php foreach ($statusOptions as $value => $label): ?>
                                    <option value="<?php echo e($value); ?>"><?php echo e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Deadline</label>
                            <input type="date" class="form-control" name="deadline">
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label">Atanacak Kullanıcı</label>
                            <select class="form-select" name="assigned_to">
                                <option value="">Seçilmedi</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?php echo e($u['id']); ?>"><?php echo e($u['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Etiketler</label>
                            <select class="form-select" name="tags[]" multiple>
                                <?php foreach ($tags as $tag): ?>
                                    <option value="<?php echo e($tag['id']); ?>"><?php echo e($tag['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label">Dosya Ekleri</label>
                        <input type="file" class="form-control" name="attachments[]" multiple>
                        <div class="progress mt-2 d-none" id="uploadProgress">
                            <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                        </div>
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="fa fa-save me-2"></i>Kaydet</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card shadow-sm">
            <div class="card-header">
                <strong>İpucu</strong>
            </div>
            <div class="card-body">
                <p class="small text-muted">Görev oluştururken kritik önceliklerde mutlaka son tarih seçin. Ayrıca görev açıklamasına @mention ekleyerek ekip arkadaşlarınıza anlık bildirim gönderin.</p>
                <p class="small text-muted">Çoklu dosya yüklerken ilerleme çubuğu yükleme durumunu gösterecek.</p>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
