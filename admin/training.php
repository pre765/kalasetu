<?php
require '../config/database.php';
require '../includes/functions.php';
require '../includes/auth.php';
require_admin();

$root = '../';
$pageTitle = 'Manage Training';
$activePage = 'training';
$isAdmin = true;

$errors = [];
$form = ['training_id' => 0, 'skill_id' => '', 'training_name' => '', 'duration' => '', 'available_seats' => '', 'description' => ''];
$action = $_GET['action'] ?? 'list';

/* ======================= HANDLE POST ======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Session expired. Please try again.');
        redirect('training.php');
    }

    // ---------- DELETE ----------
    if (($_POST['action'] ?? '') === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM training WHERE training_id = ?');
        $stmt->execute([(int) ($_POST['id'] ?? 0)]);
        if ($stmt->rowCount()) {
            set_flash('success', 'Training programme deleted.');
        }
        redirect('training.php');
    }

    // ---------- CREATE / UPDATE ----------
    $form['training_id']     = (int) ($_POST['training_id'] ?? 0);
    $form['skill_id']        = (int) ($_POST['skill_id'] ?? 0);
    $form['training_name']   = trim($_POST['training_name'] ?? '');
    $form['duration']        = trim($_POST['duration'] ?? '');
    $form['available_seats'] = trim($_POST['available_seats'] ?? '');
    $form['description']     = trim($_POST['description'] ?? '');

    $chk = $pdo->prepare('SELECT COUNT(*) FROM skills WHERE skill_id = ?');
    $chk->execute([$form['skill_id']]);
    if (!$chk->fetchColumn()) {
        $errors['skill_id'] = 'Please choose a valid skill.';
    }
    if ($form['training_name'] === '' || mb_strlen($form['training_name']) > 150) {
        $errors['training_name'] = 'Training name is required (max 150 characters).';
    }
    if ($form['duration'] === '' || mb_strlen($form['duration']) > 100) {
        $errors['duration'] = 'Duration is required, e.g. "3 Months".';
    }
    if (filter_var($form['available_seats'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000]]) === false) {
        $errors['available_seats'] = 'Seats must be a whole number from 0 to 1000.';
    }
    if (mb_strlen($form['description']) < 10) {
        $errors['description'] = 'Please write a short description (at least 10 characters).';
    }

    if (!$errors) {
        $values = [$form['skill_id'], $form['training_name'], $form['duration'], (int) $form['available_seats'], $form['description']];
        if ($form['training_id']) {
            $stmt = $pdo->prepare('UPDATE training SET skill_id = ?, training_name = ?, duration = ?, available_seats = ?, description = ? WHERE training_id = ?');
            $stmt->execute([...$values, $form['training_id']]);
            set_flash('success', 'Training programme updated.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO training (skill_id, training_name, duration, available_seats, description) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute($values);
            set_flash('success', 'Training programme added.');
        }
        redirect('training.php');
    }
    $action = $form['training_id'] ? 'edit' : 'add';
}

/* ======================= LOAD FOR EDIT ======================= */
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT * FROM training WHERE training_id = ?');
    $stmt->execute([get_int('id')]);
    $row = $stmt->fetch();
    if (!$row) {
        set_flash('warning', 'Training programme not found.');
        redirect('training.php');
    }
    $form = $row;
}

