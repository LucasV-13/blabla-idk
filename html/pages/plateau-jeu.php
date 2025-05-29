<?php
session_start();

// Gestion des textes multilingues
$language = isset($_SESSION['language']) ? $_SESSION['language'] : 'fr';
include('../languages/' . $language . '.php');

// Vérification de l'authentification et expiration de session
if (!isset($_SESSION['user_id']) || (isset($_SESSION['expires']) && $_SESSION['expires'] < time())) {
    // Détruire la session si elle a expiré
    session_destroy();
    header("Location: index.php");
    exit();
}

// Prolonger la session d'une heure
$_SESSION['expires'] = time() + (60 * 60);

// Protection CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Vérification de l'ID de partie
if (!isset($_GET['partie_id']) || !is_numeric($_GET['partie_id'])) {
    header("Location: dashboard.php");
    exit();
}

$partie_id = (int)$_GET['partie_id'];
$user_id = $_SESSION['user_id'];

include('../connexion/connexion.php');

// Récupérer les infos de la partie
try {
    $partieQuery = "SELECT p.*, 
                        (SELECT COUNT(*) FROM Utilisateurs_parties WHERE id_partie = p.id) as joueurs_actuels
                    FROM Parties p 
                    WHERE p.id = :partie_id";
    $partieStmt = $conn->prepare($partieQuery);
    $partieStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $partieStmt->execute();
    
    if (!($partie = $partieStmt->fetch(PDO::FETCH_ASSOC))) {
        header("Location: dashboard.php");
        exit();
    }

    // Vérifier si l'utilisateur fait partie de cette partie
    $userPartieQuery = "SELECT * FROM Utilisateurs_parties 
                        WHERE id_utilisateur = :user_id AND id_partie = :partie_id";
    $userPartieStmt = $conn->prepare($userPartieQuery);
    $userPartieStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $userPartieStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $userPartieStmt->execute();
    
    if ($userPartieStmt->rowCount() == 0) {
        header("Location: dashboard.php");
        exit();
    }
    
    // Récupérer les joueurs de la partie
    $joueursQuery = "SELECT u.id, u.identifiant, u.avatar, up.position 
                    FROM Utilisateurs u 
                    JOIN Utilisateurs_parties up ON u.id = up.id_utilisateur 
                    WHERE up.id_partie = :partie_id 
                    ORDER BY up.position";
    $joueursStmt = $conn->prepare($joueursQuery);
    $joueursStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $joueursStmt->execute();
    $joueurs = $joueursStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les cartes du joueur actuel
    $cartesQuery = "SELECT c.* 
                    FROM Cartes c 
                    WHERE c.id_partie = :partie_id AND c.id_utilisateur = :user_id AND c.etat = 'en_main'
                    ORDER BY c.valeur";
    $cartesStmt = $conn->prepare($cartesQuery);
    $cartesStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $cartesStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $cartesStmt->execute();
    $cartes = $cartesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les cartes jouées
    $cartesJoueesQuery = "SELECT c.*, u.identifiant as joueur_nom 
                          FROM Cartes c 
                          JOIN Utilisateurs u ON c.id_utilisateur = u.id
                          WHERE c.id_partie = :partie_id AND c.etat = 'jouee'
                          ORDER BY c.date_action DESC
                          LIMIT 10";
    $cartesJoueesStmt = $conn->prepare($cartesJoueesQuery);
    $cartesJoueesStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $cartesJoueesStmt->execute();
    $cartesJouees = $cartesJoueesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer le nombre de shurikens et vies restants
    $statutPartieQuery = "SELECT niveau, vies_restantes, shurikens_restants 
                         FROM Parties 
                         WHERE id = :partie_id";
    $statutPartieStmt = $conn->prepare($statutPartieQuery);
    $statutPartieStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $statutPartieStmt->execute();
    $statutPartie = $statutPartieStmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Erreur jeu.php: " . $e->getMessage());
    header("Location: dashboard.php");
    exit();
}

