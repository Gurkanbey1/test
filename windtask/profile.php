<?php
require_once __DIR__ . '/config.php';
require_login();

$user = current_user();
$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $name = sanitize($_POST['name'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $status = $user['status'];

    if (!$name || !$email) {
        $error = 'Ad ve e-posta gereklidir.';
    } else {
        $params = [
            'name' => $name,
            'email' => $email,
            'id' => $user['id'],
        ];
        $sql = 'UPDATE users SET name = :name, email = :email';

        if ($password) {
            $sql .= ', password_hash = :password';
            $params['password'] = password_hash($password, PASSWORD_BCRYPT);
        }

        if (!empty($_FILES['avatar']['name'])) {
            $avatar = $_FILES['avatar'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($avatar['tmp_name']);
            $allowed = ['image/jpeg', 'image/png', 'image/gif'];
            if (in_array($mime, $allowed, true) && $avatar['size'] < 5 * 1024 * 1024) {
                $ext = pathinfo($avatar['name'], PATHINFO_EXTENSION);
                $fileName = 'avatar_' . $user['id'] . '.' . $ext;
                move_uploaded_file($avatar['tmp_name'], __DIR__ . '/uploads/' . $fileName);
                $sql .= ', avatar = :avatar';
                $params['avatar'] = $fileName;
            }
        }

        $sql .= ' WHERE id = :id';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        $stmt = db()->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $user['id']]);
        $_SESSION['user'] = $stmt->fetch();

        $success = 'Profil güncellendi.';
        $user = current_user();
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="row">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header">
                <strong>Profil Ayarları</strong>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo e($error); ?></div>
                <?php elseif ($success): ?>
                    <div class="alert alert-success"><?php echo e($success); ?></div>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <div class="mb-3">
                        <label class="form-label">Ad Soyad</label>
                        <input type="text" class="form-control" name="name" value="<?php echo e($user['name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">E-posta</label>
                        <input type="email" class="form-control" name="email" value="<?php echo e($user['email']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Yeni Şifre (opsiyonel)</label>
                        <input type="password" class="form-control" name="password" placeholder="Boş bırakılırsa değişmez">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Avatar</label>
                        <input type="file" class="form-control" name="avatar" accept="image/*">
                        <?php if ($user['avatar']): ?>
                            <img src="<?php echo e(base_url('uploads/' . $user['avatar'])); ?>" alt="avatar" class="rounded mt-2" width="80">
                        <?php endif; ?>
                    </div>
                    <button class="btn btn-primary" type="submit">Kaydet</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
