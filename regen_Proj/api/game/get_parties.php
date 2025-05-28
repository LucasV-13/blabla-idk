<?php
/**
 * API Endpoint : Récupération des parties pour AJAX
 * Utilisé par le dashboard pour l'actualisation automatique
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

try {
    // Vérification authentification obligatoire
    if (!SessionManager::isLoggedIn()) {
        sendJsonResponse(false, null, 'Session expirée', 401);
    }

    // Prolongation automatique de session
    SessionManager::refreshSession();

    // Récupération sécurisée de l'utilisateur
    $userId = SessionManager::get('user_id');
    $userRole = SessionManager::get('role', 'joueur');

    // Connexion base de données via singleton
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Requête optimisée avec jointures pour récupérer toutes les parties
    $sql = "SELECT 
                p.id,
                p.nom,
                p.niveau,
                p.status,
                p.nombre_joueurs,
                p.difficulte,
                p.prive,
                p.date_creation,
                p.date_debut,
                (SELECT COUNT(*) FROM Utilisateurs_parties WHERE id_partie = p.id) as joueurs_actuels,
                
                -- Récupération admin via sous-requête optimisée
                (SELECT u.identifiant 
                 FROM Utilisateurs u 
                 JOIN Utilisateurs_parties up ON u.id = up.id_utilisateur 
                 WHERE up.id_partie = p.id AND up.position = 1 
                 LIMIT 1) as admin_nom,
                
                -- Vérification si l'utilisateur actuel participe
                (SELECT COUNT(*) > 0 
                 FROM Utilisateurs_parties 
                 WHERE id_partie = p.id AND id_utilisateur = :user_id) as user_joined,
                
                -- Position de l'utilisateur dans la partie (si participant)
                (SELECT position 
                 FROM Utilisateurs_parties 
                 WHERE id_partie = p.id AND id_utilisateur = :user_id 
                 LIMIT 1) as user_position
                
            FROM Parties p
            WHERE p.status IN (:status_waiting, :status_progress, :status_paused)
            ORDER BY 
                CASE p.status 
                    WHEN :status_progress THEN 1 
                    WHEN :status_waiting THEN 2 
                    WHEN :status_paused THEN 3 
                    ELSE 4 
                END,
                p.date_creation DESC
            LIMIT 50"; // Limite pour performance

    $stmt = $conn->prepare($sql);
    
    // Liaison des paramètres avec constantes
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':status_waiting', GAME_STATUS_WAITING);
    $stmt->bindValue(':status_progress', GAME_STATUS_IN_PROGRESS);
    $stmt->bindValue(':status_paused', GAME_STATUS_PAUSED);
    
    $stmt->execute();
    $parties = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Enrichissement des données pour chaque partie
    foreach ($parties as &$partie) {
        // Conversion des types pour JavaScript
        $partie['id'] = (int) $partie['id'];
        $partie['niveau'] = (int) $partie['niveau'];
        $partie['nombre_joueurs'] = (int) $partie['nombre_joueurs'];
        $partie['joueurs_actuels'] = (int) $partie['joueurs_actuels'];
        $partie['prive'] = (bool) $partie['prive'];
        $partie['user_joined'] = (bool) $partie['user_joined'];
        $partie['user_position'] = $partie['user_position'] ? (int) $partie['user_position'] : null;
        
        // Détermination de l'état de la partie
        $partie['is_full'] = $partie['joueurs_actuels'] >= $partie['nombre_joueurs'];
        $partie['can_join'] = ($partie['status'] === GAME_STATUS_WAITING && !$partie['is_full'] && !$partie['user_joined']);
        $partie['can_play'] = ($partie['status'] === GAME_STATUS_IN_PROGRESS && $partie['user_joined']);
        $partie['is_admin'] = ($partie['user_joined'] && $partie['user_position'] === 1);
        
        // Calcul du temps écoulé depuis création
        if ($partie['date_creation']) {
            $dateCreation = new DateTime($partie['date_creation']);
            $now = new DateTime();
            $interval = $now->diff($dateCreation);
            
            if ($interval->days > 0) {
                $partie['time_ago'] = $interval->days . ' jour' . ($interval->days > 1 ? 's' : '');
            } elseif ($interval->h > 0) {
                $partie['time_ago'] = $interval->h . ' heure' . ($interval->h > 1 ? 's' : '');
            } else {
                $partie['time_ago'] = max(1, $interval->i) . ' minute' . ($interval->i > 1 ? 's' : '');
            }
        } else {
            $partie['time_ago'] = 'Inconnue';
        }
        
        // Nettoyage des champs sensibles
        unset($partie['user_position']); // Supprimé après utilisation
    }

    // Statistiques globales pour le dashboard
    $stats = [
        'total_parties' => count($parties),
        'parties_waiting' => count(array_filter($parties, fn($p) => $p['status'] === GAME_STATUS_WAITING)),
        'parties_in_progress' => count(array_filter($parties, fn($p) => $p['status'] === GAME_STATUS_IN_PROGRESS)),
        'parties_user_joined' => count(array_filter($parties, fn($p) => $p['user_joined'])),
        'parties_can_join' => count(array_filter($parties, fn($p) => $p['can_join']))
    ];

    // Réponse de succès avec données enrichies
    sendJsonResponse(true, [
        'parties' => $parties,
        'stats' => $stats,
        'user' => [
            'id' => $userId,
            'role' => $userRole,
            'is_admin' => strtolower($userRole) === ROLE_ADMIN
        ],
        'timestamp' => time(),
        'cache_duration' => DASHBOARD_REFRESH_INTERVAL / 1000 // En secondes
    ]);

} catch (PDOException $e) {
    // Log de l'erreur pour débogage (en production)
    error_log("Erreur SQL dans get_parties.php: " . $e->getMessage());
    
    // Réponse d'erreur générique pour sécurité
    sendJsonResponse(false, null, 'Erreur lors de la récupération des parties', 500);
    
} catch (Exception $e) {
    // Log de l'erreur générale
    error_log("Erreur générale dans get_parties.php: " . $e->getMessage());
    
    // Réponse d'erreur
    sendJsonResponse(false, null, 'Une erreur inattendue s\'est produite', 500);
}

// Fonction de nettoyage automatique (appelée en fin de script)
register_shutdown_function(function() {
    // Nettoyage mémoire
    if (isset($conn)) {
        $conn = null;
    }
    
    // Log de performance (optionnel en développement)
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $executionTime = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
        error_log("get_parties.php - Temps d'exécution: " . round($executionTime * 1000, 2) . "ms");
    }
});
?>