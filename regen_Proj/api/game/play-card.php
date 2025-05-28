<?php
/**
 * API Endpoint: Jouer une carte
 * 
 * Gère la logique de jeu d'une carte dans The Mind :
 * - Validation de l'ordre des cartes (la plus petite)
 * - Gestion des erreurs et perte de vies
 * - Fin de niveau et de partie
 * - Logs d'audit complets
 * 
 * @package TheMind
 * @version 2.0
 */

// Configuration stricte
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Chargement des dépendances
require_once '../../../config/database.php';
require_once '../../../config/session.php';
require_once '../../../config/constants.php';

// Headers de sécurité
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

/**
 * Envoie une réponse JSON standardisée
 */
function sendJsonResponse(bool $success, $data = null, ?string $message = null, int $httpCode = 200): void {
    http_response_code($httpCode);
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'timestamp' => time()
    ], JSON_THROW_ON_ERROR);
    exit;
}

/**
 * Valide les entrées POST
 */
function validateInput(): array {
    // Vérification méthode HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(false, null, 'Méthode non autorisée', 405);
    }
    
    // Vérification authentification
    if (!SessionManager::isLoggedIn()) {
        sendJsonResponse(false, null, 'Non authentifié', 401);
    }
    
    // Vérification CSRF
    $csrfToken = filter_input(INPUT_POST, 'csrf_token', FILTER_SANITIZE_STRING);
    if (!SessionManager::validateCSRFToken($csrfToken)) {
        sendJsonResponse(false, null, 'Token CSRF invalide', 403);
    }
    
    // Validation des paramètres requis
    $cardId = filter_input(INPUT_POST, 'card_id', FILTER_VALIDATE_INT);
    $gameId = filter_input(INPUT_POST, 'game_id', FILTER_VALIDATE_INT);
    
    if (!$cardId || $cardId <= 0) {
        sendJsonResponse(false, null, 'ID de carte invalide', 400);
    }
    
    if (!$gameId || $gameId <= 0) {
        sendJsonResponse(false, null, 'ID de partie invalide', 400);
    }
    
    return [
        'card_id' => $cardId,
        'game_id' => $gameId,
        'user_id' => SessionManager::get('user_id')
    ];
}

/**
 * Enregistre une action dans les logs d'audit
 */
function logAction(PDO $pdo, int $gameId, int $userId, string $action, array $details): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO Actions_jeu (id_partie, id_utilisateur, type_action, details, date_action) 
            VALUES (:game_id, :user_id, :action, :details, NOW())
        ");
        
        $stmt->execute([
            ':game_id' => $gameId,
            ':user_id' => $userId,
            ':action' => $action,
            ':details' => json_encode($details, JSON_THROW_ON_ERROR)
        ]);
    } catch (Exception $e) {
        error_log("Erreur log action: " . $e->getMessage());
    }
}

/**
 * Récupère les informations de la partie
 */
function getGameInfo(PDO $pdo, int $gameId): array {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               (SELECT COUNT(*) FROM Utilisateurs_parties WHERE id_partie = p.id) as current_players
        FROM Parties p 
        WHERE p.id = :game_id
    ");
    
    $stmt->execute([':game_id' => $gameId]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$game) {
        sendJsonResponse(false, null, 'Partie introuvable', 404);
    }
    
    return $game;
}

/**
 * Vérifie si le joueur peut jouer dans cette partie
 */
