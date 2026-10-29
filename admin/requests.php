<?php
require '../config/database.php';
require '../includes/functions.php';
require '../includes/auth.php';
require '../includes/review.php';
require_admin();

$root = '../';
$pageTitle = 'Artisan Registration Requests';
$activePage = 'requests';
$isAdmin = true;

$statuses = ['PENDING', 'APPROVED', 'REJECTED'];

/* ======================= HANDLE POST (approve / reject) ======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    if (!verify_csrf()) {
        set_flash('danger', 'Session expired. Please try again.');
        redirect('requests.php');
    }

    $note = trim($_POST['admin_note'] ?? '');
    if (mb_strlen($note) > 255) {
        set_flash('danger', 'The note must be under 255 characters.');
        redirect('requests.php?id=' . $id);
    }

    // ---------- APPROVE : one click does everything (see includes/review.php) ----------
    //   INSERT artisans → lastInsertId → UPDATE artisan_accounts → DELETE artisan_requests → COMMIT
    if (($_POST['action'] ?? '') === 'approve') {
        try {
            $res = approve_artisan_request($pdo, $id, $note);
            set_flash('success', 'Approved successfully: ' . $res['name'] . ' is now artisan #' . $res['artisan_id']
                . ' and appears in Admin → Artisans and on the public site.'
                . ($res['account_id'] ? ' Their account #' . $res['account_id'] . ' is now APPROVED – they will see the Artisan Dashboard when they log in.' : ''));
            redirect('requests.php');
        } catch (Throwable $e) {
            set_flash(($e instanceof ReviewError && str_contains($e->getMessage(), 'already been reviewed')) ? 'warning' : 'danger', review_error_message('approve', $e));
            redirect(($e instanceof ReviewError && str_contains($e->getMessage(), 'already been reviewed')) ? 'requests.php' : 'requests.php?id=' . $id);
        }
    }

    // ---------- REJECT : account → REJECTED (+ reason), request deleted, no artisan ----------
    if (($_POST['action'] ?? '') === 'reject') {
        try {
            $res = reject_artisan_request($pdo, $id, $note);
            delete_image($res['profile_image'] ?: null);   // the applicant's photo is not kept
            set_flash('success', 'Rejected: the application from ' . $res['name'] . ' was removed.'
                . ($res['account_id'] ? ' Their account #' . $res['account_id'] . ' is now REJECTED and they will see your reason when they log in.' : ''));
            redirect('requests.php');
        } catch (Throwable $e) {
            set_flash(($e instanceof ReviewError && str_contains($e->getMessage(), 'already been reviewed')) ? 'warning' : 'danger', review_error_message('reject', $e));
            redirect(($e instanceof ReviewError && str_contains($e->getMessage(), 'already been reviewed')) ? 'requests.php' : 'requests.php?id=' . $id);
        }
    }

    redirect('requests.php');
}

/* ======================= DETAIL VIEW ======================= */
$detailId = get_int('id');
$request = null;
if ($detailId) {
    $stmt = $pdo->prepare('
        SELECT r.*, s.skill_name, s.category, s.district_id AS skill_district_id,
               d.district_name, ac.email AS account_email, ac.created_at AS account_created
        FROM artisan_requests r
        INNER JOIN districts d ON r.district_id = d.district_id
        INNER JOIN skills s    ON r.skill_id = s.skill_id
        LEFT JOIN artisan_accounts ac ON r.account_id = ac.account_id
        WHERE r.request_id = ?');
    $stmt->execute([$detailId]);
    $request = $stmt->fetch();
    if (!$request) {
        set_flash('warning', 'Request not found.');
        redirect('requests.php');
    }

    // Checks shown to the admin before deciding
    $warnings = [];
    if ($request['status'] === 'PENDING') {
        $stmt = $pdo->prepare('SELECT artisan_id FROM artisans WHERE skill_id = ? AND artisan_name = ? LIMIT 1');
        $stmt->execute([$request['skill_id'], $request['artisan_name']]);
        if ($dupId = $stmt->fetchColumn()) {
            $warnings[] = 'An artisan with the same name already exists for this skill (<a href="../artisan_details.php?id=' . (int) $dupId . '" target="_blank" rel="noopener">view profile</a>). Check this is not a duplicate.';
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM artisans WHERE contact LIKE ? OR contact LIKE ?');
        $stmt->execute(['%' . $request['email'] . '%', '%' . format_phone($request['phone']) . '%']);
        if ($stmt->fetchColumn() > 0) {
            $warnings[] = 'This email or phone number already appears in an existing artisan\'s contact details.';
        }
        if ((int) $request['skill_district_id'] !== (int) $request['district_id']) {
            $warnings[] = 'The skill has been moved to another district since this request was submitted. Approval is blocked until this is resolved.';
        }
    }
    $hasPhoto = $request['profile_image'] && is_file(__DIR__ . '/../assets/images/uploads/' . $request['profile_image']);
}

/* ======================= LIST VIEW ======================= */
// PENDING  → applications waiting in `artisan_requests`
// APPROVED / REJECTED → reviewed applications are deleted from `artisan_requests`;
//            their result is kept in `artisan_accounts`, so the history comes from there.
$filter = strtoupper($_GET['status'] ?? 'PENDING');
if (!in_array($filter, $statuses, true)) {
    $filter = 'PENDING';
}

$counts = $pdo->query("
    SELECT (SELECT COUNT(*) FROM artisan_requests WHERE status = 'PENDING')  AS PENDING,
           (SELECT COUNT(*) FROM artisan_accounts WHERE status = 'APPROVED') AS APPROVED,
           (SELECT COUNT(*) FROM artisan_accounts WHERE status = 'REJECTED') AS REJECTED
")->fetch();

if ($filter === 'PENDING') {
    $requests = $pdo->query("
        SELECT r.request_id, r.account_id, r.artisan_name, r.experience, r.status, r.created_at,
               s.skill_name, d.district_name
        FROM artisan_requests r
        INNER JOIN skills s    ON r.skill_id = s.skill_id
        INNER JOIN districts d ON r.district_id = d.district_id
        WHERE r.status = 'PENDING'
        ORDER BY r.created_at ASC")->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT ac.account_id, ac.name, ac.email, ac.status, ac.admin_note, ac.reviewed_at, ac.artisan_id,
               s.skill_name, d.district_name
        FROM artisan_accounts ac
        LEFT JOIN artisans a   ON ac.artisan_id = a.artisan_id
        LEFT JOIN skills s     ON a.skill_id = s.skill_id
        LEFT JOIN districts d  ON s.district_id = d.district_id
        WHERE ac.status = ?
        ORDER BY ac.reviewed_at DESC, ac.account_id DESC");
    $stmt->execute([$filter]);
    $reviewed = $stmt->fetchAll();
}

include '../includes/header.php';
?>
<div class="container py-4">
<?php if ($request): ?>
    <!-- ================= REQUEST DETAILS ================= -->
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="requests.php">Registration Requests</a></li><li class="breadcrumb-item active">#<?= (int) $request['request_id'] ?></li></ol></nav>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h1 class="h3 mb-0">Request #<?= (int) $request['request_id'] ?></h1>
        <span class="risk-pill <?= request_status_class($request['status']) ?>"><?= e($request['status']) ?></span>
    </div>
    <?php show_flash(); ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="ks-card static"><div class="card-body p-4">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <?php if ($hasPhoto): ?>
                        <img src="<?= e($root . 'assets/images/uploads/' . $request['profile_image']) ?>" alt="Photo of <?= e($request['artisan_name']) ?>" class="avatar avatar-lg avatar-img">
                    <?php else: ?>
                        <?= artisan_avatar(['artisan_id' => $request['request_id'], 'artisan_name' => $request['artisan_name']], $root, 'lg') ?>
                    <?php endif; ?>
                    <div>
                        <h2 class="h4 mb-1"><?= e($request['artisan_name']) ?></h2>
                        <div class="meta-line"><i class="bi bi-palette"></i> <?= e($request['skill_name']) ?> &nbsp;·&nbsp; <i class="bi bi-geo-alt"></i> <?= e($request['district_name']) ?></div>
                        <div class="meta-line"><i class="bi bi-hourglass-split"></i> <?= (int) $request['experience'] ?> years of experience<?= !empty($request['age']) ? ' · Age ' . (int) $request['age'] : '' ?></div>
                    </div>
                </div>
                <dl class="row mb-0">
                    <dt class="col-sm-4">Phone</dt><dd class="col-sm-8"><?= e(format_phone($request['phone'])) ?></dd>
                    <dt class="col-sm-4">Email</dt><dd class="col-sm-8"><a href="mailto:<?= e($request['email']) ?>"><?= e($request['email']) ?></a></dd>
                    <dt class="col-sm-4">District</dt><dd class="col-sm-8"><?= e($request['district_name']) ?></dd>
                    <dt class="col-sm-4">Skill</dt><dd class="col-sm-8"><a href="../skill_details.php?id=<?= (int) $request['skill_id'] ?>" target="_blank" rel="noopener"><?= e($request['skill_name']) ?></a> <span class="text-muted-ks small">(<?= e($request['category']) ?>)</span></dd>
                    <dt class="col-sm-4">Teaches apprentices</dt><dd class="col-sm-8"><?= $request['accepts_apprentices'] ? 'Yes' : 'No' ?></dd>
                    <dt class="col-sm-4">Login account</dt><dd class="col-sm-8"><?= $request['account_id']
                        ? '#' . (int) $request['account_id'] . ' · ' . e($request['account_email'])
                        : '<span class="text-muted-ks">None (sent before artisan accounts existed)</span>' ?></dd>
                    <dt class="col-sm-4">Submitted</dt><dd class="col-sm-8"><?= e(date('d M Y, H:i', strtotime($request['created_at']))) ?></dd>
                    <?php if ($request['reviewed_at']): ?>
                        <dt class="col-sm-4">Reviewed</dt><dd class="col-sm-8"><?= e(date('d M Y, H:i', strtotime($request['reviewed_at']))) ?></dd>
                    <?php endif; ?>
                    <?php if ($request['admin_note']): ?>
                        <dt class="col-sm-4">Admin note</dt><dd class="col-sm-8"><?= e($request['admin_note']) ?></dd>
                    <?php endif; ?>
                </dl>
                <hr>
                <h3 class="h6">Description</h3>
                <p class="mb-0"><?= nl2br(e($request['bio'])) ?></p>
            </div></div>
        </div>

        <div class="col-lg-4">
            <?php if ($request['status'] === 'PENDING'): ?>
                <?php foreach ($warnings as $w): ?>
                    <div class="alert alert-warning small"><i class="bi bi-exclamation-triangle me-1"></i><?= $w /* built from escaped/int values above */ ?></div>
                <?php endforeach; ?>
                <div class="ks-card static mb-3"><div class="card-body">
                    <h2 class="h5">Review</h2>
                    <p class="small text-muted-ks">Approving creates a new artisan with these details, marks the applicant's account
                        <strong>APPROVED</strong> and removes this request. Their public contact will be:<br>
                        <strong><?= e(artisan_contact($request['phone'], $request['email'])) ?></strong></p>
                    <form method="post" action="requests.php" data-confirm="Approve <?= e($request['artisan_name']) ?> and add them as an artisan?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="id" value="<?= (int) $request['request_id'] ?>">
                        <label class="form-label small" for="approve_note">Note <span class="fw-normal text-muted-ks">(optional, internal)</span></label>
                        <input type="text" id="approve_note" name="admin_note" class="form-control form-control-sm mb-2" maxlength="255">
                        <button type="submit" class="btn btn-success w-100"><i class="bi bi-check-circle me-1"></i>Approve</button>
                    </form>
                    <hr>
                    <form method="post" action="requests.php" data-confirm="Reject this request? The applicant's account will be marked REJECTED, this request removed and the photo deleted.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="id" value="<?= (int) $request['request_id'] ?>">
                        <label class="form-label small" for="reject_note">Reason <span class="fw-normal text-muted-ks">(optional, shown to the applicant)</span></label>
                        <textarea id="reject_note" name="admin_note" rows="2" class="form-control form-control-sm mb-2" maxlength="255" placeholder="e.g. Incomplete details"></textarea>
                        <button type="submit" class="btn btn-outline-danger w-100"><i class="bi bi-x-circle me-1"></i>Reject</button>
                    </form>
                </div></div>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>
    <!-- ================= LIST ================= -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h1 class="h3 mb-0">Artisan Registration Requests</h1>
        <div class="d-flex gap-2">
            <a href="diagnostics.php" class="btn btn-sm btn-outline-maroon"><i class="bi bi-clipboard2-pulse me-1"></i>Diagnostics</a>
            <a href="../register.php" class="btn btn-sm btn-outline-maroon" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Public registration form</a>
        </div>
    </div>
    <?php show_flash(); ?>

    <ul class="nav nav-pills mb-3 gap-1">
        <?php foreach ($statuses as $st): $act = $filter === $st; ?>
            <li class="nav-item">
                <a class="nav-link <?= $act ? 'active' : '' ?>" style="<?= $act ? 'background:var(--ks-maroon)' : 'color:var(--ks-maroon)' ?>" href="requests.php?status=<?= $st ?>">
                    <?= ucfirst(strtolower($st)) ?> <span class="badge rounded-pill <?= $act ? 'bg-light text-dark' : 'nav-badge' ?>"><?= (int) $counts[$st] ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($filter === 'PENDING'): ?>
    <div class="ks-table table-responsive">
        <table class="table table-hover">
            <thead><tr><th>#</th><th>Applicant</th><th>Skill</th><th>District</th><th class="text-center">Experience</th><th>Submitted</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($requests as $r): ?>
                <tr>
                    <td class="text-muted-ks"><?= (int) $r['request_id'] ?></td>
                    <td><strong><?= e($r['artisan_name']) ?></strong></td>
                    <td><?= e($r['skill_name']) ?></td>
                    <td><?= e($r['district_name']) ?></td>
                    <td class="text-center"><?= (int) $r['experience'] ?> yrs</td>
                    <td class="small text-nowrap"><?= e(date('d M Y', strtotime($r['created_at']))) ?></td>
                    <td><span class="risk-pill <?= request_status_class($r['status']) ?>"><?= e($r['status']) ?></span></td>
                    <td class="text-end text-nowrap">
                        <a href="requests.php?id=<?= (int) $r['request_id'] ?>" class="btn btn-sm btn-outline-maroon" title="Full details"><i class="bi bi-eye"></i> Details</a>
                        <form method="post" action="requests.php" class="d-inline" data-confirm="Approve <?= e($r['artisan_name']) ?> and add them as an artisan?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="id" value="<?= (int) $r['request_id'] ?>">
                            <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check-circle"></i> Approve</button>
                        </form>
                        <div class="dropdown d-inline-block">
                            <button type="button" class="btn btn-sm btn-outline-danger dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"><i class="bi bi-x-circle"></i> Reject</button>
                            <div class="dropdown-menu dropdown-menu-end p-3" style="min-width:280px">
                                <form method="post" action="requests.php" data-confirm="Reject the application from <?= e($r['artisan_name']) ?>?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="id" value="<?= (int) $r['request_id'] ?>">
                                    <label class="form-label small text-start d-block" for="rn<?= (int) $r['request_id'] ?>">Reason <span class="text-muted-ks">(optional, shown to the applicant)</span></label>
                                    <textarea id="rn<?= (int) $r['request_id'] ?>" name="admin_note" rows="2" maxlength="255" class="form-control form-control-sm mb-2"></textarea>
                                    <button type="submit" class="btn btn-sm btn-danger w-100">Reject application</button>
                                </form>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$requests): ?>
                <tr><td colspan="8" class="text-center text-muted-ks py-4">No pending requests – all caught up.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <p class="small text-muted-ks"><i class="bi bi-info-circle me-1"></i>Reviewed applications are removed from the request queue. This list shows the
        result stored on each applicant's account (<code>artisan_accounts</code>).</p>
    <div class="ks-table table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Account</th><th>Applicant</th><th><?= $filter === 'APPROVED' ? 'Skill' : 'Reason' ?></th><th>Reviewed</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($reviewed as $r): ?>
                <tr>
                    <td class="text-muted-ks"><?= (int) $r['account_id'] ?></td>
                    <td><strong><?= e($r['name']) ?></strong><br><span class="small text-muted-ks"><?= e($r['email']) ?></span></td>
                    <?php if ($filter === 'APPROVED'): ?>
                        <td><?= $r['artisan_id'] ? e($r['skill_name']) . ' <span class="small text-muted-ks">· ' . e($r['district_name']) . '</span>' : '<span class="text-muted-ks small">Artisan profile deleted</span>' ?></td>
                    <?php else: ?>
                        <td class="small"><?= $r['admin_note'] ? e($r['admin_note']) : '<span class="text-muted-ks">—</span>' ?></td>
                    <?php endif; ?>
                    <td class="small text-nowrap"><?= $r['reviewed_at'] ? e(date('d M Y', strtotime($r['reviewed_at']))) : '—' ?></td>
                    <td><span class="risk-pill <?= request_status_class($r['status']) ?>"><?= e($r['status']) ?></span></td>
                    <td class="text-end text-nowrap">
                        <?php if ($r['artisan_id']): ?>
                            <a href="../artisan_details.php?id=<?= (int) $r['artisan_id'] ?>" class="btn btn-sm btn-outline-maroon" target="_blank" rel="noopener"><i class="bi bi-person"></i> Profile</a>
                            <a href="artisans.php?action=edit&id=<?= (int) $r['artisan_id'] ?>" class="btn btn-sm btn-outline-maroon"><i class="bi bi-pencil"></i> Edit</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$reviewed): ?>
                <tr><td colspan="6" class="text-center text-muted-ks py-4">No <?= strtolower($filter) ?> applications yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
<?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
