<?php
/**
 * Fichier d'initialisation centralisé pour The Mind
 * Ce fichier doit être inclus au début de chaque page
 */

// Empêcher l'inclusion multiple
if (defined('THEMIND_INIT')) {
    return;
}
define('THEMIND_INIT', true);

// Démarrer la capture de sortie pour éviter les erreurs d'en-têtes
ob_start();

// Gestion d'erreurs personnalisée
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    
    $errorTypes = [
        E_ERROR => 'ERROR',
        E_WARNING => 'WARNING',
        E_NOTICE => 'NOTICE',
        E_USER_ERROR => 'USER_ERROR',
        E_USER_WARNING => 'USER_WARNING',
        E_USER_NOTICE => 'USER_NOTICE'
    ];
    
    $errorType = $errorTypes[$severity] ?? 'UNKNOWN';
    $errorMessage = "[$errorType] $message in $file on line $line";
    
    error_log($errorMessage);
    
    if (ENVIRONMENT === 'development') {
        echo "<div style='background: #ffebee; color: #c62828; padding: 10px; margin: 10px; border-left: 4px solid #f44336;'>";
        echo "<strong>$errorType:</strong> $message<br>";
        echo "<small>File: $file (line $line)</small>";
        echo "</div>";
    }
    
    return true;
});

