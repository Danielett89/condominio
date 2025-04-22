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

// Recupera gli appartamenti disponibili
$appartamenti = [];
try {
    $stmt = $db->query("SELECT id_appartamento, numero_interno FROM appartamenti ORDER BY numero_interno");
    $appartamenti = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // Ignora errori
}

$utente = null;
$idUtente = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isNew = ($idUtente === 0);
$pageTitle = $isNew ? 'Nuovo Utente' : 'Modifica Utente';

// Se stiamo modificando un utente esistente, recupera i suoi dati
if (!$isNew) {
    try {
        $stmt = $db->prepare("SELECT * FROM utenti WHERE id_utente = ?");
        $stmt->execute([$idUtente]);
        $utente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$utente) {
            $_SESSION['flash_message'] = 'Utente non trovato!';
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
        $nome = sanitize($_POST['nome']);
        $cognome = sanitize($_POST['cognome']);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $ruolo = $_POST['ruolo'];
        $idAppartamento = !empty($_POST['id_appartamento']) ? (int)$_POST['id_appartamento'] : null;
        $password = $_POST['password'] ?? '';
        
        // Validazione
        if (empty($nome) || empty($cognome) || empty($email) || empty($ruolo)) {
            throw new Exception("Tutti i campi obbligatori devono essere compilati.");
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("L'indirizzo email non è valido.");
        }
        
        // Verifica che l'email non sia già utilizzata (eccetto per l'utente corrente)
        $stmt = $db->prepare("SELECT id_utente FROM utenti WHERE email = ? AND id_utente != ?");
        $stmt->execute([$email, $idUtente]);
        if ($stmt->fetchColumn()) {
            throw new Exception("Questo indirizzo email è già utilizzato.");
        }
        
        if ($isNew && empty($password)) {
            throw new Exception("La password è obbligatoria per i nuovi utenti.");
        }
        
        // Inserimento o aggiornamento dell'utente
        if ($isNew) {
            $stmt = $db->prepare("INSERT INTO utenti (nome, cognome, email, password, ruolo, stato_account, id_appartamento) 
                                  VALUES (?, ?, ?, ?, ?, 'Approvato', ?)");
            $stmt->execute([$nome, $cognome, $email, $password, $ruolo, $idAppartamento]);
            
            $success = "Utente creato con successo!";
        } else {
            // In caso di modifica, aggiorna solo la password se è stata fornita
            if (!empty($password)) {
                $stmt = $db->prepare("UPDATE utenti SET nome = ?, cognome = ?, email = ?, password = ?, ruolo = ?, id_appartamento = ? 
                                      WHERE id_utente = ?");
                $stmt->execute([$nome, $cognome, $email, $password, $ruolo, $idAppartamento, $idUtente]);
            } else {
                $stmt = $db->prepare("UPDATE utenti SET nome = ?, cognome = ?, email = ?, ruolo = ?, id_appartamento = ? 
                                      WHERE id_utente = ?");
                $stmt->execute([$nome, $cognome, $email, $ruolo, $idAppartamento, $idUtente]);
            }
            
            $success = "Utente aggiornato con successo!";
        }
        
        // Aggiorna i dati visualizzati dopo il salvataggio
        if (!$isNew) {
            $stmt = $db->prepare("SELECT * FROM utenti WHERE id_utente = ?");
            $stmt->execute([$idUtente]);
            $utente = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            // Se è un nuovo utente, reindirizza alla lista utenti
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
    <style>
        .apartment-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .apartment-list li {
            padding: 8px 12px;
            background-color: #f0f7ff;
            border-radius: 6px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
        }
        .apartment-list li:before {
            content: '\f015';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            margin-right: 8px;
            color: var(--primary-color);
        }
    </style>
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
                <div class="card-header-title">Dati Utente</div>
            </div>
            
            <div class="app-card-content">
                <form method="post" action="">
                    <div class="form-group">
                        <label for="nome" class="form-label">Nome*</label>
                        <input type="text" class="form-control" id="nome" name="nome" required value="<?= $utente ? htmlspecialchars($utente['nome']) : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="cognome" class="form-label">Cognome*</label>
                        <input type="text" class="form-control" id="cognome" name="cognome" required value="<?= $utente ? htmlspecialchars($utente['cognome']) : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Email*</label>
                        <input type="email" class="form-control" id="email" name="email" required value="<?= $utente ? htmlspecialchars($utente['email']) : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label"><?= $isNew ? 'Password*' : 'Nuova Password (lascia vuoto per non modificare)' ?></label>
                        <input type="password" class="form-control" id="password" name="password" <?= $isNew ? 'required' : '' ?>>
                    </div>
                    
                    <div class="form-group">
                        <label for="ruolo" class="form-label">Ruolo*</label>
                        <select class="form-control" id="ruolo" name="ruolo" required>
                            <option value="Amministratore" <?= ($utente && $utente['ruolo'] === 'Amministratore') ? 'selected' : '' ?>>Amministratore</option>
                            <option value="Proprietario" <?= ($utente && $utente['ruolo'] === 'Proprietario') ? 'selected' : '' ?>>Proprietario</option>
                            <option value="Affittuario" <?= ($utente && $utente['ruolo'] === 'Affittuario') ? 'selected' : '' ?>>Affittuario</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Appartamenti di Proprietà</label>
                        <div class="owned-apartments">
                            <?php 
                            // Recupera gli appartamenti di proprietà dell'utente
                            if (!$isNew) {
                                $stmt = $db->prepare("SELECT id_appartamento, numero_interno FROM appartamenti WHERE id_proprietario = ? ORDER BY numero_interno");
                                $stmt->execute([$idUtente]);
                                $appartamentiPosseduti = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (count($appartamentiPosseduti) > 0): ?>
                                    <ul class="apartment-list">
                                        <?php foreach ($appartamentiPosseduti as $app): ?>
                                        <li><?= htmlspecialchars($app['numero_interno']) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <div class="text-muted">Nessun appartamento di proprietà</div>
                                <?php endif;
                            } else { ?>
                                <div class="text-muted">Salva prima l'utente per assegnare appartamenti</div>
                            <?php } ?>
                        </div>
                        <small class="form-text text-muted mt-2">Gli appartamenti si assegnano dalla sezione Gestione Appartamenti</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="id_appartamento" class="form-label">Appartamento di Residenza</label>
                        <select class="form-control" id="id_appartamento" name="id_appartamento">
                            <option value="">Nessun appartamento</option>
                            <?php foreach ($appartamenti as $appartamento): ?>
                                <option value="<?= $appartamento['id_appartamento'] ?>" <?= ($utente && $utente['id_appartamento'] == $appartamento['id_appartamento']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($appartamento['numero_interno']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Appartamento in cui l'utente risiede (necessario per affittuari)</small>
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
</body>
</html>