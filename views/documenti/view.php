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

function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Verifica se l'utente è loggato
if (!isLoggedIn()) {
    redirect(BASE_PATH . '/login.php');
}

// Controlla se l'ID è stato fornito
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect(BASE_PATH . '/views/documenti/index.php');
}

$idDocumento = (int)$_GET['id'];

// Connessione al database
try {
    $db = new PDO("mysql:host=31.11.39.173;dbname=Sql1693377_3;charset=utf8mb4", "Sql1693377", "S2zyEwzk\$ZnyJu");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Errore di connessione al database: " . $e->getMessage());
}

// Recupera i dati del documento
$documento = null;
$userRole = $_SESSION['user_role'];

try {
    $stmt = $db->prepare("SELECT d.*, u.nome, u.cognome 
                          FROM documenti d
                          JOIN utenti u ON d.id_utente_caricamento = u.id_utente
                          WHERE d.id_documento = ?");
    $stmt->execute([$idDocumento]);
    $documento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$documento) {
        redirect(BASE_PATH . '/views/documenti/index.php');
    }
    
    // Verifica i permessi di accesso
    if ($userRole === 'Affittuario' && $documento['visibile_a'] !== 'Tutti') {
        redirect(BASE_PATH . '/views/documenti/index.php');
    } elseif ($userRole === 'Proprietario' && $documento['visibile_a'] === 'Amministratore') {
        redirect(BASE_PATH . '/views/documenti/index.php');
    }
} catch(PDOException $e) {
    die("Errore: " . $e->getMessage());
}

