<?php 
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#007bff">
    <title><?= APP_NAME ?></title>
    <!-- Bootstrap CSS -->
    <link href="<?= BASE_PATH ?>/assets/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom Mobile CSS -->
    <link href="<?= BASE_PATH ?>/assets/css/mobile-app.css" rel="stylesheet">
    <!-- Web App Manifest -->
    <link rel="manifest" href="<?= BASE_PATH ?>/manifest.json">
    <!-- iOS icons -->
    <link rel="apple-touch-icon" href="<?= BASE_PATH ?>/assets/img/app-icon-192.png">
</head>
<body>
    <?php if (isLoggedIn()): ?>
    <!-- Header Top Bar (solo per utenti loggati) -->
    <header class="app-header">
        <div class="header-title"><?= APP_NAME ?></div>
        <div class="header-actions">
            <button class="btn-notification">
                <i class="fas fa-bell"></i>
            </button>
        </div>
    </header>
    <?php endif; ?>
    
    <!-- Main Container -->
    <main class="app-content">
        <!-- Flash Message -->
        <?php 
        $flash = getFlashMessage();
        if ($flash): 
        ?>
        <div class="toast-container">
            <div class="toast show">
                <div class="toast-header bg-<?= $flash['type'] ?> text-white">
                    <strong class="me-auto">Notifica</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    <?= $flash['message'] ?>
                </div>
            </div>
        </div>
        <?php endif; ?>