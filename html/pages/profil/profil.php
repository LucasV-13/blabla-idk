<?php
session_start();

// Vérification de l'authentification et expiration de session
if (!isset($_SESSION['user_id']) || (isset($_SESSION['expires']) && $_SESSION['expires'] < time())) {
    // Détruire la session si elle a expiré
    session_destroy();
    header("Location: ../../index.php");
    exit();
}

// Prolonger la session d'une heure
$_SESSION['expires'] = time() + (60 * 60);

// Protection CSRF si pas déjà défini
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username']);
$role = htmlspecialchars($_SESSION['role']);
$email = htmlspecialchars($_SESSION['email']);
$avatar = htmlspecialchars($_SESSION['avatar']);

include('../../connexion/connexion.php');
include('../menu/menu.php');

// Vérifier si l'utilisateur est un administrateur
$estAdmin = (strtolower($role) === 'admin');

// Récupérer les statistiques de l'utilisateur
try {
    $statsSql = "SELECT statistiques.parties_jouees, statistiques.parties_gagnees, statistiques.taux_reussite, statistiques.cartes_jouees 
                FROM Statistiques WHERE statistiques.id_utilisateur = :user_id";
    $statsStmt = $conn->prepare($statsSql);
    $statsStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $statsStmt->execute();
    
    $stats = [
        'parties_jouees' => 0,
        'parties_gagnees' => 0,
        'taux_reussite' => 0,
        'cartes_jouees' => 0
    ];
    
    if ($statsRow = $statsStmt->fetch(PDO::FETCH_ASSOC)) {
        $stats['parties_jouees'] = $statsRow['parties_jouees'];
        $stats['parties_gagnees'] = $statsRow['parties_gagnees'];
        $stats['taux_reussite'] = $statsRow['taux_reussite'];
        $stats['cartes_jouees'] = $statsRow['cartes_jouees'];
    }
} catch (PDOException $e) {
    error_log("Erreur statistiques profil: " . $e->getMessage());
}

// Récupérer le niveau max atteint
try {
    $niveauMaxSql = "SELECT MAX(scores.niveau_max_atteint) as niveau_max FROM Scores WHERE scores.id_utilisateur = :user_id";
    $niveauMaxStmt = $conn->prepare($niveauMaxSql);
    $niveauMaxStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $niveauMaxStmt->execute();
    $niveauMax = 0;
    
    if ($niveauMaxRow = $niveauMaxStmt->fetch(PDO::FETCH_ASSOC)) {
        $niveauMax = $niveauMaxRow['niveau_max'] ?: 0;
    }
} catch (PDOException $e) {
    error_log("Erreur niveau max profil: " . $e->getMessage());
}

// Récupérer la liste des utilisateurs (pour les admins)
$utilisateurs = [];
if ($estAdmin) {
    try {
        $usersSql = "SELECT Utilisateurs.id, Utilisateurs.identifiant, Utilisateurs.mail, Utilisateurs.mdp, Roles.nom as role_nom 
                    FROM Utilisateurs 
                    JOIN Roles ON Utilisateurs.id_role = Roles.id
                    ORDER BY Utilisateurs.id";
        $usersStmt = $conn->prepare($usersSql);
        $usersStmt->execute();
        $utilisateurs = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur liste utilisateurs profil: " . $e->getMessage());
    }
}

// Récupérer les rôles disponibles (pour l'ajout d'utilisateur)
$roles = [];
if ($estAdmin) {
    try {
        $rolesSql = "SELECT id, nom FROM Roles";
        $rolesStmt = $conn->prepare($rolesSql);
        $rolesStmt->execute();
        $roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur récupération des rôles: " . $e->getMessage());
    }
}

