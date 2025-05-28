<?php
/**
 * Constantes globales de l'application The Mind
 */

// === CHEMINS ET URLS ===
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', '/regen_Proj/');
define('ASSETS_URL', BASE_URL . 'assets/');
define('API_URL', BASE_URL . 'api/');
define('PAGES_URL', BASE_URL . 'pages/');

// === PARAMÈTRES DE JEU ===
define('MAX_LEVEL', 12);
define('MIN_PLAYERS', 2);
define('MAX_PLAYERS', 4);
define('CARDS_MIN', 1);
define('CARDS_MAX', 100);

// === STATUTS DE PARTIE ===
define('GAME_STATUS_WAITING', 'en_attente');
define('GAME_STATUS_PLAYING', 'en_cours');
define('GAME_STATUS_PAUSED', 'pause');
define('GAME_STATUS_FINISHED', 'terminee');
define('GAME_STATUS_WON', 'gagnee');
define('GAME_STATUS_CANCELLED', 'annulee');
define('GAME_STATUS_LEVEL_COMPLETE', 'niveau_termine');

// === RÔLES UTILISATEUR ===
define('ROLE_ADMIN', 'admin');
define('ROLE_PLAYER', 'joueur');

// === ÉTATS DES CARTES ===
define('CARD_STATE_IN_HAND', 'en_main');
define('CARD_STATE_PLAYED', 'jouee');
define('CARD_STATE_DISCARDED', 'defaussee');

// === TYPES D'ACTIONS ===
define('ACTION_PLAY_CARD', 'jouer_carte');
define('ACTION_USE_SHURIKEN', 'utiliser_shuriken');
define('ACTION_LOSE_LIFE', 'perdre_vie');
define('ACTION_NEW_LEVEL', 'nouveau_niveau');
define('ACTION_END_GAME', 'fin_partie');

// === NIVEAUX DE DIFFICULTÉ ===
define('DIFFICULTY_EASY', 'facile');
define('DIFFICULTY_MEDIUM', 'moyen');
define('DIFFICULTY_HARD', 'difficile');

// === TYPES DE PARTIE ===
define('GAME_TYPE_PUBLIC', 0);
define('GAME_TYPE_PRIVATE', 1);

// === BONUS DE NIVEAU ===
// Niveaux où on gagne une vie supplémentaire
define('BONUS_LIFE_LEVELS', [3, 6, 9]);
// Niveaux où on gagne un shuriken supplémentaire
define('BONUS_SHURIKEN_LEVELS', [2, 5, 8]);

// === CONFIGURATION DES VIES ===
define('LIVES_2_PLAYERS', 3);
define('LIVES_3_4_PLAYERS', 2);
define('BONUS_LIFE_EASY', 1);
define('MALUS_LIFE_HARD', 1);

// === CONFIGURATION DES SHURIKENS ===
define('INITIAL_SHURIKENS', 1);

// === LANGUES SUPPORTÉES ===
define('SUPPORTED_LANGUAGES', ['fr', 'en']);
define('DEFAULT_LANGUAGE', 'fr');

// === CONFIGURATION DES SONS ===
define('DEFAULT_VOLUME', 50);

// === MESSAGES D'ERREUR COURANTS ===
define('ERROR_UNAUTHORIZED', 'unauthorized');
define('ERROR_INVALID_CSRF', 'invalid_csrf_token');
define('ERROR_INVALID_PARAMS', 'invalid_parameters');
define('ERROR_GAME_NOT_FOUND', 'game_not_found');
define('ERROR_SERVER_ERROR', 'server_error');

// === CODES DE RÉPONSE HTTP ===
define('HTTP_OK', 200);
define('HTTP_BAD_REQUEST', 400);
define('HTTP_UNAUTHORIZED', 401);
define('HTTP_FORBIDDEN', 403);
define('HTTP_NOT_FOUND', 404);
define('HTTP_METHOD_NOT_ALLOWED', 405);
define('HTTP_INTERNAL_ERROR', 500);

// === CONFIGURATION DES SESSIONS ===
define('SESSION_LIFETIME', 3600); // 1 heure en secondes
define('SESSION_REGENERATION_TIME', 300); // 5 minutes

// === CONFIGURATION DES MISES À JOUR AUTOMATIQUES ===
define('GAME_REFRESH_INTERVAL', 3000); // 3 secondes en millisecondes
define('DASHBOARD_REFRESH_INTERVAL', 30000); // 30 secondes

// === PERMISSIONS ===
define('PERMISSION_ADMIN_PARTIE', 'admin_partie');
define('PERMISSION_CREATE_USER', 'create_user');
define('PERMISSION_DELETE_USER', 'delete_user');
define('PERMISSION_EDIT_USER', 'edit_user');
?>