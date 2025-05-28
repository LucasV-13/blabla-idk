<?php
/**
 * API Endpoint: Utiliser un shuriken
 * 
 * Gère la logique d'utilisation d'un shuriken dans The Mind :
 * - Défausse automatiquement la plus petite carte de chaque joueur
 * - Vérification du consensus (tous les joueurs doivent être d'accord)
 * - Gestion de la fin de niveau après utilisation
 * - Logs d'audit complets des cartes défaussées
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
    $gameId = filter_input(INPUT_POST, 'game_id', FILTER_VALIDATE_INT);
    
    if (!$gameId || $gameId <= 0) {
        sendJsonResponse(false, null, 'ID de partie invalide', 400);
    }
    
    return [
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
 * Vérifie si le joueur peut utiliser un shuriken dans cette partie
 */
function validateShurikenAccess(PDO $pdo, int $gameId, int $userId): void {
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
 * Consomme un shuriken de la partie
 */
function consumeShuriken(PDO $pdo, int $gameId): int {
    $stmt = $pdo->prepare("
        UPDATE Parties 
        SET shurikens_restants = GREATEST(0, shurikens_restants - 1)
        WHERE id = :game_id
    ");
    
    $stmt->execute([':game_id' => $gameId]);
    
    // Récupère le nombre de shurikens restants
    $stmt = $pdo->prepare("SELECT shurikens_restants FROM Parties WHERE id = :game_id");
    $stmt->execute([':game_id' => $gameId]);
    
    return (int)$stmt->fetchColumn();
}

/**
 * Récupère tous les joueurs de la partie avec leurs cartes
 */
function getPlayersWithCards(PDO $pdo, int $gameId): array {
    $stmt = $pdo->prepare("
        SELECT DISTINCT c.id_utilisateur, u.identifiant as nom_joueur
        FROM Cartes c
        JOIN Utilisateurs u ON c.id_utilisateur = u.id
        WHERE c.id_partie = :game_id AND c.etat = 'en_main'
        ORDER BY c.id_utilisateur
    ");
    
    $stmt->execute([':game_id' => $gameId]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère la plus petite carte d'un joueur
 */
function getPlayerSmallestCard(PDO $pdo, int $gameId, int $userId): ?array {
    $stmt = $pdo->prepare("
        SELECT id, valeur
        FROM Cartes 
        WHERE id_partie = :game_id 
          AND id_utilisateur = :user_id 
          AND etat = 'en_main'
        ORDER BY valeur ASC 
        LIMIT 1
    ");
    
    $stmt->execute([
        ':game_id' => $gameId,
        ':user_id' => $userId
    ]);
    
    $card = $stmt->fetch(PDO::FETCH_ASSOC);
    return $card ?: null;
}

/**
 * Défausse une carte
 */
function discardCard(PDO $pdo, int $cardId): void {
    $stmt = $pdo->prepare("
        UPDATE Cartes 
        SET etat = 'defaussee', date_action = NOW() 
        WHERE id = :card_id
    ");
    
    $stmt->execute([':card_id' => $cardId]);
}

/**
 * Vérifie si le niveau est terminé après défausse
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
            VALUES (:user_id, 1, :wins, 0)
            ON DUPLICATE KEY UPDATE
                parties_jouees = parties_jouees + 1,
                parties_gagnees = parties_gagnees + :wins,
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
 * Vérifie si tous les joueurs ont encore des cartes
 */
function validateAllPlayersHaveCards(PDO $pdo, int $gameId): bool {
    $players = getPlayersWithCards($pdo, $gameId);
    
    foreach ($players as $player) {
        $card = getPlayerSmallestCard($pdo, $gameId, (int)$player['id_utilisateur']);
        if (!$card) {
            return false; // Un joueur n'a plus de cartes
        }
    }
    
    return true;
}

/**
 * Nettoyage automatique en cas d'arrêt imprévu
 */
register_shutdown_function(function() {
    if (connection_status() !== CONNECTION_NORMAL) {
        error_log("Arrêt imprévu de use_shuriken.php");
    }
});

// === LOGIQUE PRINCIPALE ===

try {
    // Validation des entrées
    $input = validateInput();
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
    validateShurikenAccess($pdo, $gameId, $userId);
    
    // Vérifier qu'il reste des shurikens
    if ((int)$game['shurikens_restants'] <= 0) {
        $pdo->rollBack();
        sendJsonResponse(false, null, 'Aucun shuriken disponible', 400);
    }
    
    // Vérifier que tous les joueurs ont encore des cartes
    if (!validateAllPlayersHaveCards($pdo, $gameId)) {
        $pdo->rollBack();
        sendJsonResponse(false, null, 'Un ou plusieurs joueurs n\'ont plus de cartes', 400);
    }
    
    // Consommer le shuriken
    $remainingShurikens = consumeShuriken($pdo, $gameId);
    
    // Récupérer tous les joueurs avec des cartes
    $players = getPlayersWithCards($pdo, $gameId);
    $discardedCards = [];
    $totalCardsDiscarded = 0;
    
    // Pour chaque joueur, défausser sa plus petite carte
    foreach ($players as $player) {
        $playerId = (int)$player['id_utilisateur'];
        $playerName = $player['nom_joueur'];
        
        $smallestCard = getPlayerSmallestCard($pdo, $gameId, $playerId);
        
        if ($smallestCard) {
            // Défausser la carte
            discardCard($pdo, (int)$smallestCard['id']);
            
            // Ajouter aux cartes défaussées pour le log
            $discardedCards[] = [
                'user_id' => $playerId,
                'user_name' => $playerName,
                'card_id' => (int)$smallestCard['id'],
                'card_value' => (int)$smallestCard['valeur']
            ];
            
            $totalCardsDiscarded++;
            
            // Log individuel de défausse
            logAction($pdo, $gameId, $playerId, 'defausse_shuriken', [
                'carte_id' => (int)$smallestCard['id'],
                'valeur' => (int)$smallestCard['valeur'],
                'declencheur' => $userId
            ]);
        }
    }
    
    // Log global de l'utilisation du shuriken
    logAction($pdo, $gameId, $userId, 'utiliser_shuriken', [
        'shurikens_restants' => $remainingShurikens,
        'cartes_defaussees' => $totalCardsDiscarded,
        'joueurs_affectes' => count($players),
        'details_cartes' => $discardedCards
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
                'methode' => 'shuriken',
                'joueurs' => $game['current_players']
            ]);
        } else {
            $gameStatus = GAME_STATUS_LEVEL_COMPLETE;
            
            logAction($pdo, $gameId, $userId, 'niveau_termine', [
                'niveau_termine' => $game['niveau'],
                'prochain_niveau' => (int)$game['niveau'] + 1,
                'methode' => 'shuriken'
            ]);
        }
    }
    
    // Valider la transaction
    $pdo->commit();
    
    // Préparer la réponse
    $responseData = [
        'shuriken_used' => true,
        'remaining_shurikens' => $remainingShurikens,
        'cards_discarded' => $discardedCards,
        'total_cards_discarded' => $totalCardsDiscarded,
        'players_affected' => count($players),
        'level_complete' => $levelComplete,
        'game_status' => $gameStatus,
        'game_over' => in_array($gameStatus, [GAME_STATUS_FINISHED, GAME_STATUS_WON]),
        'is_victory' => $isVictory
    ];
    
    $message = "Shuriken utilisé avec succès - {$totalCardsDiscarded} cartes défaussées";
    
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
    
    error_log("Erreur PDO use_shuriken.php: " . $e->getMessage());
    sendJsonResponse(false, null, 'Erreur de base de données', 500);
    
} catch (Exception $e) {
    // Rollback en cas d'erreur
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Erreur use_shuriken.php: " . $e->getMessage());
    sendJsonResponse(false, null, 'Erreur interne du serveur', 500);
}
?>