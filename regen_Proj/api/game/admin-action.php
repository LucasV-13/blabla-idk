<?php
/**
 * API Endpoint : Actions administrateur de partie
 * Gère toutes les actions d'administration des parties (start, pause, resume, cancel, next_level)
 * 
 * @author The Mind Team
 * @version 1.0
 * @since 2024
 */

// Inclusion des configurations
require_once '../../config/database.php';
require_once '../../config/session.php';
require_once '../../config/constants.php';

// Démarrage de session sécurisé
SessionManager::start();

// Headers JSON obligatoires
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

/**
 * Fonction de réponse JSON standardisée
 */
function sendJsonResponse($success, $data = null, $message = null, $httpCode = 200) {
    http_response_code($httpCode);
    $response = ['success' => $success];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    if ($message !== null) {
        $response['message'] = $message;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Validation des entrées
 */
function validateInput() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(false, null, 'Méthode non autorisée', 405);
    }
    
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
    $gameId = filter_input(INPUT_POST, 'game_id', FILTER_VALIDATE_INT);
    
    if (!$action || !$gameId || $gameId <= 0) {
        sendJsonResponse(false, null, 'Paramètres invalides', 400);
    }
    
    $allowedActions = ['start', 'pause', 'resume', 'cancel', 'next_level'];
    if (!in_array($action, $allowedActions)) {
        sendJsonResponse(false, null, 'Action non autorisée', 400);
    }
    
    return [$action, $gameId];
}

/**
 * Vérifier les permissions d'administration
 */
function checkAdminPermissions($conn, $gameId, $userId) {
    $checkQuery = "SELECT up.position, p.status, p.niveau, p.nombre_joueurs,
                   (SELECT COUNT(*) FROM Utilisateurs_parties WHERE id_partie = p.id) as joueurs_actuels
                   FROM Parties p
                   JOIN Utilisateurs_parties up ON p.id = up.id_partie
                   WHERE p.id = :game_id AND up.id_utilisateur = :user_id";
    
    $stmt = $conn->prepare($checkQuery);
    $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        return ['error' => 'Partie introuvable ou vous n\'en faites pas partie', 'code' => 404];
    }
    
    if ($result['position'] != 1) {
        return ['error' => 'Seul l\'administrateur de la partie peut effectuer cette action', 'code' => 403];
    }
    
    return [
        'authorized' => true,
        'game_status' => $result['status'],
        'level' => (int) $result['niveau'],
        'max_players' => (int) $result['nombre_joueurs'],
        'current_players' => (int) $result['joueurs_actuels']
    ];
}

/**
 * Distribuer les cartes aux joueurs
 */
function distributeCards($conn, $gameId) {
    // Récupérer les informations de la partie
    $gameInfoQuery = "SELECT niveau, nombre_joueurs FROM Parties WHERE id = :game_id";
    $gameInfoStmt = $conn->prepare($gameInfoQuery);
    $gameInfoStmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
    $gameInfoStmt->execute();
    $gameInfo = $gameInfoStmt->fetch(PDO::FETCH_ASSOC);
    
    $level = $gameInfo['niveau'];
    $cardsPerPlayer = $level; // Le nombre de cartes par joueur = niveau
    
    // Récupérer les joueurs de la partie
    $playersQuery = "SELECT id_utilisateur FROM Utilisateurs_parties 
                    WHERE id_partie = :game_id ORDER BY position";
    $playersStmt = $conn->prepare($playersQuery);
    $playersStmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
    $playersStmt->execute();
    $players = $playersStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Supprimer les anciennes cartes
    $deleteOldCardsQuery = "DELETE FROM Cartes WHERE id_partie = :game_id";
    $deleteOldCardsStmt = $conn->prepare($deleteOldCardsQuery);
    $deleteOldCardsStmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
    $deleteOldCardsStmt->execute();
    
    // Générer et mélanger le deck (1-100)
    $deck = range(1, 100);
    shuffle($deck);
    
    // Distribuer les cartes
    $insertCardQuery = "INSERT INTO Cartes (id_partie, id_utilisateur, valeur, etat, date_creation) 
                       VALUES (:game_id, :user_id, :value, 'en_main', NOW())";
    $insertCardStmt = $conn->prepare($insertCardQuery);
    
    foreach ($players as $playerId) {
        for ($i = 0; $i < $cardsPerPlayer && !empty($deck); $i++) {
            $cardValue = array_pop($deck);
            $insertCardStmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
            $insertCardStmt->bindParam(':user_id', $playerId, PDO::PARAM_INT);
            $insertCardStmt->bindParam(':value', $cardValue, PDO::PARAM_INT);
            $insertCardStmt->execute();
        }
    }
    
    return count($players) * $cardsPerPlayer;
}

