<?php
/**
 * API Endpoint : Déconnexion sécurisée
 * Gère la déconnexion des utilisateurs avec nettoyage complet
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

// Headers de sécurité et JSON
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

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
 * Log de l'action de déconnexion pour audit
 */
function logLogoutAction($conn, $userId, $userName, $logoutType = 'manual') {
    try {
        if (!$conn || !$userId) {
            return false;
        }
        
        $logQuery = "INSERT INTO Actions_audit (id_utilisateur, type_action, details, ip_address, user_agent, date_action) 
                    VALUES (:user_id, 'logout', :details, :ip_address, :user_agent, NOW())";
        
        $stmt = $conn->prepare($logQuery);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        
        $details = json_encode([
            'logout_type' => $logoutType,
            'username' => $userName,
            'session_duration' => isset($_SESSION['login_time']) ? (time() - $_SESSION['login_time']) : null,
            'timestamp' => time()
        ]);
        
        $stmt->bindParam(':details', $details);
        $stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
        $stmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT']);
        
        return $stmt->execute();
        
    } catch (PDOException $e) {
        // Log l'erreur mais ne pas faire échouer la déconnexion
        error_log("Erreur lors du log de déconnexion: " . $e->getMessage());
        return false;
    }
}

/**
 * Nettoyage des sessions actives en base de données
 */
function cleanupUserSessions($conn, $userId) {
    try {
        if (!$conn || !$userId) {
            return false;
        }
        
        // Marquer toutes les sessions de l'utilisateur comme expirées
        $cleanupQuery = "UPDATE User_sessions 
                        SET status = 'expired', date_expiration = NOW() 
                        WHERE id_utilisateur = :user_id AND status = 'active'";
        
        $stmt = $conn->prepare($cleanupQuery);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        
        return $stmt->execute();
        
    } catch (PDOException $e) {
        error_log("Erreur lors du nettoyage des sessions: " . $e->getMessage());
        return false;
    }
}

/**
 * Mise à jour du statut "en ligne" de l'utilisateur
 */
function updateUserOnlineStatus($conn, $userId, $online = false) {
    try {
        if (!$conn || !$userId) {
            return false;
        }
        
        $statusQuery = "UPDATE Utilisateurs 
                       SET en_ligne = :online, derniere_activite = NOW() 
                       WHERE id = :user_id";
        
        $stmt = $conn->prepare($statusQuery);
        $stmt->bindParam(':online', $online, PDO::PARAM_BOOL);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        
        return $stmt->execute();
        
    } catch (PDOException $e) {
        error_log("Erreur lors de la mise à jour du statut en ligne: " . $e->getMessage());
        return false;
    }
}

/**
 * Validation des entrées et méthode
 */
function validateLogoutRequest() {
    // Vérification de la méthode HTTP (POST uniquement pour sécurité)
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(false, null, 'Méthode non autorisée', 405);
    }
    
    // Récupération du type de déconnexion (optionnel)
    $logoutType = filter_input(INPUT_POST, 'logout_type', FILTER_SANITIZE_STRING) ?: 'manual';
    
    // Types autorisés
    $allowedTypes = ['manual', 'timeout', 'security', 'forced'];
    if (!in_array($logoutType, $allowedTypes)) {
        $logoutType = 'manual';
    }
    
    return $logoutType;
}

