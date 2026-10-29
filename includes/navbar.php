<?php
/** Navigation bar – public version and admin version. */
function nav_link(string $href, string $label, string $key, string $active, string $icon = ''): string
{
    $cls = 'nav-link' . ($key === $active ? ' active' : '');
    $aria = $key === $active ? ' aria-current="page"' : '';
    $i = $icon ? '<i class="bi ' . $icon . ' me-1"></i>' : '';
    return '<li class="nav-item"><a class="' . $cls . '" href="' . e($href) . '"' . $aria . '>' . $i . e($label) . '</a></li>';
}
?>
<div class="toran-strip" aria-hidden="true"></div>
<nav class="navbar navbar-expand-xl ks-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand" href="<?= e($root) ?><?= $isAdmin ? 'admin/dashboard.php' : ($isArtisan ? 'artisan/' . (isset($artisanAccount) ? artisan_home($artisanAccount) : 'login.php') : 'index.php') ?>">
            <span class="brand-mark"><i class="bi bi-flower1"></i></span>
            <span class="brand-text">KalaSetu<?php if ($isAdmin): ?> <small class="brand-admin">Admin</small><?php elseif ($isArtisan): ?> <small class="brand-admin">Artisan</small><?php endif; ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
        <?php if ($isAdmin): ?>
            <?php if (!empty($_SESSION['admin_logged_in'])): ?>
            <ul class="navbar-nav me-auto mb-2 mb-xl-0">
                <?= nav_link($root . 'admin/dashboard.php', 'Dashboard', 'dashboard', $activePage, 'bi-speedometer2') ?>
                <?= nav_link($root . 'admin/skills.php',    'Skills',    'skills',    $activePage, 'bi-palette') ?>
                <?= nav_link($root . 'admin/artisans.php',  'Artisans',  'artisans',  $activePage, 'bi-people') ?>
                <?= nav_link($root . 'admin/products.php',  'Products',  'products',  $activePage, 'bi-box-seam') ?>
                <?= nav_link($root . 'admin/training.php',  'Training',  'training',  $activePage, 'bi-mortarboard') ?>
                <?= nav_link($root . 'admin/interests.php', 'Interests', 'interests', $activePage, 'bi-envelope-heart') ?>
                <?php $pendingRequests = pending_request_count($pdo); ?>
                <li class="nav-item">
                    <a class="nav-link<?= $activePage === 'requests' ? ' active' : '' ?>" href="<?= e($root) ?>admin/requests.php"<?= $activePage === 'requests' ? ' aria-current="page"' : '' ?>>
                        <i class="bi bi-person-plus me-1"></i>Requests<?php if ($pendingRequests): ?> <span class="badge rounded-pill nav-badge" title="<?= $pendingRequests ?> pending"><?= $pendingRequests ?></span><?php endif; ?>
                    </a>
                </li>
            </ul>
            <div class="d-flex gap-2 align-items-center">
                <a href="<?= e($root) ?>index.php" class="btn btn-sm btn-outline-maroon text-nowrap" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>View site</a>
                <a href="<?= e($root) ?>admin/logout.php" class="btn btn-sm btn-maroon text-nowrap"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
            </div>
            <?php endif; ?>
        <?php elseif ($isArtisan): ?>
            <?php if (!empty($artisanAccount)): ?>
            <ul class="navbar-nav me-auto mb-2 mb-xl-0">
                <?php if ($artisanAccount['status'] === 'APPROVED' && $artisanAccount['artisan_id']): ?>
                    <?= nav_link($root . 'artisan/dashboard.php', 'Dashboard',   'a_dashboard', $activePage, 'bi-speedometer2') ?>
                    <?= nav_link($root . 'artisan/profile.php',   'My Profile',  'a_profile',   $activePage, 'bi-person-badge') ?>
                    <?= nav_link($root . 'artisan/products.php',  'My Products', 'a_products',  $activePage, 'bi-box-seam') ?>
                    <?= nav_link($root . 'artisan_details.php?id=' . (int) $artisanAccount['artisan_id'], 'Public Profile', 'a_public', $activePage, 'bi-eye') ?>
                    <?= nav_link($root . 'artisan/status.php', 'Application Status', 'a_status', $activePage, 'bi-patch-check') ?>
                <?php else: ?>
                    <?= nav_link($root . 'artisan/status.php', 'Application Status', 'a_status', $activePage, 'bi-hourglass-split') ?>
                <?php endif; ?>
            </ul>
            <div class="d-flex gap-2 align-items-center">
                <span class="small text-muted-ks d-none d-xxl-inline text-nowrap"><i class="bi bi-person-circle me-1"></i><?= e($artisanAccount['name']) ?></span>
                <a href="<?= e($root) ?>index.php" class="btn btn-sm btn-outline-maroon text-nowrap"><i class="bi bi-globe me-1"></i>View site</a>
                <a href="<?= e($root) ?>artisan/logout.php" class="btn btn-sm btn-maroon text-nowrap"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
            </div>
            <?php else: ?>
            <ul class="navbar-nav me-auto mb-2 mb-xl-0">
                <?= nav_link($root . 'index.php', 'Back to website', 'home', $activePage, 'bi-arrow-left') ?>
            </ul>
            <?php endif; ?>
        <?php else: ?>
            <ul class="navbar-nav me-auto mb-2 mb-xl-0">
                <?= nav_link($root . 'index.php',            'Home',          'home',     $activePage) ?>
                <?= nav_link($root . 'skills.php',           'Skills',        'skills',   $activePage) ?>
                <?= nav_link($root . 'artisans.php',         'Artisans',      'artisans', $activePage) ?>
                <?= nav_link($root . 'products.php',         'Products',      'products', $activePage) ?>
                <?= nav_link($root . 'training.php',         'Learn',         'training', $activePage) ?>
                <?= nav_link($root . 'at_risk.php',          'Skills at Risk','at_risk',  $activePage) ?>
                <?= nav_link($root . 'popular_artisans.php', 'Most Discovered','popular', $activePage) ?>
            </ul>
            <?php if (!empty($_SESSION['artisan_account_id'])): ?>
                <a href="<?= e($root) ?>artisan/login.php" class="btn btn-sm btn-terracotta me-xl-2 mb-2 mb-xl-0 text-nowrap"><i class="bi bi-person-badge me-1"></i>My Artisan Account</a>
            <?php else: ?>
                <a href="<?= e($root) ?>register.php" class="btn btn-sm <?= $activePage === 'register' ? 'btn-terracotta' : 'btn-outline-maroon' ?> me-xl-2 mb-2 mb-xl-0 text-nowrap"><i class="bi bi-person-plus me-1"></i>Join as Artisan</a>
                <a href="<?= e($root) ?>artisan/login.php" class="btn btn-sm btn-link text-maroon text-decoration-none me-xl-2 mb-2 mb-xl-0 text-nowrap px-1" title="Artisan login"><i class="bi bi-box-arrow-in-right me-1"></i>Artisan Login</a>
            <?php endif; ?>
            <form class="d-flex nav-search" action="<?= e($root) ?>search.php" method="get" role="search">
                <input class="form-control form-control-sm" type="search" name="q" placeholder="Search KalaSetu" title="Search skills, artisans and products"
                       aria-label="Search" value="<?= e($_GET['q'] ?? '') ?>" required minlength="2" maxlength="100">
                <button class="btn btn-sm btn-maroon ms-1" type="submit" aria-label="Search"><i class="bi bi-search"></i></button>
            </form>
        <?php endif; ?>
        </div>
    </div>
</nav>
