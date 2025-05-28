<?php
/**
 * API Endpoint: État temps réel du jeu
 * 
 * Fournit l'état complet et synchronisé d'une partie en cours :
 * - Informations de partie (niveau, vies, shurikens, statut)
 * - Cartes du joueur actuel
 * - Cartes jouées récemment
 * - Liste des joueurs avec statistiques
 * - Événements récents et notifications
 * - Optimisations pour polling fréquent
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

// Headers de sécurité et optimisation
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/**
 * Envoie une réponse JSON standardisée
 */
function sendJsonResponse(bool $success, $data = null, ?string $message = null, int $httpCode = 200): void {
    http_response_code($httpCode);
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'timestamp' => time(),
        'server_time' => date('Y-m-d H:i:s')
    ], JSON_THROW_ON_ERROR);
    exit;
}

/**
 * Valide les entrées GET
 */
function validateInput(): array {
    // Vérification méthode HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendJsonResponse(false, null, 'Méthode non autorisée', 405);
    }
    
    // Vérification authentification
    if (!SessionManager::isLoggedIn()) {
        sendJsonResponse(false, null, 'Non authentifié', 401);
    }
    
    // Vérification CSRF
    $csrfToken = filter_input(INPUT_GET, 'csrf_token', FILTER_SANITIZE_STRING);
    if (!SessionManager::validateCSRFToken($csrfToken)) {
        sendJsonResponse(false, null, 'Token CSRF invalide', 403);
    }
    
    // Validation des paramètres requis
    $gameId = filter_input(INPUT_GET, 'game_id', FILTER_VALIDATE_INT);
    
    if (!$gameId || $gameId <= 0) {
        sendJsonResponse(false, null, 'ID de partie invalide', 400);
    }
    
    // Paramètres optionnels pour optimisation
    $lastUpdate = filter_input(INPUT_GET, 'last_update', FILTER_VALIDATE_INT);
    $includeHistory = filter_input(INPUT_GET, 'include_history', FILTER_VALIDATE_BOOLEAN);
    
    return [
        'game_id' => $gameId,
        'user_id' => SessionManager::get('user_id'),
        'last_update' => $lastUpdate ?: 0,
        'include_history' => $includeHistory !== false
    ];
}

/**
 * Récupère les informations complètes de la partie
 */
