<?php
require '../config/database.php';
require '../includes/functions.php';
require '../includes/auth.php';
require_admin();

$root = '../';
$pageTitle = 'Manage Skills';
$activePage = 'skills';
$isAdmin = true;

$errors = [];
$form = ['skill_id' => 0, 'skill_name' => '', 'district_id' => '', 'category' => '', 'description' => ''];
$action = $_GET['action'] ?? 'list';

/* ======================= HANDLE POST (create / update / delete) ======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Session expired. Please try again.');
        redirect('skills.php');
    }

    // ---------- DELETE ----------
    if (($_POST['action'] ?? '') === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $stmt = $pdo->prepare('DELETE FROM skills WHERE skill_id = ?');
            $stmt->execute([$id]);
            set_flash('success', 'Skill deleted. Its training programmes and registration requests were removed too (ON DELETE CASCADE).');
        } catch (PDOException $e) {
            // 23000 = foreign key restriction: artisans still use this skill
            if ($e->getCode() === '23000') {
                set_flash('danger', 'Cannot delete this skill because artisans are linked to it. Move or delete those artisans first.');
            } else {
                throw $e;
            }
        }
        redirect('skills.php');
    }

    // ---------- CREATE / UPDATE ----------
    $form['skill_id']    = (int) ($_POST['skill_id'] ?? 0);
    $form['skill_name']  = trim($_POST['skill_name'] ?? '');
    $form['district_id'] = (int) ($_POST['district_id'] ?? 0);
    $form['category']    = trim($_POST['category'] ?? '');
    $form['description'] = trim($_POST['description'] ?? '');

    if ($form['skill_name'] === '' || mb_strlen($form['skill_name']) > 150) {
        $errors['skill_name'] = 'Skill name is required (max 150 characters).';
    }
    $chk = $pdo->prepare('SELECT COUNT(*) FROM districts WHERE district_id = ?');
    $chk->execute([$form['district_id']]);
    if (!$chk->fetchColumn()) {
        $errors['district_id'] = 'Please choose a valid district.';
    }
    if ($form['category'] === '' || mb_strlen($form['category']) > 100) {
        $errors['category'] = 'Category is required (max 100 characters).';
    }
    if (mb_strlen($form['description']) < 10) {
        $errors['description'] = 'Please write a short description (at least 10 characters).';
    }

    if (!$errors) {
        if ($form['skill_id']) {
            $stmt = $pdo->prepare('UPDATE skills SET skill_name = ?, district_id = ?, category = ?, description = ? WHERE skill_id = ?');
            $stmt->execute([$form['skill_name'], $form['district_id'], $form['category'], $form['description'], $form['skill_id']]);
            set_flash('success', 'Skill updated successfully.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO skills (skill_name, district_id, category, description) VALUES (?, ?, ?, ?)');
            $stmt->execute([$form['skill_name'], $form['district_id'], $form['category'], $form['description']]);
            set_flash('success', 'Skill added successfully.');
        }
        redirect('skills.php');
    }
    $action = $form['skill_id'] ? 'edit' : 'add'; // show form again with errors
}

/* ======================= LOAD DATA FOR EDIT FORM ======================= */
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT * FROM skills WHERE skill_id = ?');
    $stmt->execute([get_int('id')]);
    $row = $stmt->fetch();
    if (!$row) {
        set_flash('warning', 'Skill not found.');
        redirect('skills.php');
    }
    $form = $row;
}