/**
 * Calculer les bonus de niveau (vies et shurikens)
 */
function calculateLevelBonuses($newLevel) {
    $bonuses = ['life' => false, 'shuriken' => false];
    
    // Bonus vie aux niveaux 3, 6, 9, 12
    if (in_array($newLevel, [3, 6, 9, 12])) {
        $bonuses['life'] = true;
    }
    
    // Bonus shuriken aux niveaux 2, 5, 8, 11
    if (in_array($newLevel, [2, 5, 8, 11])) {
        $bonuses['shuriken'] = true;
    }
    
    return $bonuses;
}

/**
 * Exécuter une action d'administration
 */
function executeAdminAction($conn, $action, $gameId, $userId, $gameData) {
    switch ($action) {
        case 'start':
            return handleStartGame($conn, $gameId, $userId, $gameData);
        case 'pause':
            return handlePauseGame($conn, $gameId, $userId, $gameData);
        case 'resume':
            return handleResumeGame($conn, $gameId, $userId, $gameData);
        case 'cancel':
            return handleCancelGame($conn, $gameId, $userId, $gameData);
        case 'next_level':
            return handleNextLevel($conn, $gameId, $userId, $gameData);
        default:
            return ['error' => 'Action non reconnue', 'code' => 400];
    }
}

/**
 * Démarrer une partie
 */
