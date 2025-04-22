<?php
// Verifica se la variabile BASE_PATH è definita
if (!defined('BASE_PATH')) {
    define('BASE_PATH', '/daniele/condominio');
}

// Funzioni per verificare i ruoli utente se non sono già definite
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Amministratore';
    }
}

// Determina la pagina attuale
$currentPage = $_SERVER['PHP_SELF'];

// Se non è definito, usa un valore di default
$pageTitle = $pageTitle ?? 'Dashboard';
$showBackButton = $showBackButton ?? false;
$backUrl = $backUrl ?? BASE_PATH . '/dashboard.php';
?>

<!-- Header -->
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

<!-- Header Menu Dropdown -->
<div class="header-dropdown" id="headerMenu">
    <a href="<?= BASE_PATH ?>/views/documenti/index.php" class="dropdown-item <?= strpos($currentPage, '/documenti/') !== false ? 'active' : '' ?>">
        <i class="fas fa-file-alt"></i> Documenti
    </a>
    <a href="<?= BASE_PATH ?>/views/utenti/profilo.php" class="dropdown-item <?= strpos($currentPage, '/utenti/profilo') !== false ? 'active' : '' ?>">
        <i class="fas fa-user"></i> Profilo
    </a>
    <?php if (isAdmin()): ?>
    <a href="<?= BASE_PATH ?>/views/admin/index.php" class="dropdown-item <?= strpos($currentPage, '/admin/') !== false ? 'active' : '' ?>">
        <i class="fas fa-cog"></i> Admin
    </a>
    <?php endif; ?>
    <a href="<?= BASE_PATH ?>/logout.php" class="dropdown-item">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>

<!-- Main Content -->
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
