<?php
/**
 * Artisan application review – the ONLY place where Approve / Reject change
 * the database. Used by admin/requests.php (real clicks) and by
 * admin/diagnostics.php (dry run that rolls back).
 *
 *  APPROVE (one transaction)                REJECT (one transaction)
 *  ─────────────────────────                ────────────────────────
 *  A. SELECT request … FOR UPDATE           SELECT request … FOR UPDATE
 *  B. validate (exists, PENDING, skill,     validate (exists, PENDING)
 *     district, account)                    UPDATE artisan_accounts → REJECTED + note
 *  C. INSERT INTO artisans (…)              DELETE FROM artisan_requests
 *  D. $artisanId = lastInsertId()           COMMIT   (no artisans row)
 *  E. UPDATE artisan_accounts → APPROVED, artisan_id
 *  F. DELETE FROM artisan_requests
 *  G. COMMIT   (any error → ROLLBACK, nothing saved)
 */

/** A problem with the application itself (shown to the admin as-is). */
class ReviewError extends RuntimeException {}

/** Does a table / column exist in the CURRENT database (kalasetu_db)? */
function db_has_table(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (!isset($cache[$table])) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $st->execute([$table]);
        $cache[$table] = (bool) $st->fetchColumn();
    }
    return $cache[$table];
}

function db_has_column(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (!isset($cache[$key])) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $st->execute([$table, $column]);
        $cache[$key] = (bool) $st->fetchColumn();
    }
    return $cache[$key];
}

/** Lock + load a pending request and the account that sent it. */
function review_load_request(PDO $pdo, int $requestId): array
{
    // A. Fetch the request and lock the row (a second Approve click waits here,
    //    then finds the row already deleted).
    $st = $pdo->prepare('SELECT * FROM artisan_requests WHERE request_id = ? FOR UPDATE');
    $st->execute([$requestId]);
    $req = $st->fetch();
    if (!$req) {
        throw new ReviewError('This application has already been reviewed.');
    }
    if (($req['status'] ?? 'PENDING') !== 'PENDING') {
        throw new ReviewError('This application has already been reviewed.');
    }

    // Which artisan account sent it? (account_id, or the same email for older requests)
    $req['_account'] = null;
    if (db_has_table($pdo, 'artisan_accounts')) {
        $accountId = $req['account_id'] ?? null;
        if ($accountId) {
            $st = $pdo->prepare('SELECT * FROM artisan_accounts WHERE account_id = ? FOR UPDATE');
            $st->execute([$accountId]);
        } else {
            $st = $pdo->prepare("SELECT * FROM artisan_accounts WHERE email = ? AND status = 'PENDING' FOR UPDATE");
            $st->execute([$req['email']]);
        }
        $acc = $st->fetch();
        if ($acc) {
            if ($acc['status'] !== 'PENDING') {
                throw new ReviewError('This application has already been reviewed.');
            }
            $req['_account'] = $acc;
        }
    }
    return $req;
}

/**
 * APPROVE: returns ['artisan_id', 'account_id', 'name', 'steps'].
 * $dryRun = true runs every statement and then ROLLS BACK (used by Diagnostics).
 * Throws ReviewError (bad application) or PDOException (database problem);
 * in both cases the transaction has been rolled back and nothing is saved.
 */
