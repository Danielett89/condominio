<?php 
if (!defined('BASE_PATH')) {
    define('BASE_PATH', '/daniele/condominio');
}

// Determina se mostrare il pulsante indietro (può essere impostato dalla pagina che include questo file)
$showBackButton = $showBackButton ?? false;
$backUrl = $backUrl ?? BASE_PATH . '/dashboard.php';
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#007bff">
    <title><?= $pageTitle ?> - Condominio</title>
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
        <?php if ($showBackButton): ?>
        <a href="<?= $backUrl ?>" class="header-back">
            <i class="fas fa-arrow-left"></i>
        </a>
        <?php endif; ?>
        <div class="header-title"><?= $pageTitle ?></div>
        <div class="header-actions">
            <button class="btn-notification" id="btnNotification">
                <i class="fas fa-bell"></i>
            </button>
            <button class="btn-menu" id="btnMenu">
                <i class="fas fa-ellipsis-v"></i>
            </button>
        </div>
    </header>
    
    <!-- Includi il menu dropdown dell'header -->
    <?php include_once $_SERVER['DOCUMENT_ROOT'] . BASE_PATH . '/includes/header-menu.php'; ?>
    <?php endif; ?>
    
    <!-- Main Container -->
    <main class="app-content">
        <!-- Flash Message -->
        <?php 
        if (isset($_SESSION['flash_message']) && isset($_SESSION['flash_type'])) {
            $flashMessage = $_SESSION['flash_message'];
            $flashType = $_SESSION['flash_type'];
            unset($_SESSION['flash_message']);
            unset($_SESSION['flash_type']);
        ?>
        <div class="alert alert-<?= $flashType ?>">
            <?= $flashMessage ?>
        </div>
        <?php } ?>