// Textes pour l'internationalisation
$language = isset($_SESSION['language']) ? $_SESSION['language'] : 'fr';
if ($language === 'fr') {
    $texts = [
        'profile_title' => 'Profil Utilisateur - The Mind',
        'statistics' => 'Statistiques',
        'identifier' => 'Identifiant',
        'games_played' => 'Parties Jouées',
        'games_won' => 'Parties Gagnées',
        'success_rate' => 'Taux de réussite',
        'max_level' => 'Niveau Max atteint',
        'create_game' => 'Créer une partie',
        'admin_panel' => 'Panel Administrateur',
        'users' => 'Utilisateurs',
        'add_user' => 'Ajouter Utilisateur',
        'game_creation' => 'Création de Partie',
        'email' => 'Email',
        'username' => 'Identifiant',
        'password' => 'Mot de Passe',
        'confirm_password' => 'Confirmer le mot de passe',
        'role' => 'Rôle',
        'select_role' => 'Sélectionner un rôle',
        'avatar' => 'Avatar',
        'avatar_placeholder' => 'Emoji (ex: 👤, 🐱, etc.)',
        'actions' => 'Actions',
        'submit' => 'Ajouter',
        'game_name' => 'Nom de la partie',
        'enter_game_name' => 'Entrez un nom pour votre partie',
        'player_count' => 'Nombre de joueurs',
        'players' => 'joueurs',
        'difficulty' => 'Niveau de difficulté',
        'easy' => 'Facile',
        'medium' => 'Moyen',
        'hard' => 'Difficile',
        'privacy' => 'Confidentialité',
        'public_game' => 'Partie publique',
        'private_game' => 'Partie privée',
        'starting_level' => 'Niveau de départ',
        'level' => 'Niveau',
        'create_start_game' => 'Créer et démarrer la partie',
        'generate_random_username' => 'Générer un identifiant aléatoire'
    ];
} else {
    $texts = [
        'profile_title' => 'User Profile - The Mind',
        'statistics' => 'Statistics',
        'identifier' => 'Username',
        'games_played' => 'Games Played',
        'games_won' => 'Games Won',
        'success_rate' => 'Success Rate',
        'max_level' => 'Max Level Reached',
        'create_game' => 'Create Game',
        'admin_panel' => 'Admin Panel',
        'users' => 'Users',
        'add_user' => 'Add User',
        'game_creation' => 'Game Creation',
        'email' => 'Email',
        'username' => 'Username',
        'password' => 'Password',
        'confirm_password' => 'Confirm Password',
        'role' => 'Role',
        'select_role' => 'Select a role',
        'avatar' => 'Avatar',
        'avatar_placeholder' => 'Emoji (e.g.: 👤, 🐱, etc.)',
        'actions' => 'Actions',
        'submit' => 'Add',
        'game_name' => 'Game Name',
        'enter_game_name' => 'Enter a name for your game',
        'player_count' => 'Player count',
        'players' => 'players',
        'difficulty' => 'Difficulty level',
        'easy' => 'Easy',
        'medium' => 'Medium',
        'hard' => 'Hard',
        'privacy' => 'Privacy',
        'public_game' => 'Public game',
        'private_game' => 'Private game',
        'starting_level' => 'Starting level',
        'level' => 'Level',
        'create_start_game' => 'Create and start game',
        'generate_random_username' => 'Generate random username'
    ];
}

// Récupérer les messages de succès/erreur
$errorMessage = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
$successMessage = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
unset($_SESSION['error_message'], $_SESSION['success_message']);
?>

