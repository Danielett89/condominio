<?php
// Inizializza la sessione
session_start();

// Definisci il percorso base
define('BASE_PATH', '/daniele/condominio');

// Funzione di reindirizzamento
function redirect($url) {
    header("Location: $url");
    exit;
}

// Funzione per verificare login
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Reindirizza alla dashboard se loggato, altrimenti mostra la landing page
if (isLoggedIn()) {
    redirect(BASE_PATH . '/dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Gestione Condominio</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f5f7fa;
            color: #333;
            margin: 0;
            padding: 20px;
            text-align: center;
        }
        .container {
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1976d2;
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            background-color: #1976d2;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            margin: 10px;
            font-weight: 500;
        }
        .btn-outline {
            background-color: transparent;
            border: 2px solid #1976d2;
            color: #1976d2;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Gestione Condominio</h1>
        <p>Benvenuto nel sistema di gestione del tuo condominio. Accedi o registrati per utilizzare tutte le funzionalità disponibili.</p>
        
        <div>
            <a href="<?= BASE_PATH ?>/login.php" class="btn">Accedi</a>
            <a href="<?= BASE_PATH ?>/register.php" class="btn btn-outline">Registrati</a>
        </div>
    </div>
</body>
</html>