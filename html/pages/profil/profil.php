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
                    JOIN Roles ON Utilisateurs.id_role = Roles.id";
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
            padding: 10px;
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
            0% { background-color: #fff; }
            50% { background-color: #e0ffe0; }
            100% { background-color: #fff; }
        }
        
        .highlight {
            animation: highlightField 1.5s;
        }
    </style>
</head>
<body>
    <!-- Champ caché pour le jeton CSRF (pour JS) -->
    <input type="hidden" id="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    <input type="hidden" id="base_url" value="../">

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
                            <th><?php echo $texts['password']; ?></th>
                            <th><?php echo $texts['actions']; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($utilisateurs as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['mail']); ?></td>
                            <td><?php echo htmlspecialchars($user['identifiant']); ?></td>
                            <td>••••••••</td>
                            <td>
                                <button class="user-action-btn edit" data-id="<?php echo $user['id']; ?>">✏️</button>
                                <button class="user-action-btn delete" data-id="<?php echo $user['id']; ?>">🗑️</button>
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
        
        // ==== Gestion des onglets ====
        if (usersTab && addUserTab && gameCreationTab) {
            usersTab.addEventListener('click', () => {
                // Activer l'onglet et afficher le panel correspondant
                setActiveTab(usersTab, usersPanel);
            });
            
            addUserTab.addEventListener('click', () => {
                // Activer l'onglet et afficher le panel correspondant
                setActiveTab(addUserTab, addUserPanel);
                // Réinitialiser le formulaire et effacer les valeurs indésirables
                if (addUserForm) {
                    addUserForm.reset();
                    resetFormFields();
                }
            });
            
            gameCreationTab.addEventListener('click', () => {
                // Activer l'onglet et afficher le panel correspondant
                setActiveTab(gameCreationTab, gameCreationPanel);
            });
        }
        
        // Fonction pour définir l'onglet actif et afficher le panel correspondant
        function setActiveTab(activeTab, activePanel) {
            // Désactiver tous les onglets
            [usersTab, addUserTab, gameCreationTab].forEach(tab => {
                if (tab) tab.classList.remove('active');
            });
            
            // Cacher tous les panels
            [usersPanel, addUserPanel, gameCreationPanel].forEach(panel => {
                if (panel) panel.style.display = 'none';
            });
            
            // Activer l'onglet et le panel sélectionnés
            if (activeTab) activeTab.classList.add('active');
            if (activePanel) activePanel.style.display = 'block';
        }
        
        // ==== Génération d'identifiant aléatoire ====
        if (generateRandomUsernameBtn && newUsernameField) {
            generateRandomUsernameBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Générer un identifiant unique basé sur un timestamp et un nombre aléatoire
                const prefix = 'user';
                const timestamp = new Date().getTime().toString().slice(-6);
                const random = Math.floor(Math.random() * 1000);
                const randomUsername = `${prefix}_${timestamp}_${random}`;
                
                // Définir la valeur du champ
                newUsernameField.value = randomUsername;
                
                // Mettre en évidence le champ
                highlightField(newUsernameField);
            });
        }
        
        // Fonction pour mettre en évidence un champ
        function highlightField(field) {
            if (!field) return;
            
            // Ajouter la classe d'animation
            field.classList.add('highlight');
            
            // Supprimer la classe après l'animation
            setTimeout(() => {
                field.classList.remove('highlight');
            }, 1500);
        }
        
        // ==== Gestion du formulaire d'ajout d'utilisateur ====
        if (addUserForm) {
            // Au chargement du formulaire, réinitialiser les champs
            resetFormFields();
            
            // Lors de la soumission du formulaire
            addUserForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Vérifier les valeurs par défaut problématiques
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
                        // Succès
                        showFormMessage(data.message, 'success');
                        addUserForm.reset();
                        userAvatarField.value = '👤';
                        
                        // Rafraîchir la page après un délai
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        // Erreur
                        showFormMessage(data.message, 'error');
                        
                        // Mettre en évidence le champ problématique s'il est spécifié
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
                        // Effacer les valeurs par défaut problématiques au focus
                        if (this === newUsernameField && this.value === 'user') {
                            this.value = '';
                        } else if ((this === newPasswordField || this === confirmPasswordField) && 
                                    this.value === 'Eloi2023*') {
                            this.value = '';
                        }
                    });
                    
                    field.addEventListener('input', function() {
                        // Effacer immédiatement si la valeur par défaut est saisie
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
            // Effacer les valeurs par défaut problématiques
            if (newUsernameField && newUsernameField.value === 'user') {
                newUsernameField.value = '';
            }
            
            if (newPasswordField && newPasswordField.value === 'Eloi2023*') {
                newPasswordField.value = '';
            }
            
            if (confirmPasswordField && confirmPasswordField.value === 'Eloi2023*') {
                confirmPasswordField.value = '';
            }
            
            // Réinitialiser l'avatar à 👤
            if (userAvatarField) {
                userAvatarField.value = '👤';
            }
            
            // Cacher le message de formulaire
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
            
            // Faire défiler jusqu'au message
            formMessage.scrollIntoView({ behavior: 'smooth', block: 'start' });
            
            // Cacher le message après un délai si c'est un succès
            if (type === 'success') {
                setTimeout(() => {
                    formMessage.style.display = 'none';
                }, 5000);
            }
        }
        
        // ==== Gestion de la suppression d'utilisateurs ====
        // Ajouter un gestionnaire d'événements pour les boutons de suppression
        document.querySelectorAll('.user-action-btn.delete').forEach(btn => {
            btn.addEventListener('click', function() {
                const row = this.closest('tr');
                const email = row.cells[0].textContent;
                const username = row.cells[1].textContent;
                const userId = this.getAttribute('data-id');
                
                if (confirm(`Êtes-vous sûr de vouloir supprimer l'utilisateur ${username} (${email}) ?`)) {
                    // Créer un objet FormData pour envoyer les données
                    const formData = new FormData();
                    formData.append('csrf_token', csrfToken);
                    formData.append('id', userId);
                    
                    // Envoyer la requête via fetch API
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
                            // Supprimer la ligne du tableau
                            row.remove();
                            alert('Utilisateur supprimé avec succès.');
                        } else {
                            alert('Erreur lors de la suppression: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        alert('Une erreur est survenue lors de la suppression.');
                    });
                }
            });
        });
        
        // ==== Bouton de création de partie ====
        if (createGameBtn && gameCreationTab) {
            createGameBtn.addEventListener('click', () => {
                // Basculer vers l'onglet de création de partie
                setActiveTab(gameCreationTab, gameCreationPanel);
            });
        }
        
        // ==== Désactiver l'autocomplétion ====
        // Fonction pour désactiver l'autocomplétion sur tous les champs
        function disableAutocomplete() {
            document.querySelectorAll('input, select, textarea').forEach(input => {
                input.setAttribute('autocomplete', 'new-' + Math.random().toString(36).substring(2));
            });
        }
        
        // Appliquer la désactivation de l'autocomplétion
        disableAutocomplete();
        
        // Nettoyage immédiat des valeurs par défaut indésirables
        window.setTimeout(resetFormFields, 100);
        window.setTimeout(resetFormFields, 500);
        window.setTimeout(resetFormFields, 1000);
    });
    </script>
    <script>

