<?php
/**
 * Page édition profil utilisateur - The Mind
 * 
 * Permet à l'utilisateur de modifier ses informations personnelles,
 * changer son mot de passe et mettre à jour son avatar
 * 
 * @package TheMind
 * @version 1.0
 * @since Phase 3
 */

declare(strict_types=1);

// Headers de sécurité
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Configuration et dépendances
require_once '../../config/database.php';
require_once '../../config/session.php';
require_once '../../config/constants.php';

// Vérification authentification
if (!SessionManager::isLoggedIn()) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

// Prolonger la session
SessionManager::extendSession();

// Configuration de la page
$pageTitle = 'Édition du Profil';
$cssFiles = ['profile', 'forms'];
$jsFiles = ['profile'];

// Gestion des textes multilingues
$language = SessionManager::get('language', 'fr');
require_once "../../languages/{$language}.php";

// Variables utilisateur
$userId = SessionManager::get('user_id');
$currentUsername = SessionManager::get('username', '');
$currentEmail = SessionManager::get('email', '');
$currentAvatar = SessionManager::get('avatar', '👤');
$currentLanguage = SessionManager::get('language', 'fr');

// Messages de feedback
$successMessage = '';
$errorMessage = '';
$fieldErrors = [];

// Connexion base de données
$db = Database::getInstance();
$conn = $db->getConnection();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification CSRF
    if (!SessionManager::validateCSRFToken(filter_input(INPUT_POST, 'csrf_token'))) {
        $errorMessage = $texts['invalid_csrf_token'];
    } else {
        // Récupération et validation des données
        $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
        
        if ($action === 'update_profile') {
            // Mise à jour des informations de base
            $newUsername = trim(filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING) ?? '');
            $newEmail = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');
            $newAvatar = trim(filter_input(INPUT_POST, 'avatar', FILTER_SANITIZE_STRING) ?? '👤');
            $newLanguage = filter_input(INPUT_POST, 'language', FILTER_SANITIZE_STRING) ?? 'fr';
            
            // Validation
            if (empty($newUsername)) {
                $fieldErrors['username'] = $texts['username_required'];
            } elseif (strlen($newUsername) < 3) {
                $fieldErrors['username'] = $texts['username_too_short'];
            } elseif (strlen($newUsername) > 30) {
                $fieldErrors['username'] = $texts['username_too_long'];
            }
            
            if (empty($newEmail)) {
                $fieldErrors['email'] = $texts['email_required'];
            } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $fieldErrors['email'] = $texts['email_invalid'];
            }
            
            if (!in_array($newLanguage, ['fr', 'en'])) {
                $newLanguage = 'fr';
            }
            
            // Vérification unicité email/username si modifiés
            try {
                if ($newUsername !== $currentUsername) {
                    $checkUsernameQuery = "SELECT id FROM Utilisateurs WHERE identifiant = :username AND id != :user_id";
                    $checkUsernameStmt = $conn->prepare($checkUsernameQuery);
                    $checkUsernameStmt->bindParam(':username', $newUsername, PDO::PARAM_STR);
                    $checkUsernameStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                    $checkUsernameStmt->execute();
                    
                    if ($checkUsernameStmt->rowCount() > 0) {
                        $fieldErrors['username'] = $texts['username_already_exists'];
                    }
                }
                
                if ($newEmail !== $currentEmail) {
                    $checkEmailQuery = "SELECT id FROM Utilisateurs WHERE mail = :email AND id != :user_id";
                    $checkEmailStmt = $conn->prepare($checkEmailQuery);
                    $checkEmailStmt->bindParam(':email', $newEmail, PDO::PARAM_STR);
                    $checkEmailStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                    $checkEmailStmt->execute();
                    
                    if ($checkEmailStmt->rowCount() > 0) {
                        $fieldErrors['email'] = $texts['email_already_exists'];
                    }
                }
                
                // Mise à jour si pas d'erreurs
                if (empty($fieldErrors)) {
                    $conn->beginTransaction();
                    
                    $updateQuery = "UPDATE Utilisateurs SET identifiant = :username, mail = :email, avatar = :avatar WHERE id = :user_id";
                    $updateStmt = $conn->prepare($updateQuery);
                    $updateStmt->bindParam(':username', $newUsername, PDO::PARAM_STR);
                    $updateStmt->bindParam(':email', $newEmail, PDO::PARAM_STR);
                    $updateStmt->bindParam(':avatar', $newAvatar, PDO::PARAM_STR);
                    $updateStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                    $updateStmt->execute();
                    
                    $conn->commit();
                    
                    // Mise à jour de la session
                    SessionManager::set('username', $newUsername);
                    SessionManager::set('email', $newEmail);
                    SessionManager::set('avatar', $newAvatar);
                    SessionManager::set('language', $newLanguage);
                    
                    // Mettre à jour les variables locales
                    $currentUsername = $newUsername;
                    $currentEmail = $newEmail;
                    $currentAvatar = $newAvatar;
                    $currentLanguage = $newLanguage;
                    
                    $successMessage = $texts['profile_updated_success'];
                }
                
            } catch (PDOException $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                error_log("Erreur mise à jour profil: " . $e->getMessage());
                $errorMessage = $texts['update_error'];
            }
            
        } elseif ($action === 'change_password') {
            // Changement de mot de passe
            $currentPassword = filter_input(INPUT_POST, 'current_password', FILTER_UNSAFE_RAW) ?? '';
            $newPassword = filter_input(INPUT_POST, 'new_password', FILTER_UNSAFE_RAW) ?? '';
            $confirmPassword = filter_input(INPUT_POST, 'confirm_password', FILTER_UNSAFE_RAW) ?? '';
            
            // Validation
            if (empty($currentPassword)) {
                $fieldErrors['current_password'] = $texts['current_password_required'];
            }
            
            if (empty($newPassword)) {
                $fieldErrors['new_password'] = $texts['new_password_required'];
            } elseif (strlen($newPassword) < 8) {
                $fieldErrors['new_password'] = $texts['password_too_short'];
            }
            
            if ($newPassword !== $confirmPassword) {
                $fieldErrors['confirm_password'] = $texts['passwords_dont_match'];
            }
            
            // Vérification du mot de passe actuel
            if (empty($fieldErrors)) {
                try {
                    $getUserQuery = "SELECT mdp FROM Utilisateurs WHERE id = :user_id";
                    $getUserStmt = $conn->prepare($getUserQuery);
                    $getUserStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                    $getUserStmt->execute();
                    $userData = $getUserStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$userData || !password_verify($currentPassword, $userData['mdp'])) {
                        $fieldErrors['current_password'] = $texts['current_password_incorrect'];
                    } else {
                        // Mise à jour du mot de passe
                        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                        
                        $updatePasswordQuery = "UPDATE Utilisateurs SET mdp = :password WHERE id = :user_id";
                        $updatePasswordStmt = $conn->prepare($updatePasswordQuery);
                        $updatePasswordStmt->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
                        $updatePasswordStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                        $updatePasswordStmt->execute();
                        
                        $successMessage = $texts['password_changed_success'];
                        
                        // Log de sécurité
                        error_log("Mot de passe changé pour utilisateur ID: " . $userId);
                    }
                    
                } catch (PDOException $e) {
                    error_log("Erreur changement mot de passe: " . $e->getMessage());
                    $errorMessage = $texts['password_change_error'];
                }
            }
        }
    }
}

