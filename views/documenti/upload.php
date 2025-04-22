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

// Verifica se l'utente è proprietario o amministratore
if (!isLoggedIn() || !isProprietario()) {
    redirect(BASE_PATH . '/views/documenti/index.php');
}

// Connessione al database
try {
    $db = new PDO("mysql:host=31.11.39.173;dbname=Sql1693377_3;charset=utf8mb4", "Sql1693377", "S2zyEwzk\$ZnyJu");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Errore di connessione al database: " . $e->getMessage());
}

// Recupera le categorie di documenti esistenti
$categorie = [];
try {
    $stmt = $db->query("SELECT DISTINCT tipo_documento FROM documenti WHERE tipo_documento IS NOT NULL AND tipo_documento != '' ORDER BY tipo_documento");
    $categorie = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {
    // Ignora errori
}

// Aggiungi categorie predefinite se non esistono ancora
$categorieDefault = [
    'Verbali assemblea',
    'Bollette',
    'Preventivi',
    'Contratti',
    'Manutenzioni',
    'Regolamenti'
];

foreach ($categorieDefault as $cat) {
    if (!in_array($cat, $categorie)) {
        $categorie[] = $cat;
    }
}

sort($categorie);

$error = '';
$success = '';

// Gestione del form di upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $titolo = sanitize($_POST['titolo']);
        $descrizione = sanitize($_POST['descrizione']);
        $tipoDocumento = sanitize($_POST['tipo_documento']);
        $visibileA = $_POST['visibile_a'];
        $userId = $_SESSION['user_id'];
        
        // Validazione
        if (empty($titolo)) {
            throw new Exception("Il titolo è obbligatorio.");
        }
        
        // Gestione del file
        if (!isset($_FILES['documento']) || $_FILES['documento']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Nessun file caricato o errore nel caricamento.");
        }
        
        $file = $_FILES['documento'];
        
        // Controllo dimensione (max 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new Exception("Il file è troppo grande. La dimensione massima è 10MB.");
        }
        
        // Controllo estensione
        $estensioniPermesse = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'txt'];
        $estensione = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($estensione, $estensioniPermesse)) {
            throw new Exception("Estensione file non permessa. Le estensioni consentite sono: " . implode(', ', $estensioniPermesse));
        }
        
        // Crea la directory se non esiste
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . BASE_PATH . '/uploads/documenti/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Genera un nome file unico
        $nomeFile = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $file['name']);
        $percorsoFile = 'uploads/documenti/' . $nomeFile;
        $percorsoCompleto = $_SERVER['DOCUMENT_ROOT'] . BASE_PATH . '/' . $percorsoFile;
        
        // Sposta il file
        if (!move_uploaded_file($file['tmp_name'], $percorsoCompleto)) {
            throw new Exception("Errore nel caricamento del file.");
        }
        
        // Salva il documento nel database
        $stmt = $db->prepare("INSERT INTO documenti (titolo, descrizione, percorso_file, tipo_documento, id_utente_caricamento, visibile_a) 
                             VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$titolo, $descrizione, $percorsoFile, $tipoDocumento, $userId, $visibileA]);
        
        $idDocumento = $db->lastInsertId();
        
        // Reindirizza alla pagina del documento
        $_SESSION['flash_message'] = "Documento caricato con successo!";
        $_SESSION['flash_type'] = "success";
        redirect(BASE_PATH . "/views/documenti/view.php?id=" . $idDocumento);
        
    } catch (Exception $e) {
        $error = $e->getMessage();
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
    <title>Carica Documento - Condominio</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/mobile-app.css">
    <style>
        .file-input-container {
            position: relative;
            overflow: hidden;
            margin-bottom: 10px;
        }
        
        .file-input-container input[type="file"] {
            position: absolute;
            font-size: 100px;
            right: 0;
            top: 0;
            opacity: 0;
            cursor: pointer;
        }
        
        .file-input-button {
            background-color: #f5f5f5;
            color: #333;
            border: 1px dashed #ccc;
            padding: 20px;
            text-align: center;
            border-radius: 8px;
        }
        
        .file-input-button i {
            font-size: 30px;
            color: #ccc;
            margin-bottom: 10px;
        }
        
        .file-input-button.has-file {
            border-color: var(--primary-color);
            background-color: rgba(25, 118, 210, 0.1);
        }
        
        .file-input-button.has-file i {
            color: var(--primary-color);
        }
        
        .selected-file {
            margin-top: 10px;
            display: none;
        }
        
        .selected-file.visible {
            display: block;
        }
        
        .file-name {
            font-weight: 500;
            word-break: break-all;
        }
        
        .file-info {
            font-size: 0.8rem;
            color: #666;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="app-header">
        <a href="<?= BASE_PATH ?>/views/documenti/index.php" class="header-back">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="header-title">Carica Documento</div>
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
                <div class="card-header-title">Carica un nuovo documento</div>
            </div>
            
            <div class="app-card-content">
                <form method="post" action="" enctype="multipart/form-data">
                    <div class="file-input-container">
                        <div class="file-input-button" id="fileInputButton">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <div>Tocca per selezionare un file</div>
                            <small>Formati supportati: PDF, DOC, XLS, JPG, PNG, TXT</small>
                        </div>
                        <input type="file" id="documento" name="documento" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.txt" required>
                        
                        <div class="selected-file" id="selectedFile">
                            <div class="file-name" id="fileName"></div>
                            <div class="file-info" id="fileInfo"></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="titolo" class="form-label">Titolo*</label>
                        <input type="text" class="form-control" id="titolo" name="titolo" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="descrizione" class="form-label">Descrizione</label>
                        <textarea class="form-control" id="descrizione" name="descrizione" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="tipo_documento" class="form-label">Tipo Documento</label>
                        <select class="form-control" id="tipo_documento" name="tipo_documento">
                            <option value="">Seleziona una categoria</option>
                            <?php foreach ($categorie as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="categoria_personalizzata" class="form-label">O inserisci una nuova categoria</label>
                        <input type="text" class="form-control" id="categoria_personalizzata" placeholder="Categoria personalizzata">
                    </div>
                    
                    <div class="form-group">
                        <label for="visibile_a" class="form-label">Visibile a*</label>
                        <select class="form-control" id="visibile_a" name="visibile_a" required>
                            <option value="Tutti">Tutti</option>
                            <option value="Solo proprietari">Solo proprietari</option>
                            <option value="Amministratore">Solo amministratore</option>
                        </select>
                    </div>
                    
                    <div class="form-group mt-4">
                        <button type="submit" class="btn btn-primary btn-block">Carica Documento</button>
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
            // Aggiorna l'interfaccia quando un file viene selezionato
            $('#documento').on('change', function() {
                const fileInput = this;
                const fileName = $('#fileName');
                const fileInfo = $('#fileInfo');
                const fileInputButton = $('#fileInputButton');
                const selectedFile = $('#selectedFile');
                
                if (fileInput.files && fileInput.files[0]) {
                    const file = fileInput.files[0];
                    
                    // Mostra il nome del file
                    fileName.text(file.name);
                    
                    // Mostra dimensione del file
                    const fileSize = (file.size / 1024).toFixed(2) + ' KB';
                    fileInfo.text('Dimensione: ' + fileSize);
                    
                    // Cambia stile del pulsante
                    fileInputButton.addClass('has-file');
                    fileInputButton.find('div').text('File selezionato');
                    fileInputButton.find('i').removeClass('fa-cloud-upload-alt').addClass('fa-check-circle');
                    
                    // Mostra info file
                    selectedFile.addClass('visible');
                } else {
                    // Reset
                    fileInputButton.removeClass('has-file');
                    fileInputButton.find('div').text('Tocca per selezionare un file');
                    fileInputButton.find('i').removeClass('fa-check-circle').addClass('fa-cloud-upload-alt');
                    selectedFile.removeClass('visible');
                }
            });
            
            // Gestisci categoria personalizzata
            $('#categoria_personalizzata').on('input', function() {
                const categoriaPersonalizzata = $(this).val().trim();
                if (categoriaPersonalizzata) {
                    $('#tipo_documento').val(categoriaPersonalizzata);
                }
            });
            
            // Reset categoria personalizzata quando si seleziona una categoria esistente
            $('#tipo_documento').on('change', function() {
                $('#categoria_personalizzata').val('');
            });
        });
    </script>
</body>
</html>