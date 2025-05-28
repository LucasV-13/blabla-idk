<?php
/**
 * Gestionnaire de sessions
 * Gestion centralisée des sessions utilisateur avec sécurité renforcée
 */
class SessionManager {
    
    /**
     * Démarre une session sécurisée
     */
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            // Configuration sécurisée des sessions
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            
            session_start();
        }
        
        // Régénérer l'ID de session périodiquement pour la sécurité
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
    
    /**
     * Vérifie si l'utilisateur est connecté
     */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && 
               (!isset($_SESSION['expires']) || $_SESSION['expires'] > time());
    }
    
    /**
     * Redirige vers la page de connexion si non connecté
     */
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: /index.php');
            exit();
        }
        self::extendSession();
    }
    
    /**
     * Prolonge la session d'une heure
     */
    public static function extendSession() {
        $_SESSION['expires'] = time() + (60 * 60); // 1 heure
    }
    
    /**
     * Détruit complètement la session
     */
    public static function destroy() {
        session_unset();
        session_destroy();
        
        // Supprimer le cookie de session
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
    }
    
    /**
     * Génère ou récupère le token CSRF
     */
    public static function getCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Valide un token CSRF
     */
    public static function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && 
               hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Stocke les informations utilisateur en session
     */
    public static function login($user) {
        // Régénérer l'ID de session lors de la connexion
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['identifiant'];
        $_SESSION['role'] = $user['role_nom'] ?? 'joueur';
        $_SESSION['email'] = $user['mail'] ?? '';
        $_SESSION['avatar'] = $user['avatar'] ?? '👤';
        $_SESSION['expires'] = time() + (60 * 60); // 1 heure
        $_SESSION['last_regeneration'] = time();
    }
    
    /**
     * Récupère une valeur de session
     */
    public static function get($key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * Définit une valeur de session
     */
    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    /**
     * Vérifie si l'utilisateur est administrateur
     */
    public static function isAdmin() {
        return self::isLoggedIn() && 
               strtolower(self::get('role', '')) === 'admin';
    }
}
?>