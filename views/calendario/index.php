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

// Recupera i dati della raccolta differenziata
$raccolta = [];
try {
    $stmt = $db->query("SELECT * FROM raccolta_differenziata ORDER BY FIELD(giorno, 'Lunedi', 'Martedi', 'Mercoledi', 'Giovedi', 'Venerdi', 'Sabato', 'Domenica'), orario_tipo DESC");
    $datiRaccolta = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organizza i dati per giorno
    foreach ($datiRaccolta as $dato) {
        $giorno = $dato['giorno'];
        if (!isset($raccolta[$giorno])) {
            $raccolta[$giorno] = [];
        }
        $raccolta[$giorno][] = $dato;
    }
} catch(PDOException $e) {
    // Se la tabella non esiste, utilizziamo dati predefiniti
    $giorni = ['Lunedi', 'Martedi', 'Mercoledi', 'Giovedi', 'Venerdi', 'Sabato', 'Domenica'];
    $raccolta = [
        'Lunedi' => [
            ['tipo_rifiuto' => 'Indifferenziato', 'orario_limite' => '06:30:00', 'orario_tipo' => 'mattina', 'colore' => '#666666']
        ],
        'Martedi' => [
            ['tipo_rifiuto' => 'Organico', 'orario_limite' => '20:00:00', 'orario_tipo' => 'sera', 'colore' => '#8bc34a'],
            ['tipo_rifiuto' => 'Plastica e Metallo', 'orario_limite' => '06:30:00', 'orario_tipo' => 'mattina', 'colore' => '#ffeb3b']
        ],
        'Mercoledi' => [
            ['tipo_rifiuto' => 'Carta e Cartone', 'orario_limite' => '06:30:00', 'orario_tipo' => 'mattina', 'colore' => '#2196f3']
        ],
        'Giovedi' => [
            ['tipo_rifiuto' => 'Organico', 'orario_limite' => '20:00:00', 'orario_tipo' => 'sera', 'colore' => '#8bc34a'],
            ['tipo_rifiuto' => 'Indifferenziato', 'orario_limite' => '06:30:00', 'orario_tipo' => 'mattina', 'colore' => '#666666']
        ],
        'Venerdi' => [
            ['tipo_rifiuto' => 'Plastica e Metallo', 'orario_limite' => '06:30:00', 'orario_tipo' => 'mattina', 'colore' => '#ffeb3b']
        ],
        'Sabato' => [
            ['tipo_rifiuto' => 'Organico', 'orario_limite' => '20:00:00', 'orario_tipo' => 'sera', 'colore' => '#8bc34a'],
            ['tipo_rifiuto' => 'Carta e Cartone', 'orario_limite' => '06:30:00', 'orario_tipo' => 'mattina', 'colore' => '#2196f3']
        ],
        'Domenica' => []
    ];
}

