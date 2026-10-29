<?php
/**
 * Approved artisan manages THEIR OWN products in the existing `products`
 * table. Every query is limited with "AND artisan_id = <my artisan_id>",
 * so an artisan can never see or change another artisan's products.
 */
require '../config/database.php';
require '../includes/functions.php';
require '../includes/auth.php';

$artisanAccount = require_approved_artisan($pdo);
$artisanId = (int) $artisanAccount['artisan_id'];

$root = '../';
$pageTitle = 'My Products';
$activePage = 'a_products';
$isArtisan = true;

$availabilityOptions = ['In Stock', 'Limited', 'Made to Order', 'Out of Stock'];   // same as Admin → Products

$errors = [];
$form = ['product_id' => 0, 'product_name' => '', 'description' => '', 'price' => '', 'availability' => 'In Stock', 'image' => null];
$action = $_GET['action'] ?? 'list';

/** Load one of MY products, or null. */
function my_product(PDO $pdo, int $productId, int $artisanId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM products WHERE product_id = ? AND artisan_id = ?');
    $stmt->execute([$productId, $artisanId]);
    return $stmt->fetch() ?: null;
}

/* ======================= HANDLE POST ======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Session expired. Please try again.');
        redirect('products.php');
    }

    // ---------- DELETE ----------
    if (($_POST['action'] ?? '') === 'delete') {
        $p = my_product($pdo, (int) ($_POST['id'] ?? 0), $artisanId);
        if ($p) {
            $pdo->prepare('DELETE FROM products WHERE product_id = ? AND artisan_id = ?')->execute([$p['product_id'], $artisanId]);
            delete_image($p['image']);
            set_flash('success', 'Product deleted.');
        }
        redirect('products.php');
    }

    // ---------- CREATE / UPDATE ----------
    $form['product_id']   = (int) ($_POST['product_id'] ?? 0);
    $form['product_name'] = trim($_POST['product_name'] ?? '');
    $form['description']  = trim($_POST['description'] ?? '');
    $form['price']        = trim($_POST['price'] ?? '');
    $form['availability'] = $_POST['availability'] ?? '';

    if ($form['product_id']) {
        $existing = my_product($pdo, $form['product_id'], $artisanId);
        if (!$existing) {
            set_flash('warning', 'Product not found.');
            redirect('products.php');
        }
        $form['image'] = $existing['image'];
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
        $values = [$form['product_name'], $form['description'], round((float) $form['price'], 2), $form['availability'], $form['image']];

        if ($form['product_id']) {
            $pdo->prepare('UPDATE products SET product_name = ?, description = ?, price = ?, availability = ?, image = ?
                           WHERE product_id = ? AND artisan_id = ?')
                ->execute([...$values, $form['product_id'], $artisanId]);
            if ($oldImage !== $form['image']) {
                delete_image($oldImage);
            }
            set_flash('success', 'Product updated successfully.');
        } else {
            $pdo->prepare('INSERT INTO products (artisan_id, product_name, description, price, availability, image) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$artisanId, ...$values]);
            set_flash('success', 'Product added. Visitors can now see it on your profile and the Products page.');
        }
        redirect('products.php');
    }
    $action = $form['product_id'] ? 'edit' : 'add';
}

/* ======================= LOAD FOR EDIT ======================= */
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $row = my_product($pdo, get_int('id'), $artisanId);
    if (!$row) {
        set_flash('warning', 'Product not found.');
        redirect('products.php');
    }
    $form = $row;
}

/* ======================= LIST ======================= */
$stmt = $pdo->prepare('SELECT product_id, product_name, price, availability, image FROM products WHERE artisan_id = ? ORDER BY product_name');
$stmt->execute([$artisanId]);
$products = $stmt->fetchAll();

include '../includes/header.php';
?>
<div class="container py-4">
<?php if ($action === 'add' || $action === 'edit'): ?>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li><li class="breadcrumb-item"><a href="products.php">My Products</a></li><li class="breadcrumb-item active"><?= $action === 'edit' ? 'Edit' : 'Add' ?></li></ol></nav>
    <h1 class="h3 mb-3"><?= $action === 'edit' ? 'Edit Product' : 'Add Product' ?></h1>
    <div class="ks-card static" style="max-width:800px"><div class="card-body p-4">
        <form method="post" action="products.php" enctype="multipart/form-data" class="needs-validation" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="product_id" value="<?= (int) $form['product_id'] ?>">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="product_name">Product name</label>
                    <input type="text" id="product_name" name="product_name" class="form-control <?= isset($errors['product_name']) ? 'is-invalid' : '' ?>" value="<?= e($form['product_name']) ?>" required maxlength="150">
                    <div class="invalid-feedback"><?= e($errors['product_name'] ?? 'Product name is required.') ?></div>
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
        <h1 class="h3 mb-0">My Products</h1>
        <a href="products.php?action=add" class="btn btn-maroon"><i class="bi bi-plus-lg me-1"></i>Add Product</a>
    </div>
    <?php show_flash(); ?>
    <div class="ks-table table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Product</th><th class="text-end">Price</th><th>Availability</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td><strong><?= e($p['product_name']) ?></strong></td>
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
            <?php if (!$products): ?><tr><td colspan="4" class="text-center text-muted-ks py-4">You have not added any products yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
