<?php
/**
 * Fonctions de sécurité centralisées pour The Mind
 * Ce fichier contient toutes les fonctions liées à la sécurité
 */

// Empêcher l'accès direct
if (!defined('CONFIG_LOADED')) {
    die('Accès direct interdit');
}

/**
 * Classe de gestion de la sécurité
 */
class Security {
    
    /**
     * Initialiser une session sécurisée
     */
    public static function initSecureSession() {
        // Configuration sécurisée de la session
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_samesite', 'Strict');
            
            session_name(SESSION_NAME);
            session_start();
            
            // Régénérer l'ID de session périodiquement
            if (!isset($_SESSION['last_regeneration'])) {
                $_SESSION['last_regeneration'] = time();
            } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
                session_regenerate_id(true);
                $_SESSION['last_regeneration'] = time();
            }
        }
    }
    
    /**
     * Vérifier l'authentification de l'utilisateur
     * CORRECTION PRINCIPALE : Simplifier la logique de vérification
     */
    public static function checkAuthentication($redirectOnFail = true) {
    // S'assurer que la session est démarrée
    if (session_status() === PHP_SESSION_NONE) {
        self::initSecureSession();
    }
    
    // Debug - À retirer après résolution
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        error_log("CheckAuth - Session ID: " . session_id());
        error_log("CheckAuth - User ID: " . ($_SESSION['user_id'] ?? 'NOT SET'));
        error_log("CheckAuth - Username: " . ($_SESSION['username'] ?? 'NOT SET'));
    }
    
    // Vérifier si l'utilisateur est connecté
    $isAuthenticated = isset($_SESSION['user_id']) && 
                      !empty($_SESSION['user_id']) &&
                      isset($_SESSION['username']) &&
                      !empty($_SESSION['username']);
    
    // Vérifier l'expiration si elle existe
    if ($isAuthenticated && isset($_SESSION['expires'])) {
        if ($_SESSION['expires'] < time()) {
            // Session expirée - nettoyer
            $isAuthenticated = false;
            session_unset();
            session_destroy();
            
            if ($redirectOnFail) {
                self::redirect(SITE_ROOT . 'index.php');
            }
        } else {
            // Prolonger la session
            $_SESSION['expires'] = time() + SESSION_LIFETIME;
        }
    }
    
    // Si non authentifié et redirection demandée
    if (!$isAuthenticated && $redirectOnFail) {
        self::redirect(SITE_ROOT . 'index.php');
    }
    
    return $isAuthenticated;
}
    
    /**
     * Vérifier les permissions d'un utilisateur
     */
    public static function checkPermission($requiredRole, $redirectOnFail = true) {
        if (!self::checkAuthentication($redirectOnFail)) {
            return false;
        }
        
        $userRole = strtolower($_SESSION['role'] ?? 'joueur');
        $requiredRole = strtolower($requiredRole);
        
        $hasPermission = ($userRole === $requiredRole) || ($userRole === 'admin');
        
        if (!$hasPermission && $redirectOnFail) {
            $redirectUrl = defined('SITE_ROOT') ? SITE_ROOT . 'pages/dashboard.php' : '/html/pages/dashboard.php';
            self::redirect($redirectUrl);
        }
        
        return $hasPermission;
    }
    
    /**
     * Générer un token CSRF
     */
    public static function generateCSRFToken() {
        self::initSecureSession();
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Vérifier un token CSRF
     */
    public static function validateCSRFToken($token) {
        self::initSecureSession();
        
        return isset($_SESSION['csrf_token']) && 
               hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Vérifier et valider une requête POST avec CSRF
     */
    public static function validatePostRequest($returnJson = false) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            if ($returnJson) {
                self::jsonResponse(['success' => false, 'message' => 'Méthode non autorisée'], 405);
            } else {
                http_response_code(405);
                die('Méthode non autorisée');
            }
        }
        
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
        if (!self::validateCSRFToken($token)) {
            if ($returnJson) {
                self::jsonResponse(['success' => false, 'message' => 'Token CSRF invalide'], 403);
            } else {
                http_response_code(403);
                die('Token CSRF invalide');
            }
        }
        
        return true;
    }
    
    /**
     * Nettoyer et valider les entrées utilisateur
     */
    public static function sanitizeInput($input, $type = 'string') {
        if (is_array($input)) {
            return array_map(function($item) use ($type) {
                return self::sanitizeInput($item, $type);
            }, $input);
        }
        
        // Nettoyer l'entrée de base
        $input = trim($input);
        
        switch ($type) {
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'url':
                return filter_var($input, FILTER_SANITIZE_URL);
            case 'html':
                return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
            case 'string':
            default:
                return htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Valider une adresse email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Valider un mot de passe
     */
    public static function validatePassword($password) {
        return strlen($password) >= PASSWORD_MIN_LENGTH;
    }
    
    /**
     * Hasher un mot de passe
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Vérifier un mot de passe
     */
    public static function verifyPassword($password, $hash) {
        // Support des anciens mots de passe en texte brut
        if (substr($hash, 0, 1) !== '$') {
            return $password === $hash;
        }
        return password_verify($password, $hash);
    }
    
    /**
     * Gestion de la limitation de taux (rate limiting)
     */
    public static function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 900) {
        if (!defined('ENABLE_RATE_LIMITING') || !ENABLE_RATE_LIMITING) {
            return true;
        }
        
        self::initSecureSession();
        
        $key = 'rate_limit_' . $identifier;
        $now = time();
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['attempts' => 0, 'reset_time' => $now + $timeWindow];
            return true;
        }
        
        $data = $_SESSION[$key];
        
        // Réinitialiser si la fenêtre de temps est expirée
        if ($now > $data['reset_time']) {
            $_SESSION[$key] = ['attempts' => 0, 'reset_time' => $now + $timeWindow];
            return true;
        }
        
        return $data['attempts'] < $maxAttempts;
    }
    
    /**
     * Incrémenter le compteur de tentatives
     */
    public static function incrementRateLimit($identifier) {
        if (!defined('ENABLE_RATE_LIMITING') || !ENABLE_RATE_LIMITING) {
            return;
        }
        
        self::initSecureSession();
        
        $key = 'rate_limit_' . $identifier;
        if (isset($_SESSION[$key])) {
            $_SESSION[$key]['attempts']++;
        }
    }
    
    /**
     * Détruire la session de manière sécurisée
     */
    public static function destroySession() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Nettoyer toutes les variables de session
            $_SESSION = array();
            
            // Supprimer le cookie de session
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            
            // Détruire la session
            session_destroy();
        }
    }
    
    /**
     * Redirection sécurisée
     * CORRECTION : Améliorer la gestion des redirections
     */
    public static function redirect($url, $statusCode = 302) {
        // Nettoyer le buffer de sortie s'il y en a un
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Nettoyer l'URL pour éviter les redirections ouvertes
        $parsedUrl = parse_url($url);
        
        // Si l'URL est relative, la construire correctement
        if (!isset($parsedUrl['scheme'])) {
            if (strpos($url, '/') === 0) {
                // URL absolue commençant par /
                $url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $url;
            } else {
                // URL relative
                $baseUrl = defined('SITE_URL') ? SITE_URL : '/html/';
                $url = $baseUrl . ltrim($url, '/');
            }
        }
        
        // Vérifier que la redirection est vers notre domaine
        $parsedUrl = parse_url($url);
        $currentHost = $_SERVER['HTTP_HOST'];
        
        if (isset($parsedUrl['host']) && $parsedUrl['host'] !== $currentHost) {
            // Redirection externe non autorisée
            $url = defined('SITE_URL') ? SITE_URL . 'index.php' : '/html/index.php';
        }
        
        header("Location: $url", true, $statusCode);
        exit();
    }
    
    /**
     * Réponse JSON sécurisée
     */
    public static function jsonResponse($data, $statusCode = 200) {
        // Nettoyer le buffer de sortie
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    /**
     * Journaliser les événements de sécurité
     */
    public static function logSecurityEvent($event, $details = []) {
        if (!defined('ENABLE_DEBUG_LOGS') || !ENABLE_DEBUG_LOGS) {
            return;
        }
        
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'user_id' => $_SESSION['user_id'] ?? null,
            'details' => $details
        ];
        
        $logFile = defined('LOGS_PATH') ? LOGS_PATH . 'security_' . date('Y-m-d') . '.log' : 'security.log';
        error_log(json_encode($logData) . PHP_EOL, 3, $logFile);
    }
    
    /**
     * Générer un identifiant unique sécurisé
     */
    public static function generateUniqueId($prefix = '', $length = 16) {
        $randomBytes = random_bytes($length);
        $uniqueId = $prefix . bin2hex($randomBytes);
        return $uniqueId;
    }
    
    /**
     * Vérifier la force d'un mot de passe
     */
    public static function getPasswordStrength($password) {
        $score = 0;
        $feedback = [];
        
        // Longueur
        if (strlen($password) >= 8) $score += 25;
        else $feedback[] = 'Au moins 8 caractères';
        
        // Minuscules
        if (preg_match('/[a-z]/', $password)) $score += 25;
        else $feedback[] = 'Au moins une minuscule';
        
        // Majuscules
        if (preg_match('/[A-Z]/', $password)) $score += 25;
        else $feedback[] = 'Au moins une majuscule';
        
        // Chiffres ou caractères spéciaux
        if (preg_match('/[\d\W]/', $password)) $score += 25;
        else $feedback[] = 'Au moins un chiffre ou caractère spécial';
        
        return [
            'score' => $score,
            'strength' => $score < 50 ? 'faible' : ($score < 75 ? 'moyen' : 'fort'),
            'feedback' => $feedback
        ];
    }
}

// ===== FONCTIONS UTILITAIRES GLOBALES =====

/**
 * Fonction raccourcie pour l'authentification
 */
function requireAuth($redirectOnFail = true) {
    return Security::checkAuthentication($redirectOnFail);
}

/**
 * Fonction raccourcie pour les permissions
 */
function requireRole($role, $redirectOnFail = true) {
    return Security::checkPermission($role, $redirectOnFail);
}

/**
 * Fonction raccourcie pour le token CSRF
 */
function csrf_token() {
    return Security::generateCSRFToken();
}

/**
 * Fonction raccourcie pour la validation CSRF
 */
function csrf_check($token) {
    return Security::validateCSRFToken($token);
}

/**
 * Fonction raccourcie pour nettoyer les entrées
 */
function clean($input, $type = 'string') {
    return Security::sanitizeInput($input, $type);
}

/**
 * Fonction raccourcie pour la redirection
 */
function redirect($url, $statusCode = 302) {
    Security::redirect($url, $statusCode);
}

/**
 * Fonction raccourcie pour les réponses JSON
 */
function json_response($data, $statusCode = 200) {
    Security::jsonResponse($data, $statusCode);
}
?>