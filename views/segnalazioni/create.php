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

$userId = $_SESSION['user_id'];

// Recupera l'appartamento dell'utente
$appartamento = null;
try {
    $stmt = $db->prepare("SELECT a.id_appartamento, a.numero_interno 
                          FROM appartamenti a 
                          JOIN utenti u ON a.id_appartamento = u.id_appartamento 
                          WHERE u.id_utente = ?");
    $stmt->execute([$userId]);
    $appartamento = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // Ignora errori
}

$error = '';
$success = '';

// Gestione del form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Recupera i dati del form
        $titolo = sanitize($_POST['titolo']);
        $descrizione = sanitize($_POST['descrizione']);
        $priorita = $_POST['priorita'];
        $idAppartamento = $_POST['id_appartamento'];
        
        // Validazione
        if (empty($titolo) || empty($descrizione) || empty($idAppartamento)) {
            throw new Exception("Tutti i campi obbligatori devono essere compilati.");
        }
        
        // Inserisci la segnalazione
        $stmt = $db->prepare("INSERT INTO segnalazioni (titolo, descrizione, id_appartamento, id_utente, data_segnalazione, priorita, stato) 
                              VALUES (?, ?, ?, ?, NOW(), ?, 'Aperta')");
        $stmt->execute([$titolo, $descrizione, $idAppartamento, $userId, $priorita]);
        
        $idSegnalazione = $db->lastInsertId();
        
        // Messaggio di successo e redirect
        $_SESSION['flash_message'] = 'Segnalazione inviata con successo!';
        $_SESSION['flash_type'] = 'success';
        redirect(BASE_PATH . '/views/segnalazioni/view.php?id=' . $idSegnalazione);
        
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
    <title>Nuova Segnalazione - Condominio</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/mobile-app.css">
    <style>
        .priority-selector {
            display: flex;
            margin-bottom: 15px;
        }
        
        .priority-option {
            flex: 1;
            text-align: center;
            padding: 10px;
            border: 1px solid #ddd;
            background-color: #f9f9f9;
            cursor: pointer;
        }
        
        .priority-option:first-child {
            border-radius: 8px 0 0 8px;
        }
        
        .priority-option:last-child {
            border-radius: 0 8px 8px 0;
        }
        
        .priority-option.selected {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .priority-option.selected-bassa {
            background-color: #4caf50;
            border-color: #4caf50;
        }
        
        .priority-option.selected-media {
            background-color: #ff9800;
            border-color: #ff9800;
        }
        
        .priority-option.selected-alta {
            background-color: #f44336;
            border-color: #f44336;
        }
        
        .priority-option.selected-urgente {
            background-color: #9c27b0;
            border-color: #9c27b0;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="app-header">
        <a href="<?= BASE_PATH ?>/views/segnalazioni/index.php" class="header-back">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="header-title">Nuova Segnalazione</div>
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
            <div class="app-card-header">
                <div class="card-header-title">Segnala un Problema</div>
            </div>
            
            <div class="app-card-content">
                <form method="post" action="">
                    <input type="hidden" name="id_appartamento" value="<?= $appartamento ? $appartamento['id_appartamento'] : '' ?>">
                    
                    <div class="form-group">
                        <label for="titolo" class="form-label">Titolo*</label>
                        <input type="text" class="form-control" id="titolo" name="titolo" required placeholder="Breve descrizione del problema">
                    </div>
                    
                    <div class="form-group">
                        <label for="descrizione" class="form-label">Descrizione Dettagliata*</label>
                        <textarea class="form-control" id="descrizione" name="descrizione" rows="5" required placeholder="Descrivi nel dettaglio il problema riscontrato"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Priorità*</label>
                        <input type="hidden" id="priorita" name="priorita" value="Media">
                        
                        <div class="priority-selector">
                            <div class="priority-option" data-value="Bassa">
                                <i class="fas fa-arrow-down"></i>
                                <div>Bassa</div>
                            </div>
                            <div class="priority-option selected selected-media" data-value="Media">
                                <i class="fas fa-minus"></i>
                                <div>Media</div>
                            </div>
                            <div class="priority-option" data-value="Alta">
                                <i class="fas fa-arrow-up"></i>
                                <div>Alta</div>
                            </div>
                            <div class="priority-option" data-value="Urgente">
                                <i class="fas fa-exclamation"></i>
                                <div>Urgente</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group mt-4">
                        <button type="submit" class="btn btn-primary btn-block">Invia Segnalazione</button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if (!$appartamento): ?>
        <div class="alert alert-warning mt-4">
            <i class="fas fa-exclamation-triangle"></i> Attenzione: non risulti associato a nessun appartamento. Contatta l'amministratore per risolvere il problema.
        </div>
        <?php endif; ?>
    </main>
    
    <!-- Includi la barra di navigazione -->
<?php include_once $_SERVER['DOCUMENT_ROOT'] . BASE_PATH . '/includes/navbar.php'; ?>
    
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="<?= BASE_PATH ?>/assets/js/mobile-app.js"></script>
    <script>
        $(document).ready(function() {
            // Gestione selezione priorità
            $('.priority-option').on('click', function() {
                const value = $(this).data('value');
                $('#priorita').val(value);
                
                // Rimuovi classe selected da tutte le opzioni
                $('.priority-option').removeClass('selected selected-bassa selected-media selected-alta selected-urgente');
                
                // Aggiungi classe selected all'opzione cliccata
                $(this).addClass('selected selected-' + value.toLowerCase());
            });
        });
    </script>
</body>
</html>