<!DOCTYPE html>
<html lang="<?php echo $language; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $texts['profile_title']; ?></title>
    <link rel="stylesheet" href="../../assets/css/styleProfil.css">
    <link rel="stylesheet" href="../../assets/css/styleMenu.css">
    <script src="../../assets/js/jsMenu.js"></script>
    <style>
        /* Styles supplémentaires */
        .action-buttons {
            margin-bottom: 20px;
            display: flex;
            justify-content: flex-end;
        }
        
        .action-btn {
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }
        
        .form-message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .success-message {
            background-color: rgba(76, 175, 80, 0.2);
            color: #4CAF50;
            border: 1px solid #4CAF50;
        }
        
        .error-message {
            background-color: rgba(244, 67, 54, 0.2);
            color: #f44336;
            border: 1px solid #f44336;
        }
        
        /* Animation pour les champs mis en évidence */
        @keyframes highlightField {
            0% { background-color: #333; }
            50% { background-color: rgba(0, 194, 203, 0.3); }
            100% { background-color: #333; }
        }
        
        .highlight {
            animation: highlightField 1.5s;
        }
        
        /* Amélioration du tableau des utilisateurs */
        .user-action-btn {
            background: none;
            border: none;
            color: #4CAF50;
            cursor: pointer;
            margin-right: 10px;
            font-size: 18px;
            transition: all 0.3s;
            padding: 5px;
            border-radius: 3px;
        }
        
        .user-action-btn:hover {
            background-color: rgba(255, 255, 255, 0.1);
            transform: scale(1.2);
        }
        
        .user-action-btn.edit:hover {
            color: var(--accent-color);
        }
        
        .user-action-btn.delete {
            color: #f44336;
        }
        
        .user-action-btn.delete:hover {
            color: #ff5252;
        }

        /* Flash messages */
        .flash-message {
            position: fixed;
            top: 100px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 5px;
            color: white;
            font-weight: bold;
            z-index: 9999;
            max-width: 400px;
            animation: slideIn 0.3s ease;
        }

        .flash-message.success {
            background-color: #4CAF50;
        }

        .flash-message.error {
            background-color: #f44336;
        }

        @keyframes slideIn {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }
    </style>
</head>
<body>
    <!-- Champ caché pour le jeton CSRF (pour JS) -->
    <input type="hidden" id="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    <input type="hidden" id="base_url" value="../../">

    <!-- Affichage des messages flash -->
    <?php if (!empty($successMessage)): ?>
    <div class="flash-message success" id="flash-success">
        <?php echo htmlspecialchars($successMessage); ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
    <div class="flash-message error" id="flash-error">
        <?php echo htmlspecialchars($errorMessage); ?>
    </div>
    <?php endif; ?>

    <!-- Main container -->
    <div class="main-container">
        <!-- User profile section -->
        <div class="profile-section">
            <div class="user-info">
                <div class="user-avatar"><?php echo $avatar; ?></div>
                <div class="user-name"><?php echo $username; ?></div>
                <div class="user-email"><?php echo $email; ?></div> 
            </div>

            <div class="stats-container">
                <h3 class="stats-heading"><?php echo $texts['statistics']; ?></h3>
                <div class="stat-item">
                    <span><?php echo $texts['identifier']; ?></span>
                    <span><?php echo $username; ?></span>
                </div>
                <div class="stat-item">
                    <span><?php echo $texts['games_played']; ?></span>
                    <span><?php echo $stats['parties_jouees']; ?></span>
                </div>
                <div class="stat-item">
                    <span><?php echo $texts['games_won']; ?></span>
                    <span><?php echo $stats['parties_gagnees']; ?></span>
                </div>
                <div class="stat-item">
                    <span><?php echo $texts['success_rate']; ?></span>
                    <span><?php echo $stats['taux_reussite']; ?>%</span>
                </div>
                <div class="stat-item">
                    <span><?php echo $texts['max_level']; ?></span>
                    <span><?php echo $niveauMax; ?></span>
                </div>
            </div>

            <?php if ($estAdmin): ?>
            <button class="create-game-btn" id="createGameBtn"><?php echo $texts['create_game']; ?></button>
            <?php endif; ?>
        </div>
        
        <?php if ($estAdmin): ?>
        <!-- Admin panel section -->
        <div class="admin-section">
            <div class="panel-heading">
                <?php echo $texts['admin_panel']; ?>
            </div>
            
            <!-- Onglets du panel admin -->
            <div class="panel-tabs">
                <div class="panel-tab active" id="usersTab"><?php echo $texts['users']; ?></div>
                <div class="panel-tab" id="addUserTab"><?php echo $texts['add_user']; ?></div>
                <div class="panel-tab" id="gameCreationTab"><?php echo $texts['game_creation']; ?></div>
            </div>
            
            <!-- Panel de la liste des utilisateurs -->
            <div class="panel-content" id="usersPanel">
                <table class="user-table">
                    <thead>
                        <tr>
                            <th><?php echo $texts['email']; ?></th>
                            <th><?php echo $texts['username']; ?></th>
                            <th><?php echo $texts['role']; ?></th>
                            <th><?php echo $texts['actions']; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($utilisateurs as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['mail']); ?></td>
                            <td><?php echo htmlspecialchars($user['identifiant']); ?></td>
                            <td><?php echo htmlspecialchars($user['role_nom']); ?></td>
                            <td>
                                <button class="user-action-btn edit" 
                                        data-id="<?php echo $user['id']; ?>" 
                                        title="Modifier cet utilisateur">✏️</button>
                                <?php if ($_SESSION['user_id'] != $user['id']): // Ne pas permettre de se supprimer soi-même ?>
                                <button class="user-action-btn delete" 
                                        data-id="<?php echo $user['id']; ?>" 
                                        data-username="<?php echo htmlspecialchars($user['identifiant']); ?>"
                                        title="Supprimer cet utilisateur">🗑️</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Panel d'ajout d'utilisateur -->
            <div class="panel-content" id="addUserPanel" style="display: none;">
                <div id="form-message" class="form-message" style="display: none;"></div>
                
                <!-- Bouton génération identifiant -->
                <div class="action-buttons">
                    <button id="generateRandomUsername" class="action-btn"><?php echo $texts['generate_random_username']; ?></button>
                </div>
                
                <form id="addUserForm" class="new-user-form" method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <!-- Champs cachés pour déjouer l'autocomplétion -->
                    <div style="display:none">
                        <input type="text" id="preventAutofill1" name="preventAutofill1" tabindex="-1">
                        <input type="password" id="preventAutofill2" name="preventAutofill2" tabindex="-1">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="newEmail"><?php echo $texts['email']; ?></label>
                            <input type="email" id="newEmail" name="email" required autocomplete="off" placeholder="email@exemple.com">
                        </div>
                        
                        <div class="form-group">
                            <label for="newUsername"><?php echo $texts['username']; ?></label>
                            <input type="text" id="newUsername" name="username" required autocomplete="off" placeholder="Nouvel identifiant">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="newPassword"><?php echo $texts['password']; ?></label>
                            <input type="password" id="newPassword" name="password" required autocomplete="off" placeholder="Nouveau mot de passe">
                        </div>
                        
                        <div class="form-group">
                            <label for="confirmPassword"><?php echo $texts['confirm_password']; ?></label>
                            <input type="password" id="confirmPassword" name="confirm_password" required autocomplete="off" placeholder="Confirmer le mot de passe">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="userRole"><?php echo $texts['role']; ?></label>
                            <select id="userRole" name="role" required>
                                <option value=""><?php echo $texts['select_role']; ?></option>
                                <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['nom']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="userAvatar"><?php echo $texts['avatar']; ?></label>
                            <input type="text" id="userAvatar" name="avatar" placeholder="<?php echo $texts['avatar_placeholder']; ?>" value="👤">
                        </div>
                    </div>
                    
                    <button type="submit" class="create-game-btn"><?php echo $texts['submit']; ?></button>
                </form>
            </div>
            
            <!-- Panel de création de partie -->
            <div class="panel-content game-creation-panel" id="gameCreationPanel" style="display: none;">
                <form id="createGameForm" method="post" action="create_game.php">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="form-group">
                        <label for="gameTitle"><?php echo $texts['game_name']; ?></label>
                        <input type="text" id="gameTitle" name="gameTitle" placeholder="<?php echo $texts['enter_game_name']; ?>">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="playerCount"><?php echo $texts['player_count']; ?></label>
                            <select id="playerCount" name="playerCount">
                                <option value="2">2 <?php echo $texts['players']; ?></option>
                                <option value="3">3 <?php echo $texts['players']; ?></option>
                                <option value="4">4 <?php echo $texts['players']; ?></option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="difficultyLevel"><?php echo $texts['difficulty']; ?></label>
                            <select id="difficultyLevel" name="difficultyLevel">
                                <option value="easy"><?php echo $texts['easy']; ?></option>
                                <option value="medium"><?php echo $texts['medium']; ?></option>
                                <option value="hard"><?php echo $texts['hard']; ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="gamePrivacy"><?php echo $texts['privacy']; ?></label>
                        <select id="gamePrivacy" name="gamePrivacy">
                            <option value="public"><?php echo $texts['public_game']; ?></option>
                            <option value="private"><?php echo $texts['private_game']; ?></option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="startingLevel"><?php echo $texts['starting_level']; ?></label>
                        <select id="startingLevel" name="startingLevel">
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo $texts['level']; ?> <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="create-game-btn"><?php echo $texts['create_start_game']; ?></button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        // Éléments DOM principaux
        const usersTab = document.getElementById('usersTab');
        const addUserTab = document.getElementById('addUserTab');
        const gameCreationTab = document.getElementById('gameCreationTab');
        const usersPanel = document.getElementById('usersPanel');
        const addUserPanel = document.getElementById('addUserPanel');
        const gameCreationPanel = document.getElementById('gameCreationPanel');
        const createGameBtn = document.getElementById('createGameBtn');
        const addUserForm = document.getElementById('addUserForm');
        const formMessage = document.getElementById('form-message');
        const generateRandomUsernameBtn = document.getElementById('generateRandomUsername');
        
        // Champs du formulaire
        const newUsernameField = document.getElementById('newUsername');
        const newPasswordField = document.getElementById('newPassword');
        const confirmPasswordField = document.getElementById('confirmPassword');
        const newEmailField = document.getElementById('newEmail');
        const userRoleField = document.getElementById('userRole');
        const userAvatarField = document.getElementById('userAvatar');
        
        // CSRF Token et URL de base
        const csrfToken = document.getElementById('csrf_token') ? document.getElementById('csrf_token').value : '';
        const baseUrl = document.getElementById('base_url') ? document.getElementById('base_url').value : '';
        
        // Faire disparaître les messages flash automatiquement
        setTimeout(() => {
            const flashMessages = document.querySelectorAll('.flash-message');
            flashMessages.forEach(msg => {
                msg.style.opacity = '0';
                msg.style.transform = 'translateX(100%)';
                setTimeout(() => msg.remove(), 300);
            });
        }, 5000);
        
        // ==== Gestion des onglets ====
        if (usersTab && addUserTab && gameCreationTab) {
            usersTab.addEventListener('click', () => {
                setActiveTab(usersTab, usersPanel);
            });
            
            addUserTab.addEventListener('click', () => {
                setActiveTab(addUserTab, addUserPanel);
                if (addUserForm) {
                    addUserForm.reset();
                    resetFormFields();
                }
            });
            
            gameCreationTab.addEventListener('click', () => {
                setActiveTab(gameCreationTab, gameCreationPanel);
            });
        }
        
        // Fonction pour définir l'onglet actif et afficher le panel correspondant
        function setActiveTab(activeTab, activePanel) {
            [usersTab, addUserTab, gameCreationTab].forEach(tab => {
                if (tab) tab.classList.remove('active');
            });
            
            [usersPanel, addUserPanel, gameCreationPanel].forEach(panel => {
                if (panel) panel.style.display = 'none';
            });
            
            if (activeTab) activeTab.classList.add('active');
            if (activePanel) activePanel.style.display = 'block';
        }
        
        // ==== Fonction pour générer un identifiant mémorisable ====
        function generateMemoableUsername() {
            const adjectives = [
                'super', 'grand', 'petit', 'rapide', 'fort', 'brave', 
                'vif', 'agile', 'sympa', 'cool', 'smart', 'pro', 
                'top', 'zen', 'tech', 'mega', 'ultra', 'hyper'
            ];
            
            const nouns = [
                'joueur', 'hero', 'ninja', 'panda', 'aigle', 'tigre', 
                'lion', 'loup', 'ours', 'robot', 'pilote', 'agent', 
                'gamer', 'master', 'expert', 'champion', 'star', 'ace'
            ];
            
            const adjective = adjectives[Math.floor(Math.random() * adjectives.length)];
            const noun = nouns[Math.floor(Math.random() * nouns.length)];
            const randomNum = Math.floor(Math.random() * 900) + 100;
            
            return `${adjective}${noun}${randomNum}`;
        }
        
        // ==== Génération d'identifiant mémorisable ====
        if (generateRandomUsernameBtn && newUsernameField) {
            generateRandomUsernameBtn.addEventListener('click', function(e) {
                e.preventDefault();
                newUsernameField.value = generateMemoableUsername();
                highlightField(newUsernameField);
            });
        }
        
        // Générer automatiquement un identifiant au chargement
        if (newUsernameField) {
            newUsernameField.value = generateMemoableUsername();
        }
        
        // Fonction pour mettre en évidence un champ
        function highlightField(field) {
            if (!field) return;
            field.classList.add('highlight');
            setTimeout(() => {
                field.classList.remove('highlight');
            }, 1500);
        }
        
        // ==== Gestion du formulaire d'ajout d'utilisateur ====
        if (addUserForm) {
            resetFormFields();
            
            addUserForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Vérifications des valeurs par défaut problématiques
                if (newUsernameField && newUsernameField.value === 'user') {
                    showFormMessage('L\'identifiant "user" n\'est pas autorisé. Veuillez en choisir un autre ou utiliser le bouton de génération.', 'error');
                    highlightField(newUsernameField);
                    return;
                }
                
                if (newPasswordField && newPasswordField.value === 'Eloi2023*') {
                    showFormMessage('Le mot de passe "Eloi2023*" n\'est pas autorisé. Veuillez en choisir un autre.', 'error');
                    highlightField(newPasswordField);
                    return;
                }
                
                // Vérifier que les mots de passe correspondent
                if (newPasswordField && confirmPasswordField && newPasswordField.value !== confirmPasswordField.value) {
                    showFormMessage('Les mots de passe ne correspondent pas.', 'error');
                    highlightField(confirmPasswordField);
                    return;
                }
                
                // Si l'identifiant est vide ou "user", le remplacer
                if (!newUsernameField.value || newUsernameField.value === 'user') {
                    newUsernameField.value = generateMemoableUsername();
                }
                
                // Créer un objet FormData pour envoyer les données
                const formData = new FormData();
                formData.append('csrf_token', csrfToken);
                formData.append('email', newEmailField ? newEmailField.value : '');
                formData.append('username', newUsernameField ? newUsernameField.value : '');
                formData.append('password', newPasswordField ? newPasswordField.value : '');
                formData.append('confirm_password', confirmPasswordField ? confirmPasswordField.value : '');
                formData.append('role', userRoleField ? userRoleField.value : '');
                formData.append('avatar', userAvatarField ? userAvatarField.value : '👤');
                
                // Envoyer les données via fetch API
                fetch(baseUrl + 'pages/profil/add_user.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erreur réseau: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        showFormMessage(data.message, 'success');
                        addUserForm.reset();
                        userAvatarField.value = '👤';
                        
                        // Rafraîchir la page après un délai
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        showFormMessage(data.message, 'error');
                        
                        if (data.field) {
                            const fieldMap = {
                                'username': newUsernameField,
                                'email': newEmailField,
                                'password': newPasswordField,
                                'role': userRoleField
                            };
                            
                            if (fieldMap[data.field]) {
                                highlightField(fieldMap[data.field]);
                            }
                        }
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    showFormMessage('Une erreur est survenue lors du traitement de la demande.', 'error');
                });
            });
            
            // Ajouter des écouteurs d'événements pour effacer les valeurs indésirables
            [newUsernameField, newPasswordField, confirmPasswordField].forEach(field => {
                if (field) {
                    field.addEventListener('focus', function() {
                        if (this === newUsernameField && this.value === 'user') {
                            this.value = '';
                        } else if ((this === newPasswordField || this === confirmPasswordField) && 
                                    this.value === 'Eloi2023*') {
                            this.value = '';
                        }
                    });
                    
                    field.addEventListener('input', function() {
                        if (this === newUsernameField && this.value === 'user') {
                            this.value = '';
                        } else if ((this === newPasswordField || this === confirmPasswordField) && 
                                    this.value === 'Eloi2023*') {
                            this.value = '';
                        }
                    });
                }
            });
        }
        
        // Fonction pour réinitialiser les champs du formulaire
        function resetFormFields() {
            if (newUsernameField && newUsernameField.value === 'user') {
                newUsernameField.value = '';
            }
            
            if (newPasswordField && newPasswordField.value === 'Eloi2023*') {
                newPasswordField.value = '';
            }
            
            if (confirmPasswordField && confirmPasswordField.value === 'Eloi2023*') {
                confirmPasswordField.value = '';
            }
            
            if (userAvatarField) {
                userAvatarField.value = '👤';
            }
            
            if (formMessage) {
                formMessage.style.display = 'none';
            }
        }
        
        // Fonction pour afficher un message dans le formulaire
        function showFormMessage(message, type) {
            if (!formMessage) return;
            
            formMessage.textContent = message;
            formMessage.className = 'form-message';
            formMessage.classList.add(type === 'success' ? 'success-message' : 'error-message');
            formMessage.style.display = 'block';
            
            formMessage.scrollIntoView({ behavior: 'smooth', block: 'start' });
            
            if (type === 'success') {
                setTimeout(() => {
                    formMessage.style.display = 'none';
                }, 5000);
            }
        }
        
        // ==== Gestion de l'édition d'utilisateurs ====
        document.querySelectorAll('.user-action-btn.edit').forEach(btn => {
            btn.addEventListener('click', function() {
                const userId = this.getAttribute('data-id');
                
                if (userId) {
                    window.location.href = baseUrl + 'pages/profil/edit_user.php?id=' + userId + '&csrf_token=' + encodeURIComponent(csrfToken);
                } else {
                    console.error('ID utilisateur manquant');
                    alert('Erreur: ID utilisateur manquant');
                }
            });
        });
        
        // Fonction standalone pour éditer un utilisateur
        function editUser(userId) {
            if (!userId || userId <= 0) {
                console.error('ID utilisateur invalide:', userId);
                alert('Erreur: ID utilisateur invalide');
                return;
            }
            
            window.location.href = baseUrl + 'pages/profil/edit_user.php?id=' + userId + '&csrf_token=' + encodeURIComponent(csrfToken);
        }
        
        // ==== Gestion de la suppression d'utilisateurs ====
        document.querySelectorAll('.user-action-btn.delete').forEach(btn => {
            btn.addEventListener('click', function() {
                const userId = this.getAttribute('data-id');
                const username = this.getAttribute('data-username');
                
                if (confirm(`Êtes-vous sûr de vouloir supprimer l'utilisateur ${username} ?`)) {
                    const formData = new FormData();
                    formData.append('csrf_token', csrfToken);
                    formData.append('id', userId);
                    
                    fetch(baseUrl + 'pages/profil/delete_user.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Erreur réseau: ' + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            this.closest('tr').remove();
                            showFlashMessage('Utilisateur supprimé avec succès.', 'success');
                        } else {
                            showFlashMessage('Erreur lors de la suppression: ' + data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        showFlashMessage('Une erreur est survenue lors de la suppression.', 'error');
                    });
                }
            });
        });
        
        // ==== Bouton de création de partie ====
        if (createGameBtn && gameCreationTab) {
            createGameBtn.addEventListener('click', () => {
                setActiveTab(gameCreationTab, gameCreationPanel);
            });
        }
        
        // Fonction pour afficher des messages flash
        function showFlashMessage(message, type) {
            const flashMessage = document.createElement('div');
            flashMessage.className = `flash-message ${type}`;
            flashMessage.textContent = message;
            document.body.appendChild(flashMessage);
            
            setTimeout(() => {
                flashMessage.style.opacity = '0';
                flashMessage.style.transform = 'translateX(100%)';
                setTimeout(() => flashMessage.remove(), 300);
            }, 3000);
        }
        
        // ==== Désactiver l'autocomplétion ====
        function disableAutocomplete() {
            document.querySelectorAll('input, select, textarea').forEach(input => {
                input.setAttribute('autocomplete', 'new-' + Math.random().toString(36).substring(2));
            });
        }
        
        disableAutocomplete();
        
        // Nettoyage immédiat des valeurs par défaut indésirables
        window.setTimeout(resetFormFields, 100);
        window.setTimeout(resetFormFields, 500);
        window.setTimeout(resetFormFields, 1000);
        
        // Rendre les fonctions accessibles globalement
        window.editUser = editUser;
    });
    </script>
</body>
</html>
