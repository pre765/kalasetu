<?php
/**
 * Admin → Diagnostics
 * Checks, on the machine that is actually running KalaSetu:
 *   - which folder the browser is running and which database PDO is connected to
 *   - every table, engine, charset, required column and foreign key
 *   - that artisan_accounts / artisan_requests / artisans agree with each other
 *   - a DRY-RUN APPROVE of any pending request: runs the real INSERT / UPDATE /
 *     DELETE statements inside a transaction and then ROLLS BACK, so it shows the
 *     exact database error (if any) without changing anything.
 */
require '../config/database.php';
require '../includes/functions.php';
require '../includes/auth.php';
require '../includes/review.php';
require_admin();

$root = '../';
$pageTitle = 'Diagnostics';
$activePage = 'diagnostics';
$isAdmin = true;

const KALASETU_CODE_VERSION = 'artisan-accounts v2 (automatic approve/reject) · 2026-10-22';
const EXPECTED_PROJECT_DIR  = '/opt/lampp/htdocs/kalasetu/kalasetu';

$checks = [];   // [level, area, message]   level: ok | warn | fail
function check(string $level, string $area, string $msg): void
{
    global $checks;
    $checks[] = [$level, $area, $msg];
}

/* ---------------- Dry-run approve (POST) ---------------- */
$dryRun = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'dryrun') {
    if (!verify_csrf()) {
        set_flash('danger', 'Session expired. Please try again.');
        redirect('diagnostics.php');
    }
    $rid = (int) ($_POST['id'] ?? 0);
    try {
        $res = approve_artisan_request($pdo, $rid, null, true);
        $dryRun = ['ok' => true, 'id' => $rid, 'steps' => $res['steps']];
    } catch (Throwable $e) {
        $dryRun = ['ok' => false, 'id' => $rid, 'type' => get_class($e), 'error' => $e->getMessage(),
                   'code' => $e instanceof PDOException ? $e->getCode() : ''];
    }
}

/* ---------------- 1. Running project ---------------- */
$projectDir = realpath(__DIR__ . '/..');
check($projectDir === EXPECTED_PROJECT_DIR ? 'ok' : 'warn', 'Project',
    'Running files from <code>' . e($projectDir) . '</code>'
    . ($projectDir === EXPECTED_PROJECT_DIR ? '' : ' – expected <code>' . EXPECTED_PROJECT_DIR . '</code>. Make sure you edit and open the same folder.'));
check('ok', 'Project', 'Code version: <strong>' . e(KALASETU_CODE_VERSION) . '</strong>. If you do not see this page after updating, the browser is running an older copy.');
check('ok', 'Project', 'URL: <code>' . e(($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '')) . '</code> · PHP ' . e(PHP_VERSION));

/* ---------------- 2. Database connection ---------------- */
$dbInUse = $pdo->query('SELECT DATABASE()')->fetchColumn();
check($dbInUse === 'kalasetu_db' ? 'ok' : 'fail', 'Database',
    'PDO is connected to <strong>' . e($dbInUse) . '</strong> (config/database.php DB_NAME = <code>' . e(DB_NAME) . '</code>)'
    . ($dbInUse === 'kalasetu_db' ? '' : ' – it must be kalasetu_db.'));
check('ok', 'Database', 'Server: ' . e($pdo->query('SELECT VERSION()')->fetchColumn()) . ' · host ' . e(DB_HOST) . ':' . e(DB_PORT));
check($pdo->getAttribute(PDO::ATTR_ERRMODE) === PDO::ERRMODE_EXCEPTION ? 'ok' : 'fail', 'Database',
    'PDO error mode: ' . ($pdo->getAttribute(PDO::ATTR_ERRMODE) === PDO::ERRMODE_EXCEPTION ? 'ERRMODE_EXCEPTION (errors are not silently ignored)' : 'NOT exception mode – failed queries would be silent'));
check(APP_DEBUG ? 'ok' : 'warn', 'Database', 'APP_DEBUG is ' . (APP_DEBUG ? 'ON – real database errors are shown to the admin.' : 'OFF – errors only go to the PHP error log.'));
$otherDbs = $pdo->query("SHOW DATABASES LIKE 'kalasetu%'")->fetchAll(PDO::FETCH_COLUMN);
if (count($otherDbs) > 1) {
    check('warn', 'Database', 'Several KalaSetu databases exist: <code>' . e(implode(', ', $otherDbs)) . '</code>. The site uses only <strong>' . e($dbInUse) . '</strong> – check phpMyAdmin is showing the same one.');
}

