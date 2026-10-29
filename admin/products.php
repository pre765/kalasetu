<?php
require '../config/database.php';
require '../includes/functions.php';
require '../includes/auth.php';
require_admin();

$root = '../';
$pageTitle = 'Manage Products';
$activePage = 'products';
$isAdmin = true;

$availabilityOptions = ['In Stock', 'Limited', 'Made to Order', 'Out of Stock'];

$errors = [];
$form = ['product_id' => 0, 'artisan_id' => '', 'product_name' => '', 'description' => '',
         'price' => '', 'availability' => 'In Stock', 'image' => null];
$action = $_GET['action'] ?? 'list';

/* ======================= HANDLE POST ======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Session expired. Please try again.');
        redirect('products.php');
    }

    // ---------- DELETE ----------
    if (($_POST['action'] ?? '') === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $img = $pdo->prepare('SELECT image FROM products WHERE product_id = ?');
        $img->execute([$id]);
        $oldImage = $img->fetchColumn();

        $stmt = $pdo->prepare('DELETE FROM products WHERE product_id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount()) {
            delete_image($oldImage ?: null);
            set_flash('success', 'Product deleted.');
        }
        redirect('products.php');
    }

    // ---------- CREATE / UPDATE ----------
    $form['product_id']   = (int) ($_POST['product_id'] ?? 0);
    $form['artisan_id']   = (int) ($_POST['artisan_id'] ?? 0);
    $form['product_name'] = trim($_POST['product_name'] ?? '');
    $form['description']  = trim($_POST['description'] ?? '');
    $form['price']        = trim($_POST['price'] ?? '');
    $form['availability'] = $_POST['availability'] ?? '';

    if ($form['product_id']) {
        $cur = $pdo->prepare('SELECT image FROM products WHERE product_id = ?');
        $cur->execute([$form['product_id']]);
        $form['image'] = $cur->fetchColumn() ?: null;
    }

    $chk = $pdo->prepare('SELECT COUNT(*) FROM artisans WHERE artisan_id = ?');
    $chk->execute([$form['artisan_id']]);
    if (!$chk->fetchColumn()) {
        $errors['artisan_id'] = 'Please choose a valid artisan.';
    }
    if ($form['product_name'] === '' || mb_strlen($form['product_name']) > 150) {
        $errors['product_name'] = 'Product name is required (max 150 characters).';
    }
    if (!is_numeric($form['price']) || (float) $form['price'] < 0 || (float) $form['price'] > 99999999) {
        $errors['price'] = 'Enter a valid price (0 or more).';
    }
    if (!in_array($form['availability'], $availabilityOptions, true)) {
        $errors['availability'] = 'Please choose availability.';
    }
    if (mb_strlen($form['description']) > 2000) {
        $errors['description'] = 'Description is too long (max 2000 characters).';
    }

    $newImage = null;
    if (!$errors) {
        try {
            $newImage = handle_image_upload('image');
        } catch (RuntimeException $ex) {
            $errors['image'] = $ex->getMessage();
        }
    }

    if (!$errors) {
        $oldImage = $form['image'];
        if ($newImage) {
            $form['image'] = $newImage;
        } elseif (isset($_POST['remove_image'])) {
            $form['image'] = null;
        }
        $values = [$form['artisan_id'], $form['product_name'], $form['description'],
                   round((float) $form['price'], 2), $form['availability'], $form['image']];

        if ($form['product_id']) {
            $stmt = $pdo->prepare('UPDATE products SET artisan_id = ?, product_name = ?, description = ?, price = ?, availability = ?, image = ?
                                   WHERE product_id = ?');
            $stmt->execute([...$values, $form['product_id']]);
            if ($oldImage !== $form['image']) {
                delete_image($oldImage);
            }
            set_flash('success', 'Product updated successfully.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO products (artisan_id, product_name, description, price, availability, image)
                                   VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute($values);
            set_flash('success', 'Product added successfully.');
        }
        redirect('products.php');
    }
    $action = $form['product_id'] ? 'edit' : 'add';
}

/* ======================= LOAD FOR EDIT ======================= */
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE product_id = ?');
    $stmt->execute([get_int('id')]);
    $row = $stmt->fetch();
    if (!$row) {
        set_flash('warning', 'Product not found.');
        redirect('products.php');
    }
    $form = $row;
}

