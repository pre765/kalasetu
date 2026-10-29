<?php
/**
 * Reusable helper functions used across KalaSetu.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Escape any value before printing it in HTML (prevents XSS). */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Redirect to another page and stop the script. */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Store a one-time message to show on the next page. */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** Print (and clear) the one-time message, if any. */
function show_flash(): void
{
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        $icon = $f['type'] === 'success' ? 'check-circle' : 'exclamation-triangle';
        echo '<div class="alert alert-' . e($f['type']) . ' alert-dismissible fade show" role="alert">'
           . '<i class="bi bi-' . $icon . ' me-2"></i>' . e($f['message'])
           . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
}

/* ---------------- CSRF protection for admin forms ---------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): bool
{
    return isset($_POST['csrf_token']) && hash_equals(csrf_token(), (string) $_POST['csrf_token']);
}

/* ---------------- Small display helpers ---------------- */

/** Project-defined risk level from the number of registered artisans. */
function risk_level(int $count): array
{
    if ($count < 10) {
        return ['label' => 'HIGH RISK', 'class' => 'risk-high'];
    }
    if ($count <= 20) {
        return ['label' => 'MEDIUM RISK', 'class' => 'risk-medium'];
    }
    return ['label' => 'LOW RISK', 'class' => 'risk-low'];
}

/** Bootstrap icon for each skill category (used when no image exists). */
function category_icon(?string $category): string
{
    $map = [
        'Pottery'             => 'bi-cup',
        'Craft'               => 'bi-basket',
        'Leather'             => 'bi-bag',
        'Jewellery'           => 'bi-gem',
        'Folk Art'            => 'bi-brush',
        'Textile'             => 'bi-scissors',
        'Woodwork'            => 'bi-tree',
        'Metal Craft'         => 'bi-hammer',
        'Musical Instruments' => 'bi-music-note-beamed',
    ];
    return $map[$category] ?? 'bi-palette';
}

/** Two-letter initials for artisan avatars. */
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last);
}

/** Avatar: uploaded photo if available, otherwise coloured initials. */
function artisan_avatar(array $a, string $root, string $size = ''): string
{
    $cls = 'avatar' . ($size ? ' avatar-' . $size : '');
    if (!empty($a['profile_image'])) {
        return '<img src="' . e($root . 'assets/images/uploads/' . $a['profile_image']) . '" alt="'
             . e($a['artisan_name']) . '" class="' . $cls . ' avatar-img">';
    }
    $tone = ((int) ($a['artisan_id'] ?? 0)) % 5;
    return '<div class="' . $cls . ' avatar-tone-' . $tone . '" aria-hidden="true">' . e(initials($a['artisan_name'])) . '</div>';
}

/** Product image area: uploaded image or a category icon tile. */
function product_image(array $p, string $root): string
{
    if (!empty($p['image'])) {
        return '<img src="' . e($root . 'assets/images/uploads/' . $p['image']) . '" alt="' . e($p['product_name']) . '" class="card-img-top product-img">';
    }
    return '<div class="product-placeholder"><i class="bi ' . category_icon($p['category'] ?? null) . '"></i></div>';
}

function format_price($price): string
{
    return '₹' . number_format((float) $price, 2);
}

/** Shorten long text for cards. */
function excerpt(?string $text, int $len = 110): string
{
    $text = trim((string) $text);
    return mb_strlen($text) > $len ? mb_substr($text, 0, $len) . '…' : $text;
}

/* ---------------- Dropdown data (used by filters and admin forms) ---------------- */

function get_districts(PDO $pdo): array
{
    return $pdo->query('SELECT district_id, district_name FROM districts ORDER BY district_name')->fetchAll();
}

function get_skills(PDO $pdo): array
{
    return $pdo->query('SELECT skill_id, skill_name FROM skills ORDER BY skill_name')->fetchAll();
}

function get_categories(PDO $pdo): array
{
    return $pdo->query('SELECT DISTINCT category FROM skills ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);
}

/** Skills with their district, for the district → skill dropdowns (register page). */
function get_skills_with_district(PDO $pdo): array
{
    return $pdo->query('SELECT skill_id, skill_name, district_id FROM skills ORDER BY skill_name')->fetchAll();
}

/* ---------------- Artisan registration helpers ---------------- */

/**
 * Normalise an Indian mobile number to 10 digits.
 * Accepts "98765 43210", "+91-9876543210", "09876543210". Returns null if invalid.
 */
function normalize_phone(string $phone): ?string
{
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
        $digits = substr($digits, 2);
    } elseif (strlen($digits) === 11 && $digits[0] === '0') {
        $digits = substr($digits, 1);
    }
    return preg_match('/^[6-9]\d{9}$/', $digits) ? $digits : null;
}

/** "9876543210" → "+91 98765 43210" */
function format_phone(string $digits): string
{
    return strlen($digits) === 10 ? '+91 ' . substr($digits, 0, 5) . ' ' . substr($digits, 5) : $digits;
}

/**
 * The `artisans` table has a single `contact` column (VARCHAR 100), so on
 * approval the phone and email from the request are combined into it.
 * Max length: 15 + 3 + 80 = 98 characters.
 */
function artisan_contact(string $phone, string $email): string
{
    return format_phone($phone) . ' · ' . $email;
}

/** Number of registration requests waiting for review (admin navbar badge + dashboard). */
function pending_request_count(PDO $pdo): int
{
    return (int) $pdo->query("SELECT COUNT(*) FROM artisan_requests WHERE status = 'PENDING'")->fetchColumn();
}

/** Bootstrap-styled badge class for a request status. */
function request_status_class(string $status): string
{
    return ['PENDING' => 'risk-medium', 'APPROVED' => 'risk-low', 'REJECTED' => 'risk-high'][$status] ?? 'risk-medium';
}

/** Read a positive integer from GET (returns 0 if missing/invalid). */
function get_int(string $key): int
{
    $v = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $v ? (int) $v : 0;
}

/**
 * Handle an optional image upload for admin forms.
 * Returns the saved file name, null if nothing uploaded, or throws on invalid file.
 */
function handle_image_upload(string $field): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed. Please try again.');
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Image must be smaller than 2 MB.');
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $info = @getimagesize($file['tmp_name']);
    if ($info === false || !isset($allowed[$info['mime']])) {
        throw new RuntimeException('Only JPG, PNG or WEBP images are allowed.');
    }
    $name = bin2hex(random_bytes(8)) . '.' . $allowed[$info['mime']];
    $dest = __DIR__ . '/../assets/images/uploads/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save the uploaded image.');
    }
    return $name;
}

/** Delete an old uploaded image file (when replaced or record deleted). */
function delete_image(?string $name): void
{
    if ($name && preg_match('/^[a-f0-9]{16}\.(jpg|png|webp)$/', $name)) {
        $path = __DIR__ . '/../assets/images/uploads/' . $name;
        if (is_file($path)) {
            unlink($path);
        }
    }
}
