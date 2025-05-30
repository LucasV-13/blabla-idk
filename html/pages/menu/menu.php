<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Vérification de l'authentification et expiration de session
if (!isset($_SESSION['user_id']) || (isset($_SESSION['expires']) && $_SESSION['expires'] < time())) {
    // Détruire la session si elle a expiré
    session_destroy();
    header("Location: ../index.php");
    exit();
}

// Prolonger la session d'une heure
$_SESSION['expires'] = time() + (60 * 60);

// Les variables suivantes devraient déjà être définies dans la session
$user_id = $_SESSION['user_id'];
$username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Invité';
$role = isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : 'joueur';
$email = isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : '';
$avatar = isset($_SESSION['avatar']) ? htmlspecialchars($_SESSION['avatar']) : '👤';

// Protection CSRF si pas déjà défini
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Gestion de la langue
$language = isset($_SESSION['language']) ? $_SESSION['language'] : 'fr';
$available_languages = ['fr' => 'Français', 'en' => 'English'];

// Chargement des textes traduits - CHEMIN CORRIGÉ
// Puisque tous les dossiers sont à la racine, nous utilisons un chemin direct
if (file_exists('languages/' . $language . '.php')) {
    include '../languages/' . $language . '.php';
} else {
    // Textes par défaut en cas d'erreur de chargement
    $texts = [
        'site_title' => 'The Mind - Jeu en ligne',
        'dashboard' => 'Tableau de bord',
        'rules' => 'Règles du jeu',
        'settings' => 'Paramètres',
        'volume' => 'Volume',
        'language' => 'Langue',
        'access_profile' => 'Accéder au Profil',
        'logout' => 'Déconnexion',
        'rules_title' => 'Règles de The Mind',
        'game_objective_title' => 'Objectif du Jeu',
        'game_objective_content' => 'Jouer des cartes de 1 à 100 dans l\'ordre croissant sans communication verbale.',
        'box_content_title' => 'Contenu de la Boîte',
        'numbered_cards' => '100 cartes numérotées',
        'special_cards' => 'Cartes Niveau, Vies (lapins), Shurikens (étoiles)',
        'setup_title' => 'Mise en Place',
        'setup_intro' => 'Distribuez Vies et Shurikens :',
        'setup_3_4_players' => '2 Vies & 1 Shuriken (3-4 joueurs)',
        'setup_2_players' => '3 Vies & 1 Shuriken (2 joueurs)',
        'turn_title' => 'Déroulement d\'un Tour',
        'turn_content' => 'Silence total, jouez vos cartes quand vous le sentez. Perdez une Vie en cas d\'erreur.',
        'shuriken_title' => 'Pouvoir Spécial : Le Shuriken',
        'shuriken_content' => 'Permet à chaque joueur de défausser sa plus petite carte si tout le monde est d\'accord.',
        'rewards_title' => 'Récompenses',
        'rewards_content' => 'Récompenses à gagner en progressant dans les niveaux.'
    ];
    error_log("Fichier de langue non trouvé : languages/{$language}.php");
}

// Vérifier si l'utilisateur a des permissions spécifiques
$user_permissions = [];

try {
    // Vérification si $conn existe déjà, sinon inclure le fichier de connexion
    if (!isset($conn) || !$conn) {
        include('../connexion/connexion.php');
    }
    
    $sql = "SELECT Permissions.nom FROM Permissions 
            JOIN Roles_Permissions ON Permissions.id = Roles_Permissions.id_permissions 
            JOIN Utilisateurs ON Utilisateurs.id_role = Roles_Permissions.id_role 
            WHERE Utilisateurs.id = :user_id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $user_permissions[] = $row['nom'];
    }
} catch (PDOException $e) {
    error_log("Erreur menu.php: " . $e->getMessage());
    // Continuer sans les permissions si une erreur survient
}

// Définir l'URL de base du site (à adapter selon votre configuration)
$site_url = "/html/"; // Remplacez par l'URL de votre site
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $texts['site_title']; ?></title>
    <link rel="stylesheet" href="style/styleMenu.css">
    <style>
        /* Ajout minimal pour le titre THE MIND */
        .site-title {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            font-family: 'Orbitron', sans-serif;
            font-size: 24px;
            font-weight: bold;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 3px;
            pointer-events: none; /* Pour éviter d'interférer avec les clics */
            z-index: 1;
        }
        
        /* Juste s'assurer que le header peut contenir le titre */
        .header {
            position: relative;
        }
    </style>
