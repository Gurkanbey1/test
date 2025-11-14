<?php
require_once __DIR__ . '/config.php';
require_login();

$taskId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$task = $taskId ? fetch_task($taskId) : null;

if (!$task) {
    include __DIR__ . '/includes/header.php';
    echo '<div class="alert alert-warning">Görev bulunamadı. Lütfen <a href="index.php">dashboard</a>a dönün.</div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$assignedUsers = fetch_users();
$statusOptions = status_options();
$checklist = fetch_task_checklist($taskId);
$totalChecklist = count($checklist);
$doneCount = array_sum(array_column($checklist, 'is_done'));
$checklistPercent = $totalChecklist ? round(($doneCount / $totalChecklist) * 100) : 0;
$files = fetch_task_files($taskId);
$tags = fetch_tags();
$taskTags = fetch_task_tags($taskId);
$timeline = get_task_timeline($taskId);
log_task_read($taskId, (int) current_user()['id']);

include __DIR__ . '/includes/header.php';
?>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <span class="badge bg-<?php echo e($task['priority'] === 'critical' ? 'danger' : ($task['priority'] === 'high' ? 'warning text-dark' : 'secondary')); ?>">
                        <?php echo e(strtoupper($task['priority'])); ?>
                    </span>
                    <span class="badge bg-info text-dark"><?php echo e($statusOptions[$task['status']] ?? $task['status']); ?></span>
                </div>
                <small class="text-muted">Oluşturulma: <?php echo e($task['created_at']); ?></small>
            </div>
            <div class="card-body">
                <h4><?php echo e($task['title']); ?></h4>
                <p><?php echo nl2br(e($task['description'])); ?></p>
                <div class="d-flex flex-wrap gap-3">
                    <div><strong>Oluşturan:</strong> <?php echo e($task['creator_name']); ?></div>
                    <div><strong>Atanan:</strong> <?php echo e($task['assignee_name'] ?: '—'); ?></div>
                    <div><strong>Deadline:</strong> <?php echo e($task['deadline'] ?: '—'); ?></div>
                </div>
                <div class="mt-3">
                    <?php foreach ($taskTags as $tagId): ?>
                        <?php $tagName = array_values(array_filter($tags, fn($t) => $t['id'] == $tagId)); ?>
                        <?php if ($tagName): ?>
                            <span class="badge bg-light text-dark border cursor-pointer task-tag-filter" data-tag="<?php echo e($tagId); ?>">
                                #<?php echo e($tagName[0]['name']); ?>
                            </span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Durum Güncelle</strong>
            </div>
            <div class="card-body">
                <form id="statusUpdateForm" data-task-id="<?php echo e($taskId); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label">Durum</label>
                            <select class="form-select" name="status">
                                <?php foreach ($statusOptions as $value => $label): ?>
                                    <option value="<?php echo e($value); ?>" <?php echo $task['status'] === $value ? 'selected' : ''; ?>>
                                        <?php echo e($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <button class="btn btn-primary" type="submit"><i class="fa fa-sync me-2"></i>Güncelle</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Görev Sohbet Odası</strong>
                <small class="text-muted">3 sn'de bir yenilenir</small>
            </div>
            <div class="card-body">
                <div class="chat-window" id="taskChat" data-task-id="<?php echo e($taskId); ?>"></div>
                <form id="commentForm" class="mt-3" enctype="multipart/form-data">
                    <input type="hidden" name="task_id" value="<?php echo e($taskId); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <div class="mb-3">
                        <textarea class="form-control" name="message" rows="3" placeholder="@mention ile ekip arkadaşını haberdar et" required></textarea>
                    </div>
                    <div class="mb-3">
                        <input type="file" class="form-control" name="files[]" multiple>
                    </div>
                    <button class="btn btn-success" type="submit"><i class="fa fa-paper-plane me-2"></i>Gönder</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Görev içi Check-list</strong>
                <div class="small text-muted" id="checklistStats">
                    Tamamlanan: <?php echo $doneCount; ?>/<?php echo $totalChecklist; ?> (%<?php echo $checklistPercent; ?>)
                </div>
            </div>
            <div class="card-body">
                <div class="progress mb-3">
                    <div class="progress-bar bg-info" id="checklistProgressBar" role="progressbar" style="width: <?php echo $checklistPercent; ?>%"></div>
                </div>
                <ul class="list-group checklist" id="checklist" data-task-id="<?php echo e($taskId); ?>">
                    <?php foreach ($checklist as $item): ?>
                        <li class="list-group-item d-flex align-items-center justify-content-between">
                            <div>
                                <input class="form-check-input me-2 checklist-toggle" type="checkbox" data-item="<?php echo e($item['id']); ?>" <?php echo $item['is_done'] ? 'checked' : ''; ?>>
                                <span class="<?php echo $item['is_done'] ? 'text-decoration-line-through text-muted' : ''; ?>">
                                    <?php echo e($item['label']); ?>
                                </span>
                            </div>
                            <small class="text-muted"><?php echo e($item['created_at']); ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <form class="mt-3" id="checklistForm">
                    <input type="hidden" name="task_id" value="<?php echo e($taskId); ?>">
                    <div class="input-group">
                        <input type="text" class="form-control" name="label" placeholder="Yeni madde ekle" required>
                        <button class="btn btn-outline-primary" type="submit">Ekle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Tüm Dosyalar</strong>
                <span class="small text-muted"><?php echo count($files); ?> kayıt</span>
            </div>
            <div class="card-body file-list">
                <?php foreach ($files as $file): ?>
                    <?php
                        $ext = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));
                        $icon = 'fa-file';
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                            $icon = 'fa-file-image';
                        } elseif ($ext === 'pdf') {
                            $icon = 'fa-file-pdf';
                        } elseif (in_array($ext, ['zip', 'rar'])) {
                            $icon = 'fa-file-archive';
                        }
                    ?>
                    <div class="d-flex align-items-center justify-content-between border rounded p-2 mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa <?php echo e($icon); ?> fa-lg text-primary"></i>
                            <div>
                                <div><?php echo e($file['original_name']); ?></div>
                                <small class="text-muted"><?php echo round($file['size'] / 1024, 1); ?> KB</small>
                            </div>
                        </div>
                        <?php if ($ext === 'pdf'): ?>
                            <a href="<?php echo e(base_url('uploads/' . $file['stored_name'])); ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Görüntüle</a>
                        <?php elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                            <button class="btn btn-sm btn-outline-secondary lightbox-trigger" data-src="<?php echo e(base_url('uploads/' . $file['stored_name'])); ?>">Önizle</button>
                        <?php else: ?>
                            <a href="<?php echo e(base_url('uploads/' . $file['stored_name'])); ?>" download class="btn btn-sm btn-outline-secondary">İndir</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (!$files): ?>
                    <p class="text-muted">Henüz dosya eklenmedi.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header">
                <strong>Görev Zaman Çizelgesi</strong>
            </div>
            <div class="card-body">
                <ul class="timeline list-unstyled">
                    <?php foreach ($timeline as $event): ?>
                        <li>
                            <div class="timeline-badge"></div>
                            <div class="timeline-content">
                                <div class="small text-muted"><?php echo e($event['created_at']); ?></div>
                                <div><?php echo e($event['detail']); ?></div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