// Determina il giorno corrente
$giornoCorrente = date('N'); // 1 (Lunedì) a 7 (Domenica)
$giorniSettimana = ['', 'Lunedi', 'Martedi', 'Mercoledi', 'Giovedi', 'Venerdi', 'Sabato', 'Domenica'];
$giornoCorrenteNome = $giorniSettimana[$giornoCorrente];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Calendario Raccolta - Condominio</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/mobile-app.css">
    <style>
        .week-calendar {
            margin-bottom: 20px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .day-header {
            padding: 12px 15px;
            background-color: #f5f5f5;
            font-weight: 600;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .day-header.today {
            background-color: var(--primary-color);
            color: white;
        }
        
        .day-content {
            padding: 15px;
            background-color: white;
        }
        
        .waste-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            padding: 10px;
            border-radius: 8px;
            background-color: #f9f9f9;
        }
        
        .waste-item:last-child {
            margin-bottom: 0;
        }
        
        .waste-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-size: 1.2rem;
        }
        
        .waste-details {
            flex: 1;
        }
        
        .waste-type {
            font-weight: 500;
            font-size: 1rem;
        }
        
        .waste-time {
            font-size: 0.8rem;
            color: #666;
        }
        
        .info-card {
            background-color: #f3effd;
            border-left: 4px solid #673ab7;
        }
        
        .info-card .card-header-title {
            color: #673ab7;
        }
        
        .waste-item.sera {
            border-left: 3px solid #673ab7;
        }
        
        .waste-item.mattina {
            border-left: 3px solid #ff9800;
        }
        
        .day-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="app-header">
        <a href="<?= BASE_PATH ?>/dashboard.php" class="header-back">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="header-title">Calendario Raccolta</div>
    </header>
    
    <!-- Content -->
    <main class="app-content">
        <h2 class="mb-3">Calendario Settimanale</h2>
        
        <?php foreach (['Lunedi', 'Martedi', 'Mercoledi', 'Giovedi', 'Venerdi', 'Sabato'] as $giorno): ?>
            <div class="week-calendar">
                <div class="day-header <?= $giorno === $giornoCorrenteNome ? 'today' : '' ?>">
                    <span><?= $giorno ?></span>
                    <?php if (isset($raccolta[$giorno]) && count($raccolta[$giorno]) > 0): ?>
                        <span>
                            <?php foreach ($raccolta[$giorno] as $item): ?>
                                <span class="day-dot" style="background-color: <?= $item['colore'] ?>"></span>
                            <?php endforeach; ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="day-content">
                    <?php if (isset($raccolta[$giorno]) && count($raccolta[$giorno]) > 0): ?>
                        <?php foreach ($raccolta[$giorno] as $item): ?>
                            <div class="waste-item <?= $item['orario_tipo'] ?>">
                                <div class="waste-icon" style="background-color: <?= $item['colore'] ?>">
                                    <?php
                                    $icon = 'fa-trash';
                                    switch ($item['tipo_rifiuto']) {
                                        case 'Organico':
                                            $icon = 'fa-apple-whole';
                                            break;
                                        case 'Plastica e Metallo':
                                            $icon = 'fa-bottle-water';
                                            break;
                                        case 'Carta e Cartone':
                                            $icon = 'fa-newspaper';
                                            break;
                                        case 'Vetro':
                                            $icon = 'fa-wine-bottle';
                                            break;
                                    }
                                    ?>
                                    <i class="fas <?= $icon ?>"></i>
                                </div>
                                <div class="waste-details">
                                    <div class="waste-type"><?= $item['tipo_rifiuto'] ?></div>
                                    <div class="waste-time">
                                        <?php if ($item['orario_tipo'] === 'mattina'): ?>
                                            <i class="fas fa-sun"></i> Entro le <?= date('H:i', strtotime($item['orario_limite'])) ?>
                                        <?php else: ?>
                                            <i class="fas fa-moon"></i> Entro le <?= date('H:i', strtotime($item['orario_limite'])) ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (isset($item['note']) && !empty($item['note'])): ?>
                                        <div class="waste-note mt-1"><?= $item['note'] ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted">
                            Nessuna raccolta prevista
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <!-- Informazioni sul Vetro -->
        <div class="app-card info-card mt-4">
            <div class="app-card-header">
                <div class="card-header-title">Nota sul Vetro</div>
            </div>
            <div class="app-card-content">
                <div class="waste-item">
                    <div class="waste-icon" style="background-color: #4caf50">
                        <i class="fas fa-wine-bottle"></i>
                    </div>
                    <div class="waste-details">
                        <div class="waste-type">Vetro</div>
                        <div class="waste-note">
                            Il vetro si conferisce nelle apposite Campane Stradali in qualsiasi momento.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Bottom Navigation -->
    <?php include_once $_SERVER['DOCUMENT_ROOT'] . BASE_PATH . '/includes/navbar.php'; ?>
    
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="<?= BASE_PATH ?>/assets/js/mobile-app.js"></script>
</body>
</html>