function getGameInfo(PDO $pdo, int $gameId): array {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               p.date_creation,
               p.date_début,
               p.date_fin,
               (SELECT COUNT(*) FROM Utilisateurs_parties WHERE id_partie = p.id) as current_players,
               (SELECT COUNT(*) FROM Cartes WHERE id_partie = p.id AND etat = 'en_main') as total_cards_remaining,
               (SELECT COUNT(*) FROM Cartes WHERE id_partie = p.id AND etat = 'jouee') as total_cards_played,
               (SELECT MAX(date_action) FROM Actions_jeu WHERE id_partie = p.id) as last_action_time
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
 * Vérifie si le joueur fait partie de cette partie
 */
function validatePlayerAccess(PDO $pdo, int $gameId, int $userId): array {
    $stmt = $pdo->prepare("
        SELECT up.position, up.date_rejointe,
               (up.position = 1) as is_admin
        FROM Utilisateurs_parties up
        WHERE up.id_partie = :game_id AND up.id_utilisateur = :user_id
    ");
    
    $stmt->execute([
        ':game_id' => $gameId,
        ':user_id' => $userId
    ]);
    
    $playerInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$playerInfo) {
        sendJsonResponse(false, null, 'Vous ne participez pas à cette partie', 403);
    }
    
    return $playerInfo;
}

/**
 * Récupère les cartes du joueur actuel
 */
function getPlayerCards(PDO $pdo, int $gameId, int $userId): array {
    $stmt = $pdo->prepare("
        SELECT c.id, c.valeur, c.date_action
        FROM Cartes c
        WHERE c.id_partie = :game_id 
          AND c.id_utilisateur = :user_id 
          AND c.etat = 'en_main'
        ORDER BY c.valeur ASC
    ");
    
    $stmt->execute([
        ':game_id' => $gameId,
        ':user_id' => $userId
    ]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les cartes jouées récemment
 */
function getPlayedCards(PDO $pdo, int $gameId, int $limit = 10): array {
    $stmt = $pdo->prepare("
        SELECT c.id, c.valeur, c.date_action,
               u.identifiant as player_name,
               u.avatar as player_avatar,
               c.id_utilisateur as player_id
        FROM Cartes c
        JOIN Utilisateurs u ON c.id_utilisateur = u.id
        WHERE c.id_partie = :game_id AND c.etat = 'jouee'
        ORDER BY c.date_action DESC
        LIMIT :limit
    ");
    
    $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les cartes défaussées récemment
 */
function getDiscardedCards(PDO $pdo, int $gameId, int $limit = 5): array {
    $stmt = $pdo->prepare("
        SELECT c.id, c.valeur, c.date_action,
               u.identifiant as player_name,
               u.avatar as player_avatar,
               c.id_utilisateur as player_id
        FROM Cartes c
        JOIN Utilisateurs u ON c.id_utilisateur = u.id
        WHERE c.id_partie = :game_id AND c.etat = 'defaussee'
        ORDER BY c.date_action DESC
        LIMIT :limit
    ");
    
    $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère la liste des joueurs avec leurs statistiques
 */
function getGamePlayers(PDO $pdo, int $gameId): array {
    $stmt = $pdo->prepare("
        SELECT u.id, u.identifiant as name, u.avatar,
               up.position, up.date_rejointe,
               (up.position = 1) as is_admin,
               (SELECT COUNT(*) FROM Cartes WHERE id_utilisateur = u.id AND id_partie = :game_id AND etat = 'en_main') as cards_count,
               (SELECT COUNT(*) FROM Cartes WHERE id_utilisateur = u.id AND id_partie = :game_id AND etat = 'jouee') as cards_played,
               (SELECT COUNT(*) FROM Actions_jeu WHERE id_utilisateur = u.id AND id_partie = :game_id AND type_action = 'jouer_carte') as total_actions,
               (SELECT MAX(date_action) FROM Actions_jeu WHERE id_utilisateur = u.id AND id_partie = :game_id) as last_activity
        FROM Utilisateurs u
        JOIN Utilisateurs_parties up ON u.id = up.id_utilisateur
        WHERE up.id_partie = :game_id
        ORDER BY up.position ASC
    ");
    
    $stmt->execute([':game_id' => $gameId]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les événements récents du jeu
 */
function getRecentEvents(PDO $pdo, int $gameId, int $since = 0, int $limit = 20): array {
    $whereClause = $since > 0 ? "AND aj.date_action > FROM_UNIXTIME(:since)" : "";
    
    $stmt = $pdo->prepare("
        SELECT aj.id, aj.type_action, aj.details, aj.date_action,
               UNIX_TIMESTAMP(aj.date_action) as timestamp,
               u.identifiant as player_name,
               u.avatar as player_avatar,
               aj.id_utilisateur as player_id
        FROM Actions_jeu aj
        JOIN Utilisateurs u ON aj.id_utilisateur = u.id
        WHERE aj.id_partie = :game_id {$whereClause}
        ORDER BY aj.date_action DESC
        LIMIT :limit
    ");
    
    $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
    if ($since > 0) {
        $stmt->bindParam(':since', $since, PDO::PARAM_INT);
    }
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Décoder les détails JSON
    foreach ($events as &$event) {
        $event['details'] = json_decode($event['details'], true) ?: [];
    }
    
    return $events;
}

/**
 * Calcule les statistiques de progression
 */
function getProgressStats(PDO $pdo, int $gameId, array $gameInfo): array {
    // Calcul du temps de jeu
    $gameStartTime = $gameInfo['date_début'] ? strtotime($gameInfo['date_début']) : null;
    $gameEndTime = $gameInfo['date_fin'] ? strtotime($gameInfo['date_fin']) : null;
    $currentTime = time();
    
    $gameDuration = 0;
    if ($gameStartTime) {
        $gameDuration = ($gameEndTime ?: $currentTime) - $gameStartTime;
    }
    
    // Calcul des cartes par niveau
    $maxCardsPerLevel = (int)$gameInfo['nombre_joueurs'] * (int)$gameInfo['niveau'];
    $cardsPlayed = (int)$gameInfo['total_cards_played'];
    $cardsRemaining = (int)$gameInfo['total_cards_remaining'];
    
    // Progression du niveau actuel
    $levelProgress = $maxCardsPerLevel > 0 ? ($cardsPlayed / $maxCardsPerLevel) * 100 : 0;
    
    // Calcul du score estimé
    $baseScore = (int)$gameInfo['niveau'] * 100;
    $difficultyMultiplier = match($gameInfo['difficulte']) {
        'facile' => 1.0,
        'moyen' => 1.2,
        'difficile' => 1.5,
        default => 1.0
    };
    $estimatedScore = (int)($baseScore * $difficultyMultiplier);
    
    return [
        'game_duration' => $gameDuration,
        'game_duration_formatted' => gmdate('H:i:s', $gameDuration),
        'level_progress' => round($levelProgress, 1),
        'cards_per_level' => $maxCardsPerLevel,
        'cards_played_current_level' => $cardsPlayed,
        'cards_remaining_current_level' => $cardsRemaining,
        'estimated_score' => $estimatedScore,
        'max_possible_score' => (int)(MAX_GAME_LEVEL * 100 * $difficultyMultiplier)
    ];
}

/**
 * Détermine les notifications pour le joueur
 */
function getPlayerNotifications(array $gameInfo, array $playerInfo, array $recentEvents): array {
    $notifications = [];
    
    // Notifications basées sur l'état du jeu
    switch ($gameInfo['status']) {
        case GAME_STATUS_WAITING:
            if ($playerInfo['is_admin']) {
                $notifications[] = [
                    'type' => 'info',
                    'message' => 'Vous pouvez démarrer la partie quand tous les joueurs sont prêts',
                    'action' => 'start_game'
                ];
            } else {
                $notifications[] = [
                    'type' => 'info', 
                    'message' => 'En attente que l\'administrateur démarre la partie',
                    'action' => null
                ];
            }
            break;
            
        case GAME_STATUS_LEVEL_COMPLETE:
            if ($playerInfo['is_admin']) {
                $notifications[] = [
                    'type' => 'success',
                    'message' => 'Niveau terminé ! Vous pouvez passer au niveau suivant',
                    'action' => 'next_level'
                ];
            } else {
                $notifications[] = [
                    'type' => 'success',
                    'message' => 'Niveau terminé ! En attente de l\'administrateur',
                    'action' => null
                ];
            }
            break;
            
        case GAME_STATUS_PAUSED:
            $notifications[] = [
                'type' => 'warning',
                'message' => 'Partie en pause',
                'action' => $playerInfo['is_admin'] ? 'resume_game' : null
            ];
            break;
    }
    
    // Notifications basées sur les événements récents
    foreach ($recentEvents as $event) {
        if ($event['timestamp'] > (time() - 10)) { // Événements des 10 dernières secondes
            switch ($event['type_action']) {
                case 'perdre_vie':
                    $notifications[] = [
                        'type' => 'error',
                        'message' => "Une vie a été perdue ! Vies restantes: {$gameInfo['vies_restantes']}",
                        'temporary' => true
                    ];
                    break;
                    
                case 'utiliser_shuriken':
                    $cardsCount = $event['details']['cartes_defaussees'] ?? 0;
                    $notifications[] = [
                        'type' => 'info',
                        'message' => "Shuriken utilisé - {$cardsCount} cartes défaussées",
                        'temporary' => true
                    ];
                    break;
            }
        }
    }
    
    return $notifications;
}

/**
 * Nettoyage automatique en cas d'arrêt imprévu
 */
register_shutdown_function(function() {
    if (connection_status() !== CONNECTION_NORMAL) {
        error_log("Arrêt imprévu de get_game_state.php");
    }
});

// === LOGIQUE PRINCIPALE ===

try {
    // Validation des entrées
    $input = validateInput();
    $gameId = $input['game_id'];
    $userId = $input['user_id'];
    $lastUpdate = $input['last_update'];
    $includeHistory = $input['include_history'];
    
    // Connexion base de données
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Vérifications préliminaires
    $gameInfo = getGameInfo($pdo, $gameId);
    $playerInfo = validatePlayerAccess($pdo, $gameId, $userId);
    
    // Optimisation: vérifier si des mises à jour ont eu lieu
    $lastActionTime = $gameInfo['last_action_time'] ? strtotime($gameInfo['last_action_time']) : 0;
    $hasUpdates = $lastUpdate == 0 || $lastActionTime > $lastUpdate;
    
    // Récupération des données principales
    $playerCards = getPlayerCards($pdo, $gameId, $userId);
    $playedCards = getPlayedCards($pdo, $gameId, 10);
    $discardedCards = getDiscardedCards($pdo, $gameId, 5);
    $players = getGamePlayers($pdo, $gameId);
    
    // Récupération des événements (seulement si nécessaire)
    $recentEvents = [];
    if ($includeHistory || $hasUpdates) {
        $eventsSince = $includeHistory ? 0 : $lastUpdate;
        $recentEvents = getRecentEvents($pdo, $gameId, $eventsSince, $includeHistory ? 50 : 10);
    }
    
    // Calcul des statistiques de progression
    $progressStats = getProgressStats($pdo, $gameId, $gameInfo);
    
    // Génération des notifications
    $notifications = getPlayerNotifications($gameInfo, $playerInfo, $recentEvents);
    
    // Calcul de la valeur minimale en jeu
    $minCardValue = null;
    $maxPlayedValue = 0;
    
    if (!empty($playerCards)) {
        $allCards = array_column($playerCards, 'valeur');
        foreach ($players as $player) {
            if ($player['id'] != $userId && $player['cards_count'] > 0) {
                // On ne peut pas voir les cartes des autres, mais on sait qu'ils en ont
            }
        }
        $minCardValue = min($allCards);
    }
    
    if (!empty($playedCards)) {
        $maxPlayedValue = max(array_column($playedCards, 'valeur'));
    }
    
    // Préparation de la réponse complète
    $responseData = [
        // Informations de base de la partie
        'game' => [
            'id' => (int)$gameInfo['id'],
            'status' => $gameInfo['status'],
            'niveau' => (int)$gameInfo['niveau'],
            'vies_restantes' => (int)$gameInfo['vies_restantes'],
            'shurikens_restants' => (int)$gameInfo['shurikens_restants'],
            'nombre_joueurs' => (int)$gameInfo['nombre_joueurs'],
            'current_players' => (int)$gameInfo['current_players'],
            'difficulte' => $gameInfo['difficulte'],
            'prive' => (bool)$gameInfo['prive'],
            'date_creation' => $gameInfo['date_creation'],
            'date_début' => $gameInfo['date_début'],
            'date_fin' => $gameInfo['date_fin'],
            'last_action_time' => $lastActionTime
        ],
        
        // Informations du joueur actuel
        'player' => [
            'id' => $userId,
            'position' => (int)$playerInfo['position'],
            'is_admin' => (bool)$playerInfo['is_admin'],
            'cards' => $playerCards,
            'cards_count' => count($playerCards),
            'min_card_value' => $minCardValue
        ],
        
        // État du jeu
        'game_state' => [
            'played_cards' => $playedCards,
            'discarded_cards' => $discardedCards,
            'players' => $players,
            'max_played_value' => $maxPlayedValue,
            'total_cards_remaining' => (int)$gameInfo['total_cards_remaining'],
            'total_cards_played' => (int)$gameInfo['total_cards_played']
        ],
        
        // Progression et statistiques
        'progress' => $progressStats,
        
        // Événements et notifications
        'events' => $recentEvents,
        'notifications' => $notifications,
        
        // Métadonnées pour optimisation
        'meta' => [
            'has_updates' => $hasUpdates,
            'last_update_time' => $lastActionTime,
            'include_history' => $includeHistory,
            'event_count' => count($recentEvents),
            'query_time' => microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))
        ]
    ];
    
    sendJsonResponse(true, $responseData, 'État du jeu récupéré avec succès');
    
} catch (PDOException $e) {
    error_log("Erreur PDO get_game_state.php: " . $e->getMessage());
    sendJsonResponse(false, null, 'Erreur de base de données', 500);
    
} catch (Exception $e) {
    error_log("Erreur get_game_state.php: " . $e->getMessage());
    sendJsonResponse(false, null, 'Erreur interne du serveur', 500);
}
?>