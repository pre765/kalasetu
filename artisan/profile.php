<?php
/**
 * Approved artisan edits their OWN public profile (the `artisans` row
 * linked through artisan_accounts.artisan_id) and their login password.
 * Name and skill stay under the admin's control (Admin → Artisans).
 */
require '../config/database.php';
require '../includes/functions.php';
require '../includes/auth.php';

$artisanAccount = require_approved_artisan($pdo);
$artisanId = (int) $artisanAccount['artisan_id'];

$root = '../';
$pageTitle = 'My Profile';
$activePage = 'a_profile';
$isArtisan = true;

$stmt = $pdo->prepare('
    SELECT a.*, s.skill_name, d.district_name
    FROM artisans a
    INNER JOIN skills s    ON a.skill_id = s.skill_id
    INNER JOIN districts d ON s.district_id = d.district_id
    WHERE a.artisan_id = ?');
$stmt->execute([$artisanId]);
$artisan = $stmt->fetch();

$form = [
    'age'                 => $artisan['age'] ?? '',
    'experience'          => $artisan['experience'],
    'bio'                 => $artisan['bio'],
    'phone'               => format_phone($artisanAccount['phone']),
    'accepts_apprentices' => (int) $artisan['accepts_apprentices'],
];
$errors = [];
$pwErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Session expired. Please try again.');
        redirect('profile.php');
    }

    /* ---------- Change password ---------- */
    if (($_POST['action'] ?? '') === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['new_password_confirm'] ?? '');
        if (!password_verify($current, $artisanAccount['password_hash'])) {
            $pwErrors['current_password'] = 'Your current password is not correct.';
        }
        if (strlen($new) < 8 || strlen($new) > 72) {
            $pwErrors['new_password'] = 'Choose a password of 8–72 characters.';
        } elseif (!hash_equals($new, $confirm)) {
            $pwErrors['new_password_confirm'] = 'The two passwords do not match.';
        }
        if (!$pwErrors) {
            $pdo->prepare('UPDATE artisan_accounts SET password_hash = ? WHERE account_id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $artisanAccount['account_id']]);
            set_flash('success', 'Your password has been changed.');
            redirect('profile.php');
        }
    }

    /* ---------- Update profile ---------- */
    if (($_POST['action'] ?? '') === 'profile') {
        $form['age']                 = trim($_POST['age'] ?? '');
        $form['experience']          = trim($_POST['experience'] ?? '');
        $form['bio']                 = trim($_POST['bio'] ?? '');
        $form['phone']               = trim($_POST['phone'] ?? '');
        $form['accepts_apprentices'] = isset($_POST['accepts_apprentices']) ? 1 : 0;

        // Same rules as Admin → Artisans
        if ($form['age'] !== '' && filter_var($form['age'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 10, 'max_range' => 120]]) === false) {
            $errors['age'] = 'Age must be a whole number between 10 and 120, or left empty.';
        }
        if (filter_var($form['experience'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]) === false) {
            $errors['experience'] = 'Experience must be a whole number between 0 and 100.';
        } elseif ($form['age'] !== '' && !isset($errors['age']) && (int) $form['experience'] >= (int) $form['age']) {
            $errors['experience'] = 'Experience must be less than age.';
        }
        if (mb_strlen($form['bio']) < 20 || mb_strlen($form['bio']) > 2000) {
            $errors['bio'] = 'Please describe your work in 20–2000 characters.';
        }
        $phone = normalize_phone($form['phone']);
        if ($phone === null) {
            $errors['phone'] = 'Enter a valid 10-digit Indian mobile number.';
        } else {
            $dup = $pdo->prepare('SELECT COUNT(*) FROM artisan_accounts WHERE phone = ? AND account_id <> ?');
            $dup->execute([$phone, $artisanAccount['account_id']]);
            if ($dup->fetchColumn() > 0) {
                $errors['phone'] = 'This mobile number is already used by another artisan account.';
            }
        }

        $newImage = null;
        if (!$errors) {
            try {
                $newImage = handle_image_upload('profile_image');
            } catch (RuntimeException $ex) {
                $errors['profile_image'] = $ex->getMessage();
            }
        }

        if (!$errors) {
            $oldImage = $artisan['profile_image'];
            $image = $newImage ?: (isset($_POST['remove_image']) ? null : $oldImage);
            try {
                // artisans (public profile) + artisan_accounts (login phone) change together
                $pdo->beginTransaction();
                $pdo->prepare('UPDATE artisans SET age = ?, experience = ?, bio = ?, contact = ?, accepts_apprentices = ?, profile_image = ?
                               WHERE artisan_id = ?')
                    ->execute([$form['age'] === '' ? null : (int) $form['age'], (int) $form['experience'], $form['bio'],
                               artisan_contact($phone, $artisanAccount['email']), $form['accepts_apprentices'], $image, $artisanId]);
                $pdo->prepare('UPDATE artisan_accounts SET phone = ? WHERE account_id = ?')
                    ->execute([$phone, $artisanAccount['account_id']]);
                $pdo->commit();
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                delete_image($newImage);
                throw $e;
            }
            if ($oldImage !== $image) {
                delete_image($oldImage);
            }
            set_flash('success', 'Your profile has been updated. Visitors now see the new details.');
            redirect('profile.php');
        }
    }
}

