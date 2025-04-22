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

// Verifica se l'utente è proprietario
if (!isLoggedIn() || !isProprietario()) {
    redirect(BASE_PATH . '/dashboard.php');
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

// Gestione del form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Recupera i dati del form
        $titolo = sanitize($_POST['titolo']);
        $descrizione = sanitize($_POST['descrizione']);
        $dataInizio = $_POST['data_inizio'];
        $dataFine = !empty($_POST['data_fine']) ? $_POST['data_fine'] : null;
        $stato = $_POST['stato'];
        $costoPrevisto = !empty($_POST['costo_previsto']) ? str_replace(',', '.', $_POST['costo_previsto']) : null;
        $costoEffettivo = !empty($_POST['costo_effettivo']) ? str_replace(',', '.', $_POST['costo_effettivo']) : null;
        $fornitore = sanitize($_POST['fornitore'] ?? '');
        $contattoFornitore = sanitize($_POST['contatto_fornitore'] ?? '');
        $note = sanitize($_POST['note'] ?? '');
        $userId = $_SESSION['user_id'];
        
        // Validazione
        if (empty($titolo) || empty($descrizione) || empty($dataInizio)) {
            throw new Exception("Tutti i campi obbligatori devono essere compilati.");
        }
        
        // Inserisci la manutenzione
        $stmt = $db->prepare("INSERT INTO manutenzioni (titolo, descrizione, data_inizio, data_fine, stato, costo_previsto, costo_effettivo, fornitore, contatto_fornitore, note, id_utente_inserimento) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$titolo, $descrizione, $dataInizio, $dataFine, $stato, $costoPrevisto, $costoEffettivo, $fornitore, $contattoFornitore, $note, $userId]);
        
        $idManutenzione = $db->lastInsertId();
        
        // Messaggio di successo e redirect
        $_SESSION['flash_message'] = 'Manutenzione aggiunta con successo!';
        $_SESSION['flash_type'] = 'success';
        redirect(BASE_PATH . '/views/manutenzioni/view.php?id=' . $idManutenzione);
        
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
    <title>Nuova Manutenzione - Condominio</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/mobile-app.css">
</head>
<body>
    <!-- Header -->
    <header class="app-header">
        <a href="<?= BASE_PATH ?>/views/manutenzioni/index.php" class="header-back">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="header-title">Nuova Manutenzione</div>
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
                <div class="card-header-title">Inserisci Manutenzione</div>
            </div>
            
            <div class="app-card-content">
                <form method="post" action="">
                    <div class="form-group">
                        <label for="titolo" class="form-label">Titolo*</label>
                        <input type="text" class="form-control" id="titolo" name="titolo" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="descrizione" class="form-label">Descrizione*</label>
                        <textarea class="form-control" id="descrizione" name="descrizione" rows="3" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="data_inizio" class="form-label">Data Inizio*</label>
                        <input type="date" class="form-control" id="data_inizio" name="data_inizio" required value="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="data_fine" class="form-label">Data Fine (prevista)</label>
                        <input type="date" class="form-control" id="data_fine" name="data_fine">
                    </div>
                    
                    <div class="form-group">
                        <label for="stato" class="form-label">Stato*</label>
                        <select class="form-control" id="stato" name="stato" required>
                            <option value="Pianificata" selected>Pianificata</option>
                            <option value="In corso">In corso</option>
                            <option value="Completata">Completata</option>
                            <option value="Annullata">Annullata</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="costo_previsto" class="form-label">Costo Previsto (€)</label>
                        <input type="text" class="form-control" id="costo_previsto" name="costo_previsto" placeholder="0,00">
                    </div>
                    
                    <div class="form-group">
                        <label for="costo_effettivo" class="form-label">Costo Effettivo (€)</label>
                        <input type="text" class="form-control" id="costo_effettivo" name="costo_effettivo" placeholder="0,00">
                    </div>
                    
                    <div class="form-group">
                        <label for="fornitore" class="form-label">Fornitore</label>
                        <input type="text" class="form-control" id="fornitore" name="fornitore">
                    </div>
                    
                    <div class="form-group">
                        <label for="contatto_fornitore" class="form-label">Contatto Fornitore</label>
                        <input type="text" class="form-control" id="contatto_fornitore" name="contatto_fornitore" placeholder="Telefono o email">
                    </div>
                    
                    <div class="form-group">
                        <label for="note" class="form-label">Note</label>
                        <textarea class="form-control" id="note" name="note" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group mt-4">
                        <button type="submit" class="btn btn-primary btn-block">Salva Manutenzione</button>
                    </div>
                </form>
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
            // Gestisci il formato numerico per l'input degli importi
            $('#costo_previsto, #costo_effettivo').on('input', function() {
                // Rimuovi tutti i caratteri eccetto numeri, punto e virgola
                let value = $(this).val().replace(/[^\d,\.]/g, '');
                
                // Sostituisci il punto con la virgola (formato italiano)
                value = value.replace(/\./g, ',');
                
                // Assicurati che ci sia solo una virgola
                const parts = value.split(',');
                if (parts.length > 2) {
                    value = parts[0] + ',' + parts.slice(1).join('');
                }
                
                // Limita i decimali a 2
                if (parts.length > 1 && parts[1].length > 2) {
                    value = parts[0] + ',' + parts[1].substring(0, 2);
                }
                
                $(this).val(value);
            });
            
            // Gestisci cambio di stato
            $('#stato').on('change', function() {
                if ($(this).val() === 'Completata') {
                    $('#costo_effettivo').parents('.form-group').addClass('highlight');
                } else {
                    $('#costo_effettivo').parents('.form-group').removeClass('highlight');
                }
            });
        });
    </script>
</body>
</html>