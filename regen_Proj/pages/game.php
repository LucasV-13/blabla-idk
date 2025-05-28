<?php
/**
 * Game Page - Interface de jeu The Mind
 * Page principale du jeu avec plateau, cartes et interactions
 */

// Chargement de la configuration
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/constants.php';

// Gestion des sessions
$sessionManager = SessionManager::getInstance();
$sessionManager->startSession();

// Vérification de l'authentification
if (!$sessionManager->isAuthenticated()) {
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

// Gestion de la langue
$language = $sessionManager->get('language', DEFAULT_LANGUAGE);
require_once '../languages/' . $language . '.php';

// Protection CSRF
$csrfToken = $sessionManager->generateCSRFToken();

// Récupération des informations utilisateur
$user = $sessionManager->getUser();

// Vérification de l'ID de partie
$partie_id = isset($_GET['partie_id']) ? (int)$_GET['partie_id'] : 0;

if ($partie_id <= 0) {
    $sessionManager->setFlash('error', $texts['invalid_game_id']);
    header('Location: ' . BASE_URL . 'pages/dashboard.php');
    exit();
}

// Connexion à la base de données
$db = Database::getInstance();
$conn = $db->getConnection();

try {
    // Récupérer les informations de la partie
    $partieQuery = "SELECT p.*, 
                        (SELECT COUNT(*) FROM Utilisateurs_parties WHERE id_partie = p.id) as joueurs_actuels
                    FROM Parties p 
                    WHERE p.id = :partie_id";
    $partieStmt = $conn->prepare($partieQuery);
    $partieStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $partieStmt->execute();
    
    $partie = $partieStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$partie) {
        $sessionManager->setFlash('error', $texts['game_not_found']);
        header('Location: ' . BASE_URL . 'pages/dashboard.php');
        exit();
    }

    // Vérifier si l'utilisateur fait partie de cette partie
    $userPartieQuery = "SELECT position FROM Utilisateurs_parties 
                        WHERE id_utilisateur = :user_id AND id_partie = :partie_id";
    $userPartieStmt = $conn->prepare($userPartieQuery);
    $userPartieStmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
    $userPartieStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $userPartieStmt->execute();
    
    $userPartie = $userPartieStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userPartie) {
        $sessionManager->setFlash('error', $texts['not_in_game']);
        header('Location: ' . BASE_URL . 'pages/dashboard.php');
        exit();
    }
    
    // Déterminer si l'utilisateur est admin (position 1)
    $estAdmin = ($userPartie['position'] == 1);
    
    // Récupérer les joueurs de la partie
    $joueursQuery = "SELECT u.id, u.identifiant, u.avatar, up.position,
                        (SELECT COUNT(*) FROM Cartes WHERE id_utilisateur = u.id AND id_partie = :partie_id AND etat = 'en_main') as cartes_en_main
                     FROM Utilisateurs u 
                     JOIN Utilisateurs_parties up ON u.id = up.id_utilisateur 
                     WHERE up.id_partie = :partie_id 
                     ORDER BY up.position";
    $joueursStmt = $conn->prepare($joueursQuery);
    $joueursStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $joueursStmt->execute();
    $joueurs = $joueursStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les cartes du joueur actuel
    $cartesQuery = "SELECT id, valeur 
                    FROM Cartes 
                    WHERE id_partie = :partie_id AND id_utilisateur = :user_id AND etat = 'en_main'
                    ORDER BY valeur ASC";
    $cartesStmt = $conn->prepare($cartesQuery);
    $cartesStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $cartesStmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
    $cartesStmt->execute();
    $cartes = $cartesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les cartes jouées (historique)
    $cartesJoueesQuery = "SELECT c.id, c.valeur, c.date_action, u.identifiant as joueur_nom, u.avatar as joueur_avatar
                          FROM Cartes c 
                          JOIN Utilisateurs u ON c.id_utilisateur = u.id
                          WHERE c.id_partie = :partie_id AND c.etat = 'jouee'
                          ORDER BY c.date_action DESC
                          LIMIT 10";
    $cartesJoueesStmt = $conn->prepare($cartesJoueesQuery);
    $cartesJoueesStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $cartesJoueesStmt->execute();
    $cartesJouees = $cartesJoueesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculer les vies maximales selon les règles
    $viesMax = $partie['nombre_joueurs'];
    if ($partie['difficulte'] == 'facile') $viesMax += 2;
    elseif ($partie['difficulte'] == 'moyen') $viesMax += 1;
    // difficile = pas de bonus
    
    // Récupérer les dernières actions pour le log
    $actionsQuery = "SELECT aj.type_action, aj.details, aj.date_action, u.identifiant as joueur_nom
                     FROM Actions_jeu aj
                     JOIN Utilisateurs u ON aj.id_utilisateur = u.id
                     WHERE aj.id_partie = :partie_id
                     ORDER BY aj.date_action DESC
                     LIMIT 5";
    $actionsStmt = $conn->prepare($actionsQuery);
    $actionsStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $actionsStmt->execute();
    $actions = $actionsStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Erreur game.php: " . $e->getMessage());
    $sessionManager->setFlash('error', $texts['error_loading_game']);
    header('Location: ' . BASE_URL . 'pages/dashboard.php');
    exit();
}

