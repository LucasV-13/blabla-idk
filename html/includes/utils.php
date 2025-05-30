<?php
/**
 * Fonctions utilitaires communes pour The Mind
 */

// Empêcher l'accès direct
if (!defined('CONFIG_LOADED')) {
    die('Accès direct interdit');
}

/**
 * Classe utilitaire générale
 */
class Utils {
    
    /**
     * Formater une date selon la locale
     */
    public static function formatDate($date, $format = 'Y-m-d H:i:s', $locale = 'fr') {
        if (is_string($date)) {
            $date = new DateTime($date);
        }
        
        if ($locale === 'fr') {
            $months = [
                1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
                5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
                9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre'
            ];
            
            $days = [
                'Monday' => 'lundi', 'Tuesday' => 'mardi', 'Wednesday' => 'mercredi',
                'Thursday' => 'jeudi', 'Friday' => 'vendredi', 'Saturday' => 'samedi',
                'Sunday' => 'dimanche'
            ];
            
            $formatted = $date->format($format);
            $formatted = str_replace(array_keys($days), array_values($days), $formatted);
            $formatted = str_replace(array_keys($months), array_values($months), $formatted);
            
            return $formatted;
        }
        
        return $date->format($format);
    }
    
    /**
     * Calculer le temps écoulé depuis une date
     */
    public static function timeAgo($datetime, $language = 'fr') {
        $time = time() - strtotime($datetime);
        
        $units = [
            31536000 => ['fr' => 'an', 'en' => 'year'],
            2592000 => ['fr' => 'mois', 'en' => 'month'],
            604800 => ['fr' => 'semaine', 'en' => 'week'],
            86400 => ['fr' => 'jour', 'en' => 'day'],
            3600 => ['fr' => 'heure', 'en' => 'hour'],
            60 => ['fr' => 'minute', 'en' => 'minute'],
            1 => ['fr' => 'seconde', 'en' => 'second']
        ];
        
        foreach ($units as $unit => $names) {
            if ($time < $unit) continue;
            $numberOfUnits = floor($time / $unit);
            $unitName = $names[$language];
            
            if ($language === 'fr') {
                return 'il y a ' . $numberOfUnits . ' ' . $unitName . ($numberOfUnits > 1 ? 's' : '');
            } else {
                return $numberOfUnits . ' ' . $unitName . ($numberOfUnits > 1 ? 's' : '') . ' ago';
            }
        }
        
        return $language === 'fr' ? 'à l\'instant' : 'just now';
    }
    
    /**
     * Générer un mot de passe aléatoire
     */
    public static function generatePassword($length = 12, $includeSpecial = true) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        if ($includeSpecial) {
            $chars .= '!@#$%^&*()_+-=[]{}|;:,.<>?';
        }
        
