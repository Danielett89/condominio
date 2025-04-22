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

function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
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

$error = '';
$success = '';
$userRole = $_SESSION['user_role'];

// Gestione del form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $messaggio = sanitize($_POST['messaggio']);
        $visibileA = $_POST['visibile_a'];
        $userId = $_SESSION['user_id'];
        
        // Validazione
        if (empty($messaggio)) {
            throw new Exception("Il messaggio non può essere vuoto.");
        }
        
        // Verifica che l'affittuario non possa inviare messaggi solo all'amministratore
        if ($userRole === 'Affittuario' && $visibileA === 'Amministratore') {
            $visibileA = 'Tutti';
        }
        
        // Inserisci il messaggio
        $stmt = $db->prepare("INSERT INTO messaggi (id_utente, messaggio, data_invio, visibile_a) VALUES (?, ?, NOW(), ?)");
        $stmt->execute([$userId, $messaggio, $visibileA]);
        
        // Messaggio di successo e redirect
        $_SESSION['flash_message'] = 'Messaggio inviato con successo!';
        $_SESSION['flash_type'] = 'success';
        redirect(BASE_PATH . '/views/comunicazioni/index.php');
        
    } catch (Exception $e) {
        $error = "Errore: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Nuovo Messaggio - Condominio</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/mobile-app.css">
    <style>
        .compose-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .compose-title {
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }
        
        .message-preview {
            margin-top: 20px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        
        .preview-title {
            font-weight: 600;
            margin-bottom: 10px;
            color: #666;
        }
        
        .preview-content {
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 8px;
            white-space: pre-wrap;
            word-break: break-word;
            min-height: 50px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="app-header">
        <a href="<?= BASE_PATH ?>/views/comunicazioni/index.php" class="header-back">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="header-title">Nuovo Messaggio</div>
    </header>
    
    <!-- Content -->
    <main class="app-content">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?= $error ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?= $success ?>
            </div>
        <?php endif; ?>
        
        <div class="app-card">
            <div class="compose-header">
                <div class="compose-title">Scrivi un messaggio</div>
            </div>
            
            <form method="post" action="">
                <div class="form-group">
                    <label for="messaggio" class="form-label">Testo del messaggio*</label>
                    <textarea class="form-control" id="messaggio" name="messaggio" rows="5" placeholder="Scrivi qui il tuo messaggio..." required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="visibile_a" class="form-label">Visibile a*</label>
                    <select class="form-control" id="visibile_a" name="visibile_a" required>
                        <option value="Tutti">Tutti</option>
                        <?php if (isProprietario()): ?>
                            <option value="Solo proprietari">Solo proprietari</option>
                        <?php else: ?>
                            <option value="Solo proprietari">Proprietari e amministratore</option>
                        <?php endif; ?>
                        <?php if (isProprietario()): ?>
                            <option value="Amministratore">Solo amministratore</option>
                        <?php endif; ?>
                    </select>
                    <small class="form-text text-muted">Scegli chi potrà visualizzare questo messaggio</small>
                </div>
                
                <div class="form-group mt-4">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-paper-plane mr-2"></i> Invia Messaggio
                    </button>
                </div>
            </form>
            
            <div class="message-preview">
                <div class="preview-title">Anteprima</div>
                <div class="preview-content" id="preview"></div>
            </div>
        </div>
    </main>
    
   <!-- Includi la barra di navigazione -->
<?php include_once $_SERVER['DOCUMENT_ROOT'] . BASE_PATH . '/includes/navbar.php'; ?>
    
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="<?= BASE_PATH ?>/assets/js/mobile-app.js"></script>
    <script>
        $(document).ready(function() {
            // Anteprima in tempo reale del messaggio
            $('#messaggio').on('input', function() {
                const text = $(this).val();
                $('#preview').text(text);
            });
        });
    </script>
</body>
</html>