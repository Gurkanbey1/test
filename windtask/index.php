<?php
require_once __DIR__ . '/config.php';
require_login();

$user = current_user();
$metrics = dashboard_metrics((int) $user['id']);
$users = fetch_users();
$tags = fetch_tags();

$statusOptions = status_options();
$priorityOptions = priority_options();

include __DIR__ . '/includes/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl">
        <div class="card shadow-sm metric-card">
            <div class="card-body">
                <div class="text-uppercase small text-muted">Bugün gelen işler</div>
                <div class="h2 mb-0"><?php echo (int) $metrics['today']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card shadow-sm metric-card">
            <div class="card-body">
                <div class="text-uppercase small text-muted">Bana atanan işler</div>
                <div class="h2 mb-0"><?php echo (int) $metrics['assigned']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card shadow-sm metric-card">
            <div class="card-body">
                <div class="text-uppercase small text-muted">Tamamlanan işler</div>
                <div class="h2 mb-0 text-success"><?php echo (int) $metrics['completed']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card shadow-sm metric-card">
            <div class="card-body">
                <div class="text-uppercase small text-muted">Beklemede olan işler</div>
                <div class="h2 mb-0 text-warning"><?php echo (int) $metrics['on_hold']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card shadow-sm metric-card">
            <div class="card-body">
                <div class="text-uppercase small text-muted">Kritik işler</div>
                <div class="h2 mb-0 text-danger"><?php echo (int) $metrics['critical']; ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Görev Filtreleri</strong>
        <button class="btn btn-sm btn-outline-secondary" id="resetFilters">Sıfırla</button>
    </div>
    <div class="card-body">
        <form id="taskFilters" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Durum</label>
                <select class="form-select" name="status">
                    <option value="">Tümü</option>
                    <?php foreach ($statusOptions as $value => $label): ?>
                        <option value="<?php echo e($value); ?>"><?php echo e($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Öncelik</label>
                <select class="form-select" name="priority">
                    <option value="">Tümü</option>
                    <?php foreach ($priorityOptions as $value => $label): ?>
                        <option value="<?php echo e($value); ?>"><?php echo e($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Kullanıcı</label>
                <select class="form-select" name="assigned_to">
                    <option value="">Tümü</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo e($u['id']); ?>"><?php echo e($u['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Etiket</label>
                <select class="form-select" name="tag_id">
                    <option value="">Tümü</option>
                    <?php foreach ($tags as $tag): ?>
                        <option value="<?php echo e($tag['id']); ?>"><?php echo e($tag['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Başlangıç Tarihi</label>
                <input type="date" class="form-control" name="start_date">
            </div>
            <div class="col-md-3">
                <label class="form-label">Bitiş Tarihi</label>
                <input type="date" class="form-control" name="end_date">
            </div>
            <div class="col-md-6">
                <label class="form-label">Arama</label>
                <input type="text" class="form-control" name="query" placeholder="Başlık, açıklama veya etiket ara...">
            </div>
        </form>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Görev Listesi</strong>
                <div class="small text-muted" id="taskCount">0 görev</div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="tasksTable">
                        <thead>
                            <tr>
                                <th>Başlık</th>
                                <th>Durum</th>
                                <th>Öncelik</th>
                                <th>Atanan</th>
                                <th>Deadline</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <strong>Kanban Board</strong>
            </div>
            <div class="card-body">
                <div class="kanban-board row g-3">
                    <?php foreach ($statusOptions as $statusKey => $statusLabel): ?>
                        <div class="col-6">
                            <div class="kanban-column" data-status="<?php echo e($statusKey); ?>">
                                <div class="kanban-title"><?php echo e($statusLabel); ?></div>
                                <div class="kanban-items" id="kanban-<?php echo e($statusKey); ?>"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="card shadow-sm">
            <div class="card-header">
                <strong>Canlı Aktiviteler</strong>
            </div>
            <div class="card-body">
                <ul class="timeline list-unstyled mb-0" id="liveTimeline"></ul>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