/* ======================= LIST ======================= */
$skills = $pdo->query("
    SELECT s.skill_id, s.skill_name, s.category, d.district_name,
           COUNT(DISTINCT a.artisan_id) AS artisan_count,
           COUNT(DISTINCT t.training_id) AS training_count
    FROM skills s
    INNER JOIN districts d ON s.district_id = d.district_id
    LEFT JOIN artisans a   ON a.skill_id = s.skill_id
    LEFT JOIN training t   ON t.skill_id = s.skill_id
    GROUP BY s.skill_id, s.skill_name, s.category, d.district_name
    ORDER BY s.skill_name
")->fetchAll();

$districts  = get_districts($pdo);
$categories = get_categories($pdo);

include '../includes/header.php';
?>
<div class="container py-4">
<?php if ($action === 'add' || $action === 'edit'): ?>
    <!-- ================= ADD / EDIT FORM ================= -->
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="skills.php">Skills</a></li><li class="breadcrumb-item active"><?= $action === 'edit' ? 'Edit' : 'Add' ?></li></ol></nav>
    <h1 class="h3 mb-3"><?= $action === 'edit' ? 'Edit Skill' : 'Add Skill' ?></h1>
    <div class="ks-card static" style="max-width:760px"><div class="card-body p-4">
        <form method="post" action="skills.php" class="needs-validation" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="skill_id" value="<?= (int) $form['skill_id'] ?>">
            <div class="mb-3">
                <label class="form-label" for="skill_name">Skill name</label>
                <input type="text" id="skill_name" name="skill_name" class="form-control <?= isset($errors['skill_name']) ? 'is-invalid' : '' ?>" value="<?= e($form['skill_name']) ?>" required maxlength="150">
                <div class="invalid-feedback"><?= e($errors['skill_name'] ?? 'Skill name is required.') ?></div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label" for="district_id">District</label>
                    <select id="district_id" name="district_id" class="form-select <?= isset($errors['district_id']) ? 'is-invalid' : '' ?>" required>
                        <option value="">Choose…</option>
                        <?php foreach ($districts as $d): ?>
                            <option value="<?= (int) $d['district_id'] ?>" <?= $d['district_id'] == $form['district_id'] ? 'selected' : '' ?>><?= e($d['district_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback"><?= e($errors['district_id'] ?? 'Please choose a district.') ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="category">Category</label>
                    <input type="text" id="category" name="category" list="categoryList" class="form-control <?= isset($errors['category']) ? 'is-invalid' : '' ?>" value="<?= e($form['category']) ?>" required maxlength="100" placeholder="e.g. Pottery, Textile">
                    <datalist id="categoryList"><?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
                    <div class="invalid-feedback"><?= e($errors['category'] ?? 'Category is required.') ?></div>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label" for="description">Description</label>
                <textarea id="description" name="description" rows="5" class="form-control <?= isset($errors['description']) ? 'is-invalid' : '' ?>" required minlength="10"><?= e($form['description']) ?></textarea>
                <div class="invalid-feedback"><?= e($errors['description'] ?? 'Please write a short description.') ?></div>
            </div>
            <button class="btn btn-maroon" type="submit"><i class="bi bi-check-lg me-1"></i><?= $action === 'edit' ? 'Save changes' : 'Add skill' ?></button>
            <a href="skills.php" class="btn btn-outline-maroon ms-1">Cancel</a>
        </form>
    </div></div>

<?php else: ?>
    <!-- ================= LIST ================= -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h1 class="h3 mb-0">Manage Skills</h1>
        <a href="skills.php?action=add" class="btn btn-maroon"><i class="bi bi-plus-lg me-1"></i>Add Skill</a>
    </div>
    <?php show_flash(); ?>
    <div class="ks-table table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Skill</th><th>District</th><th>Category</th><th class="text-center">Artisans</th><th class="text-center">Training</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($skills as $s): ?>
                <tr>
                    <td><strong><?= e($s['skill_name']) ?></strong></td>
                    <td><?= e($s['district_name']) ?></td>
                    <td><?= e($s['category']) ?></td>
                    <td class="text-center"><?= (int) $s['artisan_count'] ?></td>
                    <td class="text-center"><?= (int) $s['training_count'] ?></td>
                    <td class="text-end text-nowrap">
                        <a href="skills.php?action=edit&id=<?= (int) $s['skill_id'] ?>" class="btn btn-sm btn-outline-maroon"><i class="bi bi-pencil"></i> Edit</a>
                        <form method="post" action="skills.php" class="d-inline" data-confirm="Delete the skill '<?= e($s['skill_name']) ?>'?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $s['skill_id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i> Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$skills): ?><tr><td colspan="6" class="text-center text-muted-ks py-4">No skills yet. Click “Add Skill”.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
