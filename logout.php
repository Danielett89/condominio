<?php
session_start();

// Definisci il percorso base
define('BASE_PATH', '/daniele/condominio');

// Distruggi la sessione
$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Reindirizza all'index
header("Location: " . BASE_PATH . "/index.php");
exit;