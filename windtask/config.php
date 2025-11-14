<?php

declare(strict_types=1);

$config = [
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'windtask',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    'base_url' => getenv('BASE_URL') ?: '/windtask',
    'upload_dir' => __DIR__ . '/uploads',
    'upload_max_size' => 15 * 1024 * 1024, // 15MB per file
    'allowed_mimes' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/pdf',
        'application/zip',
        'application/x-rar-compressed',
        'text/plain',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ],
];

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $config['db']['host'],
        $config['db']['port'],
        $config['db']['name'],
        $config['db']['charset']
    );
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

if (!isset($_SESSION['fingerprint'])) {
    $_SESSION['fingerprint'] = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . session_id());
} elseif ($_SESSION['fingerprint'] !== hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . session_id())) {
    session_destroy();
    header('Location: login.php');
    exit;
}

function db(): PDO
{
    global $pdo;
    return $pdo;
}

function base_url(string $path = ''): string
{
    global $config;
    return rtrim($config['base_url'], '/') . '/' . ltrim($path, '/');
}

function sanitize(string $value): string
{
    return trim(strip_tags($value));
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): void
{
    if (!$token || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(419);
        die('CSRF token mismatch');
    }
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!current_user()) {
        header('Location: login.php');
        exit;
    }
}

function is_admin(): bool
{
    $user = current_user();
    return $user && $user['role'] === 'admin';
}