/* ======================= LIST ======================= */
$products = $pdo->query("
    SELECT p.product_id, p.product_name, p.price, p.availability, a.artisan_name, s.skill_name
    FROM products p
    INNER JOIN artisans a ON p.artisan_id = a.artisan_id
    INNER JOIN skills s   ON a.skill_id = s.skill_id
    ORDER BY p.product_name
")->fetchAll();

// Artisans grouped by skill for the dropdown
$artisanOptions = $pdo->query("
    SELECT a.artisan_id, a.artisan_name, s.skill_name
    FROM artisans a INNER JOIN skills s ON a.skill_id = s.skill_id
    ORDER BY s.skill_name, a.artisan_name
")->fetchAll();

include '../includes/header.php';
?>
<div class="container py-4">
<?php if ($action === 'add' || $action === 'edit'): ?>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="products.php">Products</a></li><li class="breadcrumb-item active"><?= $action === 'edit' ? 'Edit' : 'Add' ?></li></ol></nav>
    <h1 class="h3 mb-3"><?= $action === 'edit' ? 'Edit Product' : 'Add Product' ?></h1>
    <div class="ks-card static" style="max-width:800px"><div class="card-body p-4">
        <form method="post" action="products.php" enctype="multipart/form-data" class="needs-validation" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="product_id" value="<?= (int) $form['product_id'] ?>">
            <div class="row g-3">
                <div class="col-md-7">
                    <label class="form-label" for="product_name">Product name</label>
                    <input type="text" id="product_name" name="product_name" class="form-control <?= isset($errors['product_name']) ? 'is-invalid' : '' ?>" value="<?= e($form['product_name']) ?>" required maxlength="150">
                    <div class="invalid-feedback"><?= e($errors['product_name'] ?? 'Product name is required.') ?></div>
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="artisan_id">Artisan</label>
                    <select id="artisan_id" name="artisan_id" class="form-select <?= isset($errors['artisan_id']) ? 'is-invalid' : '' ?>" required>
                        <option value="">Choose…</option>
                        <?php $currentGroup = null; foreach ($artisanOptions as $o):
                            if ($o['skill_name'] !== $currentGroup) {
                                if ($currentGroup !== null) echo '</optgroup>';
                                echo '<optgroup label="' . e($o['skill_name']) . '">';
                                $currentGroup = $o['skill_name'];
                            } ?>
                            <option value="<?= (int) $o['artisan_id'] ?>" <?= $o['artisan_id'] == $form['artisan_id'] ? 'selected' : '' ?>><?= e($o['artisan_name']) ?></option>
                        <?php endforeach; if ($currentGroup !== null) echo '</optgroup>'; ?>
                    </select>
                    <div class="invalid-feedback"><?= e($errors['artisan_id'] ?? 'Please choose an artisan.') ?></div>
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label" for="price">Price (₹)</label>
                    <input type="number" id="price" name="price" step="0.01" min="0" class="form-control <?= isset($errors['price']) ? 'is-invalid' : '' ?>" value="<?= e($form['price']) ?>" required>
                    <div class="invalid-feedback"><?= e($errors['price'] ?? 'Enter a valid price.') ?></div>
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label" for="availability">Availability</label>
                    <select id="availability" name="availability" class="form-select <?= isset($errors['availability']) ? 'is-invalid' : '' ?>" required>
                        <?php foreach ($availabilityOptions as $opt): ?>
                            <option <?= $opt === $form['availability'] ? 'selected' : '' ?>><?= e($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback"><?= e($errors['availability'] ?? '') ?></div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="description">Description</label>
                    <textarea id="description" name="description" rows="3" maxlength="2000" class="form-control <?= isset($errors['description']) ? 'is-invalid' : '' ?>"><?= e($form['description']) ?></textarea>
                    <div class="invalid-feedback"><?= e($errors['description'] ?? '') ?></div>
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="image">Product image <span class="fw-normal text-muted-ks">(optional · JPG/PNG/WEBP · max 2 MB)</span></label>
                    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp" class="form-control <?= isset($errors['image']) ? 'is-invalid' : '' ?>">
                    <div class="invalid-feedback"><?= e($errors['image'] ?? '') ?></div>
                    <?php if (!empty($form['image'])): ?>
                        <div class="d-flex align-items-center gap-2 mt-2">
                            <img src="<?= e($root . 'assets/images/uploads/' . $form['image']) ?>" alt="" style="width:56px;height:56px;object-fit:cover;border-radius:8px">
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="remove_image" id="remove_image"><label class="form-check-label small" for="remove_image">Remove current image</label></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="mt-4">
                <button class="btn btn-maroon" type="submit"><i class="bi bi-check-lg me-1"></i><?= $action === 'edit' ? 'Save changes' : 'Add product' ?></button>
                <a href="products.php" class="btn btn-outline-maroon ms-1">Cancel</a>
            </div>
        </form>
    </div></div>

<?php else: ?>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h1 class="h3 mb-0">Manage Products</h1>
        <a href="products.php?action=add" class="btn btn-maroon"><i class="bi bi-plus-lg me-1"></i>Add Product</a>
    </div>
    <?php show_flash(); ?>
    <div class="ks-table table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Product</th><th>Artisan</th><th>Skill</th><th class="text-end">Price</th><th>Availability</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td><strong><?= e($p['product_name']) ?></strong></td>
                    <td><?= e($p['artisan_name']) ?></td>
                    <td><?= e($p['skill_name']) ?></td>
                    <td class="text-end text-nowrap"><?= format_price($p['price']) ?></td>
                    <td><span class="ks-badge"><?= e($p['availability']) ?></span></td>
                    <td class="text-end text-nowrap">
                        <a href="products.php?action=edit&id=<?= (int) $p['product_id'] ?>" class="btn btn-sm btn-outline-maroon"><i class="bi bi-pencil"></i> Edit</a>
                        <form method="post" action="products.php" class="d-inline" data-confirm="Delete the product '<?= e($p['product_name']) ?>'?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $p['product_id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i> Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$products): ?><tr><td colspan="6" class="text-center text-muted-ks py-4">No products yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
