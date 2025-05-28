<?php
/**
 * API Endpoint : Rejoindre une partie
 * Permet à un utilisateur de rejoindre une partie existante
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
    // Vérification de la méthode HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(false, null, 'Méthode non autorisée', 405);
    }
    
    // Récupération et validation de l'ID de partie
    $gameId = filter_input(INPUT_POST, 'game_id', FILTER_VALIDATE_INT);
    if (!$gameId || $gameId <= 0) {
        sendJsonResponse(false, null, 'ID de partie invalide', 400);
    }
    
    return $gameId;
}

/**
 * Vérification des permissions pour rejoindre une partie
 */
function checkGameAvailability($conn, $gameId, $userId) {
    // Vérifier si l'utilisateur fait déjà partie de cette partie
    $checkUserQuery = "SELECT up.position, p.status, p.nombre_joueurs,
                       (SELECT COUNT(*) FROM Utilisateurs_parties WHERE id_partie = p.id) as joueurs_actuels
                       FROM Parties p
                       LEFT JOIN Utilisateurs_parties up ON p.id = up.id_partie AND up.id_utilisateur = :user_id
                       WHERE p.id = :game_id";
    
    $stmt = $conn->prepare($checkUserQuery);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        return ['error' => 'Partie introuvable', 'code' => 404];
    }
    
    // Si l'utilisateur fait déjà partie de la partie
    if ($result['position'] !== null) {
        return [
            'already_joined' => true,
            'position' => (int) $result['position'],
            'redirect_to_game' => true
        ];
    }
    
    // Vérifier si la partie est en attente
    if ($result['status'] !== GAME_STATUS_WAITING) {
        $statusMessages = [
            GAME_STATUS_IN_PROGRESS => 'Cette partie est déjà en cours',
            GAME_STATUS_COMPLETED => 'Cette partie est terminée',
            GAME_STATUS_CANCELLED => 'Cette partie a été annulée',
            GAME_STATUS_PAUSED => 'Cette partie est en pause'
        ];
        
        $message = $statusMessages[$result['status']] ?? 'Cette partie n\'est pas disponible';
        return ['error' => $message, 'code' => 409];
    }
    
    // Vérifier s'il reste de la place
    if ($result['joueurs_actuels'] >= $result['nombre_joueurs']) {
        return ['error' => 'Cette partie est complète', 'code' => 409];
    }
    
    return [
        'can_join' => true,
        'current_players' => (int) $result['joueurs_actuels'],
        'max_players' => (int) $result['nombre_joueurs']
    ];
}

/**
 * Ajouter un utilisateur à une partie
 */