/* ---------------- 3. Tables, engines, charset ---------------- */
$tableInfo = [];
foreach ($pdo->query("SELECT TABLE_NAME, ENGINE, TABLE_COLLATION, TABLE_ROWS FROM information_schema.TABLES
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'") as $t) {
    $tableInfo[$t['TABLE_NAME']] = $t;
}
$required = ['districts', 'skills', 'artisans', 'products', 'training', 'visitor_interest', 'artisan_requests', 'artisan_accounts'];
foreach ($required as $t) {
    if (!isset($tableInfo[$t])) {
        check('fail', 'Tables', "Table <code>$t</code> is missing." . ($t === 'artisan_accounts' ? ' Import <code>database/artisan_auth_upgrade.sql</code>.' : ''));
        continue;
    }
    $count = (int) $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    $engineOk = $tableInfo[$t]['ENGINE'] === 'InnoDB';
    check($engineOk ? 'ok' : 'fail', 'Tables', "<code>$t</code>: $count rows · engine " . e($tableInfo[$t]['ENGINE'])
        . ' · ' . e($tableInfo[$t]['TABLE_COLLATION'])
        . ($engineOk ? '' : ' – must be InnoDB, otherwise transactions and ROLLBACK do nothing. Run artisan_auth_upgrade.sql.'));
}

/* ---------------- 4. Columns ---------------- */
$cols = [];
foreach ($pdo->query("SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, CHARACTER_MAXIMUM_LENGTH, CHARACTER_SET_NAME
                      FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()") as $c) {
    $cols[$c['TABLE_NAME']][$c['COLUMN_NAME']] = $c;
}
$expected = [
    'artisans'         => ['artisan_id', 'skill_id', 'artisan_name', 'age', 'experience', 'bio', 'contact', 'accepts_apprentices', 'profile_image', 'profile_views'],
    'artisan_requests' => ['request_id', 'account_id', 'district_id', 'skill_id', 'artisan_name', 'age', 'phone', 'email', 'experience', 'bio', 'accepts_apprentices', 'profile_image', 'status', 'created_at'],
    'artisan_accounts' => ['account_id', 'name', 'email', 'phone', 'password_hash', 'status', 'artisan_id', 'admin_note', 'reviewed_at', 'created_at'],
];
foreach ($expected as $t => $list) {
    if (!isset($cols[$t])) {
        continue;
    }
    $missing = array_diff($list, array_keys($cols[$t]));
    check($missing ? 'fail' : 'ok', 'Columns', "<code>$t</code>: " . ($missing
        ? 'missing column(s) <code>' . e(implode(', ', $missing)) . '</code> – run artisan_auth_upgrade.sql.'
        : 'all ' . count($list) . ' required columns present.'));
}
if (isset($cols['artisans'])) {
    // Approve inserts exactly these columns. Any other NOT NULL column without a default makes the INSERT fail.
    $inserted = ['skill_id', 'artisan_name', 'age', 'experience', 'bio', 'contact', 'accepts_apprentices', 'profile_image'];
    foreach ($cols['artisans'] as $name => $c) {
        $autoInc = str_contains($c['EXTRA'], 'auto_increment');
        if ($c['IS_NULLABLE'] === 'NO' && $c['COLUMN_DEFAULT'] === null && !$autoInc && !in_array($name, $inserted, true)) {
            check('fail', 'Columns', "<code>artisans.$name</code> is NOT NULL without a default, but approval does not fill it → INSERT fails. Give it a default or allow NULL.");
        }
    }
    foreach (['age', 'profile_image'] as $n) {
        if (isset($cols['artisans'][$n]) && $cols['artisans'][$n]['IS_NULLABLE'] === 'NO') {
            check('warn', 'Columns', "<code>artisans.$n</code> is NOT NULL – approving a request that has no $n will fail with \"Column '$n' cannot be null\". "
                . "Fix: <code>ALTER TABLE artisans MODIFY $n " . e($cols['artisans'][$n]['COLUMN_TYPE']) . ' NULL;</code>');
        }
    }
    if (isset($cols['artisans']['artisan_id']) && !str_contains($cols['artisans']['artisan_id']['EXTRA'], 'auto_increment')) {
        check('fail', 'Columns', '<code>artisans.artisan_id</code> is not AUTO_INCREMENT – lastInsertId() would return 0.');
    }
    if (isset($cols['artisans']['contact']) && (int) $cols['artisans']['contact']['CHARACTER_MAXIMUM_LENGTH'] < 100) {
        check('warn', 'Columns', '<code>artisans.contact</code> is only ' . (int) $cols['artisans']['contact']['CHARACTER_MAXIMUM_LENGTH'] . ' characters – "phone · email" can be up to 98.');
    }
    if (isset($cols['artisans']['contact']) && !str_starts_with((string) $cols['artisans']['contact']['CHARACTER_SET_NAME'], 'utf8')) {
        check('warn', 'Columns', '<code>artisans.contact</code> uses charset ' . e($cols['artisans']['contact']['CHARACTER_SET_NAME']) . ' – the "·" separator needs utf8mb4.');
    }
}
foreach (['artisan_requests', 'artisan_accounts'] as $t) {
    if (isset($cols[$t]['status']) && $cols[$t]['status']['COLUMN_TYPE'] !== "enum('PENDING','APPROVED','REJECTED')") {
        check('fail', 'Columns', "<code>$t.status</code> is " . e($cols[$t]['status']['COLUMN_TYPE']) . " – expected enum('PENDING','APPROVED','REJECTED').");
    }
}

/* ---------------- 5. Foreign keys ---------------- */
$fks = $pdo->query("SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME, CONSTRAINT_NAME
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL
                    ORDER BY TABLE_NAME, COLUMN_NAME")->fetchAll();
$fkSet = [];
foreach ($fks as $f) {
    $fkSet[$f['TABLE_NAME'] . '.' . $f['COLUMN_NAME']] = $f['REFERENCED_TABLE_NAME'];
}
foreach (['artisans.skill_id' => 'skills', 'artisan_accounts.artisan_id' => 'artisans', 'artisan_requests.account_id' => 'artisan_accounts', 'products.artisan_id' => 'artisans'] as $k => $ref) {
    check(($fkSet[$k] ?? '') === $ref ? 'ok' : 'warn', 'Foreign keys', "<code>$k → $ref</code> " . (($fkSet[$k] ?? '') === $ref ? 'present.' : 'is missing.'));
}

/* ---------------- 6. Data consistency ---------------- */
if (isset($tableInfo['artisan_accounts'], $cols['artisan_requests']['account_id'])) {
    $n = (int) $pdo->query("SELECT COUNT(*) FROM artisan_requests WHERE status <> 'PENDING'")->fetchColumn();
    check('ok', 'Data', $n ? "$n old reviewed row(s) are kept in artisan_requests as history (from before automatic approval). They are not shown in the request queue."
                           : 'artisan_requests contains only PENDING applications.');
    $n = (int) $pdo->query("SELECT COUNT(*) FROM artisan_requests r LEFT JOIN artisan_accounts ac ON ac.account_id = r.account_id WHERE r.status = 'PENDING' AND ac.account_id IS NULL")->fetchColumn();
    check($n ? 'warn' : 'ok', 'Data', $n ? "$n pending request(s) have no artisan account (sent before logins existed). They can still be approved; the applicant just has no login."
                                       : 'Every pending request belongs to an artisan account.');
    $n = (int) $pdo->query("SELECT COUNT(*) FROM artisan_accounts ac LEFT JOIN artisan_requests r ON r.account_id = ac.account_id WHERE ac.status = 'PENDING' AND r.request_id IS NULL")->fetchColumn();
    check($n ? 'warn' : 'ok', 'Data', $n ? "$n PENDING account(s) have no request in the queue – they would wait forever. Ask them to apply again or check artisan_requests."
                                       : 'Every PENDING account has a request waiting for review.');
    $n = (int) $pdo->query("SELECT COUNT(*) FROM artisan_accounts ac LEFT JOIN artisans a ON a.artisan_id = ac.artisan_id WHERE ac.status = 'APPROVED' AND a.artisan_id IS NULL")->fetchColumn();
    check($n ? 'warn' : 'ok', 'Data', $n ? "$n APPROVED account(s) are not linked to an existing artisan (the artisan was deleted)."
                                       : 'Every APPROVED account is linked to a row in artisans.');
}

$pending = [];
if (isset($tableInfo['artisan_requests'])) {
    $pending = $pdo->query("SELECT r.request_id, r.artisan_name, r.email, r.status, s.skill_name, d.district_name
                            FROM artisan_requests r
                            LEFT JOIN skills s ON s.skill_id = r.skill_id
                            LEFT JOIN districts d ON d.district_id = r.district_id
                            ORDER BY r.request_id")->fetchAll();
}

$fails = count(array_filter($checks, fn($c) => $c[0] === 'fail'));
$warns = count(array_filter($checks, fn($c) => $c[0] === 'warn'));

include '../includes/header.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h1 class="h3 mb-0"><i class="bi bi-clipboard2-pulse me-1"></i>Diagnostics</h1>
        <a href="requests.php" class="btn btn-sm btn-outline-maroon"><i class="bi bi-arrow-left me-1"></i>Back to Requests</a>
    </div>
    <?php show_flash(); ?>

    <div class="alert <?= $fails ? 'alert-danger' : ($warns ? 'alert-warning' : 'alert-success') ?>">
        <?= $fails ? "<strong>$fails problem(s)</strong> must be fixed for Approve to work." : ($warns ? "No blocking problems. $warns warning(s) below." : 'Everything needed for automatic approve/reject is in place.') ?>
    </div>

    <?php if ($dryRun): ?>
        <div class="ks-card static mb-4" style="border-left:5px solid <?= $dryRun['ok'] ? '#2e7d32' : '#b3261e' ?>"><div class="card-body">
            <h2 class="h5">Dry-run approve of request #<?= (int) $dryRun['id'] ?> – <?= $dryRun['ok'] ? 'would SUCCEED' : 'would FAIL' ?></h2>
            <?php if ($dryRun['ok']): ?>
                <ol class="small mb-0"><?php foreach ($dryRun['steps'] as $s): ?><li><?= e($s) ?></li><?php endforeach; ?></ol>
            <?php else: ?>
                <p class="mb-1"><strong>Exact error</strong> (<?= e($dryRun['type']) ?><?= $dryRun['code'] ? ' · SQLSTATE ' . e($dryRun['code']) : '' ?>):</p>
                <pre class="small p-2 rounded" style="background:#fbeaea;white-space:pre-wrap"><?= e($dryRun['error']) ?></pre>
                <p class="small text-muted-ks mb-0">The transaction was rolled back – nothing was changed.</p>
            <?php endif; ?>
        </div></div>
    <?php endif; ?>

    <div class="ks-table table-responsive mb-4">
        <table class="table table-sm align-middle">
            <thead><tr><th style="width:90px">Result</th><th style="width:130px">Area</th><th>Check</th></tr></thead>
            <tbody>
            <?php foreach ($checks as [$level, $area, $msg]): ?>
                <tr>
                    <td><span class="risk-pill <?= ['ok' => 'risk-low', 'warn' => 'risk-medium', 'fail' => 'risk-high'][$level] ?>"><?= strtoupper($level) ?></span></td>
                    <td class="small fw-semibold"><?= e($area) ?></td>
                    <td class="small"><?= $msg /* built from escaped values above */ ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2 class="h5">Test approve without saving</h2>
    <p class="small text-muted-ks">Runs the real Approve statements (INSERT artisans → UPDATE artisan_accounts → DELETE artisan_requests) inside a transaction and then <strong>rolls back</strong>. Use it to see the exact reason if Approve fails.</p>
    <div class="ks-table table-responsive">
        <table class="table table-sm align-middle">
            <thead><tr><th>#</th><th>Applicant</th><th>Skill</th><th>District</th><th>Status</th><th class="text-end"></th></tr></thead>
            <tbody>
            <?php foreach ($pending as $p): ?>
                <tr>
                    <td><?= (int) $p['request_id'] ?></td>
                    <td><?= e($p['artisan_name']) ?><br><span class="small text-muted-ks"><?= e($p['email']) ?></span></td>
                    <td><?= $p['skill_name'] ? e($p['skill_name']) : '<span class="text-danger">missing skill</span>' ?></td>
                    <td><?= $p['district_name'] ? e($p['district_name']) : '<span class="text-danger">missing district</span>' ?></td>
                    <td><span class="risk-pill <?= request_status_class($p['status']) ?>"><?= e($p['status']) ?></span></td>
                    <td class="text-end">
                        <form method="post" action="diagnostics.php" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="dryrun">
                            <input type="hidden" name="id" value="<?= (int) $p['request_id'] ?>">
                            <button class="btn btn-sm btn-outline-maroon" type="submit"><i class="bi bi-play-circle me-1"></i>Dry-run approve</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$pending): ?><tr><td colspan="6" class="text-center text-muted-ks py-3">No requests in artisan_requests.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