// Gestione eliminazione documento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && isProprietario()) {
    try {
        // Elimina il file dal server
        $percorsoCompleto = $_SERVER['DOCUMENT_ROOT'] . BASE_PATH . '/' . $documento['percorso_file'];
        if (file_exists($percorsoCompleto)) {
            unlink($percorsoCompleto);
        }
        
        // Elimina il documento dal database
        $stmt = $db->prepare("DELETE FROM documenti WHERE id_documento = ?");
        $stmt->execute([$idDocumento]);
        
        // Reindirizza alla lista documenti
        $_SESSION['flash_message'] = "Documento eliminato con successo!";
        $_SESSION['flash_type'] = "success";
        redirect(BASE_PATH . "/views/documenti/index.php");
        
    } catch (Exception $e) {
        $error = $e->getMessage();
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
    <title>Documento - Condominio</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/mobile-app.css">
    <style>
        .document-icon {
            font-size: 60px;
            margin: 20px 0;
            text-align: center;
        }
        
        .doc-pdf { color: #f44336; }
        .doc-word { color: #2196f3; }
        .doc-excel { color: #4caf50; }
        .doc-image { color: #ff9800; }
        .doc-other { color: #9e9e9e; }
        
        .document-actions {
            display: flex;
            margin-top: 20px;
            gap: 10px;
        }
        
        .document-actions .btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .document-actions .btn i {
            margin-right: 5px;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid #ddd;
            color: #333;
        }
        
        .btn-danger {
            background-color: #f44336;
            color: white;
        }
        
        .delete-confirmation {
            display: none;
            margin-top: 20px;
            padding: 15px;
            background-color: #ffebee;
            border-radius: 8px;
            border: 1px solid #ffcdd2;
        }
        
        .delete-confirmation p {
            color: #d32f2f;
            margin-bottom: 10px;
        }
        
        .delete-actions {
            display: flex;
            gap: 10px;
        }
        
        .delete-actions .btn {
            flex: 1;
        }
        
        .preview-container {
            margin-top: 20px;
            background-color: #f5f5f5;
            border-radius: 8px;
            overflow: hidden;
            display: none;
        }
        
        .preview-container.visible {
            display: block;
        }
        
        .preview-image {
            width: 100%;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="app-header">
        <a href="<?= BASE_PATH ?>/views/documenti/index.php" class="header-back">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="header-title">Dettaglio Documento</div>
    </header>
    
    <!-- Content -->
    <main class="app-content">
        <?php if (!empty($flashMessage)): ?>
            <div class="alert alert-<?= $flashType ?>">
                <?= $flashMessage ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?= $error ?>
            </div>
        <?php endif; ?>
        
        <div class="app-card">
            <div class="app-card-content">
                <?php
                // Determina l'icona in base al tipo di file
                $estensione = pathinfo($documento['percorso_file'], PATHINFO_EXTENSION);
                $iconClass = 'doc-other';
                $iconType = 'fa-file';
                
                if (in_array(strtolower($estensione), ['pdf'])) {
                    $iconClass = 'doc-pdf';
                    $iconType = 'fa-file-pdf';
                } elseif (in_array(strtolower($estensione), ['doc', 'docx'])) {
                    $iconClass = 'doc-word';
                    $iconType = 'fa-file-word';
                } elseif (in_array(strtolower($estensione), ['xls', 'xlsx'])) {
                    $iconClass = 'doc-excel';
                    $iconType = 'fa-file-excel';
                } elseif (in_array(strtolower($estensione), ['jpg', 'jpeg', 'png', 'gif'])) {
                    $iconClass = 'doc-image';
                    $iconType = 'fa-file-image';
                }
                
                $isImage = in_array(strtolower($estensione), ['jpg', 'jpeg', 'png', 'gif']);
                $isPdf = in_array(strtolower($estensione), ['pdf']);
                $canPreview = $isImage || $isPdf;
                ?>
                
                <div class="document-icon">
                    <i class="fas <?= $iconType ?> <?= $iconClass ?>"></i>
                </div>
                
                <div class="detail-group">
                    <div class="detail-label">Titolo</div>
                    <div class="detail-value highlight"><?= htmlspecialchars($documento['titolo']) ?></div>
                </div>
                
                <?php if (!empty($documento['descrizione'])): ?>
                <div class="detail-group">
                    <div class="detail-label">Descrizione</div>
                    <div class="detail-value"><?= nl2br(htmlspecialchars($documento['descrizione'])) ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($documento['tipo_documento'])): ?>
                <div class="detail-group">
                    <div class="detail-label">Categoria</div>
                    <div class="detail-value"><?= htmlspecialchars($documento['tipo_documento']) ?></div>
                </div>
                <?php endif; ?>
                
                <div class="detail-group">
                    <div class="detail-label">Caricato da</div>
                    <div class="detail-value"><?= htmlspecialchars($documento['nome'] . ' ' . $documento['cognome']) ?></div>
                </div>
                
                <div class="detail-group">
                    <div class="detail-label">Data Caricamento</div>
                    <div class="detail-value"><?= date('d/m/Y H:i', strtotime($documento['data_caricamento'])) ?></div>
                </div>
                
                <?php if (isProprietario()): ?>
                <div class="detail-group">
                    <div class="detail-label">Visibilità</div>
                    <div class="detail-value">
                        <?php
                        switch ($documento['visibile_a']) {
                            case 'Tutti':
                                echo 'Visibile a tutti';
                                break;
                            case 'Solo proprietari':
                                echo 'Visibile solo ai proprietari';
                                break;
                            case 'Amministratore':
                                echo 'Visibile solo all\'amministratore';
                                break;
                        }
                        ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($isImage): ?>
                <div class="preview-container visible">
                    <img src="<?= BASE_PATH . '/' . $documento['percorso_file'] ?>" alt="Anteprima" class="preview-image">
                </div>
                <?php endif; ?>
                
                <div class="document-actions">
                    <a href="<?= BASE_PATH . '/' . $documento['percorso_file'] ?>" class="btn btn-primary" download>
                        <i class="fas fa-download"></i> Scarica
                    </a>
                    
                    <?php if ($canPreview && !$isImage): ?>
                    <a href="<?= BASE_PATH . '/' . $documento['percorso_file'] ?>" class="btn btn-outline" target="_blank">
                        <i class="fas fa-eye"></i> Visualizza
                    </a>
                    <?php endif; ?>
                    
                    <?php if (isProprietario()): ?>
                    <button type="button" class="btn btn-danger" id="btnDelete">
                        <i class="fas fa-trash"></i> Elimina
                    </button>
                    <?php endif; ?>
                </div>
                
                <?php if (isProprietario()): ?>
                <div class="delete-confirmation" id="deleteConfirmation">
                    <p><strong>Sei sicuro di voler eliminare questo documento?</strong></p>
                    <p>Questa azione non può essere annullata.</p>
                    
                    <div class="delete-actions">
                        <button type="button" class="btn btn-outline" id="btnCancelDelete">Annulla</button>
                        <form method="post" action="">
                            <input type="hidden" name="delete" value="1">
                            <button type="submit" class="btn btn-danger">Conferma Eliminazione</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
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
            // Gestione eliminazione documento
            $('#btnDelete').on('click', function() {
                $('#deleteConfirmation').slideDown();
            });
            
            $('#btnCancelDelete').on('click', function() {
                $('#deleteConfirmation').slideUp();
            });
        });
    </script>
</body>
</html>