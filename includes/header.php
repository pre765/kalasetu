<?php
/**
 * Common <head> + navbar.
 * Before including, a page may set:
 *   $root       – path back to project root ('' for public pages, '../' for admin)
 *   $pageTitle  – title shown in the browser tab
 *   $activePage – which navbar link to highlight
 *   $isAdmin    – true on admin pages (shows the admin navbar)
 *   $isArtisan  – true on artisan-area pages (shows the artisan navbar;
 *                 the page also sets $artisanAccount)
 */
$root       = $root ?? '';
$pageTitle  = $pageTitle ?? 'KalaSetu';
$activePage = $activePage ?? '';
$isAdmin    = $isAdmin ?? false;
$isArtisan  = $isArtisan ?? false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | KalaSetu</title>
    <meta name="description" content="KalaSetu – discover India's traditional skills, the artisans who keep them alive, and opportunities to learn them.">

    <!-- Bootstrap 5 + Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Fonts: Marcellus for headings, Mukta for body text -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Marcellus&family=Mukta:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link href="<?= e($root) ?>assets/css/style.css" rel="stylesheet">
</head>
<body class="<?= $isAdmin ? 'admin-body' : ($isArtisan ? 'artisan-body' : '') ?>">
<?php include __DIR__ . '/navbar.php'; ?>
<main>