        $password = '';
        $charsLength = strlen($chars);
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $charsLength - 1)];
        }
        
        return $password;
    }
    
    /**
     * Générer un nom d'utilisateur unique
     */
    public static function generateUsername($prefix = 'user') {
        $adjectives = [
            'super', 'grand', 'petit', 'rapide', 'fort', 'brave', 
            'vif', 'agile', 'sympa', 'cool', 'smart', 'pro', 
            'top', 'zen', 'tech', 'mega', 'ultra', 'hyper'
        ];
        
        $nouns = [
            'joueur', 'hero', 'ninja', 'panda', 'aigle', 'tigre', 
            'lion', 'loup', 'ours', 'robot', 'pilote', 'agent', 
            'gamer', 'master', 'expert', 'champion', 'star', 'ace'
        ];
        
        $adjective = $adjectives[array_rand($adjectives)];
        $noun = $nouns[array_rand($nouns)];
        $number = random_int(100, 999);
        
        return $prefix . '_' . $adjective . $noun . $number;
    }
    
    /**
     * Valider différents types de données
     */
    public static function validate($value, $type, $options = []) {
        switch ($type) {
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
                
            case 'url':
                return filter_var($value, FILTER_VALIDATE_URL) !== false;
                
            case 'int':
                $min = $options['min'] ?? null;
                $max = $options['max'] ?? null;
                $int = filter_var($value, FILTER_VALIDATE_INT);
                if ($int === false) return false;
                if ($min !== null && $int < $min) return false;
                if ($max !== null && $int > $max) return false;
                return true;
                
            case 'float':
                $min = $options['min'] ?? null;
                $max = $options['max'] ?? null;
                $float = filter_var($value, FILTER_VALIDATE_FLOAT);
                if ($float === false) return false;
                if ($min !== null && $float < $min) return false;
                if ($max !== null && $float > $max) return false;
                return true;
                
            case 'string':
                $minLength = $options['min_length'] ?? 0;
                $maxLength = $options['max_length'] ?? PHP_INT_MAX;
                $length = strlen($value);
                return $length >= $minLength && $length <= $maxLength;
                
            case 'username':
                return preg_match('/^[a-zA-Z0-9_-]{3,20}$/', $value);
                
            case 'password':
                $minLength = $options['min_length'] ?? PASSWORD_MIN_LENGTH;
                return strlen($value) >= $minLength;
                
            default:
                return false;
        }
    }
    
    /**
     * Convertir une taille en octets en format lisible
     */
    public static function formatBytes($size, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Tronquer un texte avec des points de suspension
     */
    public static function truncate($text, $length = 100, $suffix = '...') {
        if (strlen($text) <= $length) {
            return $text;
        }
        
        return substr($text, 0, $length - strlen($suffix)) . $suffix;
    }
    
    /**
     * Créer une pagination
     */
    public static function paginate($totalItems, $itemsPerPage, $currentPage = 1) {
        $totalPages = ceil($totalItems / $itemsPerPage);
        $currentPage = max(1, min($currentPage, $totalPages));
        $offset = ($currentPage - 1) * $itemsPerPage;
        
        return [
            'total_items' => $totalItems,
            'items_per_page' => $itemsPerPage,
            'total_pages' => $totalPages,
            'current_page' => $currentPage,
            'offset' => $offset,
            'has_previous' => $currentPage > 1,
            'has_next' => $currentPage < $totalPages,
            'previous_page' => $currentPage > 1 ? $currentPage - 1 : null,
            'next_page' => $currentPage < $totalPages ? $currentPage + 1 : null
        ];
    }
    
    /**
     * Échapper les données pour JavaScript
     */
    public static function escapeJs($data) {
        return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    }
    
    /**
     * Générer un slug à partir d'une chaîne
     */
    public static function slug($text) {
        // Remplacer les caractères accentués
        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        // Convertir en minuscules
        $text = strtolower($text);
        // Remplacer tout ce qui n'est pas alphanumérique par des tirets
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        // Supprimer les tirets en début et fin
        $text = trim($text, '-');
        
        return $text;
    }
    
    /**
     * Vérifier si une chaîne est du JSON valide
     */
    public static function isJson($string) {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
    
    /**
     * Obtenir l'adresse IP réelle du client
     */
    public static function getRealIpAddress() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Créer un breadcrumb
     */
    public static function breadcrumb($items, $separator = ' > ') {
        $breadcrumb = [];
        foreach ($items as $item) {
            if (isset($item['url'])) {
                $breadcrumb[] = '<a href="' . $item['url'] . '">' . $item['title'] . '</a>';
            } else {
                $breadcrumb[] = $item['title'];
            }
        }
        
        return implode($separator, $breadcrumb);
    }
}

/**
 * Classe pour la gestion des logs
 */
class Logger {
    
    /**
     * Niveaux de log
     */
    const DEBUG = 'DEBUG';
    const INFO = 'INFO';
    const WARNING = 'WARNING';
    const ERROR = 'ERROR';
    
    /**
     * Écrire un log
     */
    public static function log($level, $message, $context = []) {
        if (!ENABLE_DEBUG_LOGS) {
            return;
        }
        
        $levels = [self::DEBUG => 0, self::INFO => 1, self::WARNING => 2, self::ERROR => 3];
        $currentLevel = $levels[LOG_LEVEL] ?? 1;
        
        if ($levels[$level] < $currentLevel) {
            return;
        }
        
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'ip' => Utils::getRealIpAddress(),
            'user_id' => $_SESSION['user_id'] ?? null
        ];
        
        $logFile = LOGS_PATH . 'app_' . date('Y-m-d') . '.log';
        $logLine = json_encode($logData, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        
        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
        
        // Rotation des logs si le fichier devient trop gros
        if (file_exists($logFile) && filesize($logFile) > MAX_LOG_SIZE) {
            $backupFile = $logFile . '.' . time();
            rename($logFile, $backupFile);
        }
    }
    
    /**
     * Log de debug
     */
    public static function debug($message, $context = []) {
        self::log(self::DEBUG, $message, $context);
    }
    
    /**
     * Log d'information
     */
    public static function info($message, $context = []) {
        self::log(self::INFO, $message, $context);
    }
    
    /**
     * Log d'avertissement
     */
    public static function warning($message, $context = []) {
        self::log(self::WARNING, $message, $context);
    }
    
    /**
     * Log d'erreur
     */
    public static function error($message, $context = []) {
        self::log(self::ERROR, $message, $context);
    }
}

/**
 * Classe pour la gestion des langues
 */
class Language {
    private static $texts = [];
    private static $currentLanguage = DEFAULT_LANGUAGE;
    
    /**
     * Charger une langue
     */
    public static function load($language = null) {
        if ($language === null) {
            $language = $_SESSION['language'] ?? DEFAULT_LANGUAGE;
        }
        
        if (!array_key_exists($language, AVAILABLE_LANGUAGES)) {
            $language = DEFAULT_LANGUAGE;
        }
        
        $languageFile = LANGUAGES_PATH . $language . '.php';
        
        if (file_exists($languageFile)) {
            include $languageFile;
            self::$texts = $texts ?? [];
            self::$currentLanguage = $language;
        } else {
            // Utiliser les textes par défaut
            $defaultTexts = DEFAULT_TEXTS[$language] ?? DEFAULT_TEXTS[DEFAULT_LANGUAGE];
            self::$texts = $defaultTexts;
        }
        
        return self::$texts;
    }
    
    /**
     * Obtenir un texte traduit
     */
    public static function get($key, $default = null) {
        return self::$texts[$key] ?? $default ?? $key;
    }
    
    /**
     * Obtenir la langue actuelle
     */
    public static function getCurrentLanguage() {
        return self::$currentLanguage;
    }
    
    /**
     * Obtenir tous les textes
     */
    public static function getAll() {
        return self::$texts;
    }
}

// ===== FONCTIONS UTILITAIRES GLOBALES =====

/**
 * Fonction raccourcie pour les logs
 */
function logInfo($message, $context = []) {
    Logger::info($message, $context);
}

function logError($message, $context = []) {
    Logger::error($message, $context);
}

function logDebug($message, $context = []) {
    Logger::debug($message, $context);
}

function logWarning($message, $context = []) {
    Logger::warning($message, $context);
}

/**
 * Fonction raccourcie pour la traduction
 */
function t($key, $default = null) {
    return Language::get($key, $default);
}

/**
 * Fonction pour formater une date
 */
function formatDate($date, $format = 'Y-m-d H:i:s') {
    return Utils::formatDate($date, $format, Language::getCurrentLanguage());
}

/**
 * Fonction pour le temps écoulé
 */
function timeAgo($datetime) {
    return Utils::timeAgo($datetime, Language::getCurrentLanguage());
}

/**
 * Fonction pour valider des données
 */
function validate($value, $type, $options = []) {
    return Utils::validate($value, $type, $options);
}

/**
 * Fonction pour tronquer du texte
 */
function truncate($text, $length = 100, $suffix = '...') {
    return Utils::truncate($text, $length, $suffix);
}

/**
 * Fonction pour échapper pour JavaScript
 */
function escapeJs($data) {
    return Utils::escapeJs($data);
}
?>