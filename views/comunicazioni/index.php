<?php
session_start();

// Definisci il percorso base
define('BASE_PATH', '/daniele/condominio');

// Funzioni base
function redirect($url) {
    header("Location: $url");
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isProprietario() {
    return isset($_SESSION['user_role']) && ($_SESSION['user_role'] === 'Proprietario' || $_SESSION['user_role'] === 'Amministratore');
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Amministratore';
}

// Verifica se l'utente è loggato
if (!isLoggedIn()) {
    redirect(BASE_PATH . '/login.php');
}

// Connessione al database
try {
    $db = new PDO("mysql:host=31.11.39.173;dbname=Sql1693377_3;charset=utf8mb4", "Sql1693377", "S2zyEwzk\$ZnyJu");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Errore di connessione al database: " . $e->getMessage());
}

// Recupera i messaggi
$userRole = $_SESSION['user_role'];
$userId = $_SESSION['user_id'];
$messaggi = [];

try {
    if ($userRole === 'Affittuario') {
        // Filtro per affittuari (vedono solo messaggi pubblici)
        $stmt = $db->query("SELECT m.*, u.nome, u.cognome, u.ruolo 
                           FROM messaggi m 
                           JOIN utenti u ON m.id_utente = u.id_utente 
                           WHERE m.visibile_a = 'Tutti'
                           ORDER BY m.data_invio DESC");
    } else {
        // Admin e proprietari vedono tutti i messaggi
        $stmt = $db->query("SELECT m.*, u.nome, u.cognome, u.ruolo 
                           FROM messaggi m 
                           JOIN utenti u ON m.id_utente = u.id_utente 
                           ORDER BY m.data_invio DESC");
    }
    $messaggi = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // Ignora errori se le tabelle non esistono ancora
}

// Gestione messaggi flash
$flashMessage = '';
$flashType = '';

if (isset($_SESSION['flash_message']) && isset($_SESSION['flash_type'])) {
    $flashMessage = $_SESSION['flash_message'];
    $flashType = $_SESSION['flash_type'];
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Comunicazioni - Condominio</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/mobile-app.css">
    <style>
        .message-card {
            margin-bottom: 15px;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .message-header {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            background-color: #f5f5f5;
            position: relative;
        }
        
        .message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: bold;
            margin-right: 12px;
            color: white;
        }
        
        .admin-avatar {
            background-color: #f44336;
        }
        
        .proprietario-avatar {
            background-color: #1976d2;
        }
        
        .affittuario-avatar {
            background-color: #4caf50;
        }
        
        .message-user {
            font-weight: 600;
            font-size: 1rem;
        }
        
        .message-role {
            font-size: 0.75rem;
            opacity: 0.7;
        }
        
        .message-time {
            position: absolute;
            right: 15px;
            top: 12px;
            font-size: 0.75rem;
            color: #666;
        }
        
        .message-content {
            padding: 15px;
            background-color: white;
        }
        
        .message-text {
            margin: 0;
            white-space: pre-wrap;
            word-break: break-word;
        }
        
        .message-visibility {
            margin-top: 10px;
            font-size: 0.75rem;
            color: #666;
            text-align: right;
            font-style: italic;
        }
        
        .compose-fab {
            position: fixed;
            bottom: calc(var(--bottom-nav-height) + 20px);
            right: 20px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background-color: var(--accent-color);
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 3px 5px rgba(0, 0, 0, 0.2);
            border: none;
            font-size: 1.5rem;
            z-index: 1001;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="app-header">
        <div class="header-title">Comunicazioni</div>
    </header>
    
    <!-- Content -->
    <main class="app-content">
        <?php if (!empty($flashMessage)): ?>
            <div class="alert alert-<?= $flashType ?>">
                <?= $flashMessage ?>
            </div>
        <?php endif; ?>
        
        <!-- Elenco Messaggi -->
        <?php if (count($messaggi) > 0): ?>
            <?php foreach ($messaggi as $messaggio): ?>
                <?php 
                    $avatarClass = '';
                    switch ($messaggio['ruolo']) {
                        case 'Amministratore':
                            $avatarClass = 'admin-avatar';
                            break;
                        case 'Proprietario':
                            $avatarClass = 'proprietario-avatar';
                            break;
                        case 'Affittuario':
                            $avatarClass = 'affittuario-avatar';
                            break;
                    }
                ?>
                <div class="message-card">
                    <div class="message-header">
                        <div class="message-avatar <?= $avatarClass ?>">
                            <?= strtoupper(substr($messaggio['nome'], 0, 1)) ?>
                        </div>
                        <div>
                            <div class="message-user"><?= htmlspecialchars($messaggio['nome'] . ' ' . $messaggio['cognome']) ?></div>
                            <div class="message-role"><?= $messaggio['ruolo'] ?></div>
                        </div>
                        <div class="message-time">
                            <?= date('d/m/Y H:i', strtotime($messaggio['data_invio'])) ?>
                        </div>
                    </div>
                    <div class="message-content">
                        <p class="message-text"><?= nl2br(htmlspecialchars($messaggio['messaggio'])) ?></p>
                        
                        <?php if (isProprietario() && $messaggio['visibile_a'] !== 'Tutti'): ?>
                            <div class="message-visibility">
                                <?php switch ($messaggio['visibile_a']) {
                                    case 'Solo proprietari':
                                        echo 'Visibile solo ai proprietari';
                                        break;
                                    case 'Amministratore':
                                        echo 'Visibile solo all\'amministratore';
                                        break;
                                } ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-comments empty-icon"></i>
                <p>Nessun messaggio presente</p>
                <p class="mt-2">Inizia una nuova conversazione!</p>
            </div>
        <?php endif; ?>
        
        <!-- Pulsante Nuovo Messaggio -->
        <a href="<?= BASE_PATH ?>/views/comunicazioni/create.php" class="compose-fab">
            <i class="fas fa-pen"></i>
        </a>
    </main>
    
    <!-- Includi la barra di navigazione -->
<?php include_once $_SERVER['DOCUMENT_ROOT'] . BASE_PATH . '/includes/navbar.php'; ?>
    
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="<?= BASE_PATH ?>/assets/js/mobile-app.js"></script>
</body>
</html>