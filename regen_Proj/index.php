<?php
/**
 * Page d'accueil et de connexion - The Mind
 * Point d'entrée principal de l'application
 */

// Inclusion des fichiers de configuration
require_once 'config/constants.php';
require_once 'config/session.php';
require_once 'config/database.php';

// Démarrage de la session
SessionManager::start();

// Si l'utilisateur est déjà connecté, rediriger vers le dashboard
if (SessionManager::isLoggedIn()) {
    header('Location: pages/dashboard.php');
    exit();
}

// Gestion de la langue
$language = SessionManager::get('language', 'fr');
$supported_languages = ['fr', 'en'];
if (!in_array($language, $supported_languages)) {
    $language = 'fr';
}

// Gestion du paramètre de langue dans l'URL
if (isset($_GET['lang']) && in_array($_GET['lang'], $supported_languages)) {
    $language = $_GET['lang'];
    SessionManager::set('language', $language);
}

// Inclusion des textes avec vérification
$texts = [];
$language_file = "languages/{$language}.php";
if (file_exists($language_file)) {
    require_once $language_file;
} else {
    // Fallback vers le français si le fichier n'existe pas
    if (file_exists('languages/fr.php')) {
        require_once 'languages/fr.php';
    } else {
        // Textes par défaut si aucun fichier de langue n'existe
        $texts = [
            'site_title' => 'The Mind - Jeu en ligne',
            'login_title' => 'THE MIND',
            'login_player' => 'JOUEUR',
            'login_secret_code' => 'CODE SECRET',
            'login_button' => 'ENTRER DANS L\'ESPRIT',
            'error_message_form_validation' => 'Erreur de validation du formulaire.',
            'error_message_incorrect_credentials' => 'Nom d\'utilisateur ou mot de passe incorrect.',
            'error_message_connection_error' => 'Une erreur s\'est produite lors de la connexion.',
            'error_message_required_fields' => 'Veuillez remplir tous les champs requis.'
        ];
    }
}