try {
    // Validation de la requête
    $logoutType = validateLogoutRequest();
    
    // Récupération des informations utilisateur avant nettoyage
    $userId = SessionManager::get('user_id');
    $userName = SessionManager::get('username', 'Utilisateur inconnu');
    $userRole = SessionManager::get('role', 'joueur');
    $isLoggedIn = SessionManager::isLoggedIn();
    
    // Si l'utilisateur n'est pas connecté
    if (!$isLoggedIn) {
        sendJsonResponse(true, [
            'message' => 'Aucune session active trouvée',
            'redirect_url' => BASE_URL . 'index.php',
            'already_logged_out' => true
        ]);
    }
    
    // Vérification CSRF pour les déconnexions manuelles
    if ($logoutType === 'manual') {
        $csrfToken = filter_input(INPUT_POST, 'csrf_token');
        if (!SessionManager::validateCSRFToken($csrfToken)) {
            sendJsonResponse(false, null, 'Token CSRF invalide', 403);
        }
    }
    
    // Connexion base de données pour les logs
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Log de l'action avant destruction de session
    $logSuccess = logLogoutAction($conn, $userId, $userName, $logoutType);
    
    // Nettoyage des sessions en base de données
    $cleanupSuccess = cleanupUserSessions($conn, $userId);
    
    // Mise à jour du statut en ligne
    $statusUpdateSuccess = updateUserOnlineStatus($conn, $userId, false);
    
    // Sauvegarde des informations pour la réponse
    $responseData = [
        'user' => [
            'id' => $userId,
            'username' => $userName,
            'role' => $userRole
        ],
        'logout_type' => $logoutType,
        'session_cleanup' => [
            'audit_logged' => $logSuccess,
            'sessions_cleaned' => $cleanupSuccess,
            'status_updated' => $statusUpdateSuccess
        ],
        'redirect_url' => BASE_URL . 'index.php',
        'timestamp' => time()
    ];
    
    // Destruction complète de la session côté serveur
    SessionManager::destroy();
    
    // Instructions pour le nettoyage côté client
    $clientCleanup = [
        'clear_local_storage' => true,
        'clear_session_storage' => true,
        'clear_cookies' => true,
        'reload_page' => true
    ];
    
    // Messages personnalisés selon le type de déconnexion
    $messages = [
        'manual' => 'Vous avez été déconnecté avec succès',
        'timeout' => 'Votre session a expiré. Vous avez été déconnecté automatiquement',
        'security' => 'Déconnexion de sécurité effectuée',
        'forced' => 'Déconnexion forcée par un administrateur'
    ];
    
    $message = $messages[$logoutType] ?? $messages['manual'];
    
    // Réponse de succès
    sendJsonResponse(true, array_merge($responseData, [
        'message' => $message,
        'client_cleanup' => $clientCleanup
    ]));
    
} catch (PDOException $e) {
    // Log de l'erreur SQL mais continuer la déconnexion
    error_log("Erreur SQL dans logout.php: " . $e->getMessage());
    
    // Forcer la destruction de session même en cas d'erreur de base
    SessionManager::destroy();
    
    sendJsonResponse(true, [
        'message' => 'Déconnexion effectuée (avec erreurs de nettoyage)',
        'redirect_url' => BASE_URL . 'index.php',
        'warning' => 'Certaines données de session n\'ont pas pu être nettoyées'
    ]);
    
} catch (Exception $e) {
    // Log de l'erreur générale
    error_log("Erreur générale dans logout.php: " . $e->getMessage());
    
    // Forcer la destruction de session
    SessionManager::destroy();
    
    sendJsonResponse(true, [
        'message' => 'Déconnexion d\'urgence effectuée',
        'redirect_url' => BASE_URL . 'index.php',
        'emergency_logout' => true
    ]);
}

// Nettoyage automatique et sécurisé
register_shutdown_function(function() {
    // S'assurer que la session est détruite
    if (session_status() === PHP_SESSION_ACTIVE) {
        SessionManager::destroy();
    }
    
    // Nettoyage des variables sensibles
    if (isset($conn)) {
        $conn = null;
    }
    
    // Nettoyage mémoire
    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }
    
    // Log de performance en mode debug
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $executionTime = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
        error_log("logout.php - Temps d'exécution: " . round($executionTime * 1000, 2) . "ms");
    }
});

// Headers finaux de sécurité
header('Clear-Site-Data: "cache", "cookies", "storage", "executionContexts"');
?>