function fetch_users(?string $status = 'active'): array
{
    $sql = 'SELECT id, name, email, role, avatar, status FROM users';
    $params = [];
    if ($status) {
        $sql .= ' WHERE status = :status';
        $params['status'] = $status;
    }
    $sql .= ' ORDER BY name ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function status_options(): array
{
    return [
        'new' => 'Yeni',
        'in_progress' => 'Devam',
        'on_hold' => 'Beklemede',
        'completed' => 'Tamamlandı',
    ];
}

function priority_options(): array
{
    return [
        'low' => 'Düşük',
        'medium' => 'Orta',
        'high' => 'Yüksek',
        'critical' => 'Kritik',
    ];
}

function fetch_tags(): array
{
    $stmt = db()->query('SELECT id, name FROM task_tags ORDER BY name ASC');
    return $stmt->fetchAll();
}

function fetch_task(int $taskId): ?array
{
    $stmt = db()->prepare('SELECT t.*, u.name AS creator_name, a.name AS assignee_name
        FROM tasks t
        LEFT JOIN users u ON u.id = t.created_by
        LEFT JOIN users a ON a.id = t.assigned_to
        WHERE t.id = :id');
    $stmt->execute(['id' => $taskId]);
    return $stmt->fetch() ?: null;
}

function fetch_task_tags(int $taskId): array
{
    $stmt = db()->prepare('SELECT tag_id FROM task_tag_map WHERE task_id = :task_id');
    $stmt->execute(['task_id' => $taskId]);
    return array_column($stmt->fetchAll(), 'tag_id');
}

function fetch_task_comments(int $taskId): array
{
    $stmt = db()->prepare('SELECT c.*, u.name AS user_name, u.avatar
        FROM task_comments c
        INNER JOIN users u ON u.id = c.user_id
        WHERE c.task_id = :task_id
        ORDER BY c.created_at ASC');
    $stmt->execute(['task_id' => $taskId]);
    return $stmt->fetchAll();
}

function fetch_task_files(int $taskId): array
{
    $stmt = db()->prepare('SELECT * FROM task_files WHERE task_id = :task_id ORDER BY created_at DESC');
    $stmt->execute(['task_id' => $taskId]);
    return $stmt->fetchAll();
}

function fetch_task_checklist(int $taskId): array
{
    $stmt = db()->prepare('SELECT * FROM task_checklists WHERE task_id = :task_id ORDER BY created_at ASC');
    $stmt->execute(['task_id' => $taskId]);
    return $stmt->fetchAll();
}

function fetch_notifications(int $userId, bool $onlyUnread = false): array
{
    $sql = 'SELECT * FROM notifications WHERE user_id = :uid';
    if ($onlyUnread) {
        $sql .= ' AND is_read = 0';
    }
    $sql .= ' ORDER BY created_at DESC LIMIT 20';
    $stmt = db()->prepare($sql);
    $stmt->execute(['uid' => $userId]);
    return $stmt->fetchAll();
}

function create_notification(int $userId, string $type, string $message, string $link = '#'): void
{
    $stmt = db()->prepare('INSERT INTO notifications (user_id, type, message, link, is_read, created_at)
        VALUES (:user_id, :type, :message, :link, 0, NOW())');
    $stmt->execute([
        'user_id' => $userId,
        'type' => $type,
        'message' => $message,
        'link' => $link,
    ]);
}

function log_task_status(int $taskId, string $oldStatus, string $newStatus, int $userId): void
{
    $stmt = db()->prepare('INSERT INTO task_status_logs (task_id, old_status, new_status, changed_by, created_at)
        VALUES (:task_id, :old_status, :new_status, :user_id, NOW())');
    $stmt->execute([
        'task_id' => $taskId,
        'old_status' => $oldStatus,
        'new_status' => $newStatus,
        'user_id' => $userId,
    ]);
}

function log_task_read(int $taskId, int $userId): void
{
    $stmt = db()->prepare('REPLACE INTO task_read_log (task_id, user_id, read_at) VALUES (:task_id, :user_id, NOW())');
    $stmt->execute([
        'task_id' => $taskId,
        'user_id' => $userId,
    ]);
}

function get_task_timeline(int $taskId): array
{
    $timeline = [];

    $stmt = db()->prepare('SELECT created_at, "created" AS type, CONCAT(u.name, " görevi oluşturdu") AS detail
        FROM tasks t
        INNER JOIN users u ON u.id = t.created_by
        WHERE t.id = :task_id');
    $stmt->execute(['task_id' => $taskId]);
    $timeline = array_merge($timeline, $stmt->fetchAll() ?: []);

    $stmt = db()->prepare('SELECT created_at, "status" AS type,
        CONCAT(u.name, " durum değiştirdi: ", old_status, " → ", new_status) AS detail
        FROM task_status_logs l
        INNER JOIN users u ON u.id = l.changed_by
        WHERE l.task_id = :task_id');
    $stmt->execute(['task_id' => $taskId]);
    $timeline = array_merge($timeline, $stmt->fetchAll() ?: []);

    $stmt = db()->prepare('SELECT created_at, "comment" AS type,
        CONCAT(u.name, " mesaj gönderdi") AS detail
        FROM task_comments c
        INNER JOIN users u ON u.id = c.user_id
        WHERE c.task_id = :task_id');
    $stmt->execute(['task_id' => $taskId]);
    $timeline = array_merge($timeline, $stmt->fetchAll() ?: []);

    $stmt = db()->prepare('SELECT created_at, "file" AS type,
        CONCAT(u.name, " dosya yükledi: ", original_name) AS detail
        FROM task_files f
        INNER JOIN users u ON u.id = f.user_id
        WHERE f.task_id = :task_id');
    $stmt->execute(['task_id' => $taskId]);
    $timeline = array_merge($timeline, $stmt->fetchAll() ?: []);

    usort($timeline, static fn($a, $b) => strtotime($a['created_at']) <=> strtotime($b['created_at']));

    return $timeline;
}

function handle_file_uploads(array $files, int $taskId, ?int $commentId, int $userId): array
{
    global $config;
    $saved = [];
    if (empty($files['name'][0])) {
        return $saved;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);

    for ($i = 0; $i < count($files['name']); $i++) {
        $original = $files['name'][$i];
        $tmp = $files['tmp_name'][$i];
        $size = (int) $files['size'][$i];

        if ($size <= 0 || $size > $config['upload_max_size']) {
            continue;
        }
        $mime = $finfo->file($tmp);
        if (!in_array($mime, $config['allowed_mimes'], true)) {
            continue;
        }

        $safeName = bin2hex(random_bytes(8)) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $original);
        $target = $config['upload_dir'] . DIRECTORY_SEPARATOR . $safeName;
        if (!move_uploaded_file($tmp, $target)) {
            continue;
        }

        $stmt = db()->prepare('INSERT INTO task_files (task_id, comment_id, stored_name, original_name, size, user_id, created_at)
            VALUES (:task_id, :comment_id, :stored_name, :original_name, :size, :user_id, NOW())');
        $stmt->execute([
            'task_id' => $taskId,
            'comment_id' => $commentId,
            'stored_name' => $safeName,
            'original_name' => $original,
            'size' => $size,
            'user_id' => $userId,
        ]);

        $saved[] = [
            'stored_name' => $safeName,
            'original_name' => $original,
            'size' => $size,
        ];
    }
    return $saved;
}

function detect_mentions(string $message): array
{
    if (!preg_match_all('/@([\\p{L}0-9._-]+)/u', $message, $matches)) {
        return [];
    }
    $names = array_unique($matches[1]);
    if (!$names) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($names), '?'));
    $stmt = db()->prepare("SELECT id, name FROM users WHERE name IN ($placeholders)");
    $stmt->execute($names);
    return $stmt->fetchAll();
}

function save_checklist_item(int $taskId, string $label, int $userId): void
{
    $stmt = db()->prepare('INSERT INTO task_checklists (task_id, label, is_done, created_by, created_at)
        VALUES (:task_id, :label, 0, :user_id, NOW())');
    $stmt->execute([
        'task_id' => $taskId,
        'label' => $label,
        'user_id' => $userId,
    ]);
}

function update_checklist_item(int $itemId, bool $done): void
{
    $stmt = db()->prepare('UPDATE task_checklists SET is_done = :done WHERE id = :id');
    $stmt->execute([
        'done' => $done ? 1 : 0,
        'id' => $itemId,
    ]);
}

function dashboard_metrics(int $userId): array
{
    $metrics = [
        'today' => 0,
        'assigned' => 0,
        'completed' => 0,
        'on_hold' => 0,
        'critical' => 0,
    ];

    $sql = 'SELECT
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS today,
        SUM(CASE WHEN assigned_to = :uid AND status != "completed" THEN 1 ELSE 0 END) AS assigned,
        SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN status = "on_hold" THEN 1 ELSE 0 END) AS on_hold,
        SUM(CASE WHEN priority = "critical" THEN 1 ELSE 0 END) AS critical
        FROM tasks';
    $stmt = db()->prepare($sql);
    $stmt->execute(['uid' => $userId]);
    $row = $stmt->fetch();
    if ($row) {
        $metrics = array_merge($metrics, $row);
    }
    return $metrics;
}

function mark_notifications_read(int $userId): void
{
    $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :uid');
    $stmt->execute(['uid' => $userId]);
}

?>
