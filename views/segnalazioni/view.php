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

// Controlla se l'ID è stato fornito
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect(BASE_PATH . '/views/segnalazioni/index.php');
}

$idSegnalazione = (int)$_GET['id'];

// Connessione al database
try {
    $db = new PDO("mysql:host=31.11.39.173;dbname=Sql1693377_3;charset=utf8mb4", "Sql1693377", "S2zyEwzk\$ZnyJu");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Errore di connessione al database: " . $e->getMessage());
}

// Recupera i dati della segnalazione
$segnalazione = null;
$userRole = $_SESSION['user_role'];
$userId = $_SESSION['user_id'];

try {
    $stmt = $db->prepare("SELECT s.*, a.numero_interno, u.nome, u.cognome 
                          FROM segnalazioni s
                          JOIN appartamenti a ON s.id_appartamento = a.id_appartamento
                          JOIN utenti u ON s.id_utente = u.id_utente
                          WHERE s.id_segnalazione = ?");
    $stmt->execute([$idSegnalazione]);
    $segnalazione = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$segnalazione) {
        redirect(BASE_PATH . '/views/segnalazioni/index.php');
    }
    
    // Verifica che l'utente abbia accesso a questa segnalazione (se non è proprietario/admin)
    if ($userRole === 'Affittuario' && $segnalazione['id_utente'] !== $userId) {
        redirect(BASE_PATH . '/views/segnalazioni/index.php');
    }
} catch(PDOException $e) {
    die("Errore: " . $e->getMessage());
}

