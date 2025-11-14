<?php
require_once __DIR__ . '/config.php';
require_login();

$user = current_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all'])) {
    verify_csrf($_POST['csrf_token'] ?? null);
    mark_notifications_read((int) $user['id']);
}

$notifications = fetch_notifications((int) $user['id'], false);

include __DIR__ . '/includes/header.php';
?>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Bildirimler</strong>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
            <button class="btn btn-sm btn-outline-secondary" name="mark_all" value="1">Tümünü okundu işaretle</button>
        </form>
    </div>
    <div class="card-body">
        <?php if (!$notifications): ?>
            <p class="text-muted">Bildirim bulunamadı.</p>
        <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($notifications as $note): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-start <?php echo $note['is_read'] ? '' : 'bg-light'; ?>">
                        <div>
                            <div class="fw-semibold text-capitalize"><?php echo e($note['type']); ?></div>
                            <div><?php echo e($note['message']); ?></div>
                            <small class="text-muted"><?php echo e($note['created_at']); ?></small>
                        </div>
                        <a href="<?php echo e($note['link']); ?>" class="btn btn-sm btn-outline-primary">Aç</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