</head>
<body>
    <!-- Header avec boutons -->
    <div class="header">
        <a href="<?php echo $site_url; ?>pages/dashboard.php" class="header-btn" id="dashboardBtn" aria-label="<?php echo $texts['dashboard']; ?>">
            <span class="header-icon" aria-hidden="true">🏠</span>
        </a>
        <button class="header-btn" id="rulesBtn" aria-label="<?php echo $texts['rules']; ?>">
            <span class="header-icon" aria-hidden="true">📜</span>
        </button>
        <button class="header-btn" id="settingsBtn" aria-label="<?php echo $texts['settings']; ?>">
            <span class="header-icon" aria-hidden="true">⚙️</span>
        </button>
        
        <!-- Titre THE MIND centré -->
        <div class="site-title">THE MIND</div>
        
        <div class="user-display">
            <span class="user-avatar-small"><?php echo $avatar; ?></span>
            <span class="username"><?php echo $username; ?></span>
        </div>
    </div>
    
    <!-- Le contenu principal de la page serait ici -->

    <!-- Settings Modal -->
    <div id="settingsModal" class="modal settings-modal">
        <div class="modal-content">
            <span class="close" id="closeSettings">&times;</span>
            <div class="user-info-small">
                <div class="user-avatar-small"><?php echo $avatar; ?></div>
                <div class="user-details">
                <strong><?php echo $username; ?></strong><br>
                <small><?php echo $email; ?></small>
                </div>
            </div>
            
            <div class="volume-control">
                <label for="volumeSlider"><?php echo $texts['volume']; ?> :</label>
                <input type="range" id="volumeSlider" min="0" max="100" value="<?php echo isset($_SESSION['volume']) ? $_SESSION['volume'] : 50; ?>">
            </div>
            
            <div class="language-selector">
                <label for="languageSelect"><?php echo $texts['language']; ?> :</label>
                <select id="languageSelect">
                    <?php foreach ($available_languages as $code => $name): ?>
                        <option value="<?php echo $code; ?>" <?php echo $language == $code ? 'selected' : ''; ?>><?php echo $name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <a href="<?php echo $site_url; ?>pages/profil/profil.php">
                <button class="settings-btn" id="profileBtnModal"><?php echo $texts['access_profile']; ?></button>
            </a>
            
            <hr>
            
            <form method="post" action="<?php echo $site_url; ?>/pages/logout.php" id="logoutForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <button type="submit" class="logout-btn"><?php echo $texts['logout']; ?></button>
            </form>
        </div>
    </div>
    
    <!-- Rules Modal -->
    <div id="rulesModal" class="modal rules-modal">
        <div class="modal-content">
            <span class="close" id="closeRules">&times;</span>
            <h2><?php echo $texts['rules_title']; ?></h2>
            
            <div class="rule-section">
                <div class="rule-title">
                    <i>🎯</i><?php echo $texts['game_objective_title']; ?>
                </div>
                <div class="rule-content">
                    <?php echo $texts['game_objective_content']; ?>
                </div>
            </div>
            
            <div class="rule-section">
                <div class="rule-title">
                    <i>🃏</i><?php echo $texts['box_content_title']; ?>
                </div>
                <div class="rule-content">
                    <ul>
                        <li><?php echo $texts['numbered_cards']; ?></li>
                        <li><?php echo $texts['special_cards']; ?></li>
                    </ul>
                </div>
            </div>
            
            <div class="rule-section">
                <div class="rule-title">
                    <i>👥</i><?php echo $texts['setup_title']; ?>
                </div>
                <div class="rule-content">
                    <?php echo $texts['setup_intro']; ?>
                    <ul>
                        <li><?php echo $texts['setup_3_4_players']; ?></li>
                        <li><?php echo $texts['setup_2_players']; ?></li>
                    </ul>
                </div>
            </div>
            
            <div class="rule-section">
                <div class="rule-title">
                    <i>✏️</i><?php echo $texts['turn_title']; ?>
                </div>
                <div class="rule-content">
                    <?php echo $texts['turn_content']; ?>
                </div>
            </div>
            
            <div class="rule-section">
                <div class="rule-title">
                    <i>⭐</i><?php echo $texts['shuriken_title']; ?>
                </div>
                <div class="rule-content">
                    <?php echo $texts['shuriken_content']; ?>
                </div>
            </div>
            
            <div class="rule-section">
                <div class="rule-title">
                    <i>🎁</i><?php echo $texts['rewards_title']; ?>
                </div>
                <div class="rule-content">
                    <?php echo $texts['rewards_content']; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Champ caché pour le jeton CSRF (pour JS) -->
    <input type="hidden" id="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    <input type="hidden" id="base_url" value="<?php echo $site_url; ?>">
    
    <script src="<?php echo $site_url; ?>assets/js/jsMenu.js"></script>
</body>
</html>