// Gestione del form di aggiornamento stato (solo per proprietari/admin)
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isProprietario()) {
    try {
        $nuovoStato = $_POST['stato'];
        $noteRisoluzione = sanitize($_POST['note_risoluzione'] ?? '');
        
        // Aggiorna lo stato della segnalazione
        $stmt = $db->prepare("UPDATE segnalazioni SET stato = ?, note_risoluzione = ?, data_chiusura = ? WHERE id_segnalazione = ?");
        
        // Se lo stato è "Risolta" o "Chiusa", imposta la data di chiusura
        $dataChiusura = ($nuovoStato === 'Risolta' || $nuovoStato === 'Chiusa') ? date('Y-m-d H:i:s') : null;
        
        $stmt->execute([$nuovoStato, $noteRisoluzione, $dataChiusura, $idSegnalazione]);
        
        // Aggiorna i dati visualizzati
        $segnalazione['stato'] = $nuovoStato;
        $segnalazione['note_risoluzione'] = $noteRisoluzione;
        $segnalazione['data_chiusura'] = $dataChiusura;
        
        $success = "Stato della segnalazione aggiornato con successo!";
        
    } catch (Exception $e) {
        $error = "Errore nell'aggiornamento: " . $e->getMessage();
    }
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
    <title>Dettaglio Segnalazione - Condominio</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/mobile-app.css">
    <style>
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            color: white;
            font-size: 0.9rem;
            margin-top: 10px;
            margin-bottom: 15px;
        }
        
        .status-aperta { background-color: #f44336; }
        .status-in-lavorazione { background-color: #ff9800; }
        .status-risolta { background-color: #4caf50; }
        .status-chiusa { background-color: #9e9e9e; }
        
        .priority-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            color: white;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .priority-bassa { background-color: #4caf50; }
        .priority-media { background-color: #ff9800; }
        .priority-alta { background-color: #f44336; }
        .priority-urgente { background-color: #9c27b0; }
        
        .action-buttons {
            display: flex;
            margin-top: 20px;
            gap: 10px;
        }
        
        .action-buttons .btn {
            flex: 1;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid #ddd;
            color: #333;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="app-header">
        <a href="<?= BASE_PATH ?>/views/segnalazioni/index.php" class="header-back">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="header-title">Dettaglio Segnalazione</div>
    </header>
    
    <!-- Content -->
    <main class="app-content">
        <?php if (!empty($flashMessage) || !empty($success)): ?>
            <div class="alert alert-<?= !empty($flashType) ? $flashType : 'success' ?>">
                <?= !empty($flashMessage) ? $flashMessage : $success ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?= $error ?>
            </div>
        <?php endif; ?>
        
        <!-- Dettagli Segnalazione -->
        <div class="app-card">
            <div class="app-card-header">
                <div class="card-header-title">Segnalazione</div>
            </div>
            
            <div class="app-card-content">
                <div class="detail-value highlight"><?= htmlspecialchars($segnalazione['titolo']) ?></div>
                
                <div class="status-badge status-<?= strtolower(str_replace(' ', '-', $segnalazione['stato'])) ?>">
                    <?= $segnalazione['stato'] ?>
                </div>
                
                <div class="detail-group">
                    <div class="detail-label">Priorità</div>
                    <div class="priority-badge priority-<?= strtolower($segnalazione['priorita']) ?>">
                        <?= $segnalazione['priorita'] ?>
                    </div>
                </div>
                
                <div class="detail-group">
                    <div class="detail-label">Appartamento</div>
                    <div class="detail-value"><?= htmlspecialchars($segnalazione['numero_interno']) ?></div>
                </div>
                
                <div class="detail-group">
                    <div class="detail-label">Segnalato da</div>
                    <div class="detail-value"><?= htmlspecialchars($segnalazione['nome'] . ' ' . $segnalazione['cognome']) ?></div>
                </div>
                
                <div class="detail-group">
                    <div class="detail-label">Data Segnalazione</div>
                    <div class="detail-value"><?= date('d/m/Y H:i', strtotime($segnalazione['data_segnalazione'])) ?></div>
                </div>
                
                <div class="detail-group">
                    <div class="detail-label">Descrizione</div>
                    <div class="detail-value"><?= nl2br(htmlspecialchars($segnalazione['descrizione'])) ?></div>
                </div>
                
                <?php if (!empty($segnalazione['note_risoluzione'])): ?>
                <div class="detail-group">
                    <div class="detail-label">Note Risoluzione</div>
                    <div class="detail-value note-text"><?= nl2br(htmlspecialchars($segnalazione['note_risoluzione'])) ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($segnalazione['data_chiusura'])): ?>
                <div class="detail-group">
                    <div class="detail-label">Data Chiusura</div>
                    <div class="detail-value"><?= date('d/m/Y H:i', strtotime($segnalazione['data_chiusura'])) ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (isProprietario()): ?>
                <div class="action-buttons">
                    <a href="<?= BASE_PATH ?>/views/manutenzioni/create.php?ref=segnalazione&id=<?= $idSegnalazione ?>" class="btn btn-primary">
                        <i class="fas fa-tools"></i> Crea Manutenzione
                    </a>
                    <a href="<?= BASE_PATH ?>/views/comunicazioni/create.php?ref=segnalazione&id=<?= $idSegnalazione ?>" class="btn btn-outline">
                        <i class="fas fa-comment"></i> Rispondi
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (isProprietario()): ?>
        <!-- Aggiornamento Stato -->
        <div class="app-card mt-4">
            <div class="app-card-header">
                <div class="card-header-title">Aggiorna Stato</div>
            </div>
            
            <div class="app-card-content">
                <form method="post" action="">
                    <div class="form-group">
                        <label for="stato" class="form-label">Stato</label>
                        <select class="form-control" id="stato" name="stato">
                            <option value="Aperta" <?= $segnalazione['stato'] === 'Aperta' ? 'selected' : '' ?>>Aperta</option>
                            <option value="In lavorazione" <?= $segnalazione['stato'] === 'In lavorazione' ? 'selected' : '' ?>>In lavorazione</option>
                            <option value="Risolta" <?= $segnalazione['stato'] === 'Risolta' ? 'selected' : '' ?>>Risolta</option>
                            <option value="Chiusa" <?= $segnalazione['stato'] === 'Chiusa' ? 'selected' : '' ?>>Chiusa</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="note_risoluzione" class="form-label">Note Risoluzione</label>
                        <textarea class="form-control" id="note_risoluzione" name="note_risoluzione" rows="3"><?= htmlspecialchars($segnalazione['note_risoluzione'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary btn-block">Aggiorna Stato</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </main>
    
    <!-- Includi la barra di navigazione -->
<?php include_once $_SERVER['DOCUMENT_ROOT'] . BASE_PATH . '/includes/navbar.php'; ?>
    
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="<?= BASE_PATH ?>/assets/js/mobile-app.js"></script>
</body>
</html>