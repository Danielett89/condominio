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

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Amministratore';
}

function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Verifica se l'utente è amministratore
if (!isLoggedIn() || !isAdmin()) {
    redirect(BASE_PATH . '/dashboard.php');
}

// Connessione al database
try {
    $db = new PDO("mysql:host=31.11.39.173;dbname=Sql1693377_3;charset=utf8mb4", "Sql1693377", "S2zyEwzk\$ZnyJu");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Errore di connessione al database: " . $e->getMessage());
}

// Recupera i proprietari disponibili
$proprietari = [];
try {
    $stmt = $db->query("SELECT id_utente, nome, cognome FROM utenti WHERE ruolo IN ('Amministratore', 'Proprietario') ORDER BY cognome, nome");
    $proprietari = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // Ignora errori
}

$appartamento = null;
$idAppartamento = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isNew = ($idAppartamento === 0);
$pageTitle = $isNew ? 'Nuovo Appartamento' : 'Modifica Appartamento';

// Se stiamo modificando un appartamento esistente, recupera i suoi dati
if (!$isNew) {
    try {
        $stmt = $db->prepare("SELECT * FROM appartamenti WHERE id_appartamento = ?");
        $stmt->execute([$idAppartamento]);
        $appartamento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$appartamento) {
            $_SESSION['flash_message'] = 'Appartamento non trovato!';
            $_SESSION['flash_type'] = 'danger';
            redirect(BASE_PATH . '/views/admin/index.php');
        }
    } catch(PDOException $e) {
        $_SESSION['flash_message'] = 'Errore nel recupero dati: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'danger';
        redirect(BASE_PATH . '/views/admin/index.php');
    }
}

$error = '';
$success = '';

