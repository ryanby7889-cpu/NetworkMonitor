<?php
// Shared page header.
// Usage: set $pageTitle before including this file.
$pageTitle = $pageTitle ?? 'Network Monitor';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

    <!-- Load shared design tokens and components first. -->
    <link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/common.css">

    <?php if (!empty($pageCss)): ?>
        <?php foreach ((array)$pageCss as $css): ?>
            <link rel="stylesheet" href="<?= htmlspecialchars($css, ENT_QUOTES, 'UTF-8') ?>">
        <?php endforeach; ?>
    <?php endif; ?>
<script>
(function () {
    try {
        if (localStorage.getItem('netmonitor_theme') === 'dark') {
            document.documentElement.classList.add('theme-dark');
        }
    } catch (e) {}
})();
</script>
</head>
<body>
