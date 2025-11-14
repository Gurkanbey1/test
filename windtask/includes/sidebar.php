<?php $user = current_user(); ?>
<aside class="sidebar bg-dark text-white d-flex flex-column">
    <div class="p-3 border-bottom border-secondary">
        <div class="fw-bold text-uppercase small">Navigasyon</div>
    </div>
    <ul class="nav nav-pills flex-column mb-auto p-2">
        <li class="nav-item">
            <a href="<?php echo e(base_url('index.php')); ?>" class="nav-link text-white">
                <i class="fa fa-gauge me-2"></i> Dashboard
            </a>
        </li>
        <li>
            <a href="<?php echo e(base_url('task_create.php')); ?>" class="nav-link text-white">
                <i class="fa fa-plus-circle me-2"></i> Görev Oluştur
            </a>
        </li>
        <li>
            <a href="<?php echo e(base_url('index.php')); ?>#tasksTable" class="nav-link text-white">
                <i class="fa fa-layer-group me-2"></i> Görevler
            </a>
        </li>
        <li>
            <a href="<?php echo e(base_url('notifications.php')); ?>" class="nav-link text-white">
                <i class="fa fa-bell me-2"></i> Bildirimler
            </a>
        </li>
        <li>
            <a href="<?php echo e(base_url('profile.php')); ?>" class="nav-link text-white">
                <i class="fa fa-user me-2"></i> Profil
            </a>
        </li>
        <?php if ($user && $user['role'] === 'admin'): ?>
            <li>
                <a href="<?php echo e(base_url('admin_users.php')); ?>" class="nav-link text-white">
                    <i class="fa fa-users-cog me-2"></i> Kullanıcılar
                </a>
            </li>
        <?php endif; ?>
    </ul>
</aside>
