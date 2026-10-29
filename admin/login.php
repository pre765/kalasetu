<?php
require '../config/database.php';
require '../includes/functions.php';
require '../includes/auth.php';

$root = '../';
$pageTitle = 'Admin Login';
$isAdmin = true;

// Already logged in → go to dashboard
if (!empty($_SESSION['admin_logged_in'])) {
    redirect('dashboard.php');
}

$error = '';
$username = '';

// Simple brute-force protection: 5 wrong attempts → wait 60 seconds
$_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? 0;
$lockedUntil = $_SESSION['login_locked_until'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (time() < $lockedUntil) {
        $error = 'Too many failed attempts. Please wait ' . ($lockedUntil - time()) . ' seconds.';
    } elseif (!verify_csrf()) {
        $error = 'Your session expired. Please try again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } elseif (admin_check_credentials($username, $password)) {
        session_regenerate_id(true);          // prevent session fixation
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username']  = $username;
        $_SESSION['login_attempts']  = 0;
        unset($_SESSION['login_locked_until']);
        set_flash('success', 'Welcome back, ' . $username . '!');
        redirect('dashboard.php');
    } else {
        $_SESSION['login_attempts']++;
        if ($_SESSION['login_attempts'] >= 5) {
            $_SESSION['login_locked_until'] = time() + 60;
            $_SESSION['login_attempts'] = 0;
        }
        $error = 'Invalid username or password.';
    }
}

include '../includes/header.php';
?>
<div class="container py-5">
    <div class="ks-card static login-card mx-auto">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <span class="brand-mark mx-auto mb-2" style="width:56px;height:56px;font-size:1.6rem"><i class="bi bi-shield-lock"></i></span>
                <h1 class="h3 mb-1">Admin Login</h1>
                <p class="text-muted-ks small mb-0">Manage KalaSetu's skills, artisans and training data</p>
            </div>
            <?php show_flash(); ?>
            <?php if ($error): ?>
                <div class="alert alert-danger small"><i class="bi bi-exclamation-triangle me-1"></i><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post" class="needs-validation" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" value="<?= e($username) ?>" required autocomplete="username" autofocus>
                    <div class="invalid-feedback">Username is required.</div>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
                    <div class="invalid-feedback">Password is required.</div>
                </div>
                <button type="submit" class="btn btn-maroon w-100 btn-lg"><i class="bi bi-box-arrow-in-right me-1"></i>Log in</button>
            </form>
            <div class="text-center mt-3"><a href="../index.php" class="small"><i class="bi bi-arrow-left"></i> Back to website</a></div>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
