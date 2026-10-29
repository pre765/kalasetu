<?php
/**
 * Artisan login – email + password from `artisan_accounts`.
 * After login the artisan is sent by account status:
 *   APPROVED → dashboard.php      PENDING / REJECTED → status.php
 */
require '../config/database.php';
require '../includes/functions.php';
require '../includes/auth.php';

$root = '../';
$pageTitle = 'Artisan Login';
$activePage = 'a_login';
$isArtisan = true;

// Already logged in → go to the right page for the current status
if ($acc = current_artisan_account($pdo)) {
    redirect(artisan_home($acc));
}

$error = '';
$email = '';

// Same brute-force protection as the admin login: 5 wrong attempts → wait 60 seconds
$_SESSION['artisan_login_attempts'] = $_SESSION['artisan_login_attempts'] ?? 0;
$lockedUntil = $_SESSION['artisan_locked_until'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if (time() < $lockedUntil) {
        $error = 'Too many failed attempts. Please wait ' . ($lockedUntil - time()) . ' seconds.';
    } elseif (!verify_csrf()) {
        $error = 'Your session expired. Please try again.';
    } elseif ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } elseif ($acc = artisan_check_credentials($pdo, $email, $password)) {
        artisan_login($acc);
        $acc = current_artisan_account($pdo);
        set_flash('success', 'Welcome, ' . $acc['name'] . '!');
        redirect(artisan_home($acc));
    } else {
        $_SESSION['artisan_login_attempts']++;
        if ($_SESSION['artisan_login_attempts'] >= 5) {
            $_SESSION['artisan_locked_until'] = time() + 60;
            $_SESSION['artisan_login_attempts'] = 0;
        }
        $error = 'Invalid email or password.';
    }
}

include '../includes/header.php';
?>
<div class="container py-5">
    <div class="ks-card static login-card mx-auto">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <span class="brand-mark mx-auto mb-2" style="width:56px;height:56px;font-size:1.6rem"><i class="bi bi-person-badge"></i></span>
                <h1 class="h3 mb-1">Artisan Login</h1>
                <p class="text-muted-ks small mb-0">Check your application status or manage your KalaSetu profile</p>
            </div>
            <?php show_flash(); ?>
            <?php if ($error): ?>
                <div class="alert alert-danger small"><i class="bi bi-exclamation-triangle me-1"></i><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post" class="needs-validation" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= e($email) ?>" required maxlength="80" autocomplete="username" autofocus>
                    <div class="invalid-feedback">Enter the email you registered with.</div>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
                    <div class="invalid-feedback">Password is required.</div>
                </div>
                <button type="submit" class="btn btn-maroon w-100 btn-lg"><i class="bi bi-box-arrow-in-right me-1"></i>Log in</button>
            </form>
            <div class="text-center mt-3 small">
                New artisan? <a href="../register.php">Register here</a><br>
                <a href="../index.php"><i class="bi bi-arrow-left"></i> Back to website</a>
            </div>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
