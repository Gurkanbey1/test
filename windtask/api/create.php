<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}

if (!current_user()) {
    http_response_code(401);
    echo json_encode(['error' => 'Auth required']);
    exit;
}

$payload = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];
verify_csrf($payload['csrf_token'] ?? null);

$title = sanitize($payload['title'] ?? '');
$description = sanitize($payload['description'] ?? '');
$priority = $payload['priority'] ?? 'medium';
$status = $payload['status'] ?? 'new';
$deadline = $payload['deadline'] ?? null;
$assigned = isset($payload['assigned_to']) ? (int) $payload['assigned_to'] : null;
$selectedTags = array_map('intval', $payload['tags'] ?? []);

if (!$title || !$description) {
    http_response_code(422);
    echo json_encode(['error' => 'Başlık ve açıklama zorunludur.']);
    exit;
}

if (!isset(priority_options()[$priority]) || !isset(status_options()[$status])) {
    http_response_code(422);
    echo json_encode(['error' => 'Geçersiz durum veya öncelik.']);
    exit;
}

$user = current_user();
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
    $mapStmt = db()->prepare('INSERT INTO task_tag_map (task_id, tag_id) VALUES (:task_id, :tag_id)');
    foreach ($selectedTags as $tagId) {
        $mapStmt->execute(['task_id' => $taskId, 'tag_id' => $tagId]);
    }
}

if ($assigned && $assigned !== (int) $user['id']) {
    create_notification($assigned, 'Yeni görev', "{$user['name']} size {$title} görevini atadı.", base_url("task.php?id={$taskId}"));
}

echo json_encode(['success' => true, 'task_id' => $taskId]);