function addUserToGame($conn, $gameId, $userId) {
    try {
        // Commencer une transaction pour garantir la cohérence
        $conn->beginTransaction();
        
        // Recalculer la position maximale (sécurité contre les conditions de course)
        $maxPosQuery = "SELECT COALESCE(MAX(position), 0) as max_pos 
                       FROM Utilisateurs_parties 
                       WHERE id_partie = :game_id 
                       FOR UPDATE"; // Verrou pour éviter les doublons
        
        $maxPosStmt = $conn->prepare($maxPosQuery);
        $maxPosStmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
        $maxPosStmt->execute();
        
        $maxPos = $maxPosStmt->fetch(PDO::FETCH_ASSOC)['max_pos'];
        $newPosition = $maxPos + 1;
        
        // Insérer l'utilisateur dans la partie
        $joinQuery = "INSERT INTO Utilisateurs_parties (id_utilisateur, id_partie, position, date_rejointe) 
                     VALUES (:user_id, :game_id, :position, NOW())";
        
        $joinStmt = $conn->prepare($joinQuery);
        $joinStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $joinStmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
        $joinStmt->bindParam(':position', $newPosition, PDO::PARAM_INT);
        $joinStmt->execute();
        
        // Vérifier si la partie est maintenant complète
        $checkFullQuery = "SELECT p.nombre_joueurs,
                          (SELECT COUNT(*) FROM Utilisateurs_parties WHERE id_partie = p.id) as joueurs_actuels
                          FROM Parties p 
                          WHERE p.id = :game_id";
        
        $checkFullStmt = $conn->prepare($checkFullQuery);
        $checkFullStmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
        $checkFullStmt->execute();
        
        $gameInfo = $checkFullStmt->fetch(PDO::FETCH_ASSOC);
        $isFull = $gameInfo['joueurs_actuels'] >= $gameInfo['nombre_joueurs'];
        
        // Si la partie est complète, mettre à jour le statut
        if ($isFull) {
            $updateStatusQuery = "UPDATE Parties SET status = :status_full WHERE id = :game_id";
            $updateStatusStmt = $conn->prepare($updateStatusQuery);
            $updateStatusStmt->bindValue(':status_full', GAME_STATUS_FULL);
            $updateStatusStmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
            $updateStatusStmt->execute();
        }
        
        // Log de l'action pour audit
        $logQuery = "INSERT INTO Actions_jeu (id_partie, id_utilisateur, type_action, details, date_action) 
                    VALUES (:game_id, :user_id, 'rejoindre_partie', :details, NOW())";
        
        $logStmt = $conn->prepare($logQuery);
        $logStmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
        $logStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        
        $details = json_encode([
            'position' => $newPosition,
            'joueurs_actuels' => (int) $gameInfo['joueurs_actuels'],
            'partie_complete' => $isFull
        ]);
        $logStmt->bindParam(':details', $details);
        $logStmt->execute();
        
        // Valider la transaction
        $conn->commit();
        
        return [
            'success' => true,
            'position' => $newPosition,
            'is_admin' => ($newPosition === 1),
            'current_players' => (int) $gameInfo['joueurs_actuels'],
            'max_players' => (int) $gameInfo['nombre_joueurs'],
            'game_full' => $isFull
        ];
        
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
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
    $gameId = validateInput();
    $userId = SessionManager::get('user_id');
    $userName = SessionManager::get('username', 'Utilisateur');
    
    // Connexion base de données
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Vérification de la disponibilité de la partie
    $availabilityCheck = checkGameAvailability($conn, $gameId, $userId);
    
    // Si erreur
    if (isset($availabilityCheck['error'])) {
        sendJsonResponse(false, null, $availabilityCheck['error'], $availabilityCheck['code']);
    }
    
    // Si l'utilisateur fait déjà partie de la partie
    if (isset($availabilityCheck['already_joined'])) {
        sendJsonResponse(true, [
            'already_joined' => true,
            'message' => 'Vous faites déjà partie de cette partie',
            'redirect_url' => BASE_URL . 'pages/game.php?id=' . $gameId,
            'position' => $availabilityCheck['position']
        ]);
    }
    
    // Ajouter l'utilisateur à la partie
    $joinResult = addUserToGame($conn, $gameId, $userId);
    
    // Récupérer les informations complètes de la partie pour la réponse
    $gameInfoQuery = "SELECT p.nom, p.niveau, p.difficulte, p.nombre_joueurs,
                     (SELECT COUNT(*) FROM Utilisateurs_parties WHERE id_partie = p.id) as joueurs_actuels,
                     (SELECT u.identifiant FROM Utilisateurs u 
                      JOIN Utilisateurs_parties up ON u.id = up.id_utilisateur 
                      WHERE up.id_partie = p.id AND up.position = 1 LIMIT 1) as admin_nom
                     FROM Parties p WHERE p.id = :game_id";
    
    $gameInfoStmt = $conn->prepare($gameInfoQuery);
    $gameInfoStmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
    $gameInfoStmt->execute();
    $gameInfo = $gameInfoStmt->fetch(PDO::FETCH_ASSOC);
    
    // Réponse de succès
    sendJsonResponse(true, [
        'message' => 'Vous avez rejoint la partie avec succès !',
        'game' => [
            'id' => $gameId,
            'nom' => $gameInfo['nom'],
            'niveau' => (int) $gameInfo['niveau'],
            'difficulte' => $gameInfo['difficulte'],
            'admin' => $gameInfo['admin_nom']
        ],
        'player' => [
            'position' => $joinResult['position'],
            'is_admin' => $joinResult['is_admin'],
            'name' => $userName
        ],
        'status' => [
            'current_players' => $joinResult['current_players'],
            'max_players' => $joinResult['max_players'],
            'game_full' => $joinResult['game_full']
        ],
        'redirect_url' => BASE_URL . 'pages/game.php?id=' . $gameId,
        'timestamp' => time()
    ]);
    
} catch (PDOException $e) {
    // Log de l'erreur SQL
    error_log("Erreur SQL dans join_game.php: " . $e->getMessage() . " - Game ID: $gameId, User ID: $userId");
    
    // Vérifier si c'est une violation de contrainte unique (utilisateur déjà dans la partie)
    if ($e->getCode() == '23000' && strpos($e->getMessage(), 'Duplicate') !== false) {
        sendJsonResponse(false, null, 'Vous faites déjà partie de cette partie', 409);
    }
    
    sendJsonResponse(false, null, 'Erreur lors de la jointure à la partie', 500);
    
} catch (Exception $e) {
    // Log de l'erreur générale
    error_log("Erreur générale dans join_game.php: " . $e->getMessage());
    sendJsonResponse(false, null, 'Une erreur inattendue s\'est produite', 500);
}

// Nettoyage automatique
register_shutdown_function(function() {
    if (isset($conn)) {
        $conn = null;
    }
    
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $executionTime = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
        error_log("join_game.php - Temps d'exécution: " . round($executionTime * 1000, 2) . "ms");
    }
});
?>