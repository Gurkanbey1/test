<?php
require_once __DIR__ . '/config.php';

if (current_user()) {
    header('Location: index.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'E-posta ve şifre gerekli.';
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = :email AND status = "active" LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user'] = $user;
            session_regenerate_id(true);
            header('Location: index.php');
            exit;
        }
        $error = 'Bilgiler eşleşmedi veya hesabınız pasif.';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WindTask Giriş</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo e(base_url('assets/css/style.css')); ?>">
</head>
<body class="auth-bg d-flex align-items-center justify-content-center">
    <div class="card shadow-lg" style="max-width: 420px; width: 100%;">
        <div class="card-body p-4">
            <div class="text-center mb-3">
                <i class="fa fa-wind fa-2x text-primary mb-2"></i>
                <h4 class="fw-bold">WindTask</h4>
                <p class="text-muted small">Wind Medya İş Takip & Ekip Yönetim Sistemi</p>
            </div>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo e($error); ?></div>
            <?php endif; ?>
            <form method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <div class="mb-3">
                    <label class="form-label">E-posta</label>
                    <input type="email" class="form-control" name="email" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Şifre</label>
                    <input type="password" class="form-control" name="password" required>
                </div>
                <button class="btn btn-primary w-100" type="submit">Giriş Yap</button>
            </form>
        </div>
    </div>
</body>
</html>