function validatePlayerAccess(PDO $pdo, int $gameId, int $userId): void {
    $stmt = $pdo->prepare("
        SELECT 1 FROM Utilisateurs_parties 
        WHERE id_partie = :game_id AND id_utilisateur = :user_id
    ");
    
    $stmt->execute([
        ':game_id' => $gameId,
        ':user_id' => $userId
    ]);
    
    if (!$stmt->fetch()) {
        sendJsonResponse(false, null, 'Vous ne participez pas à cette partie', 403);
    }
}

/**
 * Récupère les informations de la carte à jouer
 */
function getCardInfo(PDO $pdo, int $cardId, int $gameId, int $userId): array {
    $stmt = $pdo->prepare("
        SELECT id, valeur, etat 
        FROM Cartes 
        WHERE id = :card_id 
          AND id_partie = :game_id 
          AND id_utilisateur = :user_id 
          AND etat = 'en_main'
    ");
    
    $stmt->execute([
        ':card_id' => $cardId,
        ':game_id' => $gameId,
        ':user_id' => $userId
    ]);
    
    $card = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$card) {
        sendJsonResponse(false, null, 'Carte invalide ou déjà jouée', 400);
    }
    
    return $card;
}

/**
 * Récupère la valeur de la plus petite carte en jeu (toutes mains confondues)
 */
function getMinCardValue(PDO $pdo, int $gameId): ?int {
    $stmt = $pdo->prepare("
        SELECT MIN(valeur) as min_value
        FROM Cartes 
        WHERE id_partie = :game_id AND etat = 'en_main'
    ");
    
    $stmt->execute([':game_id' => $gameId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? (int)$result['min_value'] : null;
}

/**
 * Récupère la valeur de la dernière carte jouée
 */
function getLastPlayedCardValue(PDO $pdo, int $gameId): int {
    $stmt = $pdo->prepare("
        SELECT MAX(valeur) as max_value
        FROM Cartes 
        WHERE id_partie = :game_id AND etat = 'jouee'
    ");
    
    $stmt->execute([':game_id' => $gameId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result && $result['max_value'] ? (int)$result['max_value'] : 0;
}

/**
 * Marque la carte comme jouée
 */
function playCard(PDO $pdo, int $cardId): void {
    $stmt = $pdo->prepare("
        UPDATE Cartes 
        SET etat = 'jouee', date_action = NOW() 
        WHERE id = :card_id
    ");
    
    $stmt->execute([':card_id' => $cardId]);
}

/**
 * Fait perdre une vie à la partie
 */
function loseLife(PDO $pdo, int $gameId): int {
    $stmt = $pdo->prepare("
        UPDATE Parties 
        SET vies_restantes = GREATEST(0, vies_restantes - 1)
        WHERE id = :game_id
    ");
    
    $stmt->execute([':game_id' => $gameId]);
    
    // Récupère le nombre de vies restantes
    $stmt = $pdo->prepare("SELECT vies_restantes FROM Parties WHERE id = :game_id");
    $stmt->execute([':game_id' => $gameId]);
    
    return (int)$stmt->fetchColumn();
}

/**
 * Termine la partie (défaite)
 */
function endGameDefeat(PDO $pdo, int $gameId): void {
    $stmt = $pdo->prepare("
        UPDATE Parties 
        SET status = :status, date_fin = NOW() 
        WHERE id = :game_id
    ");
    
    $stmt->execute([
        ':status' => GAME_STATUS_FINISHED,
        ':game_id' => $gameId
    ]);
    
    // Sauvegarder les scores (défaite)
    saveGameScore($pdo, $gameId, false);
}

/**
 * Vérifie si le niveau est terminé
 */
function checkLevelComplete(PDO $pdo, int $gameId): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as remaining_cards
        FROM Cartes 
        WHERE id_partie = :game_id AND etat = 'en_main'
    ");
    
    $stmt->execute([':game_id' => $gameId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return (int)$result['remaining_cards'] === 0;
}

/**
 * Marque le niveau comme terminé
 */
function completeLevel(PDO $pdo, int $gameId, int $currentLevel): void {
    if ($currentLevel >= MAX_GAME_LEVEL) {
        // Partie gagnée
        $stmt = $pdo->prepare("
            UPDATE Parties 
            SET status = :status, date_fin = NOW() 
            WHERE id = :game_id
        ");
        
        $stmt->execute([
            ':status' => GAME_STATUS_WON,
            ':game_id' => $gameId
        ]);
        
        // Sauvegarder les scores (victoire)
        saveGameScore($pdo, $gameId, true);
    } else {
        // Niveau terminé, prêt pour le suivant
        $stmt = $pdo->prepare("
            UPDATE Parties 
            SET status = :status 
            WHERE id = :game_id
        ");
        
        $stmt->execute([
            ':status' => GAME_STATUS_LEVEL_COMPLETE,
            ':game_id' => $gameId
        ]);
    }
}

/**
 * Sauvegarde les scores de fin de partie
 */
function saveGameScore(PDO $pdo, int $gameId, bool $isWin): void {
    try {
        // Récupérer les informations de la partie
        $stmt = $pdo->prepare("
            SELECT niveau, nombre_joueurs, difficulte 
            FROM Parties 
            WHERE id = :game_id
        ");
        $stmt->execute([':game_id' => $gameId]);
        $gameInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$gameInfo) return;
        
        // Calculer le score
        $baseScore = $gameInfo['niveau'] * 100;
        $difficultyMultiplier = match($gameInfo['difficulte']) {
            'facile' => 1.0,
            'moyen' => 1.2,
            'difficile' => 1.5,
            default => 1.0
        };
        $score = (int)($baseScore * $difficultyMultiplier);
        
        // Sauvegarder pour chaque joueur
        $stmt = $pdo->prepare("
            SELECT id_utilisateur 
            FROM Utilisateurs_parties 
            WHERE id_partie = :game_id
        ");
        $stmt->execute([':game_id' => $gameId]);
        $players = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($players as $userId) {
            // Insérer le score
            $stmt = $pdo->prepare("
                INSERT INTO Scores (id_utilisateur, id_partie, score, niveau_max_atteint, date_score)
                VALUES (:user_id, :game_id, :score, :max_level, NOW())
                ON DUPLICATE KEY UPDATE 
                    score = GREATEST(score, :score),
                    niveau_max_atteint = GREATEST(niveau_max_atteint, :max_level)
            ");
            
            $stmt->execute([
                ':user_id' => $userId,
                ':game_id' => $gameId,
                ':score' => $score,
                ':max_level' => $gameInfo['niveau']
            ]);
            
            // Mettre à jour les statistiques
            updatePlayerStats($pdo, $userId, $isWin);
        }
    } catch (Exception $e) {
        error_log("Erreur sauvegarde score: " . $e->getMessage());
    }
}

/**
 * Met à jour les statistiques du joueur
 */
function updatePlayerStats(PDO $pdo, int $userId, bool $isWin): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO Statistiques (id_utilisateur, parties_jouees, parties_gagnees, cartes_jouees)
            VALUES (:user_id, 1, :wins, 1)
            ON DUPLICATE KEY UPDATE
                parties_jouees = parties_jouees + 1,
                parties_gagnees = parties_gagnees + :wins,
                cartes_jouees = cartes_jouees + 1,
                taux_reussite = ROUND((parties_gagnees * 100.0) / parties_jouees, 2)
        ");
        
        $wins = $isWin ? 1 : 0;
        $stmt->execute([
            ':user_id' => $userId,
            ':wins' => $wins
        ]);
    } catch (Exception $e) {
        error_log("Erreur mise à jour stats: " . $e->getMessage());
    }
}

/**
 * Nettoyage automatique en cas d'arrêt imprévu
 */
register_shutdown_function(function() {
    if (connection_status() !== CONNECTION_NORMAL) {
        error_log("Arrêt imprévu de play_card.php");
    }
});

// === LOGIQUE PRINCIPALE ===

try {
    // Validation des entrées
    $input = validateInput();
    $cardId = $input['card_id'];
    $gameId = $input['game_id'];
    $userId = $input['user_id'];
    
    // Connexion base de données
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Démarrer une transaction
    $pdo->beginTransaction();
    
    // Vérifications préliminaires
    $game = getGameInfo($pdo, $gameId);
    
    // Vérifier le statut de la partie
    if ($game['status'] !== GAME_STATUS_IN_PROGRESS) {
        $pdo->rollBack();
        sendJsonResponse(false, null, 'La partie n\'est pas en cours', 400);
    }
    
    // Vérifier l'accès du joueur
    validatePlayerAccess($pdo, $gameId, $userId);
    
    // Récupérer les informations de la carte
    $card = getCardInfo($pdo, $cardId, $gameId, $userId);
    $cardValue = (int)$card['valeur'];
    
    // Récupérer la plus petite carte en jeu
    $minCardValue = getMinCardValue($pdo, $gameId);
    
    // Récupérer la dernière carte jouée
    $lastPlayedValue = getLastPlayedCardValue($pdo, $gameId);
    
    // Vérifier l'ordre des cartes
    $isError = false;
    $remainingLives = (int)$game['vies_restantes'];
    
    // Vérification 1: La carte ne doit pas être plus petite que la dernière jouée
    if ($cardValue < $lastPlayedValue) {
        $pdo->rollBack();
        sendJsonResponse(false, null, "Carte trop petite (dernière jouée: {$lastPlayedValue})", 400);
    }
    
    // Vérification 2: La carte doit être la plus petite en jeu
    if ($minCardValue !== null && $cardValue > $minCardValue) {
        $isError = true;
        $remainingLives = loseLife($pdo, $gameId);
        
        // Log de l'erreur
        logAction($pdo, $gameId, $userId, 'carte_erreur', [
            'carte_jouee' => $cardValue,
            'carte_min_attendue' => $minCardValue,
            'vies_restantes' => $remainingLives
        ]);
        
        // Vérifier si la partie est perdue
        if ($remainingLives <= 0) {
            endGameDefeat($pdo, $gameId);
            
            logAction($pdo, $gameId, $userId, 'fin_partie', [
                'raison' => 'plus_de_vies',
                'niveau_atteint' => $game['niveau']
            ]);
            
            $pdo->commit();
            
            sendJsonResponse(true, [
                'card_played' => true,
                'error_occurred' => true,
                'lives_lost' => true,
                'remaining_lives' => 0,
                'game_status' => GAME_STATUS_FINISHED,
                'game_over' => true,
                'is_victory' => false
            ], 'Carte jouée mais erreur fatale - Partie terminée');
        }
    }
    
    // Jouer la carte
    playCard($pdo, $cardId);
    
    // Log de l'action de jeu
    logAction($pdo, $gameId, $userId, 'jouer_carte', [
        'carte_id' => $cardId,
        'valeur' => $cardValue,
        'erreur' => $isError,
        'vies_perdues' => $isError ? 1 : 0
    ]);
    
    // Vérifier si le niveau est terminé
    $levelComplete = checkLevelComplete($pdo, $gameId);
    $gameStatus = $game['status'];
    $isVictory = false;
    
    if ($levelComplete) {
        completeLevel($pdo, $gameId, (int)$game['niveau']);
        
        if ((int)$game['niveau'] >= MAX_GAME_LEVEL) {
            $gameStatus = GAME_STATUS_WON;
            $isVictory = true;
            
            logAction($pdo, $gameId, $userId, 'victoire', [
                'niveau_final' => $game['niveau'],
                'joueurs' => $game['current_players']
            ]);
        } else {
            $gameStatus = GAME_STATUS_LEVEL_COMPLETE;
            
            logAction($pdo, $gameId, $userId, 'niveau_termine', [
                'niveau_termine' => $game['niveau'],
                'prochain_niveau' => (int)$game['niveau'] + 1
            ]);
        }
    }
    
    // Valider la transaction
    $pdo->commit();
    
    // Préparer la réponse
    $responseData = [
        'card_played' => true,
        'card_value' => $cardValue,
        'error_occurred' => $isError,
        'lives_lost' => $isError,
        'remaining_lives' => $remainingLives,
        'level_complete' => $levelComplete,
        'game_status' => $gameStatus,
        'game_over' => in_array($gameStatus, [GAME_STATUS_FINISHED, GAME_STATUS_WON]),
        'is_victory' => $isVictory
    ];
    
    $message = $isError 
        ? 'Carte jouée mais il y avait une carte plus petite en jeu - Vous perdez une vie!'
        : 'Carte jouée avec succès';
    
    if ($levelComplete) {
        $message .= $isVictory 
            ? ' - Félicitations, vous avez gagné!' 
            : ' - Niveau terminé!';
    }
    
    sendJsonResponse(true, $responseData, $message);
    
} catch (PDOException $e) {
    // Rollback en cas d'erreur
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Erreur PDO play_card.php: " . $e->getMessage());
    sendJsonResponse(false, null, 'Erreur de base de données', 500);
    
} catch (Exception $e) {
    // Rollback en cas d'erreur
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Erreur play_card.php: " . $e->getMessage());
    sendJsonResponse(false, null, 'Erreur interne du serveur', 500);
}
?>