function handleStartGame($conn, $gameId, $userId, $gameData) {
    if ($gameData['game_status'] !== GAME_STATUS_WAITING) {
        return ['error' => 'La partie ne peut être démarrée que depuis l\'état "en attente"', 'code' => 409];
    }
    
    if ($gameData['current_players'] < 2) {
        return ['error' => 'Il faut au moins 2 joueurs pour démarrer une partie', 'code' => 409];
    }
    
    $conn->beginTransaction();
    
    try {
        // Mettre à jour le statut de la partie
        $updateQuery = "UPDATE Parties SET status = :status, date_debut = NOW() WHERE id = :game_id";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bindValue(':status', GAME_STATUS_IN_PROGRESS);
        $updateStmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
        $updateStmt->execute();
        
        // Distribuer les cartes
        $cardsDistributed = distributeCards($conn, $gameId);
        
        // Log de l'action
        $logQuery = "INSERT INTO Actions_jeu (id_partie, id_utilisateur, type_action, details, date_action) 
                    VALUES (:game_id, :user_id, 'start_game', :details, NOW())";
        $logStmt = $conn->prepare($logQuery);
        $logStmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
        $logStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        
        $details = json_encode([
            'level' => $gameData['level'],
            'players_count' => $gameData['current_players'],
            'cards_distributed' => $cardsDistributed
        ]);
        $logStmt->bindParam(':details', $details);
        $logStmt->execute();
        
        $conn->commit();
        
        return [
            'success' => true,
            'message' => 'Partie démarrée avec succès !',
            'game_status' => GAME_STATUS_IN_PROGRESS,
            'cards_distributed' => $cardsDistributed
        ];
        
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

/**
 * Mettre en pause une partie
 */
function handlePauseGame($conn, $gameId, $userId, $gameData) {
    if ($gameData['game_status'] !== GAME_STATUS_IN_PROGRESS) {
        return ['error' => 'Seule une partie en cours peut être mise en pause', 'code' => 409];
    }
    
    $updateQuery = "UPDATE Parties SET status = :status WHERE id = :game_id";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bindValue(':status', GAME_STATUS_PAUSED);
    $updateStmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
    $updateStmt->execute();
    
    return [
        'success' => true,
        'message' => 'Partie mise en pause',
        'game_status' => GAME_STATUS_PAUSED
    ];
}

/**
 * Reprendre une partie
 */
function handleResumeGame($conn, $gameId, $userId, $gameData) {
    if ($gameData['game_status'] !== GAME_STATUS_PAUSED) {
        return ['error' => 'Seule une partie en pause peut être reprise', 'code' => 409];
    }
    
    $updateQuery = "UPDATE Parties SET status = :status WHERE id = :game_id";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bindValue(':status', GAME_STATUS_IN_PROGRESS);
    $updateStmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
    $updateStmt->execute();
    
    return [
        'success' => true,
        'message' => 'Partie reprise',
        'game_status' => GAME_STATUS_IN_PROGRESS
    ];
}

/**
 * Annuler une partie
 */
function handleCancelGame($conn, $gameId, $userId, $gameData) {
    $allowedStatuses = [GAME_STATUS_WAITING, GAME_STATUS_IN_PROGRESS, GAME_STATUS_PAUSED];
    if (!in_array($gameData['game_status'], $allowedStatuses)) {
        return ['error' => 'Cette partie ne peut plus être annulée', 'code' => 409];
    }
    
    $conn->beginTransaction();
    
    try {
        // Mettre à jour le statut
        $updateQuery = "UPDATE Parties SET status = :status, date_fin = NOW() WHERE id = :game_id";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bindValue(':status', GAME_STATUS_CANCELLED);
        $updateStmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
        $updateStmt->execute();
        
        // Supprimer les cartes en cours
        $deleteCardsQuery = "DELETE FROM Cartes WHERE id_partie = :game_id";
        $deleteCardsStmt = $conn->prepare($deleteCardsQuery);
        $deleteCardsStmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
        $deleteCardsStmt->execute();
        
        // Log de l'action
        $logQuery = "INSERT INTO Actions_jeu (id_partie, id_utilisateur, type_action, details, date_action) 
                    VALUES (:game_id, :user_id, 'cancel_game', :details, NOW())";
        $logStmt = $conn->prepare($logQuery);
        $logStmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
        $logStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        
        $details = json_encode([
            'previous_status' => $gameData['game_status'],
            'level_reached' => $gameData['level'],
            'reason' => 'admin_cancellation'
        ]);
        $logStmt->bindParam(':details', $details);
        $logStmt->execute();
        
        $conn->commit();
        
        return [
            'success' => true,
            'message' => 'Partie annulée',
            'game_status' => GAME_STATUS_CANCELLED,
            'redirect_to_dashboard' => true
        ];
        
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

/**
 * Passer au niveau suivant
 */
function handleNextLevel($conn, $gameId, $userId, $gameData) {
    if ($gameData['game_status'] !== GAME_STATUS_LEVEL_COMPLETE) {
        return ['error' => 'Le niveau actuel doit être terminé pour passer au suivant', 'code' => 409];
    }
    
    if ($gameData['level'] >= MAX_GAME_LEVEL) {
        return ['error' => 'Niveau maximum atteint', 'code' => 409];
    }
    
    $conn->beginTransaction();
    
    try {
        $newLevel = $gameData['level'] + 1;
        $bonuses = calculateLevelBonuses($newLevel);
        
        // Récupérer les vies et shurikens actuels
        $resourcesQuery = "SELECT vies_restantes, shurikens_restants FROM Parties WHERE id = :game_id";
        $resourcesStmt = $conn->prepare($resourcesQuery);
        $resourcesStmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
        $resourcesStmt->execute();
        $resources = $resourcesStmt->fetch(PDO::FETCH_ASSOC);
        
        $newLives = $resources['vies_restantes'] + ($bonuses['life'] ? 1 : 0);
        $newShurikens = $resources['shurikens_restants'] + ($bonuses['shuriken'] ? 1 : 0);
        
        // Mettre à jour la partie
        $updateQuery = "UPDATE Parties 
                       SET niveau = :level, status = :status, vies_restantes = :lives, shurikens_restants = :shurikens 
                       WHERE id = :game_id";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bindParam(':level', $newLevel, PDO::PARAM_INT);
        $updateStmt->bindValue(':status', GAME_STATUS_IN_PROGRESS);
        $updateStmt->bindParam(':lives', $newLives, PDO::PARAM_INT);
        $updateStmt->bindParam(':shurikens', $newShurikens, PDO::PARAM_INT);
        $updateStmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
        $updateStmt->execute();
        
        // Distribuer les nouvelles cartes
        $cardsDistributed = distributeCards($conn, $gameId);
        
        // Log de l'action
        $logQuery = "INSERT INTO Actions_jeu (id_partie, id_utilisateur, type_action, details, date_action) 
                    VALUES (:game_id, :user_id, 'next_level', :details, NOW())";
        $logStmt = $conn->prepare($logQuery);
        $logStmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
        $logStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        
        $details = json_encode([
            'previous_level' => $gameData['level'],
            'new_level' => $newLevel,
            'bonuses' => $bonuses,
            'cards_distributed' => $cardsDistributed
        ]);
        $logStmt->bindParam(':details', $details);
        $logStmt->execute();
        
        $conn->commit();
        
        $message = "Niveau $newLevel démarré !";
        if ($bonuses['life']) $message .= " Bonus : +1 vie !";
        if ($bonuses['shuriken']) $message .= " Bonus : +1 shuriken !";
        
        return [
            'success' => true,
            'message' => $message,
            'new_level' => $newLevel,
            'bonuses' => $bonuses,
            'resources' => [
                'lives' => $newLives,
                'shurikens' => $newShurikens
            ],
            'cards_distributed' => $cardsDistributed,
            'game_status' => GAME_STATUS_IN_PROGRESS
        ];
        
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

try {
    // Vérification authentification
    if (!SessionManager::isLoggedIn()) {
        sendJsonResponse(false, null, 'Session expirée', 401);
    }
    
    // Vérification CSRF
    if (!SessionManager::validateCSRFToken(filter_input(INPUT_POST, 'csrf_token'))) {
        sendJsonResponse(false, null, 'Token CSRF invalide', 403);
    }
    
    // Prolongation de session
    SessionManager::refreshSession();
    
    // Validation des entrées
    [$action, $gameId] = validateInput();
    $userId = SessionManager::get('user_id');
    
    // Connexion base de données
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Vérification des permissions
    $permissionCheck = checkAdminPermissions($conn, $gameId, $userId);
    
    if (isset($permissionCheck['error'])) {
        sendJsonResponse(false, null, $permissionCheck['error'], $permissionCheck['code']);
    }
    
    // Exécution de l'action
    $result = executeAdminAction($conn, $action, $gameId, $userId, $permissionCheck);
    
    if (isset($result['error'])) {
        sendJsonResponse(false, null, $result['error'], $result['code']);
    }
    
    // Réponse de succès
    sendJsonResponse(true, $result, $result['message']);
    
} catch (PDOException $e) {
    error_log("Erreur SQL dans admin_action.php: " . $e->getMessage());
    sendJsonResponse(false, null, 'Erreur lors de l\'exécution de l\'action', 500);
    
} catch (Exception $e) {
    error_log("Erreur générale dans admin_action.php: " . $e->getMessage());
    sendJsonResponse(false, null, 'Une erreur inattendue s\'est produite', 500);
}

// Nettoyage automatique
register_shutdown_function(function() {
    if (isset($conn)) {
        $conn = null;
    }
    
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $executionTime = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
        error_log("admin_action.php - Temps d'exécution: " . round($executionTime * 1000, 2) . "ms");
    }
});
?>