<?php
require 'config/database.php';
require 'includes/functions.php';
require 'includes/auth.php';

$root = '';
$pageTitle = 'Register as Artisan';
$activePage = 'register';

$errors = [];
$old = ['artisan_name' => '', 'phone' => '', 'email' => '', 'district_id' => '', 'skill_id' => '',
        'age' => '', 'experience' => '', 'bio' => '', 'accepts_apprentices' => 0];

// ---------- Who is registering? ----------
// Not logged in          → new account + application.
// Logged-in, REJECTED    → "apply again" with the SAME account (no duplicate account).
// Logged-in, PENDING/APPROVED → nothing to register; go to the artisan area.
$account = current_artisan_account($pdo);
if ($account && $account['status'] !== 'REJECTED') {
    redirect('artisan/' . artisan_home($account));
}
$reapply = $account !== null;
if ($reapply && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $old['artisan_name'] = $account['name'];
    $old['phone']        = format_phone($account['phone']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['artisan_name']        = trim($_POST['artisan_name'] ?? '');
    $old['phone']               = trim($_POST['phone'] ?? '');
    $old['email']               = $reapply ? $account['email'] : trim($_POST['email'] ?? '');
    $password                   = (string) ($_POST['password'] ?? '');
    $passwordConfirm            = (string) ($_POST['password_confirm'] ?? '');
    $old['district_id']         = (int) ($_POST['district_id'] ?? 0);
    $old['skill_id']            = (int) ($_POST['skill_id'] ?? 0);
    $old['age']                 = trim($_POST['age'] ?? '');
    $old['experience']          = trim($_POST['experience'] ?? '');
    $old['bio']                 = trim($_POST['bio'] ?? '');
    $old['accepts_apprentices'] = isset($_POST['accepts_apprentices']) ? 1 : 0;

    // ---------- Server-side validation ----------
    if (mb_strlen($old['artisan_name']) < 2 || mb_strlen($old['artisan_name']) > 150) {
        $errors['artisan_name'] = 'Please enter your full name (2–150 characters).';
    }
    $phone = normalize_phone($old['phone']);
    if ($phone === null) {
        $errors['phone'] = 'Enter a valid 10-digit Indian mobile number.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 80) {
        $errors['email'] = 'Enter a valid email address (max 80 characters).';
    }
    if (!$reapply) {
        if (strlen($password) < 8 || strlen($password) > 72) {
            $errors['password'] = 'Choose a password of 8–72 characters.';
        } elseif (!hash_equals($password, $passwordConfirm)) {
            $errors['password_confirm'] = 'The two passwords do not match.';
        }
    }

    // The skill must exist AND belong to the chosen district,
    // because an artisan's district is taken from skills.district_id.
    $stmt = $pdo->prepare('SELECT district_id FROM skills WHERE skill_id = ?');
    $stmt->execute([$old['skill_id']]);
    $skillDistrict = $stmt->fetchColumn();
    $chk = $pdo->prepare('SELECT COUNT(*) FROM districts WHERE district_id = ?');
    $chk->execute([$old['district_id']]);
    if (!$chk->fetchColumn()) {
        $errors['district_id'] = 'Please choose your district.';
    }
    if ($skillDistrict === false) {
        $errors['skill_id'] = 'Please choose your skill.';
    } elseif (!isset($errors['district_id']) && (int) $skillDistrict !== $old['district_id']) {
        $errors['skill_id'] = 'This skill is not listed for the selected district. Please choose a skill from your district.';
    }

    if (filter_var($old['age'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 10, 'max_range' => 120]]) === false) {
        $errors['age'] = 'Enter your age as a whole number (10–120).';
    }
    if (filter_var($old['experience'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]) === false) {
        $errors['experience'] = 'Enter years of experience as a whole number (0–100).';
    } elseif (!isset($errors['age']) && (int) $old['experience'] >= (int) $old['age']) {
        $errors['experience'] = 'Years of experience must be less than your age.';
    }
    if (mb_strlen($old['bio']) < 20 || mb_strlen($old['bio']) > 2000) {
        $errors['bio'] = 'Please describe your work in 20–2000 characters.';
    }

    // ---------- Duplicate check: one account per email, one per phone ----------
    if (!$errors) {
        $dup = $pdo->prepare('SELECT email FROM artisan_accounts WHERE (email = ? OR phone = ?) AND account_id <> ? LIMIT 1');
        $dup->execute([$old['email'], $phone, $reapply ? $account['account_id'] : 0]);
        $existingEmail = $dup->fetchColumn();
        if ($existingEmail !== false) {
            if (!$reapply && strcasecmp($existingEmail, $old['email']) === 0) {
                $errors['email'] = 'An artisan account with this email already exists. Please log in to see your application status.';
            } else {
                $errors['phone'] = 'This mobile number is already used by another artisan account.';
            }
        }
        // Applications without an account (sent before artisan accounts existed)
        $dup = $pdo->prepare("SELECT COUNT(*) FROM artisan_requests WHERE (email = ? OR phone = ?) AND status = 'PENDING' AND account_id IS NULL");
        $dup->execute([$old['email'], $phone]);
        if (!$errors && $dup->fetchColumn() > 0) {
            $errors['email'] = 'An application with this email or phone number is already waiting for review.';
        }
    }

    // ---------- Optional photo (same upload rules as the admin panel) ----------
    $photo = null;
    if (!$errors) {
        try {
            $photo = handle_image_upload('profile_image');
        } catch (RuntimeException $ex) {
            $errors['profile_image'] = $ex->getMessage();
        }
    }

    // ---------- Save account + PENDING application together (one transaction) ----------
    if (!$errors) {
        try {
            $pdo->beginTransaction();

            if ($reapply) {
                // Same account goes back to PENDING; the old rejection note is cleared
                $upd = $pdo->prepare("UPDATE artisan_accounts
                                      SET name = ?, phone = ?, status = 'PENDING', admin_note = NULL, reviewed_at = NULL
                                      WHERE account_id = ? AND status = 'REJECTED'");
                $upd->execute([$old['artisan_name'], $phone, $account['account_id']]);
                if ($upd->rowCount() !== 1) {
                    throw new RuntimeException('Account is no longer in REJECTED state.');
                }
                $accountId = (int) $account['account_id'];
            } else {
                // 1) the artisan's persistent identity (password stored only as a hash)
                $ins = $pdo->prepare("INSERT INTO artisan_accounts (name, email, phone, password_hash, status)
                                      VALUES (?, ?, ?, ?, 'PENDING')");
                $ins->execute([$old['artisan_name'], $old['email'], $phone, password_hash($password, PASSWORD_DEFAULT)]);
                $accountId = (int) $pdo->lastInsertId();
            }

            // 2) the application the admin will review
            $ins = $pdo->prepare("INSERT INTO artisan_requests
                (account_id, district_id, skill_id, artisan_name, age, phone, email, experience, bio, accepts_apprentices, profile_image, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING')");
            $ins->execute([$accountId, $old['district_id'], $old['skill_id'], $old['artisan_name'], (int) $old['age'], $phone, $old['email'],
                           (int) $old['experience'], $old['bio'], $old['accepts_apprentices'], $photo]);
            $requestId = (int) $pdo->lastInsertId();

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            delete_image($photo); // don't leave an orphan file behind
            if ($e instanceof PDOException && $e->getCode() === '23000') {
                // UNIQUE(email) hit by a simultaneous registration
                $errors['email'] = 'An artisan account with this email already exists. Please log in.';
            } elseif ($e instanceof RuntimeException) {
                redirect('artisan/status.php');
            } else {
                throw $e;
            }
        }

        if (!$errors) {
            if (!$reapply) {
                artisan_login(['account_id' => $accountId, 'name' => $old['artisan_name']]); // logged in straight away
            }
            set_flash('success', 'Thank you! Your application #' . $requestId . ' has been received and is now under review.');
            redirect('artisan/status.php');   // Post/Redirect/Get
        }
    }
}

$districts = get_districts($pdo);
$skills    = get_skills_with_district($pdo);

include 'includes/header.php';
?>

<section class="page-header">
    <div class="container">
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2"><li class="breadcrumb-item"><a href="index.php">Home</a></li><li class="breadcrumb-item active" aria-current="page">Register as Artisan</li></ol></nav>
        <h1><?= $reapply ? 'Apply Again as an Artisan' : 'Register as an Artisan' ?></h1>
        <p class="text-muted-ks mb-0">Practise a traditional skill? Share your work so learners and buyers can discover you.</p>
    </div>
</section>

<div class="container mt-4">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="ks-card static"><div class="card-body p-4">
                <?php if ($reapply): ?>
                    <div class="alert alert-info small"><i class="bi bi-arrow-repeat me-1"></i>You are applying again with your existing account
                        (<strong><?= e($account['email']) ?></strong>). Your previous application was not approved<?= $account['admin_note'] ? ' – reason: “' . e($account['admin_note']) . '”' : '' ?>.</div>
                <?php else: ?>
                    <p class="small text-muted-ks">Already registered? <a href="artisan/login.php">Log in to see your application status</a>.</p>
                <?php endif; ?>
                <?php if ($errors): ?>
                    <div class="alert alert-danger small"><i class="bi bi-exclamation-triangle me-1"></i>Please correct the highlighted fields.</div>
                <?php endif; ?>
                <form method="post" action="register.php" enctype="multipart/form-data" class="needs-validation" id="registerForm" novalidate>
                    <h2 class="h5 mb-3">Your details</h2>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="artisan_name">Full name</label>
                            <input type="text" id="artisan_name" name="artisan_name" class="form-control <?= isset($errors['artisan_name']) ? 'is-invalid' : '' ?>"
                                   value="<?= e($old['artisan_name']) ?>" required minlength="2" maxlength="150" autocomplete="name">
                            <div class="invalid-feedback"><?= e($errors['artisan_name'] ?? 'Please enter your name.') ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Mobile number</label>
                            <input type="tel" id="phone" name="phone" class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                                   value="<?= e($old['phone']) ?>" required maxlength="16" placeholder="98765 43210" autocomplete="tel" pattern="[\d\s+\-]{10,16}">
                            <div class="invalid-feedback"><?= e($errors['phone'] ?? 'Enter a valid 10-digit mobile number.') ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email <span class="fw-normal text-muted-ks">(your login)</span></label>
                            <?php if ($reapply): ?>
                                <input type="email" id="email" class="form-control" value="<?= e($account['email']) ?>" readonly disabled>
                            <?php else: ?>
                            <input type="email" id="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                                   value="<?= e($old['email']) ?>" required maxlength="80" autocomplete="email">
                            <div class="invalid-feedback"><?= isset($errors['email']) && str_contains($errors['email'], 'log in')
                                ? e($errors['email']) . ' <a href="artisan/login.php">Log in</a>' : e($errors['email'] ?? 'Enter a valid email address.') ?></div>
                            <?php endif; ?>
                        </div>
                        <?php if (!$reapply): ?>
                        <div class="col-md-6">
                            <label class="form-label" for="password">Create a password</label>
                            <input type="password" id="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                                   required minlength="8" maxlength="72" autocomplete="new-password">
                            <div class="invalid-feedback"><?= e($errors['password'] ?? 'At least 8 characters.') ?></div>
                            <div class="form-text">You'll use your email and this password to check your application status.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="password_confirm">Confirm password</label>
                            <input type="password" id="password_confirm" name="password_confirm" class="form-control <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>"
                                   required minlength="8" maxlength="72" autocomplete="new-password">
                            <div class="invalid-feedback"><?= e($errors['password_confirm'] ?? 'Please type the password again.') ?></div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <h2 class="h5 mt-4 mb-3">Your craft</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="district_id">District</label>
                            <select id="district_id" name="district_id" class="form-select <?= isset($errors['district_id']) ? 'is-invalid' : '' ?>" required>
                                <option value="">Choose your district…</option>
                                <?php foreach ($districts as $d): ?>
                                    <option value="<?= (int) $d['district_id'] ?>" <?= $d['district_id'] == $old['district_id'] ? 'selected' : '' ?>><?= e($d['district_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback"><?= e($errors['district_id'] ?? 'Please choose your district.') ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="skill_id">Skill</label>
                            <select id="skill_id" name="skill_id" class="form-select <?= isset($errors['skill_id']) ? 'is-invalid' : '' ?>" required>
                                <option value="">Choose your skill…</option>
                                <?php foreach ($skills as $s): ?>
                                    <option value="<?= (int) $s['skill_id'] ?>" data-district="<?= (int) $s['district_id'] ?>" <?= $s['skill_id'] == $old['skill_id'] ? 'selected' : '' ?>><?= e($s['skill_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback"><?= e($errors['skill_id'] ?? 'Please choose your skill.') ?></div>
                            <div class="form-text" id="skillHelp">Skills are listed for the district you choose.</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="age">Age</label>
                            <input type="number" id="age" name="age" class="form-control <?= isset($errors['age']) ? 'is-invalid' : '' ?>"
                                   value="<?= e($old['age']) ?>" required min="10" max="120">
                            <div class="invalid-feedback"><?= e($errors['age'] ?? 'Enter your age.') ?></div>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="experience">Years of experience</label>
                            <input type="number" id="experience" name="experience" class="form-control <?= isset($errors['experience']) ? 'is-invalid' : '' ?>"
                                   value="<?= e($old['experience']) ?>" required min="0" max="100">
                            <div class="invalid-feedback"><?= e($errors['experience'] ?? 'Enter years of experience.') ?></div>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="accepts_apprentices" name="accepts_apprentices" <?= $old['accepts_apprentices'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="accepts_apprentices">I am willing to teach apprentices</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="bio">Description</label>
                            <textarea id="bio" name="bio" rows="5" class="form-control <?= isset($errors['bio']) ? 'is-invalid' : '' ?>" required minlength="20" maxlength="2000"
                                      placeholder="How did you learn this craft? What do you make? What makes your work special?"><?= e($old['bio']) ?></textarea>
                            <div class="invalid-feedback"><?= e($errors['bio'] ?? 'Please describe your work (at least 20 characters).') ?></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="profile_image">Photo <span class="fw-normal text-muted-ks">(optional · JPG/PNG/WEBP · max 2 MB)</span></label>
                            <input type="file" id="profile_image" name="profile_image" accept="image/jpeg,image/png,image/webp" class="form-control <?= isset($errors['profile_image']) ? 'is-invalid' : '' ?>">
                            <div class="invalid-feedback"><?= e($errors['profile_image'] ?? '') ?></div>
                            <?php if (!isset($errors['profile_image']) && $errors): ?>
                                <div class="form-text">If you chose a photo, please select it again.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-4 d-flex flex-wrap gap-2 align-items-center">
                        <button type="submit" class="btn btn-terracotta btn-lg"><i class="bi bi-send me-1"></i><?= $reapply ? 'Submit New Application' : 'Submit Registration' ?></button>
                        <span class="small text-muted-ks">Your phone and email are shared on your public profile only after approval.</span>
                    </div>
                </form>
            </div></div>
        </div>

        <div class="col-lg-4">
            <div class="ks-card static mb-4"><div class="card-body">
                <h2 class="h5 mb-3">How it works</h2>
                <ol class="list-unstyled mb-0">
                    <li class="d-flex gap-3 mb-3"><span class="rank-num">1</span><div><strong>Submit</strong><br><span class="small text-muted-ks">Fill in your details and craft. This also creates your artisan login.</span></div></li>
                    <li class="d-flex gap-3 mb-3"><span class="rank-num">2</span><div><strong>Review</strong><br><span class="small text-muted-ks">The KalaSetu admin checks your application. Log in any time to see its status.</span></div></li>
                    <li class="d-flex gap-3"><span class="rank-num">3</span><div><strong>Get discovered</strong><br><span class="small text-muted-ks">Once approved, your profile appears in the artisan directory and you get your own Artisan Dashboard to manage your profile and products.</span></div></li>
                </ol>
            </div></div>
            <div class="ks-card static"><div class="card-body small">
                <div class="fw-semibold mb-1"><i class="bi bi-info-circle me-1 text-terracotta"></i>Skill not listed?</div>
                Each skill on KalaSetu belongs to a district, and new skills are added by the KalaSetu admin. If your craft isn't listed for your district yet, please check back later.
            </div></div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
