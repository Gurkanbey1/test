<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!current_user()) {
    http_response_code(401);
    echo json_encode(['error' => 'Auth required']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$type = $_GET['type'] ?? null;

if ($method === 'GET') {
    if ($type === 'timeline') {
        $taskId = (int) ($_GET['task_id'] ?? 0);
        echo json_encode(['data' => get_task_timeline($taskId)]);
        exit;
    }
    if ($type === 'live') {
        $stmt = db()->query('SELECT detail, created_at FROM (
                SELECT CONCAT(u.name, " yeni görev oluşturdu: ", t.title) AS detail, t.created_at
                FROM tasks t
                JOIN users u ON u.id = t.created_by
                UNION ALL
                SELECT CONCAT(u.name, " durum güncelledi: ", old_status, " → ", new_status) AS detail, l.created_at
                FROM task_status_logs l
                JOIN users u ON u.id = l.changed_by
                UNION ALL
                SELECT CONCAT(u.name, " mesaj yazdı") AS detail, c.created_at
                FROM task_comments c
                JOIN users u ON u.id = c.user_id
            ) AS feed
            ORDER BY created_at DESC
            LIMIT 20');
        echo json_encode(['data' => $stmt->fetchAll()]);
        exit;
    }

    $params = [];
    $filters = [];

    if (!empty($_GET['status'])) {
        $filters[] = 't.status = :status';
        $params['status'] = $_GET['status'];
    }
    if (!empty($_GET['priority'])) {
        $filters[] = 't.priority = :priority';
        $params['priority'] = $_GET['priority'];
    }
    if (!empty($_GET['assigned_to'])) {
        $filters[] = 't.assigned_to = :assigned';
        $params['assigned'] = (int) $_GET['assigned_to'];
    }
    if (!empty($_GET['tag_id'])) {
        $filters[] = 'EXISTS (SELECT 1 FROM task_tag_map m WHERE m.task_id = t.id AND m.tag_id = :tag_id)';
        $params['tag_id'] = (int) $_GET['tag_id'];
    }
    if (!empty($_GET['start_date'])) {
        $filters[] = 'DATE(t.created_at) >= :start';
        $params['start'] = $_GET['start_date'];
    }
    if (!empty($_GET['end_date'])) {
        $filters[] = 'DATE(t.created_at) <= :end';
        $params['end'] = $_GET['end_date'];
    }
    if (!empty($_GET['query'])) {
        $filters[] = '(t.title LIKE :q OR t.description LIKE :q)';
        $params['q'] = '%' . $_GET['query'] . '%';
    }

    $sql = 'SELECT t.*, creator.name AS creator_name, assignee.name AS assignee_name
        FROM tasks t
        LEFT JOIN users creator ON creator.id = t.created_by
        LEFT JOIN users assignee ON assignee.id = t.assigned_to';
    if ($filters) {
        $sql .= ' WHERE ' . implode(' AND ', $filters);
    }
    $sql .= ' ORDER BY t.updated_at DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();

    echo json_encode(['data' => $tasks]);
    exit;
}

$payload = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];
$action = $payload['action'] ?? null;

if ($action === 'update_status') {
    verify_csrf($payload['csrf_token'] ?? null);
    $taskId = (int) ($payload['task_id'] ?? 0);
    $newStatus = $payload['status'] ?? 'new';
    if (!$taskId || !isset(status_options()[$newStatus])) {
        http_response_code(422);
        echo json_encode(['error' => 'Geçersiz']);
        exit;
    }
    $stmt = db()->prepare('SELECT status, assigned_to, title FROM tasks WHERE id = :id');
    $stmt->execute(['id' => $taskId]);
    $task = $stmt->fetch();
    if (!$task) {
        http_response_code(404);
        echo json_encode(['error' => 'Task not found']);
        exit;
    }
    $oldStatus = $task['status'];
    if ($oldStatus === $newStatus) {
        echo json_encode(['success' => true]);
        exit;
    }
    $stmt = db()->prepare('UPDATE tasks SET status = :status, updated_at = NOW() WHERE id = :id');
    $stmt->execute(['status' => $newStatus, 'id' => $taskId]);
    log_task_status($taskId, $oldStatus, $newStatus, (int) current_user()['id']);
    if ($task['assigned_to']) {
        create_notification((int) $task['assigned_to'], 'Durum değişti', "{$task['title']} görevi {$newStatus} oldu.", base_url("task.php?id={$taskId}"));
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'checklist_add') {
    verify_csrf($payload['csrf_token'] ?? null);
    $taskId = (int) ($payload['task_id'] ?? 0);
    $label = sanitize($payload['label'] ?? '');
    if (!$taskId || !$label) {
        http_response_code(422);
        echo json_encode(['error' => 'Eksik veri']);
        exit;
    }
    save_checklist_item($taskId, $label, (int) current_user()['id']);
    echo json_encode(['success' => true, 'items' => fetch_task_checklist($taskId)]);
    exit;
}

if ($action === 'checklist_toggle') {
    verify_csrf($payload['csrf_token'] ?? null);
    $itemId = (int) ($payload['item_id'] ?? 0);
    $done = (bool) ($payload['is_done'] ?? false);
    if (!$itemId) {
        http_response_code(422);
        echo json_encode(['error' => 'Eksik veri']);
        exit;
    }
    update_checklist_item($itemId, $done);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'kanban_move') {
    verify_csrf($payload['csrf_token'] ?? null);
    $taskId = (int) ($payload['task_id'] ?? 0);
    $status = $payload['status'] ?? '';
    if (!$taskId || !isset(status_options()[$status])) {
        http_response_code(422);
        echo json_encode(['error' => 'Geçersiz']);
        exit;
    }
    $stmt = db()->prepare('SELECT status, assigned_to, title FROM tasks WHERE id = :id');
    $stmt->execute(['id' => $taskId]);
    $task = $stmt->fetch();
    if (!$task) {
        http_response_code(404);
        echo json_encode(['error' => 'Task not found']);
        exit;
    }
    $stmt = db()->prepare('UPDATE tasks SET status = :status, updated_at = NOW() WHERE id = :id');
    $stmt->execute(['status' => $status, 'id' => $taskId]);
    log_task_status($taskId, $task['status'], $status, (int) current_user()['id']);
    if ($task['assigned_to']) {
        create_notification((int) $task['assigned_to'], 'Kanban güncelleme', "{$task['title']} görevinin durumu {$status} oldu.", base_url("task.php?id={$taskId}"));
    }
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Tanımsız istek']);
