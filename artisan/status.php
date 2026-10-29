<?php
/**
 * Application status page – for every artisan account.
 * The status always comes from artisan_accounts.status (read fresh on every
 * page load) – never from whether a row exists in artisan_requests, because
 * reviewed requests are deleted.
 *   PENDING  → "Your application is currently under review."
 *   APPROVED → "🎉 Congratulations! …" + Go to Artisan Dashboard
 *   REJECTED → "Your artisan application was not approved." + Reason
 */
require '../config/database.php';
require '../includes/functions.php';
require '../includes/auth.php';

$artisanAccount = require_artisan($pdo);

$root = '../';
$pageTitle = 'Application Status';
$activePage = 'a_status';
$isArtisan = true;

// The pending application (if any) for this account
$application = null;
if ($artisanAccount['status'] === 'PENDING') {
    $stmt = $pdo->prepare('
        SELECT r.request_id, r.experience, r.created_at, s.skill_name, d.district_name
        FROM artisan_requests r
        INNER JOIN skills s    ON r.skill_id = s.skill_id
        INNER JOIN districts d ON r.district_id = d.district_id
        WHERE r.account_id = ?');
    $stmt->execute([$artisanAccount['account_id']]);
    $application = $stmt->fetch() ?: null;
}

$status = $artisanAccount['status'];

include '../includes/header.php';
?>
<section class="page-header">
    <div class="container">
        <h1>Welcome, <?= e($artisanAccount['name']) ?></h1>
        <p class="text-muted-ks mb-0">Your KalaSetu artisan account · <?= e($artisanAccount['email']) ?></p>
    </div>
</section>

<div class="container mt-4">
    <?php show_flash(); ?>
    <div class="ks-card static mx-auto" style="max-width:720px">
        <div class="card-body p-4 p-md-5 text-center">
            <p class="small text-muted-ks mb-1">Application Status</p>
            <p class="mb-4"><span class="risk-pill <?= request_status_class($status) ?> fs-6"><?= e($status) ?></span></p>

            <?php if ($status === 'PENDING'): ?>
                <span class="skill-icon mb-3" style="width:64px;height:64px;font-size:2rem"><i class="bi bi-hourglass-split"></i></span>
                <h2 class="h4">Your application is currently under review.</h2>
                <p class="text-muted-ks">The KalaSetu admin is checking your details. Log in again later – as soon as your application
                    is approved, this page becomes your <strong>Artisan Dashboard</strong> and your profile appears in the public artisan directory.</p>
                <?php if ($application): ?>
                    <dl class="row text-start small mx-auto mt-4 mb-0" style="max-width:420px">
                        <dt class="col-5">Application</dt><dd class="col-7">#<?= (int) $application['request_id'] ?></dd>
                        <dt class="col-5">Skill</dt><dd class="col-7"><?= e($application['skill_name']) ?></dd>
                        <dt class="col-5">District</dt><dd class="col-7"><?= e($application['district_name']) ?></dd>
                        <dt class="col-5">Experience</dt><dd class="col-7"><?= (int) $application['experience'] ?> years</dd>
                        <dt class="col-5">Submitted</dt><dd class="col-7"><?= e(date('d M Y, H:i', strtotime($application['created_at']))) ?></dd>
                    </dl>
                <?php endif; ?>

            <?php elseif ($status === 'REJECTED'): ?>
                <span class="skill-icon mb-3" style="width:64px;height:64px;font-size:2rem;color:#b3261e;background:rgba(179,38,30,.08)"><i class="bi bi-x-circle"></i></span>
                <h2 class="h4">Your artisan application was not approved.</h2>
                <?php if ($artisanAccount['admin_note']): ?>
                    <div class="alert alert-warning text-start small mt-3"><strong>Reason:</strong><br><?= nl2br(e($artisanAccount['admin_note'])) ?></div>
                <?php else: ?>
                    <p class="text-muted-ks"><strong>Reason:</strong> no reason was given.</p>
                <?php endif; ?>
                <?php if ($artisanAccount['reviewed_at']): ?>
                    <p class="small text-muted-ks">Reviewed on <?= e(date('d M Y', strtotime($artisanAccount['reviewed_at']))) ?></p>
                <?php endif; ?>
                <p class="mb-4">You can correct your details and apply again with the same account.</p>
                <a href="../register.php" class="btn btn-terracotta"><i class="bi bi-arrow-repeat me-1"></i>Apply again</a>

            <?php elseif ($artisanAccount['artisan_id']): /* APPROVED with a public artisan profile */ ?>
                <span class="skill-icon mb-3" style="width:64px;height:64px;font-size:2rem;color:#2e7d32;background:rgba(46,125,50,.1)"><i class="bi bi-patch-check-fill"></i></span>
                <h2 class="h3">🎉 Congratulations!</h2>
                <p class="fs-5 mb-1">Your application has been approved.</p>
                <p class="fs-5 fw-semibold" style="color:#2e7d32">You are now a registered KalaSetu Artisan.</p>
                <?php if ($artisanAccount['reviewed_at']): ?>
                    <p class="small text-muted-ks">Approved on <?= e(date('d M Y', strtotime($artisanAccount['reviewed_at']))) ?> · Artisan #<?= (int) $artisanAccount['artisan_id'] ?></p>
                <?php endif; ?>
                <a href="dashboard.php" class="btn btn-maroon btn-lg mt-2"><i class="bi bi-speedometer2 me-1"></i>Go to Artisan Dashboard</a>
                <a href="../artisan_details.php?id=<?= (int) $artisanAccount['artisan_id'] ?>" class="btn btn-outline-maroon btn-lg mt-2 ms-1" target="_blank" rel="noopener"><i class="bi bi-eye me-1"></i>See my public profile</a>

            <?php else: /* APPROVED, but the public artisan profile was deleted by the admin */ ?>
                <span class="skill-icon mb-3" style="width:64px;height:64px;font-size:2rem"><i class="bi bi-info-circle"></i></span>
                <h2 class="h4">Your application was approved, but your artisan profile is no longer listed.</h2>
                <p class="text-muted-ks mb-0">It was removed by the KalaSetu admin. Please contact the KalaSetu team if you think this is a mistake.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