include '../includes/header.php';
?>
<div class="container py-4">
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li><li class="breadcrumb-item active">My Profile</li></ol></nav>
    <h1 class="h3 mb-3">My Profile</h1>
    <?php show_flash(); ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="ks-card static"><div class="card-body p-4">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <?= artisan_avatar($artisan, $root, 'lg') ?>
                    <div>
                        <h2 class="h4 mb-1"><?= e($artisan['artisan_name']) ?></h2>
                        <div class="meta-line"><i class="bi bi-palette"></i> <?= e($artisan['skill_name']) ?> &nbsp;·&nbsp; <i class="bi bi-geo-alt"></i> <?= e($artisan['district_name']) ?></div>
                        <div class="small text-muted-ks">Name and skill are managed by the KalaSetu admin.</div>
                    </div>
                </div>
                <form method="post" action="profile.php" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="profile">
                    <div class="row g-3">
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="age">Age <span class="fw-normal text-muted-ks">(optional)</span></label>
                            <input type="number" id="age" name="age" class="form-control <?= isset($errors['age']) ? 'is-invalid' : '' ?>" value="<?= e($form['age']) ?>" min="10" max="120">
                            <div class="invalid-feedback"><?= e($errors['age'] ?? 'Enter a valid age.') ?></div>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="experience">Experience (years)</label>
                            <input type="number" id="experience" name="experience" class="form-control <?= isset($errors['experience']) ? 'is-invalid' : '' ?>" value="<?= e($form['experience']) ?>" required min="0" max="100">
                            <div class="invalid-feedback"><?= e($errors['experience'] ?? 'Enter years of experience.') ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Mobile number</label>
                            <input type="tel" id="phone" name="phone" class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" value="<?= e($form['phone']) ?>" required maxlength="16" pattern="[\d\s+\-]{10,16}">
                            <div class="invalid-feedback"><?= e($errors['phone'] ?? 'Enter a valid 10-digit mobile number.') ?></div>
                            <div class="form-text">Public contact will show: phone · <?= e($artisanAccount['email']) ?></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="bio">Description</label>
                            <textarea id="bio" name="bio" rows="5" class="form-control <?= isset($errors['bio']) ? 'is-invalid' : '' ?>" required minlength="20" maxlength="2000"><?= e($form['bio']) ?></textarea>
                            <div class="invalid-feedback"><?= e($errors['bio'] ?? 'Please describe your work (at least 20 characters).') ?></div>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label" for="profile_image">Photo <span class="fw-normal text-muted-ks">(optional · JPG/PNG/WEBP · max 2 MB)</span></label>
                            <input type="file" id="profile_image" name="profile_image" accept="image/jpeg,image/png,image/webp" class="form-control <?= isset($errors['profile_image']) ? 'is-invalid' : '' ?>">
                            <div class="invalid-feedback"><?= e($errors['profile_image'] ?? '') ?></div>
                            <?php if (!empty($artisan['profile_image'])): ?>
                                <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="remove_image" id="remove_image"><label class="form-check-label small" for="remove_image">Remove current photo</label></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-5 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="accepts_apprentices" name="accepts_apprentices" <?= $form['accepts_apprentices'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="accepts_apprentices">I am willing to teach apprentices</label>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button class="btn btn-maroon" type="submit"><i class="bi bi-check-lg me-1"></i>Save changes</button>
                        <a href="dashboard.php" class="btn btn-outline-maroon ms-1">Cancel</a>
                    </div>
                </form>
            </div></div>
        </div>

        <div class="col-lg-4">
            <div class="ks-card static"><div class="card-body">
                <h2 class="h5">Change password</h2>
                <form method="post" action="profile.php" class="needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="password">
                    <div class="mb-2">
                        <label class="form-label small" for="current_password">Current password</label>
                        <input type="password" id="current_password" name="current_password" class="form-control form-control-sm <?= isset($pwErrors['current_password']) ? 'is-invalid' : '' ?>" required autocomplete="current-password">
                        <div class="invalid-feedback"><?= e($pwErrors['current_password'] ?? 'Required.') ?></div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small" for="new_password">New password</label>
                        <input type="password" id="new_password" name="new_password" class="form-control form-control-sm <?= isset($pwErrors['new_password']) ? 'is-invalid' : '' ?>" required minlength="8" maxlength="72" autocomplete="new-password">
                        <div class="invalid-feedback"><?= e($pwErrors['new_password'] ?? 'At least 8 characters.') ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small" for="new_password_confirm">Confirm new password</label>
                        <input type="password" id="new_password_confirm" name="new_password_confirm" class="form-control form-control-sm <?= isset($pwErrors['new_password_confirm']) ? 'is-invalid' : '' ?>" required minlength="8" maxlength="72" autocomplete="new-password">
                        <div class="invalid-feedback"><?= e($pwErrors['new_password_confirm'] ?? 'Please type it again.') ?></div>
                    </div>
                    <button class="btn btn-sm btn-outline-maroon w-100" type="submit"><i class="bi bi-key me-1"></i>Update password</button>
                </form>
            </div></div>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