function approve_artisan_request(PDO $pdo, int $requestId, ?string $note = null, bool $dryRun = false): array
{
    $steps = [];
    $pdo->beginTransaction();
    try {
        $req = review_load_request($pdo, $requestId);
        $steps[] = "A. Loaded and locked request #$requestId ({$req['artisan_name']}).";

        // B. Validate skill + district
        $st = $pdo->prepare('SELECT skill_id, skill_name, district_id FROM skills WHERE skill_id = ?');
        $st->execute([$req['skill_id']]);
        $skill = $st->fetch();
        if (!$skill) {
            throw new ReviewError("The chosen skill (#{$req['skill_id']}) no longer exists.");
        }
        $st = $pdo->prepare('SELECT COUNT(*) FROM districts WHERE district_id = ?');
        $st->execute([$req['district_id']]);
        if (!$st->fetchColumn()) {
            throw new ReviewError("The chosen district (#{$req['district_id']}) no longer exists.");
        }
        if ((int) $skill['district_id'] !== (int) $req['district_id']) {
            throw new ReviewError("The skill '{$skill['skill_name']}' now belongs to a different district than the one the artisan chose. Fix it in Manage Skills before approving.");
        }
        $steps[] = "B. Validated: status PENDING, skill '{$skill['skill_name']}' exists and belongs to the chosen district.";

        // C. INSERT into the existing artisans table (same columns every artisan uses)
        $ins = $pdo->prepare('INSERT INTO artisans
            (skill_id, artisan_name, age, experience, bio, contact, accepts_apprentices, profile_image)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $ins->execute([
            $req['skill_id'],
            $req['artisan_name'],
            isset($req['age']) && $req['age'] !== null ? (int) $req['age'] : null,
            (int) $req['experience'],
            $req['bio'],
            artisan_contact($req['phone'], $req['email']),
            (int) $req['accepts_apprentices'],
            $req['profile_image'],
        ]);

        // D. The new artisan's id
        $artisanId = (int) $pdo->lastInsertId();
        if ($artisanId <= 0) {
            throw new ReviewError('The artisan row was not created (lastInsertId() returned 0). Check that artisans.artisan_id is AUTO_INCREMENT.');
        }
        $steps[] = "C/D. Inserted into artisans → new artisan_id = $artisanId.";

        // E. Mark the applicant's account APPROVED and link it to the new artisan
        $accountId = null;
        if ($req['_account']) {
            $accountId = (int) $req['_account']['account_id'];
            $upd = $pdo->prepare("UPDATE artisan_accounts
                                  SET status = 'APPROVED', artisan_id = ?, admin_note = ?, reviewed_at = CURRENT_TIMESTAMP
                                  WHERE account_id = ? AND status = 'PENDING'");
            $upd->execute([$artisanId, $note !== '' ? $note : null, $accountId]);
            if ($upd->rowCount() !== 1) {
                throw new ReviewError("Account #$accountId could not be set to APPROVED (it is no longer PENDING).");
            }
            $steps[] = "E. Account #$accountId → status = APPROVED, artisan_id = $artisanId.";
        } else {
            $steps[] = 'E. No artisan account found for this request (sent before artisan logins existed) – skipped.';
        }

        // F. Remove the reviewed application
        $del = $pdo->prepare('DELETE FROM artisan_requests WHERE request_id = ?');
        $del->execute([$requestId]);
        if ($del->rowCount() !== 1) {
            throw new ReviewError("Request #$requestId could not be deleted.");
        }
        $steps[] = "F. Deleted request #$requestId from artisan_requests.";

        // G. Save everything together (or, for a dry run, undo everything)
        if ($dryRun) {
            $pdo->rollBack();
            $steps[] = 'G. DRY RUN → ROLLBACK. Every statement worked; nothing was saved.';
        } else {
            $pdo->commit();
            $steps[] = 'G. COMMIT – all changes saved together.';
        }
        return ['artisan_id' => $artisanId, 'account_id' => $accountId, 'name' => $req['artisan_name'], 'steps' => $steps];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();     // nothing is partially saved
        }
        error_log('KalaSetu approve #' . $requestId . ' failed: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * REJECT: returns ['account_id', 'name', 'profile_image'].
 * Throws ReviewError / PDOException after rolling back.
 */
function reject_artisan_request(PDO $pdo, int $requestId, ?string $note = null): array
{
    $pdo->beginTransaction();
    try {
        $req = review_load_request($pdo, $requestId);

        $accountId = null;
        if ($req['_account']) {
            $accountId = (int) $req['_account']['account_id'];
            $upd = $pdo->prepare("UPDATE artisan_accounts
                                  SET status = 'REJECTED', artisan_id = NULL, admin_note = ?, reviewed_at = CURRENT_TIMESTAMP
                                  WHERE account_id = ? AND status = 'PENDING'");
            $upd->execute([$note !== '' ? $note : null, $accountId]);
            if ($upd->rowCount() !== 1) {
                throw new ReviewError("Account #$accountId could not be set to REJECTED (it is no longer PENDING).");
            }
        }

        $del = $pdo->prepare('DELETE FROM artisan_requests WHERE request_id = ?');
        $del->execute([$requestId]);
        if ($del->rowCount() !== 1) {
            throw new ReviewError("Request #$requestId could not be deleted.");
        }

        $pdo->commit();
        return ['account_id' => $accountId, 'name' => $req['artisan_name'], 'profile_image' => $req['profile_image']];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('KalaSetu reject #' . $requestId . ' failed: ' . $e->getMessage());
        throw $e;
    }
}

/** Message for the admin when a review fails. Database details only in debug mode. */
function review_error_message(string $action, Throwable $e): string
{
    if ($e instanceof ReviewError) {
        return $e->getMessage() === 'This application has already been reviewed.'
            ? $e->getMessage()
            : ucfirst($action) . ' failed: ' . $e->getMessage() . ' No changes were saved.';
    }
    if (defined('APP_DEBUG') && APP_DEBUG) {
        return ucfirst($action) . ' failed – database error: ' . rtrim($e->getMessage(), '. ')
             . '. No changes were saved. Open Admin → Diagnostics for a full check.';
    }
    return 'Could not ' . $action . ' this request. No changes were saved. (Details are in the PHP error log.)';
}
