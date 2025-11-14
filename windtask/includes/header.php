<?php
require_once __DIR__ . '/../config.php';
$user = current_user();
$unread = $user ? fetch_notifications((int) $user['id'], true) : [];
?>
<!DOCTYPE html>
<html lang="tr" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <meta name="current-user" content="<?php echo e($user['id'] ?? ''); ?>">
    <title>WindTask</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4@5/bootstrap-4.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo e(base_url('assets/css/style.css')); ?>">
</head>
<body>
<div class="d-flex wrapper">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="main flex-grow-1">
        <nav class="navbar navbar-expand-lg navbar-light bg-body border-bottom px-3">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-secondary btn-sm d-lg-none" type="button" id="sidebarToggle">
                    <i class="fa fa-bars"></i>
                </button>
                <span class="fw-semibold">WindTask</span>
            </div>
            <form class="d-flex ms-auto me-3" role="search" id="globalSearchForm">
                <input class="form-control form-control-sm me-2" type="search" placeholder="Görev ara..." aria-label="Search">
                <button class="btn btn-outline-primary btn-sm" type="submit"><i class="fa fa-search"></i></button>
            </form>
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-outline-secondary" id="themeToggle" type="button">
                    <span class="light-icon">☀️</span>
                    <span class="dark-icon d-none">🌙</span>
                </button>
                <div class="dropdown">
                    <button class="btn btn-outline-primary position-relative" type="button" data-bs-toggle="dropdown">
                        <i class="fa fa-bell"></i>
                        <?php if ($unread): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?php echo count($unread); ?>
                            </span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-2" style="min-width: 280px; max-height: 400px; overflow-y: auto;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong>Bildirimler</strong>
                            <a href="<?php echo e(base_url('notifications.php')); ?>" class="text-decoration-none small">Tümü</a>
                        </div>
                        <?php if (!$unread): ?>
                            <p class="text-muted small mb-0">Yeni bildirim yok.</p>
                        <?php else: ?>
                            <?php foreach ($unread as $note): ?>
                                <a href="<?php echo e($note['link']); ?>" class="dropdown-item small">
                                    <div class="fw-semibold"><?php echo e($note['type']); ?></div>
                                    <div><?php echo e($note['message']); ?></div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <img src="<?php echo e($user['avatar'] ? base_url('uploads/' . $user['avatar']) : 'https://via.placeholder.com/32'); ?>" alt="avatar" class="rounded-circle me-1" width="32" height="32">
                        <?php echo e($user['name'] ?? 'Kullanıcı'); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?php echo e(base_url('profile.php')); ?>"><i class="fa fa-user me-2"></i>Profil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?php echo e(base_url('logout.php')); ?>"><i class="fa fa-sign-out-alt me-2"></i>Çıkış</a></li>
                    </ul>
                </div>
            </div>
        </nav>
        <main class="p-3">
