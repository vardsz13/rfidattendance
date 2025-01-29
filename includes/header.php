<!-- /header.php -->
<?php
require_once dirname(__DIR__) . '/includes/links.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?></title>
    <script src="<?= CDN['TAILWIND'] ?>"></script>
    <script src="<?= CDN['JQUERY'] ?>"></script>
    <link href="<?= CDN['FONT_AWESOME'] ?>" rel="stylesheet">
    <?php if (isset($useDataTables)): ?>
        <link href="<?= CDN['DATATABLES_CSS'] ?>" rel="stylesheet">
        <script src="<?= CDN['DATATABLES_JS'] ?>"></script>
    <?php endif; ?>
    <?php if (isset($useCalendar)): ?>
        <link href="<?= CDN['FULLCALENDAR_CSS'] ?>" rel="stylesheet">
        <script src="<?= CDN['FULLCALENDAR_JS'] ?>"></script>
    <?php endif; ?>
    <?php if (isset($useCharts)): ?>
        <script src="<?= CDN['CHART_JS'] ?>"></script>
    <?php endif; ?>
    <link href="<?= ASSETS_URL ?>/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">
    <?php include 'nav.php'; ?>
    <div class="container mx-auto px-4 py-8">
    <?php
    $flash = getFlashMessage();
    if ($flash): ?>
        <div class="mb-4 p-4 rounded <?= $flash['type'] === 'error' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' ?>">
            <?= $flash['message'] ?>
        </div>
    <?php endif; ?>