// Liste des avatars disponibles
$availableAvatars = [
    '👤', '👨', '👩', '🧑', '👦', '👧', '👨‍🦰', '👩‍🦰', '👱‍♂️', '👱‍♀️',
    '👴', '👵', '🧔', '🧙‍♂️', '🧙‍♀️', '👮‍♂️', '👮‍♀️', '🦸‍♂️', '🦸‍♀️',
    '🤴', '👸', '👳‍♂️', '👳‍♀️', '👷‍♂️', '👷‍♀️', '💂‍♂️', '💂‍♀️',
    '🕵️‍♂️', '🕵️‍♀️', '🧑‍💻', '🧑‍🎨', '🧑‍🎤', '🧑‍🎓', '🧑‍⚕️',
    '🎮', '🎯', '🎲', '🎪', '🎨', '🚀', '⚡', '🔥', '💎', '🌟'
];
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($language) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($texts['edit_profile']) ?> - The Mind">
    <title><?= htmlspecialchars($texts['edit_profile']) ?> - The Mind</title>
    
    <!-- CSS Core -->
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/main.css">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/components/buttons.css">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/components/modals.css">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/components/forms.css">
    
    <!-- CSS spécifiques -->
    <?php foreach ($cssFiles as $cssFile): ?>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/<?= $cssFile ?>.css">
    <?php endforeach; ?>
    
    <!-- CSS Edit Profile spécifique -->
    <style>
        .edit-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .edit-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .edit-header h1 {
            color: var(--text-primary);
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .edit-header p {
            color: var(--text-secondary);
            font-size: 1.125rem;
        }
        
        .edit-sections {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        
        .edit-section {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
        }
        
        .section-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .section-icon {
            font-size: 2rem;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }
        
        .avatar-selector {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
            gap: 0.75rem;
            margin-top: 1rem;
            max-height: 200px;
            overflow-y: auto;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
        }
        
        .avatar-option {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--card-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        
        .avatar-option:hover {
            transform: scale(1.1);
            box-shadow: var(--shadow-md);
        }
        
        .avatar-option.selected {
            border-color: var(--primary-color);
            background: var(--primary-color);
            color: white;
            transform: scale(1.2);
        }
        
        .current-avatar-display {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .current-avatar {
            width: 80px;
            height: 80px;
            font-size: 3rem;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-lg);
        }
        
        .password-requirements {
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-top: 1rem;
            border-left: 4px solid var(--accent-color);
        }
        
        .password-requirements h4 {
            margin: 0 0 0.5rem 0;
            color: var(--text-primary);
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .password-requirements ul {
            margin: 0;
            padding-left: 1.25rem;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .password-requirements li {
            margin-bottom: 0.25rem;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-bottom: 2rem;
        }
        
        .back-link:hover {
            color: var(--primary-color);
            transform: translateX(-4px);
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }
        
        @media (max-width: 768px) {
            .edit-container {
                padding: 1rem;
            }
            
            .edit-header h1 {
                font-size: 2rem;
            }
            
            .edit-section {
                padding: 1.5rem;
            }
            
            .avatar-selector {
                grid-template-columns: repeat(6, 1fr);
            }
            
            .avatar-option {
                width: 50px;
                height: 50px;
                font-size: 1.5rem;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="<?= BASE_URL ?>pages/dashboard.php" class="nav-brand">
                <span class="brand-icon">🧠</span>
                The Mind
            </a>
            
            <div class="nav-actions">
                <a href="<?= BASE_URL ?>pages/profile/index.php" class="btn btn-secondary">
                    👤 <?= htmlspecialchars($texts['profile']) ?>
                </a>
                <a href="<?= BASE_URL ?>pages/dashboard.php" class="btn btn-outline">
                    🏠 <?= htmlspecialchars($texts['dashboard']) ?>
                </a>
            </div>
        </div>
    </nav>

    <!-- Contenu principal -->
    <main class="edit-container">
        <!-- Lien retour -->
        <a href="<?= BASE_URL ?>pages/profile/index.php" class="back-link">
            ← <?= htmlspecialchars($texts['back_to_profile']) ?>
        </a>
        
        <!-- Header -->
        <div class="edit-header">
            <h1><?= htmlspecialchars($texts['edit_profile']) ?></h1>
            <p><?= htmlspecialchars($texts['edit_profile_description']) ?></p>
        </div>
        
        <!-- Messages de feedback -->
        <?php if (!empty($successMessage)): ?>
        <div class="alert alert-success">
            <strong>✅ <?= htmlspecialchars($texts['success']) ?>!</strong>
            <?= htmlspecialchars($successMessage) ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger">
            <strong>❌ <?= htmlspecialchars($texts['error']) ?>!</strong>
            <?= htmlspecialchars($errorMessage) ?>
        </div>
        <?php endif; ?>
        
        <!-- Sections d'édition -->
        <div class="edit-sections">
            <!-- Section informations personnelles -->
            <section class="edit-section">
                <div class="section-header">
                    <span class="section-icon">👤</span>
                    <h2 class="section-title"><?= htmlspecialchars($texts['personal_information']) ?></h2>
                </div>
                
                <form method="post" action="" id="profileForm">
                    <input type="hidden" name="csrf_token" value="<?= SessionManager::getCSRFToken() ?>">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="username" class="form-label">
                                <?= htmlspecialchars($texts['username']) ?> *
                            </label>
                            <input 
                                type="text" 
                                id="username" 
                                name="username" 
                                class="form-input <?= isset($fieldErrors['username']) ? 'error' : '' ?>"
                                value="<?= htmlspecialchars($currentUsername) ?>"
                                required
                                maxlength="30"
                                pattern="[a-zA-Z0-9_-]{3,30}"
                            >
                            <?php if (isset($fieldErrors['username'])): ?>
                            <div class="form-error"><?= htmlspecialchars($fieldErrors['username']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="email" class="form-label">
                                <?= htmlspecialchars($texts['email']) ?> *
                            </label>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                class="form-input <?= isset($fieldErrors['email']) ? 'error' : '' ?>"
                                value="<?= htmlspecialchars($currentEmail) ?>"
                                required
                            >
                            <?php if (isset($fieldErrors['email'])): ?>
                            <div class="form-error"><?= htmlspecialchars($fieldErrors['email']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="language" class="form-label">
                                <?= htmlspecialchars($texts['language']) ?>
                            </label>
                            <select id="language" name="language" class="form-select">
                                <option value="fr" <?= $currentLanguage === 'fr' ? 'selected' : '' ?>>
                                    🇫🇷 Français
                                </option>
                                <option value="en" <?= $currentLanguage === 'en' ? 'selected' : '' ?>>
                                    🇺🇸 English
                                </option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Sélecteur d'avatar -->
                    <div class="form-group">
                        <label class="form-label"><?= htmlspecialchars($texts['avatar']) ?></label>
                        
                        <div class="current-avatar-display">
                            <div class="current-avatar" id="currentAvatar">
                                <?= htmlspecialchars($currentAvatar) ?>
                            </div>
                            <div>
                                <strong><?= htmlspecialchars($texts['current_avatar']) ?></strong>
                                <br>
                                <small class="text-secondary"><?= htmlspecialchars($texts['click_to_change']) ?></small>
                            </div>
                        </div>
                        
                        <input type="hidden" id="avatar" name="avatar" value="<?= htmlspecialchars($currentAvatar) ?>">
                        
                        <div class="avatar-selector">
                            <?php foreach ($availableAvatars as $avatar): ?>
                            <div 
                                class="avatar-option <?= $avatar === $currentAvatar ? 'selected' : '' ?>" 
                                data-avatar="<?= htmlspecialchars($avatar) ?>"
                                title="<?= htmlspecialchars($avatar) ?>"
                            >
                                <?= htmlspecialchars($avatar) ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="resetForm()">
                            🔄 <?= htmlspecialchars($texts['reset']) ?>
                        </button>
                        <button type="submit" class="btn btn-primary">
                            💾 <?= htmlspecialchars($texts['save_changes']) ?>
                        </button>
                    </div>
                </form>
            </section>
            
            <!-- Section changement de mot de passe -->
            <section class="edit-section">
                <div class="section-header">
                    <span class="section-icon">🔒</span>
                    <h2 class="section-title"><?= htmlspecialchars($texts['change_password']) ?></h2>
                </div>
                
                <form method="post" action="" id="passwordForm">
                    <input type="hidden" name="csrf_token" value="<?= SessionManager::getCSRFToken() ?>">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label for="current_password" class="form-label">
                            <?= htmlspecialchars($texts['current_password']) ?> *
                        </label>
                        <input 
                            type="password" 
                            id="current_password" 
                            name="current_password" 
                            class="form-input <?= isset($fieldErrors['current_password']) ? 'error' : '' ?>"
                            required
                            autocomplete="current-password"
                        >
                        <?php if (isset($fieldErrors['current_password'])): ?>
                        <div class="form-error"><?= htmlspecialchars($fieldErrors['current_password']) ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="new_password" class="form-label">
                                <?= htmlspecialchars($texts['new_password']) ?> *
                            </label>
                            <input 
                                type="password" 
                                id="new_password" 
                                name="new_password" 
                                class="form-input <?= isset($fieldErrors['new_password']) ? 'error' : '' ?>"
                                required
                                minlength="8"
                                autocomplete="new-password"
                            >
                            <?php if (isset($fieldErrors['new_password'])): ?>
                            <div class="form-error"><?= htmlspecialchars($fieldErrors['new_password']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password" class="form-label">
                                <?= htmlspecialchars($texts['confirm_password']) ?> *
                            </label>
                            <input 
                                type="password" 
                                id="confirm_password" 
                                name="confirm_password" 
                                class="form-input <?= isset($fieldErrors['confirm_password']) ? 'error' : '' ?>"
                                required
                                minlength="8"
                                autocomplete="new-password"
                            >
                            <?php if (isset($fieldErrors['confirm_password'])): ?>
                            <div class="form-error"><?= htmlspecialchars($fieldErrors['confirm_password']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="password-requirements">
                        <h4><?= htmlspecialchars($texts['password_requirements']) ?></h4>
                        <ul>
                            <li><?= htmlspecialchars($texts['password_min_length']) ?></li>
                            <li><?= htmlspecialchars($texts['password_complexity']) ?></li>
                            <li><?= htmlspecialchars($texts['password_security']) ?></li>
                        </ul>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="clearPasswordForm()">
                            🧹 <?= htmlspecialchars($texts['clear']) ?>
                        </button>
                        <button type="submit" class="btn btn-primary">
                            🔒 <?= htmlspecialchars($texts['change_password']) ?>
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </main>

    <!-- Scripts -->
    <script src="<?= ASSETS_URL ?>js/main.js"></script>
    
    <!-- Scripts spécifiques -->
    <?php foreach ($jsFiles as $jsFile): ?>
    <script src="<?= ASSETS_URL ?>js/<?= $jsFile ?>.js"></script>
    <?php endforeach; ?>
    
    <!-- Configuration JavaScript -->
    <script>
        // Configuration globale pour cette page
        window.TheMind.config.userId = <?= json_encode($userId) ?>;
        window.TheMind.config.pageType = 'profile-edit';
        
        document.addEventListener('DOMContentLoaded', function() {
            // Gestion du sélecteur d'avatar
            const avatarOptions = document.querySelectorAll('.avatar-option');
            const avatarInput = document.getElementById('avatar');
            const currentAvatarDisplay = document.getElementById('currentAvatar');
            
            avatarOptions.forEach(option => {
                option.addEventListener('click', function() {
                    // Retirer la sélection précédente
                    avatarOptions.forEach(opt => opt.classList.remove('selected'));
                    
                    // Sélectionner le nouvel avatar
                    this.classList.add('selected');
                    const selectedAvatar = this.dataset.avatar;
                    
                    // Mettre à jour l'input caché et l'affichage
                    avatarInput.value = selectedAvatar;
                    currentAvatarDisplay.textContent = selectedAvatar;
                    
                    // Animation
                    currentAvatarDisplay.style.transform = 'scale(1.2)';
                    setTimeout(() => {
                        currentAvatarDisplay.style.transform = 'scale(1)';
                    }, 200);
                });
            });
            
            // Validation en temps réel des mots de passe
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            
            function validatePasswords() {
                const newPassword = newPasswordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                // Vérifier la correspondance
                if (confirmPassword && newPassword !== confirmPassword) {
                    confirmPasswordInput.setCustomValidity('<?= htmlspecialchars($texts['passwords_dont_match']) ?>');
                } else {
                    confirmPasswordInput.setCustomValidity('');
                }
                
                // Vérifier la complexité
                if (newPassword && newPassword.length < 8) {
                    newPasswordInput.setCustomValidity('<?= htmlspecialchars($texts['password_too_short']) ?>');
                } else {
                    newPasswordInput.setCustomValidity('');
                }
            }
            
            newPasswordInput.addEventListener('input', validatePasswords);
            confirmPasswordInput.addEventListener('input', validatePasswords);
            
            // Validation du formulaire profil
            const profileForm = document.getElementById('profileForm');
            profileForm.addEventListener('submit', function(e) {
                const username = document.getElementById('username').value.trim();
                const email = document.getElementById('email').value.trim();
                
                if (username.length < 3) {
                    e.preventDefault();
                    alert('<?= htmlspecialchars($texts['username_too_short']) ?>');
                    return;
                }
                
                if (!email || !email.includes('@')) {
                    e.preventDefault();
                    alert('<?= htmlspecialchars($texts['email_invalid']) ?>');
                    return;
                }
            });
            
            // Animation d'entrée pour les sections
            const sections = document.querySelectorAll('.edit-section');
            sections.forEach((section, index) => {
                section.style.opacity = '0';
                section.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    section.style.transition = 'all 0.5s ease';
                    section.style.opacity = '1';
                    section.style.transform = 'translateY(0)';
                }, index * 200);
            });
            
            console.log('Page édition profil initialisée');
        });
        
        // Fonctions utilitaires
        function resetForm() {
            if (confirm('<?= htmlspecialchars($texts['confirm_reset_form']) ?>')) {
                document.getElementById('profileForm').reset();
                
                // Remettre l'avatar par défaut
                const defaultAvatar = '<?= htmlspecialchars($currentAvatar) ?>';
                document.getElementById('avatar').value = defaultAvatar;
                document.getElementById('currentAvatar').textContent = defaultAvatar;
                
                // Remettre la sélection d'avatar
                document.querySelectorAll('.avatar-option').forEach(opt => {
                    opt.classList.remove('selected');
                    if (opt.dataset.avatar === defaultAvatar) {
                        opt.classList.add('selected');
                    }
                });
            }
        }
        
        function clearPasswordForm() {
            document.getElementById('passwordForm').reset();
        }
    </script>
    
    <!-- Champs cachés pour JavaScript -->
    <input type="hidden" id="csrf_token" value="<?= SessionManager::getCSRFToken() ?>">
    <input type="hidden" id="user_id" value="<?= htmlspecialchars($userId) ?>">
    <input type="hidden" id="language" value="<?= htmlspecialchars($language) ?>">
</body>
</html>