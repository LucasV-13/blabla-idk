<?php
/**
 * Configuration centrale de l'application The Mind
 * Ce fichier contient toutes les configurations de base du site
 */

// Empêcher l'accès direct au fichier
if (!defined('THEMIND_CONFIG')) {
    define('THEMIND_CONFIG', true);
}

// ===== CONFIGURATION DES CHEMINS =====
// Définir si le site est à la racine ou dans un sous-dossier
// Changer cette valeur selon votre installation :
// Pour une installation à la racine : '/'
// Pour une installation dans un sous-dossier : '/html/' ou '/votre-dossier/'
define('SITE_ROOT', '/html/');

// Chemins absolus basés sur la racine du site
define('SITE_URL', $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . SITE_ROOT);
define('BASE_PATH', __DIR__ . '/');

// Chemins relatifs pour les includes
define('INCLUDES_PATH', BASE_PATH . 'includes/');
define('ASSETS_PATH', SITE_ROOT . 'assets/');
define('PAGES_PATH', SITE_ROOT . 'pages/');
define('LANGUAGES_PATH', BASE_PATH . 'languages/');
define('LOGS_PATH', BASE_PATH . 'logs/');

// ===== CONFIGURATION DE LA BASE DE DONNÉES =====
define('DB_HOST', 'localhost');
define('DB_NAME', 'bddthemind');
define('DB_USER', 'user');
define('DB_PASS', 'Eloi2023*');
define('DB_CHARSET', 'utf8mb4');

// Options PDO par défaut
define('DB_OPTIONS', [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
]);

// ===== CONFIGURATION DES SESSIONS =====
define('SESSION_LIFETIME', 3600); // 1 heure en secondes
define('SESSION_NAME', 'THEMIND_SESSION');
define('CSRF_TOKEN_LENGTH', 32);

// ===== CONFIGURATION DE SÉCURITÉ =====
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes
define('PASSWORD_MIN_LENGTH', 6);
define('ENABLE_RATE_LIMITING', true);

// ===== CONFIGURATION DE L'APPLICATION =====
define('DEFAULT_LANGUAGE', 'fr');
define('AVAILABLE_LANGUAGES', ['fr' => 'Français', 'en' => 'English']);
define('DEFAULT_AVATAR', '👤');
define('MAX_PLAYERS_PER_GAME', 4);
define('MIN_PLAYERS_PER_GAME', 2);
define('MAX_GAME_LEVEL', 12);

// ===== CONFIGURATION DES LOGS =====
define('ENABLE_DEBUG_LOGS', true);
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR
define('MAX_LOG_SIZE', 10485760); // 10MB

// ===== FONCTIONS UTILITAIRES DE CONFIGURATION =====

/**
 * Obtenir l'URL complète d'un chemin relatif
 */
function getUrl($path = '') {
    return SITE_URL . ltrim($path, '/');
}

/**
 * Obtenir le chemin absolu d'un fichier
 */
function getPath($path = '') {
    return BASE_PATH . ltrim($path, '/');
}

/**
 * Obtenir le chemin des assets
 */
function getAssetUrl($asset = '') {
    return ASSETS_PATH . ltrim($asset, '/');
}

/**
 * Obtenir le chemin des pages
 */
function getPageUrl($page = '') {
    return PAGES_PATH . ltrim($page, '/');
}

/**
 * Vérifier si l'application est en mode debug
 */
function isDebugMode() {
    return ENABLE_DEBUG_LOGS && (LOG_LEVEL === 'DEBUG');
}

/**
 * Obtenir les informations de version
 */
function getAppInfo() {
    return [
        'name' => 'The Mind',
        'version' => '1.0.0',
        'author' => 'Développeur The Mind',
        'description' => 'Jeu en ligne The Mind',
        'site_root' => SITE_ROOT,
        'debug_mode' => isDebugMode()
    ];
}

// ===== CONFIGURATION SELON L'ENVIRONNEMENT =====
// Détecter l'environnement (développement, production)
$environment = 'development';
if (isset($_SERVER['HTTP_HOST'])) {
    if (strpos($_SERVER['HTTP_HOST'], 'localhost') === false && 
        strpos($_SERVER['HTTP_HOST'], '127.0.0.1') === false) {
        $environment = 'production';
    }
}

define('ENVIRONMENT', $environment);

// Configuration spécifique à l'environnement
if (ENVIRONMENT === 'production') {
    // Configuration de production
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
} else {
    // Configuration de développement
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    error_reporting(E_ALL);
}

// ===== AUTOLOADER SIMPLE =====
spl_autoload_register(function ($className) {
    $file = INCLUDES_PATH . 'classes/' . $className . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// ===== INITIALISATION =====
// Créer les dossiers nécessaires s'ils n'existent pas
$requiredDirs = [
    LOGS_PATH,
    INCLUDES_PATH,
    INCLUDES_PATH . 'classes/',
    BASE_PATH . 'uploads/',
    BASE_PATH . 'cache/'
];

foreach ($requiredDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// ===== CONSTANTES POUR LES TEXTES PAR DÉFAUT =====
define('DEFAULT_TEXTS', [
    'fr' => [
        'site_title' => 'The Mind - Jeu en ligne',
        'error_general' => 'Une erreur est survenue',
        'success_general' => 'Opération réussie',
        'loading' => 'Chargement...',
        'unauthorized' => 'Accès non autorisé'
    ],
    'en' => [
        'site_title' => 'The Mind - Online Game',
        'error_general' => 'An error occurred',
        'success_general' => 'Operation successful',
        'loading' => 'Loading...',
        'unauthorized' => 'Unauthorized access'
    ]
]);

// Marquer que la configuration est chargée
define('CONFIG_LOADED', true);
?>