// Gestion des exceptions non capturées
set_exception_handler(function($exception) {
    error_log("Uncaught exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
    
    if (ENVIRONMENT === 'development') {
        echo "<div style='background: #ffebee; color: #c62828; padding: 10px; margin: 10px; border-left: 4px solid #f44336;'>";
        echo "<strong>Exception:</strong> " . $exception->getMessage() . "<br>";
        echo "<small>File: " . $exception->getFile() . " (line " . $exception->getLine() . ")</small>";
        echo "<pre>" . $exception->getTraceAsString() . "</pre>";
        echo "</div>";
    } else {
        echo "Une erreur est survenue. Veuillez réessayer plus tard.";
    }
});

// Charger la configuration
$configFile = __DIR__ . '/../config.php';
if (!file_exists($configFile)) {
    die('Fichier de configuration manquant. Veuillez vérifier votre installation.');
}
require_once $configFile;

// Vérifier que la configuration est chargée
if (!defined('CONFIG_LOADED')) {
    die('Erreur de chargement de la configuration.');
}

// Charger les fichiers de base
$includeFiles = [
    INCLUDES_PATH . 'securite.php',
    INCLUDES_PATH . 'database.php',
    INCLUDES_PATH . 'utils.php'
];

foreach ($includeFiles as $file) {
    if (file_exists($file)) {
        require_once $file;
    } else {
        die("Fichier requis manquant: $file");
    }
}

// Initialiser la session sécurisée
Security::initSecureSession();

// Charger la langue
$language = $_SESSION['language'] ?? DEFAULT_LANGUAGE;
Language::load($language);

// Créer une variable globale pour les textes (compatibilité)
$texts = Language::getAll();

// Fonctions d'initialisation spécifiques selon le contexte
class Init {
    
    /**
     * Initialisation pour les pages publiques (sans authentification)
     */
    public static function publicPage() {
        // Générer le token CSRF
        Security::generateCSRFToken();
        
        // Log de l'accès
        Logger::info('Page publique accédée', [
            'page' => $_SERVER['REQUEST_URI'] ?? '',
            'ip' => Utils::getRealIpAddress(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }
    
    /**
     * Initialisation pour les pages protégées (avec authentification)
     */
    public static function protectedPage($redirectUrl = null) {
        if (!$redirectUrl) {
            $redirectUrl = getUrl('index.php');
        }
        
        // Vérifier l'authentification
        if (!Security::checkAuthentication(false)) {
            Security::redirect($redirectUrl);
        }
        
        // Log de l'accès
        Logger::info('Page protégée accédée', [
            'page' => $_SERVER['REQUEST_URI'] ?? '',
            'user_id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null
        ]);
    }
    
    /**
     * Initialisation pour les pages d'administration
     */
    public static function adminPage($redirectUrl = null) {
        if (!$redirectUrl) {
            $redirectUrl = getUrl('pages/dashboard.php');
        }
        
        // Vérifier l'authentification et les permissions
        if (!Security::checkPermission('admin', false)) {
            Security::redirect($redirectUrl);
        }
        
        // Log de l'accès admin
        Logger::info('Page admin accédée', [
            'page' => $_SERVER['REQUEST_URI'] ?? '',
            'user_id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null
        ]);
    }
    
    /**
     * Initialisation pour les API/AJAX
     */
    public static function apiEndpoint($requireAuth = true) {
        // Headers de sécurité pour les API
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        
        if ($requireAuth) {
            if (!Security::checkAuthentication(false)) {
                Security::jsonResponse(['success' => false, 'message' => 'Non authentifié'], 401);
            }
        }
        
        // Valider la méthode HTTP pour les modifications
        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
            $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
            if (!Security::validateCSRFToken($token)) {
                Security::jsonResponse(['success' => false, 'message' => 'Token CSRF invalide'], 403);
            }
        }
        
        // Log de l'accès API
        Logger::info('API endpoint accédé', [
            'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
    }
    
    /**
     * Initialisation pour les pages de jeu
     */
    public static function gamePage($gameId = null) {
        // Initialisation de base pour une page protégée
        self::protectedPage();
        
        // Vérifications spécifiques au jeu
        if ($gameId) {
            $gameDb = gameDb();
            
            // Vérifier que la partie existe
            $game = $gameDb->getGameInfo($gameId);
            if (!$game) {
                Security::redirect(getUrl('pages/dashboard.php'));
            }
            
            // Vérifier que l'utilisateur participe à la partie
            if (!$gameDb->isUserInGame($_SESSION['user_id'], $gameId)) {
                Security::redirect(getUrl('pages/dashboard.php'));
            }
            
            // Stocker l'ID de la partie dans la session
            $_SESSION['current_game_id'] = $gameId;
        }
        
        Logger::info('Page de jeu accédée', [
            'game_id' => $gameId,
            'user_id' => $_SESSION['user_id']
        ]);
    }
}

// Fonction pour nettoyer la sortie en cas d'erreur
function cleanOutput() {
    if (ob_get_level()) {
        ob_end_clean();
    }
}

// Fonction pour terminer proprement
function gracefulShutdown() {
    // Nettoyer les sessions temporaires
    if (isset($_SESSION['temp_data'])) {
        unset($_SESSION['temp_data']);
    }
    
    // Vider le buffer de sortie si nécessaire
    if (ob_get_level()) {
        ob_end_flush();
    }
}

// Enregistrer la fonction de nettoyage
register_shutdown_function('gracefulShutdown');

// ===== VARIABLES GLOBALES UTILES =====

// Informations utilisateur courantes (si connecté)
$currentUser = null;
if (Security::checkAuthentication(false)) {
    $currentUser = [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'avatar' => $_SESSION['avatar'] ?? DEFAULT_AVATAR,
        'role' => $_SESSION['role'] ?? 'joueur'
    ];
}

// Token CSRF pour les formulaires
$csrfToken = Security::generateCSRFToken();

// URL de base pour les assets
$assetsUrl = getAssetUrl();
$pagesUrl = getPageUrl();

// ===== FONCTIONS DE HELPER POUR LES VUES =====

/**
 * Inclure un template avec des variables
 */
function includeTemplate($template, $variables = []) {
    extract($variables);
    $templatePath = BASE_PATH . 'templates/' . $template . '.php';
    
    if (file_exists($templatePath)) {
        include $templatePath;
    } else {
        Logger::error("Template non trouvé: $template");
        echo "<!-- Template $template non trouvé -->";
    }
}

/**
 * Générer les méta-tags de base
 */
function generateMeta($title = null, $description = null, $keywords = null) {
    $siteName = t('site_title', 'The Mind');
    $fullTitle = $title ? "$title - $siteName" : $siteName;
    
    echo "<title>" . htmlspecialchars($fullTitle) . "</title>\n";
    echo "<meta charset=\"UTF-8\">\n";
    echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
    
    if ($description) {
        echo "<meta name=\"description\" content=\"" . htmlspecialchars($description) . "\">\n";
    }
    
    if ($keywords) {
        echo "<meta name=\"keywords\" content=\"" . htmlspecialchars($keywords) . "\">\n";
    }
    
    // Méta-tags de sécurité
    echo "<meta http-equiv=\"X-Content-Type-Options\" content=\"nosniff\">\n";
    echo "<meta http-equiv=\"X-Frame-Options\" content=\"DENY\">\n";
    echo "<meta http-equiv=\"X-XSS-Protection\" content=\"1; mode=block\">\n";
}

/**
 * Inclure les CSS communs
 */
function includeCommonCSS() {
    $cssFiles = [
        'style.css',
        'styleMenu.css'
    ];
    
    foreach ($cssFiles as $cssFile) {
        echo "<link rel=\"stylesheet\" href=\"" . getAssetUrl("css/$cssFile") . "\">\n";
    }
}

/**
 * Inclure les JS communs
 */
function includeCommonJS() {
    $jsFiles = [
        'jsUtils.js',
        'jsMenu.js'
    ];
    
    foreach ($jsFiles as $jsFile) {
        echo "<script src=\"" . getAssetUrl("js/$jsFile") . "\"></script>\n";
    }
}

/**
 * Générer les variables JavaScript globales
 */
function generateJSGlobals() {
    global $csrfToken, $currentUser;
    
    $globals = [
        'SITE_URL' => SITE_URL,
        'ASSETS_URL' => ASSETS_PATH,
        'PAGES_URL' => PAGES_PATH,
        'CSRF_TOKEN' => $csrfToken,
        'CURRENT_USER' => $currentUser,
        'LANGUAGE' => Language::getCurrentLanguage(),
        'TEXTS' => Language::getAll()
    ];
    
    echo "<script>\n";
    echo "window.THEMIND = " . json_encode($globals, JSON_UNESCAPED_UNICODE) . ";\n";
    echo "</script>\n";
}

// ===== HELPERS POUR LA GESTION DES ERREURS =====

/**
 * Afficher un message flash
 */
function showFlashMessage() {
    $messages = ['success_message', 'error_message', 'info_message', 'warning_message'];
    
    foreach ($messages as $messageType) {
        if (isset($_SESSION[$messageType])) {
            $message = $_SESSION[$messageType];
            unset($_SESSION[$messageType]);
            
            $class = str_replace('_message', '', $messageType);
            echo "<div class=\"flash-message flash-$class\">$message</div>\n";
        }
    }
}

/**
 * Définir un message flash
 */
function setFlashMessage($message, $type = 'info') {
    $_SESSION[$type . '_message'] = $message;
}

// Log de l'initialisation
Logger::debug('Application initialisée', [
    'page' => $_SERVER['REQUEST_URI'] ?? '',
    'user_id' => $_SESSION['user_id'] ?? null,
    'language' => Language::getCurrentLanguage()
]);

// Marquer l'initialisation comme terminée
define('THEMIND_INITIALIZED', true);
?>