// Gestion des messages flash
$flashMessage = $sessionManager->getFlash('success') ?: $sessionManager->getFlash('error');
$flashType = $sessionManager->getFlash('success') ? 'success' : 'error';

?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($language); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($texts['game_room']); ?> - <?php echo SITE_NAME; ?></title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>css/main.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>css/components/buttons.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>css/components/modals.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>css/components/cards.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>css/components/forms.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>css/game.css">
    
    <!-- Préchargement des fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&display=swap" rel="stylesheet">
    
    <!-- Préchargement des sons -->
    <link rel="preload" href="<?php echo ASSETS_URL; ?>sounds/card_select.mp3" as="audio">
    <link rel="preload" href="<?php echo ASSETS_URL; ?>sounds/card_play.mp3" as="audio">
    <link rel="preload" href="<?php echo ASSETS_URL; ?>sounds/shuriken.mp3" as="audio">
</head>
<body class="game-page" data-game-status="<?php echo htmlspecialchars($partie['status']); ?>">
    <!-- Champs cachés pour JavaScript -->
    <input type="hidden" id="csrf-token" value="<?php echo htmlspecialchars($csrfToken); ?>">
    <input type="hidden" id="partie-id" value="<?php echo (int)$partie_id; ?>">
    <input type="hidden" id="user-id" value="<?php echo (int)$user['id']; ?>">
    <input type="hidden" id="user-position" value="<?php echo (int)$userPartie['position']; ?>">
    <input type="hidden" id="est-admin" value="<?php echo $estAdmin ? '1' : '0'; ?>">
    <input type="hidden" id="language" value="<?php echo htmlspecialchars($language); ?>">
    <input type="hidden" id="base-url" value="<?php echo BASE_URL; ?>">
    <input type="hidden" id="api-url" value="<?php echo API_URL; ?>">
    <input type="hidden" id="vies-max" value="<?php echo (int)$viesMax; ?>">

    <!-- Header du jeu -->
    <header class="game-header">
        <div class="game-header-container">
            <div class="game-header-left">
                <a href="dashboard.php" class="back-btn" title="<?php echo htmlspecialchars($texts['back_to_dashboard']); ?>">
                    <span class="back-icon">←</span>
                    <span class="back-text"><?php echo htmlspecialchars($texts['dashboard']); ?></span>
                </a>
                
                <div class="game-title-section">
                    <h1 class="game-title">
                        <?php echo htmlspecialchars($partie['nom'] ?: $texts['game_name_column'] . ' ' . $partie['id']); ?>
                        <?php if ($partie['prive']): ?>
                        <span class="private-badge" title="<?php echo htmlspecialchars($texts['private_game']); ?>">🔒</span>
                        <?php endif; ?>
                    </h1>
                    <div class="game-subtitle">
                        <?php echo htmlspecialchars($texts['level']); ?> <span id="current-level"><?php echo (int)$partie['niveau']; ?></span>
                        • <?php echo htmlspecialchars($texts[$partie['difficulte']] ?? $partie['difficulte']); ?>
                    </div>
                </div>
            </div>
            
            <div class="game-header-right">
                <div class="game-status-badge status-<?php echo str_replace('_', '-', $partie['status']); ?>">
                    <?php echo htmlspecialchars($texts['status_' . $partie['status']] ?? $partie['status']); ?>
                </div>
                
                <button type="button" class="game-menu-btn" onclick="toggleGameMenu()" 
                        aria-expanded="false" aria-haspopup="true">
                    <span class="menu-icon">⋮</span>
                </button>
                
                <div class="game-menu-dropdown">
                    <button type="button" class="menu-item" onclick="showRulesModal()">
                        <span class="menu-icon">📜</span>
                        <?php echo htmlspecialchars($texts['rules']); ?>
                    </button>
                    <button type="button" class="menu-item" onclick="showGameLog()">
                        <span class="menu-icon">📋</span>
                        <?php echo htmlspecialchars($texts['game_log']); ?>
                    </button>
                    <div class="menu-separator"></div>
                    <a href="dashboard.php" class="menu-item">
                        <span class="menu-icon">🏠</span>
                        <?php echo htmlspecialchars($texts['leave_game']); ?>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Message flash -->
    <?php if ($flashMessage): ?>
    <div class="flash-message flash-<?php echo $flashType; ?>" role="alert">
        <div class="flash-content">
            <span class="flash-icon">
                <?php echo $flashType === 'success' ? '✅' : '❌'; ?>
            </span>
            <span class="flash-text"><?php echo htmlspecialchars($flashMessage); ?></span>
        </div>
        <button type="button" class="flash-close" aria-label="<?php echo htmlspecialchars($texts['close']); ?>">
            ✕
        </button>
    </div>
    <?php endif; ?>

    <!-- Contenu principal du jeu -->
    <main class="game-content">
        <!-- Panneau d'informations de partie -->
        <section class="game-info-panel">
            <div class="info-grid">
                <div class="info-card level-info">
                    <div class="info-icon">🎯</div>
                    <div class="info-content">
                        <div class="info-label"><?php echo htmlspecialchars($texts['level']); ?></div>
                        <div class="info-value" id="niveau-display"><?php echo (int)$partie['niveau']; ?></div>
                    </div>
                </div>
                
                <div class="info-card lives-info">
                    <div class="info-icon">❤️</div>
                    <div class="info-content">
                        <div class="info-label"><?php echo htmlspecialchars($texts['lives']); ?></div>
                        <div class="info-value">
                            <div class="lives-display" id="vies-display">
                                <?php for ($i = 0; $i < $partie['vies_restantes']; $i++): ?>
                                <span class="life-icon active" data-life="<?php echo $i + 1; ?>">❤️</span>
                                <?php endfor; ?>
                                
                                <?php for ($i = $partie['vies_restantes']; $i < $viesMax; $i++): ?>
                                <span class="life-icon lost" data-life="<?php echo $i + 1; ?>">💔</span>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="info-card shurikens-info">
                    <div class="info-icon">⭐</div>
                    <div class="info-content">
                        <div class="info-label"><?php echo htmlspecialchars($texts['shurikens']); ?></div>
                        <div class="info-value">
                            <div class="shurikens-display" id="shurikens-display">
                                <?php for ($i = 0; $i < $partie['shurikens_restants']; $i++): ?>
                                <span class="shuriken-icon available">⭐</span>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="info-card players-info">
                    <div class="info-icon">👥</div>
                    <div class="info-content">
                        <div class="info-label"><?php echo htmlspecialchars($texts['players']); ?></div>
                        <div class="info-value"><?php echo count($joueurs); ?>/<?php echo (int)$partie['nombre_joueurs']; ?></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Zone de jeu principale -->
        <div class="game-layout">
            <!-- Colonne gauche : Cartes du joueur et actions -->
            <section class="player-section">
                <div class="section-header">
                    <h3 class="section-title"><?php echo htmlspecialchars($texts['your_cards']); ?></h3>
                    <div class="cards-count">
                        <span id="cards-count"><?php echo count($cartes); ?></span> 
                        <?php echo htmlspecialchars($texts['cards']); ?>
                    </div>
                </div>
                
                <div class="player-cards-container">
                    <div class="player-cards" id="player-cards">
                        <?php if (!empty($cartes)): ?>
                            <?php foreach ($cartes as $index => $carte): ?>
                            <div class="game-card selectable" 
                                 data-card-id="<?php echo (int)$carte['id']; ?>" 
                                 data-card-value="<?php echo (int)$carte['valeur']; ?>"
                                 data-card-index="<?php echo $index; ?>"
                                 tabindex="0"
                                 role="button"
                                 aria-label="<?php echo htmlspecialchars($texts['card']); ?> <?php echo (int)$carte['valeur']; ?>">
                                <div class="card-inner">
                                    <div class="card-front">
                                        <div class="card-value"><?php echo (int)$carte['valeur']; ?></div>
                                        <div class="card-suit">🧠</div>
                                    </div>
                                    <div class="card-back">
                                        <div class="card-logo">
                                            <div class="mind-eye">👁️</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-cards-message">
                                <div class="no-cards-icon">🎴</div>
                                <div class="no-cards-text"><?php echo htmlspecialchars($texts['no_cards_left']); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Actions du joueur -->
                <div class="player-actions" id="player-actions">
                    <?php if ($partie['status'] === 'en_cours' && !empty($cartes)): ?>
                    <button type="button" class="btn btn-primary btn-lg play-card-btn" 
                            id="play-card-btn" disabled>
                        <span class="btn-icon">🎯</span>
                        <span class="btn-text"><?php echo htmlspecialchars($texts['play_card']); ?></span>
                    </button>
                    
                    <?php if ($partie['shurikens_restants'] > 0): ?>
                    <button type="button" class="btn btn-secondary btn-lg shuriken-btn" 
                            id="use-shuriken-btn">
                        <span class="btn-icon">⭐</span>
                        <span class="btn-text"><?php echo htmlspecialchars($texts['use_shuriken']); ?></span>
                    </button>
                    <?php endif; ?>
                    
                    <?php elseif ($partie['status'] === 'en_attente'): ?>
                    <div class="waiting-message">
                        <div class="waiting-icon">⏳</div>
                        <div class="waiting-text"><?php echo htmlspecialchars($texts['waiting_game_start']); ?></div>
                    </div>
                    
                    <?php elseif ($partie['status'] === 'pause'): ?>
                    <div class="paused-message">
                        <div class="paused-icon">⏸️</div>
                        <div class="paused-text"><?php echo htmlspecialchars($texts['game_paused']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </section>
            
            <!-- Colonne centrale : Cartes jouées -->
            <section class="played-cards-section">
                <div class="section-header">
                    <h3 class="section-title"><?php echo htmlspecialchars($texts['played_cards']); ?></h3>
                    <div class="played-count">
                        <span id="played-count"><?php echo count($cartesJouees); ?></span>
                    </div>
                </div>
                
                <div class="played-cards-container">
                    <div class="played-cards" id="played-cards">
                        <?php if (!empty($cartesJouees)): ?>
                            <?php foreach ($cartesJouees as $index => $carte): ?>
                            <div class="played-card" 
                                 data-card-id="<?php echo (int)$carte['id']; ?>"
                                 data-card-value="<?php echo (int)$carte['valeur']; ?>"
                                 data-played-order="<?php echo $index; ?>">
                                <div class="played-card-value"><?php echo (int)$carte['valeur']; ?></div>
                                <div class="played-card-info">
                                    <div class="player-info">
                                        <span class="player-avatar"><?php echo htmlspecialchars($carte['joueur_avatar']); ?></span>
                                        <span class="player-name"><?php echo htmlspecialchars($carte['joueur_nom']); ?></span>
                                    </div>
                                    <div class="play-time">
                                        <?php echo date('H:i', strtotime($carte['date_action'])); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-played-cards">
                                <div class="no-played-icon">🎴</div>
                                <div class="no-played-text"><?php echo htmlspecialchars($texts['no_cards_played_yet']); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
            
            <!-- Colonne droite : Joueurs -->
            <section class="players-section">
                <div class="section-header">
                    <h3 class="section-title"><?php echo htmlspecialchars($texts['players']); ?></h3>
                </div>
                
                <div class="players-list" id="players-list">
                    <?php foreach ($joueurs as $joueur): ?>
                    <div class="player-item <?php echo $joueur['id'] == $user['id'] ? 'current-player' : ''; ?>"
                         data-player-id="<?php echo (int)$joueur['id']; ?>"
                         data-player-position="<?php echo (int)$joueur['position']; ?>">
                        
                        <div class="player-avatar-container">
                            <div class="player-avatar"><?php echo htmlspecialchars($joueur['avatar']); ?></div>
                            <?php if ($joueur['position'] == 1): ?>
                            <div class="admin-crown" title="<?php echo htmlspecialchars($texts['game_admin']); ?>">👑</div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="player-info">
                            <div class="player-name"><?php echo htmlspecialchars($joueur['identifiant']); ?></div>
                            <div class="player-cards-count">
                                <span class="cards-count-number" data-player-cards="<?php echo (int)$joueur['id']; ?>">
                                    <?php echo (int)$joueur['cartes_en_main']; ?>
                                </span>
                                <span class="cards-count-label"><?php echo htmlspecialchars($texts['cards']); ?></span>
                            </div>
                        </div>
                        
                        <div class="player-status">
                            <div class="connection-indicator online" title="<?php echo htmlspecialchars($texts['online']); ?>">●</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <!-- Contrôles d'administration (visible uniquement pour l'admin) -->
        <?php if ($estAdmin): ?>
        <section class="admin-controls" id="admin-controls">
            <div class="admin-header">
                <h3 class="admin-title">
                    <span class="admin-icon">👑</span>
                    <?php echo htmlspecialchars($texts['admin_controls']); ?>
                </h3>
            </div>
            
            <div class="admin-actions">
                <?php if ($partie['status'] === 'en_attente'): ?>
                <button type="button" class="btn btn-success admin-btn" id="start-game-btn">
                    <span class="btn-icon">▶️</span>
                    <?php echo htmlspecialchars($texts['start_game']); ?>
                </button>
                
                <?php elseif ($partie['status'] === 'en_cours'): ?>
                <button type="button" class="btn btn-warning admin-btn" id="pause-game-btn">
                    <span class="btn-icon">⏸️</span>
                    <?php echo htmlspecialchars($texts['pause_game']); ?>
                </button>
                
                <?php elseif ($partie['status'] === 'pause'): ?>
                <button type="button" class="btn btn-success admin-btn" id="resume-game-btn">
                    <span class="btn-icon">▶️</span>
                    <?php echo htmlspecialchars($texts['resume_game']); ?>
                </button>
                <?php endif; ?>
                
                <button type="button" class="btn btn-danger admin-btn" id="cancel-game-btn">
                    <span class="btn-icon">❌</span>
                    <?php echo htmlspecialchars($texts['cancel_game']); ?>
                </button>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <!-- Modales -->
    <div id="shuriken-modal" class="modal" role="dialog" aria-labelledby="shuriken-modal-title">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="shuriken-modal-title" class="modal-title">
                    <span class="modal-icon">⭐</span>
                    <?php echo htmlspecialchars($texts['use_shuriken_title']); ?>
                </h3>
            </div>
            <div class="modal-body">
                <p><?php echo htmlspecialchars($texts['use_shuriken_desc']); ?></p>
                <div class="shuriken-warning">
                    <span class="warning-icon">⚠️</span>
                    <?php echo htmlspecialchars($texts['shuriken_warning']); ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancel-shuriken">
                    <?php echo htmlspecialchars($texts['cancel']); ?>
                </button>
                <button type="button" class="btn btn-primary" id="confirm-shuriken">
                    <span class="btn-icon">⭐</span>
                    <?php echo htmlspecialchars($texts['confirm']); ?>
                </button>
            </div>
        </div>
    </div>

    <div id="game-result-modal" class="modal" role="dialog" aria-labelledby="game-result-title">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="game-result-title" class="modal-title"></h3>
            </div>
            <div class="modal-body">
                <div id="game-result-message"></div>
                <div id="game-result-stats"></div>
            </div>
            <div class="modal-footer" id="game-result-actions">
                <a href="dashboard.php" class="btn btn-secondary">
                    <?php echo htmlspecialchars($texts['back_to_dashboard']); ?>
                </a>
                <?php if ($estAdmin): ?>
                <button type="button" class="btn btn-primary" id="next-level-btn" style="display: none;">
                    <?php echo htmlspecialchars($texts['next_level']); ?>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Log de jeu -->
    <div id="game-log-modal" class="modal" role="dialog" aria-labelledby="game-log-title">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3 id="game-log-title" class="modal-title">
                    <span class="modal-icon">📋</span>
                    <?php echo htmlspecialchars($texts['game_log']); ?>
                </h3>
                <button type="button" class="modal-close" onclick="closeModal('game-log-modal')" 
                        aria-label="<?php echo htmlspecialchars($texts['close']); ?>">✕</button>
            </div>
            <div class="modal-body">
                <div class="log-entries" id="log-entries">
                    <?php foreach ($actions as $action): ?>
                    <div class="log-entry">
                        <div class="log-time"><?php echo date('H:i:s', strtotime($action['date_action'])); ?></div>
                        <div class="log-content">
                            <span class="log-player"><?php echo htmlspecialchars($action['joueur_nom']); ?></span>
                            <span class="log-action">
                                <?php
                                switch ($action['type_action']) {
                                    case 'jouer_carte':
                                        $details = json_decode($action['details'], true);
                                        echo $texts['played_card'] . ' ' . ($details['valeur'] ?? '');
                                        break;
                                    case 'utiliser_shuriken':
                                        echo $texts['used_shuriken'];
                                        break;
                                    case 'perdre_vie':
                                        echo $texts['lost_life'];
                                        break;
                                    case 'nouveau_niveau':
                                        $details = json_decode($action['details'], true);
                                        echo $texts['completed_level'] . ' ' . ($details['niveau_termine'] ?? '');
                                        break;
                                    case 'fin_partie':
                                        echo $texts['game_ended'];
                                        break;
                                    default:
                                        echo htmlspecialchars($action['type_action']);
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Règles du jeu -->
    <?php include '../includes/modals/rules.php'; ?>

    <!-- Sons préchargés -->
    <audio preload="auto" id="sound-card-select">
        <source src="<?php echo ASSETS_URL; ?>sounds/card_select.mp3" type="audio/mpeg">
    </audio>
    <audio preload="auto" id="sound-card-play">
        <source src="<?php echo ASSETS_URL; ?>sounds/card_play.mp3" type="audio/mpeg">
    </audio>
    <audio preload="auto" id="sound-shuriken">
        <source src="<?php echo ASSETS_URL; ?>sounds/shuriken.mp3" type="audio/mpeg">
    </audio>
    <audio preload="auto" id="sound-error">
        <source src="<?php echo ASSETS_URL; ?>sounds/error.mp3" type="audio/mpeg">
    </audio>
    <audio preload="auto" id="sound-level-complete">
        <source src="<?php echo ASSETS_URL; ?>sounds/level_complete.mp3" type="audio/mpeg">
    </audio>
    <audio preload="auto" id="sound-game-win">
        <source src="<?php echo ASSETS_URL; ?>sounds/game_win.mp3" type="audio/mpeg">
    </audio>
    <audio preload="auto" id="sound-game-lose">
        <source src="<?php echo ASSETS_URL; ?>sounds/game_lose.mp3" type="audio/mpeg">
    </audio>

    <!-- Scripts -->
    <script src="<?php echo ASSETS_URL; ?>js/main.js"></script>
    <script src="<?php echo ASSETS_URL; ?>js/game.js"></script>
    
    <!-- Initialisation du jeu -->
    <script>
        // Configuration spécifique au jeu
        TheMind.game.config = {
            partieId: <?php echo (int)$partie_id; ?>,
            userId: <?php echo (int)$user['id']; ?>,
            userPosition: <?php echo (int)$userPartie['position']; ?>,
            estAdmin: <?php echo $estAdmin ? 'true' : 'false'; ?>,
            status: '<?php echo htmlspecialchars($partie['status']); ?>',
            niveau: <?php echo (int)$partie['niveau']; ?>,
            viesMax: <?php echo (int)$viesMax; ?>,
            updateInterval: 3000, // 3 secondes
            sounds: {
                cardSelect: 'sound-card-select',
                cardPlay: 'sound-card-play',
                shuriken: 'sound-shuriken',
                error: 'sound-error',
                levelComplete: 'sound-level-complete',
                gameWin: 'sound-game-win',
                gameLose: 'sound-game-lose'
            }
        };

        // Données initiales
        TheMind.game.initialData = {
            cartes: <?php echo json_encode($cartes); ?>,
            cartesJouees: <?php echo json_encode($cartesJouees); ?>,
            joueurs: <?php echo json_encode($joueurs); ?>,
            partie: <?php echo json_encode($partie); ?>
        };

        // Démarrage automatique du jeu
        document.addEventListener('DOMContentLoaded', function() {
            TheMind.game.init();
            
            // Démarrer les mises à jour si le jeu est en cours
            if ('<?php echo $partie['status']; ?>' === 'en_cours') {
                TheMind.game.startGameUpdates();
            }
        });

        // Fonctions globales pour compatibilité
        window.toggleGameMenu = function() {
            const menuBtn = document.querySelector('.game-menu-btn');
            const dropdown = document.querySelector('.game-menu-dropdown');
            
            if (menuBtn && dropdown) {
                const isExpanded = menuBtn.getAttribute('aria-expanded') === 'true';
                menuBtn.setAttribute('aria-expanded', !isExpanded);
            }
        };

        window.showRulesModal = function() {
            TheMind.utils.showModal('rules-modal');
        };

        window.showGameLog = function() {
            TheMind.utils.showModal('game-log-modal');
        };

        window.closeModal = function(modalId) {
            TheMind.utils.hideModal(modalId);
        };

        // Fermer les menus sur clic extérieur
        document.addEventListener('click', function(e) {
            const menuBtn = document.querySelector('.game-menu-btn');
            const dropdown = document.querySelector('.game-menu-dropdown');
            
            if (menuBtn && dropdown && 
                !menuBtn.contains(e.target) && 
                !dropdown.contains(e.target)) {
                menuBtn.setAttribute('aria-expanded', 'false');
            }
        });

        // Gestion de la visibilité de la page
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                TheMind.game.stopGameUpdates();
            } else if ('<?php echo $partie['status']; ?>' === 'en_cours') {
                TheMind.game.startGameUpdates();
            }
        });

        // Nettoyage avant fermeture de page
        window.addEventListener('beforeunload', function() {
            TheMind.game.cleanup();
        });

        // Gestion des raccourcis clavier
        document.addEventListener('keydown', function(e) {
            // Échapper ferme les modales
            if (e.key === 'Escape') {
                const openModal = document.querySelector('.modal[style*="block"]');
                if (openModal) {
                    TheMind.utils.hideModal(openModal.id);
                    return;
                }
                
                // Fermer le menu de jeu
                const menuBtn = document.querySelector('.game-menu-btn');
                if (menuBtn && menuBtn.getAttribute('aria-expanded') === 'true') {
                    menuBtn.setAttribute('aria-expanded', 'false');
                }
            }
            
            // Raccourcis pour les actions de jeu
            if (!e.ctrlKey && !e.metaKey && !e.altKey) {
                switch (e.key) {
                    case ' ': // Espace pour jouer la carte sélectionnée
                        e.preventDefault();
                        TheMind.game.playSelectedCard();
                        break;
                    case 's': // S pour utiliser un shuriken
                        e.preventDefault();
                        TheMind.game.showShurikenModal();
                        break;
                    case 'r': // R pour afficher les règles
                        e.preventDefault();
                        showRulesModal();
                        break;
                    case 'l': // L pour afficher le log
                        e.preventDefault();
                        showGameLog();
                        break;
                }
            }
        });

        // Mise à jour périodique du temps de jeu
        setInterval(function() {
            const timeElements = document.querySelectorAll('.play-time');
            timeElements.forEach(element => {
                const datetime = element.getAttribute('datetime');
                if (datetime) {
                    const date = new Date(datetime);
                    element.textContent = date.toLocaleTimeString('fr-FR', {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                }
            });
        }, 60000); // Mise à jour chaque minute

        // Analytics et métriques (optionnel)
        if (typeof gtag !== 'undefined') {
            gtag('event', 'game_page_view', {
                'custom_parameter': 'game_id_<?php echo (int)$partie_id; ?>'
            });
        }
    </script>

    <!-- Schema.org pour SEO -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Game",
        "name": "<?php echo htmlspecialchars($partie['nom'] ?: 'The Mind Game'); ?>",
        "description": "<?php echo htmlspecialchars($texts['game_description'] ?? 'Cooperative card game The Mind'); ?>",
        "numberOfPlayers": "<?php echo (int)$partie['nombre_joueurs']; ?>",
        "gameItem": {
            "@type": "Thing",
            "name": "Level <?php echo (int)$partie['niveau']; ?>"
        }
    }
    </script>
</body>
</html>