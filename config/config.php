<?php
// Definizione delle costanti di configurazione
define('APP_NAME', 'Gestione Condominio');
define('APP_URL', 'https://catalogue.cdclick-europe.com/daniele/condominio'); // URL corretto
define('BASE_PATH', '/daniele/condominio'); // Percorso base
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('DOCUMENT_PATH', UPLOAD_PATH . 'documenti/');
define('SEGNALAZIONE_PATH', UPLOAD_PATH . 'segnalazioni/');

// Impostazioni temporali
date_default_timezone_set('Europe/Rome');
setlocale(LC_TIME, 'it_IT');

// Configurazione sessione
ini_set('session.cookie_httponly', 1);
session_start();

// Funzione di autoload per i controller e i modelli
spl_autoload_register(function ($class_name) {
    // Controlla se è un controller
    if (strpos($class_name, 'Controller') !== false) {
        $filename = __DIR__ . '/../controllers/' . $class_name . '.php';
        if (file_exists($filename)) {
            require_once $filename;
            return true;
        }
    }
    
    // Controlla se è un modello
    $filename = __DIR__ . '/../models/' . $class_name . '.php';
    if (file_exists($filename)) {
        require_once $filename;
        return true;
    }
    
    return false;
});