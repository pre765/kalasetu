<?php
/**
 * Authentication helpers for the two kinds of login in KalaSetu:
 *   ADMIN   – one fixed account defined below (no table needed)
 *   ARTISAN – accounts stored in the `artisan_accounts` table (further down)
 * Visitors never log in.
 *
 * The single admin account is defined here. The password is stored ONLY as a bcrypt hash created with
 * password_hash() – never in plain text.
 *
 * Demo login:  username = admin   password = Kala@2026
 *
 * To change the password, run in a terminal:
 *     php -r "echo password_hash('NewPassword', PASSWORD_DEFAULT);"
 * and paste the result below.
 */

define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD_HASH', '$2y$10$E4Tc13luHKhoCdeuQW32NuQbA.0xzwOYnhF/B3XD5SPoo4twCb1ly');

/** Check username + password using password_verify(). */
function admin_check_credentials(string $username, string $password): bool
{
    return hash_equals(ADMIN_USERNAME, $username) && password_verify($password, ADMIN_PASSWORD_HASH);
}

/** Call at the top of every protected admin page. */
function require_admin(): void
{
    if (empty($_SESSION['admin_logged_in'])) {
        set_flash('warning', 'Please log in to access the admin panel.');
        header('Location: login.php');
        exit;
    }
}

/* =====================================================================
 * Artisan accounts
 * ---------------------------------------------------------------------
 * Same session style as the admin login above, but artisans are stored in
 * the `artisan_accounts` table (one row per registered artisan, password
 * saved with password_hash()). Only the account_id is kept in the session;
 * the status (PENDING / APPROVED / REJECTED) is always read fresh from the
 * database, so an approval or rejection by the admin is seen immediately.
 * ===================================================================== */

/** Logged-in artisan account (joined with its public artisan row), or null. */
function current_artisan_account(PDO $pdo): ?array
{
    static $cache = null;
    if (empty($_SESSION['artisan_account_id'])) {
        return null;
    }
    if ($cache !== null && (int) $cache['account_id'] === (int) $_SESSION['artisan_account_id']) {
        return $cache;
    }
    $stmt = $pdo->prepare('
        SELECT ac.*, a.artisan_name, a.skill_id, a.profile_image, a.profile_views
        FROM artisan_accounts ac
        LEFT JOIN artisans a ON ac.artisan_id = a.artisan_id
        WHERE ac.account_id = ?');
    $stmt->execute([(int) $_SESSION['artisan_account_id']]);
    $row = $stmt->fetch();
    if (!$row) {                       // account was removed → end the artisan session
        unset($_SESSION['artisan_account_id'], $_SESSION['artisan_name']);
        return null;
    }
    return $cache = $row;
}

/** Check email + password against artisan_accounts. Returns the account or null. */
function artisan_check_credentials(PDO $pdo, string $email, string $password): ?array
{
    $stmt = $pdo->prepare('SELECT account_id, name, password_hash FROM artisan_accounts WHERE email = ?');
    $stmt->execute([$email]);
    $acc = $stmt->fetch();
    return ($acc && password_verify($password, $acc['password_hash'])) ? $acc : null;
}

/** Start the artisan session after login / registration. */
function artisan_login(array $account): void
{
    session_regenerate_id(true);       // prevent session fixation
    $_SESSION['artisan_account_id'] = (int) $account['account_id'];
    $_SESSION['artisan_name']       = $account['name'];
    $_SESSION['artisan_login_attempts'] = 0;
    unset($_SESSION['artisan_locked_until']);
}

/** Where an artisan belongs, by account status. */
function artisan_home(array $account): string
{
    return $account['status'] === 'APPROVED' ? 'dashboard.php' : 'status.php';
}

/** Call at the top of every artisan page. Returns the account. */
function require_artisan(PDO $pdo): array
{
    $acc = current_artisan_account($pdo);
    if (!$acc) {
        set_flash('warning', 'Please log in to your artisan account.');
        header('Location: login.php');
        exit;
    }
    return $acc;
}

/**
 * Pages only for APPROVED artisans whose public profile exists.
 * PENDING / REJECTED artisans are sent to their status page instead.
 */
function require_approved_artisan(PDO $pdo): array
{
    $acc = require_artisan($pdo);
    if ($acc['status'] !== 'APPROVED' || empty($acc['artisan_id'])) {
        header('Location: status.php');
        exit;
    }
    return $acc;
}