/* ======================= LIST ======================= */
$trainings = $pdo->query("
    SELECT t.training_id, t.training_name, t.duration, t.available_seats, s.skill_name
    FROM training t INNER JOIN skills s ON t.skill_id = s.skill_id
    ORDER BY s.skill_name, t.training_name
")->fetchAll();

$skills = get_skills($pdo);

include '../includes/header.php';
?>
<div class="container py-4">
<?php if ($action === 'add' || $action === 'edit'): ?>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="training.php">Training</a></li><li class="breadcrumb-item active"><?= $action === 'edit' ? 'Edit' : 'Add' ?></li></ol></nav>
    <h1 class="h3 mb-3"><?= $action === 'edit' ? 'Edit Training Programme' : 'Add Training Programme' ?></h1>
    <div class="ks-card static" style="max-width:760px"><div class="card-body p-4">
        <form method="post" action="training.php" class="needs-validation" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="training_id" value="<?= (int) $form['training_id'] ?>">
            <div class="row g-3">
                <div class="col-md-7">
                    <label class="form-label" for="training_name">Training name</label>
                    <input type="text" id="training_name" name="training_name" class="form-control <?= isset($errors['training_name']) ? 'is-invalid' : '' ?>" value="<?= e($form['training_name']) ?>" required maxlength="150">
                    <div class="invalid-feedback"><?= e($errors['training_name'] ?? 'Training name is required.') ?></div>
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="skill_id">Skill</label>
                    <select id="skill_id" name="skill_id" class="form-select <?= isset($errors['skill_id']) ? 'is-invalid' : '' ?>" required>
                        <option value="">Choose…</option>
                        <?php foreach ($skills as $s): ?>
                            <option value="<?= (int) $s['skill_id'] ?>" <?= $s['skill_id'] == $form['skill_id'] ? 'selected' : '' ?>><?= e($s['skill_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback"><?= e($errors['skill_id'] ?? 'Please choose a skill.') ?></div>
                </div>
                <div class="col-6">
                    <label class="form-label" for="duration">Duration</label>
                    <input type="text" id="duration" name="duration" class="form-control <?= isset($errors['duration']) ? 'is-invalid' : '' ?>" value="<?= e($form['duration']) ?>" required maxlength="100" placeholder="e.g. 3 Months">
                    <div class="invalid-feedback"><?= e($errors['duration'] ?? 'Duration is required.') ?></div>
                </div>
                <div class="col-6">
                    <label class="form-label" for="available_seats">Available seats</label>
                    <input type="number" id="available_seats" name="available_seats" min="0" max="1000" class="form-control <?= isset($errors['available_seats']) ? 'is-invalid' : '' ?>" value="<?= e($form['available_seats']) ?>" required>
                    <div class="invalid-feedback"><?= e($errors['available_seats'] ?? 'Enter number of seats.') ?></div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="description">Description</label>
                    <textarea id="description" name="description" rows="4" class="form-control <?= isset($errors['description']) ? 'is-invalid' : '' ?>" required minlength="10"><?= e($form['description']) ?></textarea>
                    <div class="invalid-feedback"><?= e($errors['description'] ?? 'Please write a short description.') ?></div>
                </div>
            </div>
            <div class="mt-4">
                <button class="btn btn-maroon" type="submit"><i class="bi bi-check-lg me-1"></i><?= $action === 'edit' ? 'Save changes' : 'Add training' ?></button>
                <a href="training.php" class="btn btn-outline-maroon ms-1">Cancel</a>
            </div>
        </form>
    </div></div>

<?php else: ?>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h1 class="h3 mb-0">Manage Training</h1>
        <a href="training.php?action=add" class="btn btn-maroon"><i class="bi bi-plus-lg me-1"></i>Add Training</a>
    </div>
    <?php show_flash(); ?>
    <div class="ks-table table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Training</th><th>Skill</th><th>Duration</th><th class="text-center">Seats</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($trainings as $t): ?>
                <tr>
                    <td><strong><?= e($t['training_name']) ?></strong></td>
                    <td><?= e($t['skill_name']) ?></td>
                    <td><?= e($t['duration']) ?></td>
                    <td class="text-center"><?= (int) $t['available_seats'] ?></td>
                    <td class="text-end text-nowrap">
                        <a href="training.php?action=edit&id=<?= (int) $t['training_id'] ?>" class="btn btn-sm btn-outline-maroon"><i class="bi bi-pencil"></i> Edit</a>
                        <form method="post" action="training.php" class="d-inline" data-confirm="Delete '<?= e($t['training_name']) ?>'?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $t['training_id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i> Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$trainings): ?><tr><td colspan="5" class="text-center text-muted-ks py-4">No training programmes yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