// Vérifier qui est l'administrateur de la partie
$estAdmin = false;
foreach ($joueurs as $joueur) {
    if ($joueur['id'] == $user_id && $joueur['position'] == 1) {
        $estAdmin = true;
        break;
    }
}

// Inclure le menu après avoir défini les variables
include('menu/menu.php');
?>

<!DOCTYPE html>
<html lang="<?php echo $language; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $texts['game_room']; ?> - The Mind</title>
    <link rel="stylesheet" href="style/styleJeu.css">
    <link rel="stylesheet" href="style/styleMenu.css">
    <style>
        /* Styles supplémentaires pour les cartes */
        .card-inner {
            background-image: url('../assets/images/card-background.jpg');
            background-size: cover;
        }
        
        .life-icon {
            background-image: url('../assets/images/heart.png');
            width: 30px;
            height: 30px;
            display: inline-block;
            background-size: contain;
            background-repeat: no-repeat;
        }
        
        .life-lost {
            background-image: url('../assets/images/heart-empty.png');
        }
        
        .shuriken-icon {
            background-image: url('../assets/images/shuriken.png');
            width: 30px;
            height: 30px;
            display: inline-block;
            background-size: contain;
            background-repeat: no-repeat;
        }
        
        .game-container {
            background-image: url('../assets/images/background.jpg');
            background-size: cover;
            padding: 20px;
            border-radius: 15px;
        }
    </style>
