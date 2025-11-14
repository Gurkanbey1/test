<?php
require_once __DIR__ . '/config.php';
require_login();
if (!is_admin()) {
    http_response_code(403);
    die('Yetkiniz yok');
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = sanitize($_POST['name'] ?? '');
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $role = $_POST['role'] ?? 'user';
        $password = $_POST['password'] ?? '';

        if (!$name || !$email || !$password) {
            $error = 'Tüm alanlar zorunludur.';
        } else {
            $stmt = db()->prepare('INSERT INTO users (name, email, password_hash, role, status, created_at)
                VALUES (:name, :email, :password, :role, "active", NOW())');
            $stmt->execute([
                'name' => $name,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_BCRYPT),
                'role' => $role,
            ]);
            $success = 'Kullanıcı oluşturuldu.';
        }
    } elseif ($action === 'status') {
        $id = (int) ($_POST['user_id'] ?? 0);
        $status = $_POST['status'] ?? 'active';
        $stmt = db()->prepare('UPDATE users SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
        $success = 'Durum güncellendi.';
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['user_id'] ?? 0);
        $stmt = db()->prepare('DELETE FROM users WHERE id = :id AND role != "admin"');
        $stmt->execute(['id' => $id]);
        $success = 'Kullanıcı silindi.';
    }
}

$users = fetch_users(null);

include __DIR__ . '/includes/header.php';
?>

<div class="row">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header">
                <strong>Yeni Kullanıcı</strong>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo e($error); ?></div>
                <?php elseif ($success): ?>
                    <div class="alert alert-success"><?php echo e($success); ?></div>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label">Ad Soyad</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">E-posta</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Şifre</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rol</label>
                        <select class="form-select" name="role">
                            <option value="user">Kullanıcı</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <button class="btn btn-primary" type="submit">Oluştur</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header">
                <strong>Kullanıcı Listesi</strong>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Ad</th>
                                <th>E-posta</th>
                                <th>Rol</th>
                                <th>Durum</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?php echo e($u['name']); ?></td>
                                    <td><?php echo e($u['email']); ?></td>
                                    <td class="text-capitalize"><?php echo e($u['role']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $u['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo e($u['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="status">
                                            <input type="hidden" name="user_id" value="<?php echo e($u['id']); ?>">
                                            <input type="hidden" name="status" value="<?php echo $u['status'] === 'active' ? 'suspended' : 'active'; ?>">
                                            <button class="btn btn-sm btn-outline-warning" type="submit">
                                                <?php echo $u['status'] === 'active' ? 'Askıya Al' : 'Aktifleştir'; ?>
                                            </button>
                                        </form>
                                        <?php if ($u['role'] !== 'admin'): ?>
                                            <form method="POST" class="d-inline ms-1" onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?php echo e($u['id']); ?>">
                                                <button class="btn btn-sm btn-outline-danger" type="submit">Sil</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
