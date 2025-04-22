<?php
// Verifica se la variabile BASE_PATH è definita
if (!defined('BASE_PATH')) {
    define('BASE_PATH', '/daniele/condominio');
}

// Funzione per verificare se l'utente è amministratore (se non è già definita nel file che include questo)
if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Amministratore';
    }
}

// Determina la pagina attuale
$currentPage = $_SERVER['PHP_SELF'];
?>

<!-- Bottom Navigation -->
<nav class="mobile-navbar">
    <a href="<?= BASE_PATH ?>/dashboard.php" class="nav-item <?= strpos($currentPage, 'dashboard.php') !== false ? 'active' : '' ?>">
        <i class="fas fa-home"></i>
        <span>Home</span>
    </a>
    <a href="<?= BASE_PATH ?>/views/spese/index.php" class="nav-item <?= (strpos($currentPage, '/spese/') !== false || strpos($currentPage, '/acqua/') !== false) ? 'active' : '' ?>">
        <i class="fas fa-file-invoice-dollar"></i>
        <span>Spese</span>
    </a>
    <a href="<?= BASE_PATH ?>/views/documenti/index.php" class="nav-item <?= strpos($currentPage, '/documenti/') !== false ? 'active' : '' ?>">
        <i class="fas fa-file-alt"></i>
        <span>Documenti</span>
    </a>
    <a href="<?= BASE_PATH ?>/views/calendario/index.php" class="nav-item <?= strpos($currentPage, '/calendario/') !== false ? 'active' : '' ?>">
        <i class="fas fa-calendar-alt"></i>
        <span>Calendario</span>
    </a>
    <a href="<?= BASE_PATH ?>/views/comunicazioni/index.php" class="nav-item <?= strpos($currentPage, '/comunicazioni/') !== false ? 'active' : '' ?>">
        <i class="fas fa-comments"></i>
        <span>Chat</span>
    </a>
    <a href="<?= BASE_PATH ?>/views/utenti/profilo.php" class="nav-item <?= strpos($currentPage, '/utenti/profilo') !== false ? 'active' : '' ?>">
        <i class="fas fa-user"></i>
        <span>Profilo</span>
    </a>
    <?php if (isAdmin()): ?>
    <a href="<?= BASE_PATH ?>/views/admin/index.php" class="nav-item <?= strpos($currentPage, '/admin/') !== false ? 'active' : '' ?>">
        <i class="fas fa-cog"></i>
        <span>Admin</span>
    </a>
    <?php endif; ?>
</nav>