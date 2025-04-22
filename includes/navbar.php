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
    <a href="<?= BASE_PATH ?>/views/calendario/index.php" class="nav-item <?= strpos($currentPage, '/calendario/') !== false ? 'active' : '' ?>">
        <i class="fas fa-calendar-alt"></i>
        <span>Calendario</span>
    </a>
    <a href="<?= BASE_PATH ?>/views/comunicazioni/index.php" class="nav-item <?= strpos($currentPage, '/comunicazioni/') !== false ? 'active' : '' ?>">
        <i class="fas fa-comments"></i>
        <span>Chat</span>
    </a>
</nav>
