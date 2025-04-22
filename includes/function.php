<?php
/**
 * File di funzioni di utilità generale
 */

/**
 * Pulisce e sanitizza un input
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Reindirizza a una URL
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Genera un messaggio flash da visualizzare una sola volta
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Recupera e cancella un messaggio flash
 */
function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Verifica se l'utente è autenticato
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Verifica il ruolo dell'utente
 */
function hasRole($role) {
    if (!isLoggedIn()) return false;
    return $_SESSION['user_role'] === $role;
}

/**
 * Verifica se l'utente è amministratore
 */
function isAdmin() {
    return hasRole('Amministratore');
}

/**
 * Verifica se l'utente è proprietario
 */
function isProprietario() {
    return hasRole('Proprietario') || isAdmin();
}

/**
 * Formatta un numero come importo in euro
 */
function formatCurrency($amount) {
    return number_format($amount, 2, ',', '.') . ' €';
}

/**
 * Formatta una data nel formato italiano
 */
function formatDate($date) {
    if (empty($date)) return '';
    $timestamp = strtotime($date);
    return date('d/m/Y', $timestamp);
}