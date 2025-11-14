<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Auth required']);
    exit;
}

$taskId = (int) ($_GET['task_id'] ?? $_POST['task_id'] ?? 0);
if (!$taskId) {
    http_response_code(422);
    echo json_encode(['error' => 'Task id required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $comments = fetch_task_comments($taskId);
    echo json_encode(['data' => $comments]);
    exit;
}

verify_csrf($_POST['csrf_token'] ?? null);
$message = trim($_POST['message'] ?? '');
if (!$message) {
    http_response_code(422);
    echo json_encode(['error' => 'Mesaj boş olamaz']);
    exit;
}

$stmt = db()->prepare('INSERT INTO task_comments (task_id, user_id, message, created_at)
    VALUES (:task_id, :user_id, :message, NOW())');
$stmt->execute([
    'task_id' => $taskId,
    'user_id' => $user['id'],
    'message' => $message,
]);
$commentId = (int) db()->lastInsertId();

if (!empty($_FILES['files'])) {
    handle_file_uploads($_FILES['files'], $taskId, $commentId, (int) $user['id']);
}

$mentions = detect_mentions($message);
foreach ($mentions as $mention) {
    if ((int) $mention['id'] !== (int) $user['id']) {
        create_notification((int) $mention['id'], '@mention', "{$user['name']} sizi bir yorumda bahsetti.", base_url("task.php?id={$taskId}"));
    }
}

$stmt = db()->prepare('SELECT assigned_to, title FROM tasks WHERE id = :id');
$stmt->execute(['id' => $taskId]);
$task = $stmt->fetch();
if ($task && $task['assigned_to'] && $task['assigned_to'] != $user['id']) {
    create_notification((int) $task['assigned_to'], 'Yeni mesaj', "{$task['title']} görevinde yeni mesaj var.", base_url("task.php?id={$taskId}"));
}

$comments = fetch_task_comments($taskId);
echo json_encode(['success' => true, 'data' => $comments]);
