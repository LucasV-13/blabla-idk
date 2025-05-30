<?php
session_start();

include('connexion/connexion.php');

// Protection CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Gestion des textes multilingues
$language = isset($_SESSION['language']) ? $_SESSION['language'] : 'fr';
include('languages/' . $language . '.php');

// Vérification si le formulaire a été soumis
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Vérification CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = $texts['error_message_form_validation'];
    } 
    // Vérification si les champs sont définis et non vides
    elseif (!empty($_POST['username']) && !empty($_POST['password'])) {
        $input_username = $_POST['username'];  // Récupérer le nom d'utilisateur
        $input_password = $_POST['password'];  // Récupérer le mot de passe
        
        try {
            // Préparer et exécuter une requête pour trouver l'utilisateur avec PDO
            $stmt = $conn->prepare("SELECT * FROM Utilisateurs WHERE identifiant = :username");
            $stmt->bindParam(':username', $input_username, PDO::PARAM_STR);
            $stmt->execute();
            
            // Si un utilisateur est trouvé
            if ($user = $stmt->fetch()) {
                // Vérification du mot de passe (supporte à la fois haché et texte brut)
                $password_verified = false;
                
                // Si le mot de passe semble être haché (commence par $)
                if (substr($user['mdp'], 0, 1) === '$') {
                    $password_verified = password_verify($input_password, $user['mdp']);
                } else {
                    // Sinon, vérifier en texte brut
                    $password_verified = ($input_password === $user['mdp']);
                }
                
                if ($password_verified) {
                    // Récupérer le rôle avec PDO
                    $role_stmt = $conn->prepare("SELECT nom FROM Roles WHERE id = :role_id");
                    $role_stmt->bindParam(':role_id', $user['id_role'], PDO::PARAM_INT);
                    $role_stmt->execute();
                    
                    if ($role = $role_stmt->fetch()) {
                        // Régénérer l'ID de session pour prévenir la fixation de session
                        session_regenerate_id(true);
                        
                        // Stockage des informations en session
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['identifiant'];
                        $_SESSION['role'] = $role['nom'];
                        $_SESSION['email'] = $user['mail'] ?? '';
                        $_SESSION['avatar'] = $user['avatar'] ?? '👤';
                        
                        // Définir un délai d'expiration de session (1 heure)
                        $_SESSION['expires'] = time() + (60 * 60);
                        
                        // Redirection vers le tableau de bord
                        header("Location: pages/dashboard.php");
                        exit();
                    } else {
                        $error_message = $texts['error_message_role_not_found'];
                    }
                } else {
                    $error_message = $texts['error_message_incorrect_credentials'];
                    // Ajouter un délai pour ralentir les tentatives de force brute
                    sleep(1);
                }
            } else {
                $error_message = $texts['error_message_incorrect_credentials'];
                // Ajouter un délai pour ralentir les tentatives de force brute
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
<html lang="<?php echo $language; ?>">
<head>
    <title><?php echo $texts['site_title']; ?> - <?php echo $texts['login_button']; ?></title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="mind-icon">
            <div class="card-glow">
                <div class="card-number">42</div>
            </div>
        </div>
        
        <h2><?php echo $texts['login_title']; ?></h2>
        
        <?php if (!empty($error_message)): ?>
            <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <!-- Jeton CSRF -->
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="form-group">
                <label for="username"><?php echo $texts['login_player']; ?></label>
                <input type="text" id="username" name="username" required autocomplete="off">
            </div>
            
            <div class="form-group">
                <label for="password"><?php echo $texts['login_secret_code']; ?></label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit"><?php echo $texts['login_button']; ?></button>
        </form>
    </div>
</body>
</html>