document.addEventListener('DOMContentLoaded', function() {
    // Fonction pour générer un identifiant vraiment unique et aléatoire
    function generateTrulyUniqueUsername() {
        const prefix = 'user';
        const timestamp = Date.now(); // Timestamp en millisecondes
        const random1 = Math.floor(Math.random() * 10000);
        const random2 = Math.random().toString(36).substring(2, 7); // Caractères alphanumériques
        return `${prefix}_${timestamp}_${random1}_${random2}`;
    }
    
    // On force l'utilisation d'un identifiant unique au chargement de la page
    const newUsernameField = document.getElementById('newUsername');
    if (newUsernameField) {
        // Générer immédiatement un identifiant unique
        newUsernameField.value = generateTrulyUniqueUsername();
        
        // Ajouter un attribut readonly pour empêcher la modification
        newUsernameField.setAttribute('readonly', 'readonly');
        newUsernameField.style.backgroundColor = '#f0f0f0';
        
        // Ajoutez un texte explicatif
        const infoText = document.createElement('small');
        infoText.style.display = 'block';
        infoText.style.marginTop = '5px';
        infoText.style.color = '#666';
        infoText.textContent = 'Identifiant généré automatiquement pour éviter les conflits.';
        newUsernameField.parentNode.appendChild(infoText);
    }
    
    // Remplacer la fonction du bouton "Générer un identifiant aléatoire"
    const generateRandomUsernameBtn = document.getElementById('generateRandomUsername');
    if (generateRandomUsernameBtn && newUsernameField) {
        generateRandomUsernameBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Générer un nouvel identifiant unique
            newUsernameField.value = generateTrulyUniqueUsername();
        });
        
        // Changer le texte du bouton pour plus de clarté
        generateRandomUsernameBtn.textContent = 'Générer un nouvel identifiant unique';
    }
    
    // Intercepter le formulaire avant soumission pour forcer les valeurs correctes
    const addUserForm = document.getElementById('addUserForm');
    if (addUserForm) {
        addUserForm.addEventListener('submit', function(e) {
            // Si l'identifiant est vide ou "user", le remplacer
            if (!newUsernameField.value || newUsernameField.value === 'user') {
                newUsernameField.value = generateTrulyUniqueUsername();
            }
            
            // Si le mot de passe est "Eloi2023*", l'afficher à l'utilisateur
            const passwordField = document.getElementById('newPassword');
            const confirmPasswordField = document.getElementById('confirmPassword');
            
            if (passwordField && passwordField.value === 'Eloi2023*') {
                if (!confirm("Le mot de passe 'Eloi2023*' n'est pas recommandé. Voulez-vous continuer quand même?")) {
                    e.preventDefault();
                    return false;
                }
            }
            
            // S'assurer que les mots de passe correspondent
            if (passwordField && confirmPasswordField && passwordField.value !== confirmPasswordField.value) {
                alert("Les mots de passe ne correspondent pas!");
                e.preventDefault();
                return false;
            }
        });
    }
    
    // Fonction pour vider le cache d'autocomplétion
    function clearAutocompleteCache() {
        // Créer un formulaire temporaire avec un champ de mot de passe
        const tempForm = document.createElement('form');
        tempForm.style.display = 'none';
        tempForm.setAttribute('autocomplete', 'off');
        
        const tempInput = document.createElement('input');
        tempInput.setAttribute('type', 'password');
        tempInput.setAttribute('autocomplete', 'new-password');
        tempInput.setAttribute('name', 'temp_' + Math.random());
        
        tempForm.appendChild(tempInput);
        document.body.appendChild(tempForm);
        
        // Simuler une soumission et supprimer le formulaire
        tempInput.focus();
        tempForm.reset();
        
        setTimeout(function() {
            document.body.removeChild(tempForm);
        }, 1000);
    }
    
    // Tenter de vider le cache d'autocomplétion
    clearAutocompleteCache();
});
    </script>
</body>
</html>