// Gestione del form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $numeroInterno = sanitize($_POST['numero_interno']);
        $superficieMq = str_replace(',', '.', $_POST['superficie_mq']);
        $terrazzoMq = str_replace(',', '.', $_POST['terrazzo_mq'] ?? 0);
        $volumeMc = str_replace(',', '.', $_POST['volume_mc']);
        $esposizione = sanitize($_POST['esposizione']);
        $superficieOmogeneizzata = str_replace(',', '.', $_POST['superficie_omogeneizzata']);
        $millesimi = str_replace(',', '.', $_POST['millesimi']);
        $numeroOccupanti = (int)$_POST['numero_occupanti'];
        $idProprietario = (int)$_POST['id_proprietario'];
        
        // Validazione
        if (empty($numeroInterno) || empty($superficieMq) || empty($millesimi)) {
            throw new Exception("Tutti i campi obbligatori devono essere compilati.");
        }
        
        // Verifica che il numero interno non sia già utilizzato (eccetto per l'appartamento corrente)
        $stmt = $db->prepare("SELECT id_appartamento FROM appartamenti WHERE numero_interno = ? AND id_appartamento != ?");
        $stmt->execute([$numeroInterno, $idAppartamento]);
        if ($stmt->fetchColumn()) {
            throw new Exception("Questo numero interno è già utilizzato.");
        }
        
        // Inserimento o aggiornamento dell'appartamento
        if ($isNew) {
            $stmt = $db->prepare("INSERT INTO appartamenti (numero_interno, id_proprietario, superficie_mq, terrazzo_mq, volume_mc, esposizione, superficie_omogeneizzata, millesimi, numero_occupanti) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$numeroInterno, $idProprietario, $superficieMq, $terrazzoMq, $volumeMc, $esposizione, $superficieOmogeneizzata, $millesimi, $numeroOccupanti]);
            
            $success = "Appartamento creato con successo!";
        } else {
            $stmt = $db->prepare("UPDATE appartamenti SET numero_interno = ?, id_proprietario = ?, superficie_mq = ?, terrazzo_mq = ?, volume_mc = ?, esposizione = ?, superficie_omogeneizzata = ?, millesimi = ?, numero_occupanti = ? 
                                  WHERE id_appartamento = ?");
            $stmt->execute([$numeroInterno, $idProprietario, $superficieMq, $terrazzoMq, $volumeMc, $esposizione, $superficieOmogeneizzata, $millesimi, $numeroOccupanti, $idAppartamento]);
            
            $success = "Appartamento aggiornato con successo!";
        }
        
        // Aggiorna i dati visualizzati dopo il salvataggio
        if (!$isNew) {
            $stmt = $db->prepare("SELECT * FROM appartamenti WHERE id_appartamento = ?");
            $stmt->execute([$idAppartamento]);
            $appartamento = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            // Se è un nuovo appartamento, reindirizza alla lista
            $_SESSION['flash_message'] = $success;
            $_SESSION['flash_type'] = 'success';
            redirect(BASE_PATH . '/views/admin/index.php');
        }
        
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
    <title><?= $pageTitle ?> - Condominio</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/mobile-app.css">
</head>
<body>
    <!-- Header -->
    <header class="app-header">
        <a href="<?= BASE_PATH ?>/views/admin/index.php" class="header-back">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="header-title"><?= $pageTitle ?></div>
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
                <div class="card-header-title">Dati Appartamento</div>
            </div>
            
            <div class="app-card-content">
                <form method="post" action="">
                    <div class="form-group">
                        <label for="numero_interno" class="form-label">Numero Interno*</label>
                        <input type="text" class="form-control" id="numero_interno" name="numero_interno" required value="<?= $appartamento ? htmlspecialchars($appartamento['numero_interno']) : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="superficie_mq" class="form-label">Superficie (mq)*</label>
                        <input type="text" class="form-control" id="superficie_mq" name="superficie_mq" required value="<?= $appartamento ? str_replace('.', ',', $appartamento['superficie_mq']) : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="terrazzo_mq" class="form-label">Terrazzo (mq)</label>
                        <input type="text" class="form-control" id="terrazzo_mq" name="terrazzo_mq" value="<?= $appartamento ? str_replace('.', ',', $appartamento['terrazzo_mq']) : '0' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="volume_mc" class="form-label">Volume (mc)</label>
                        <input type="text" class="form-control" id="volume_mc" name="volume_mc" value="<?= $appartamento ? str_replace('.', ',', $appartamento['volume_mc']) : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="esposizione" class="form-label">Esposizione</label>
                        <input type="text" class="form-control" id="esposizione" name="esposizione" value="<?= $appartamento ? htmlspecialchars($appartamento['esposizione']) : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="superficie_omogeneizzata" class="form-label">Superficie Omogeneizzata (mq)</label>
                        <input type="text" class="form-control" id="superficie_omogeneizzata" name="superficie_omogeneizzata" value="<?= $appartamento ? str_replace('.', ',', $appartamento['superficie_omogeneizzata']) : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="millesimi" class="form-label">Millesimi*</label>
                        <input type="text" class="form-control" id="millesimi" name="millesimi" required value="<?= $appartamento ? str_replace('.', ',', $appartamento['millesimi']) : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="numero_occupanti" class="form-label">Numero Occupanti</label>
                        <input type="number" class="form-control" id="numero_occupanti" name="numero_occupanti" min="0" value="<?= $appartamento ? $appartamento['numero_occupanti'] : '0' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="id_proprietario" class="form-label">Proprietario*</label>
                        <select class="form-control" id="id_proprietario" name="id_proprietario" required>
                            <option value="">Seleziona proprietario</option>
                            <?php foreach ($proprietari as $proprietario): ?>
                                <option value="<?= $proprietario['id_utente'] ?>" <?= ($appartamento && $appartamento['id_proprietario'] == $proprietario['id_utente']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($proprietario['nome'] . ' ' . $proprietario['cognome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Questa assegnazione definisce la proprietà dell'appartamento</small>
                    </div>
                    
                    <div class="form-group mt-4">
                        <button type="submit" class="btn btn-primary btn-block">Salva</button>
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
            // Gestisci il formato numerico per gli input numerici
            $('input[type="text"]').on('input', function() {
                if ($(this).attr('id') === 'superficie_mq' || 
                    $(this).attr('id') === 'terrazzo_mq' || 
                    $(this).attr('id') === 'volume_mc' || 
                    $(this).attr('id') === 'superficie_omogeneizzata' || 
                    $(this).attr('id') === 'millesimi') {
                    
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
                }
            });
        });
    </script>
</body>
</html>