</head>
<body>
    <!-- Champs cachés pour JavaScript -->
    <input type="hidden" id="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    <input type="hidden" id="partie_id" value="<?php echo $partie_id; ?>">
    <input type="hidden" id="user_id" value="<?php echo $user_id; ?>">
    <input type="hidden" id="partie_status" value="<?php echo $partie['status']; ?>">
    <input type="hidden" id="est_admin" value="<?php echo $estAdmin ? '1' : '0'; ?>">

    <div class="game-container">
        <!-- Informations de partie -->
        <div class="game-info">
            <div class="level-display">
                <div class="info-label"><?php echo $texts['level']; ?></div>
                <div class="info-value" id="niveau"><?php echo $statutPartie['niveau']; ?></div>
            </div>
            
            <div class="lives-display">
                <div class="info-label"><?php echo $texts['lives']; ?></div>
                <div class="info-value" id="vies">
                    <?php 
                    // Afficher les vies actuelles
                    for ($i = 0; $i < $statutPartie['vies_restantes']; $i++): ?>
                        <span class="life-icon"></span>
                    <?php endfor; ?>
                    
                    <?php 
                    // Afficher les vies perdues
                    $viesMax = $partie['nombre_joueurs'] + ($partie['difficulte'] == 'facile' ? 2 : ($partie['difficulte'] == 'moyen' ? 1 : 0));
                    for ($i = $statutPartie['vies_restantes']; $i < $viesMax; $i++): ?>
                        <span class="life-icon life-lost"></span>
                    <?php endfor; ?>
                </div>
            </div>
            
            <div class="shurikens-display">
                <div class="info-label"><?php echo $texts['shurikens']; ?></div>
                <div class="info-value" id="shurikens">
                    <?php for ($i = 0; $i < $statutPartie['shurikens_restants']; $i++): ?>
                        <span class="shuriken-icon"></span>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
        
        <!-- Zone de jeu principale -->
        <div class="game-area">
            <!-- Cartes du joueur -->
            <div class="player-cards-section">
                <h3><?php echo $texts['your_cards']; ?></h3>
                <div class="player-cards" id="player-cards">
                    <?php if (count($cartes) > 0): ?>
                        <?php foreach ($cartes as $carte): ?>
                        <div class="card" data-id="<?php echo $carte['id']; ?>" data-value="<?php echo $carte['valeur']; ?>">
                            <div class="card-inner">
                                <div class="card-front">
                                    <div class="card-value"><?php echo $carte['valeur']; ?></div>
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
                        <div class="no-cards-message"><?php echo $texts['empty_deck']; ?></div>
                    <?php endif; ?>
                </div>
                
                <!-- Actions du joueur -->
                <div class="player-actions">
                    <?php if ($partie['status'] == 'en_cours' && count($cartes) > 0): ?>
                    <button id="play-card-btn" class="action-btn"><?php echo $texts['play_card']; ?></button>
                    <?php if ($statutPartie['shurikens_restants'] > 0): ?>
                    <button id="use-shuriken-btn" class="action-btn shuriken-btn"><?php echo $texts['use_shuriken']; ?></button>
                    <?php endif; ?>
                    <?php elseif ($partie['status'] == 'en_attente'): ?>
                    <div class="waiting-message"><?php echo $texts['waiting']; ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Cartes jouées -->
            <div class="played-cards-section">
                <h3><?php echo $texts['played_cards']; ?></h3>
                <div class="played-cards" id="played-cards">
                    <?php if (count($cartesJouees) > 0): ?>
                        <?php foreach ($cartesJouees as $carte): ?>
                        <div class="played-card" data-id="<?php echo $carte['id']; ?>">
                            <div class="played-card-value"><?php echo $carte['valeur']; ?></div>
                            <div class="played-card-info"><?php echo $texts['card_played_by']; ?> <?php echo $carte['joueur_nom']; ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-cards-played"><?php echo $texts['no_cards_played']; ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Liste des joueurs -->
            <div class="players-section">
                <h3><?php echo $texts['players']; ?></h3>
                <div class="players-list" id="players-list">
                    <?php foreach ($joueurs as $joueur): ?>
                    <div class="player-item <?php echo $joueur['id'] == $user_id ? 'current-player' : ''; ?>">
                        <div class="player-avatar"><?php echo $joueur['avatar']; ?></div>
                        <div class="player-name"><?php echo $joueur['identifiant']; ?></div>
                        <?php if ($joueur['position'] == 1): ?>
                        <div class="player-badge admin-badge">👑</div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Contrôles d'administration (visibles uniquement par l'admin) -->
        <?php if ($estAdmin): ?>
        <div class="admin-controls">
            <h3><?php echo $texts['admin_controls']; ?></h3>
            <div class="admin-buttons">
                <?php if ($partie['status'] == 'en_attente'): ?>
                <button id="start-game-btn" class="admin-btn"><?php echo $texts['start_game']; ?></button>
                <?php elseif ($partie['status'] == 'en_cours'): ?>
                <button id="pause-game-btn" class="admin-btn"><?php echo $texts['pause_game']; ?></button>
                <?php elseif ($partie['status'] == 'pause'): ?>
                <button id="resume-game-btn" class="admin-btn"><?php echo $texts['resume_game']; ?></button>
                <?php endif; ?>
                <button id="cancel-game-btn" class="admin-btn cancel-btn"><?php echo $texts['cancel_game']; ?></button>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Modals -->
    <div id="game-over-modal" class="modal">
        <div class="modal-content">
            <div id="game-result-message"></div>
            <div class="modal-buttons">
                <button id="back-to-dashboard" class="modal-btn"><?php echo $texts['back_to_dashboard']; ?></button>
                <?php if ($estAdmin): ?>
                <button id="next-level-btn" class="modal-btn"><?php echo $texts['next_level']; ?></button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div id="shuriken-modal" class="modal">
        <div class="modal-content">
            <h3><?php echo $texts['use_shuriken_title']; ?></h3>
            <p><?php echo $texts['use_shuriken_desc']; ?></p>
            <div class="modal-buttons">
                <button id="confirm-shuriken" class="modal-btn"><?php echo $texts['confirm']; ?></button>
                <button id="cancel-shuriken" class="modal-btn"><?php echo $texts['cancel']; ?></button>
            </div>
        </div>
    </div>
    
    <div id="error-modal" class="modal">
        <div class="modal-content">
            <h3><?php echo $texts['error']; ?></h3>
            <p id="error-message"></p>
            <div class="modal-buttons">
                <button id="close-error" class="modal-btn"><?php echo $texts['close']; ?></button>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/jsJeu.js"></script>
</body>
</html>