// Variables pour le formulaire
$error_message = '';
$username = '';

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification CSRF
    if (!SessionManager::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = $texts['error_message_form_validation'];
    } 
    // Vérification des champs
    elseif (!empty($_POST['username']) && !empty($_POST['password'])) {
        $input_username = trim($_POST['username']);
        $input_password = $_POST['password'];
        $username = htmlspecialchars($input_username); // Pour réafficher en cas d'erreur
        
        try {
            // Récupération de la connexion à la base de données
            $conn = Database::getInstance()->getConnection();
            
            // Recherche de l'utilisateur
            $stmt = $conn->prepare("SELECT u.*, r.nom as role_nom FROM Utilisateurs u 
                                   JOIN Roles r ON u.id_role = r.id 
                                   WHERE u.identifiant = :username");
            $stmt->bindParam(':username', $input_username, PDO::PARAM_STR);
            $stmt->execute();
            
            if ($user = $stmt->fetch()) {
                // Vérification du mot de passe
                $password_verified = false;
                
                // Support des mots de passe hachés et en texte brut
                if (substr($user['mdp'], 0, 1) === '$') {
                    $password_verified = password_verify($input_password, $user['mdp']);
                } else {
                    $password_verified = ($input_password === $user['mdp']);
                }
                
                if ($password_verified) {
                    // Connexion réussie
                    SessionManager::login($user);
                    
                    // Redirection vers le dashboard
                    header('Location: pages/dashboard.php');
                    exit();
                } else {
                    $error_message = $texts['error_message_incorrect_credentials'];
                    // Délai pour ralentir les tentatives de force brute
                    sleep(1);
                }
            } else {
                $error_message = $texts['error_message_incorrect_credentials'];
                // Délai pour ralentir les tentatives de force brute
                sleep(1);
            }
        } catch (PDOException $e) {
            $error_message = $texts['error_message_connection_error'];
            error_log("Erreur de connexion: " . $e->getMessage());
        }
    } else {
        $error_message = $texts['error_message_required_fields'];
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $language ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $texts['site_title'] ?> - <?= $texts['login_button'] ?></title>
    <meta name="description" content="Connexion au jeu The Mind - Jeu de cartes coopératif en ligne">
    <meta name="csrf-token" content="<?= SessionManager::getCSRFToken() ?>">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
    
    <!-- Styles -->
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components/buttons.css">
    <link rel="stylesheet" href="assets/css/components/forms.css">
    <link rel="stylesheet" href="assets/css/login.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
</head>
<body class="login-page">
    <!-- Sélecteur de langue -->
    <div class="login__language">
        <select id="languageSelect" onchange="changeLanguage(this.value)">
            <option value="fr" <?= $language === 'fr' ? 'selected' : '' ?>>🇫🇷 Français</option>
            <option value="en" <?= $language === 'en' ? 'selected' : '' ?>>🇬🇧 English</option>
        </select>
    </div>

    <!-- Container principal de connexion -->
    <div class="login">
        <!-- Particules d'arrière-plan -->
        <div class="login__particles">
            <div class="login__particle" style="left: 10%; animation-delay: 0s;"></div>
            <div class="login__particle" style="left: 20%; animation-delay: 1s;"></div>
            <div class="login__particle" style="left: 30%; animation-delay: 2s;"></div>
            <div class="login__particle" style="left: 40%; animation-delay: 3s;"></div>
            <div class="login__particle" style="left: 50%; animation-delay: 4s;"></div>
            <div class="login__particle" style="left: 60%; animation-delay: 5s;"></div>
            <div class="login__particle" style="left: 70%; animation-delay: 1.5s;"></div>
            <div class="login__particle" style="left: 80%; animation-delay: 2.5s;"></div>
            <div class="login__particle" style="left: 90%; animation-delay: 3.5s;"></div>
        </div>
        
        <!-- Container du formulaire -->
        <div class="login__container">
            <!-- Logo/Icône du jeu -->
            <div class="login__logo">
                <div class="card-glow pulse">
                    <div class="card-number">42</div>
                </div>
            </div>
            
            <!-- Titre principal -->
            <h1 class="login__title"><?= $texts['login_title'] ?></h1>
            
            <!-- Message d'erreur -->
            <?php if (!empty($error_message)): ?>
                <div class="login__error" role="alert">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>
            
            <!-- Formulaire de connexion -->
            <form class="login__form" method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" novalidate>
                <!-- Jeton CSRF -->
                <input type="hidden" name="csrf_token" value="<?= SessionManager::getCSRFToken() ?>">
                
                <!-- Champ nom d'utilisateur -->
                <div class="form-group">
                    <label for="username" class="form-label"><?= $texts['login_player'] ?></label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        class="form-input" 
                        value="<?= htmlspecialchars($username) ?>"
                        required 
                        autocomplete="username"
                        placeholder="Votre identifiant"
                        autofocus
                    >
                </div>
                
                <!-- Champ mot de passe -->
                <div class="form-group">
                    <label for="password" class="form-label"><?= $texts['login_secret_code'] ?></label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-input" 
                        required 
                        autocomplete="current-password"
                        placeholder="Votre mot de passe"
                    >
                    <!-- Indicateur de Caps Lock -->
                    <div id="capsLockWarning" class="login__capslock">
                        Verrouillage majuscules activé
                    </div>
                </div>
                
                <!-- Bouton de connexion -->
                <button type="submit" class="btn btn--primary btn--submit">
                    <span><?= $texts['login_button'] ?></span>
                </button>
            </form>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="assets/js/main.js"></script>
    <script src="assets/js/login.js"></script>
    
    <script>
        // Changement de langue
        function changeLanguage(lang) {
            if (['fr', 'en'].includes(lang)) {
                TheMind.utils.fetchAPI('api/common/save-preferences.php', {
                    method: 'POST',
                    body: `preference=language&value=${lang}`
                }).then(() => {
                    window.location.reload();
                }).catch(() => {
                    // Fallback: utiliser une URL avec paramètre
                    window.location.href = `?lang=${lang}`;
                });
            }
        }
        
        // Gestion du changement de langue via URL
        const urlParams = new URLSearchParams(window.location.search);
        const langParam = urlParams.get('lang');
        if (langParam && ['fr', 'en'].includes(langParam)) {
            document.getElementById('languageSelect').value = langParam;
        }
        
        // Détection Caps Lock
        document.getElementById('password').addEventListener('keydown', function(e) {
            const capsLockWarning = document.getElementById('capsLockWarning');
            if (e.getModifierState && e.getModifierState('CapsLock')) {
                capsLockWarning.classList.add('show');
            } else {
                capsLockWarning.classList.remove('show');
            }
        });
        
        // Cacher l'avertissement Caps Lock lors du focus out
        document.getElementById('password').addEventListener('blur', function() {
            document.getElementById('capsLockWarning').classList.remove('show');
        });
        
        // Animation d'entrée
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.login__container').classList.add('fade-in');
            
            // Préremplir le nom d'utilisateur si il y a eu une erreur
            <?php if (!empty($username)): ?>
            document.getElementById('password').focus();
            <?php endif; ?>
        });
    </script>
</body>
</html>