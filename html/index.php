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
    <style>
        :root {
            --primary-color: #ff4b2b;
            --secondary-color: #ffd966;
            --dark-color: #1a1a2e;
            --light-color: #f8f8ff;
            --accent-color: #00c2cb;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Orbitron', sans-serif;
            background-color: var(--dark-color);
            color: var(--light-color);
            min-height: 100vh;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(255, 75, 43, 0.1) 0%, transparent 20%),
                radial-gradient(circle at 90% 80%, rgba(0, 194, 203, 0.1) 0%, transparent 20%);
        }

        /* Container principal avec flexbox */
        .main-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 100vh;
            padding: 20px 30px;
            gap: 40px;
            max-width: 1100px;
            margin: 0 auto;
        }

        /* Container de connexion - bien à gauche */
        .login-container {
            background-color: rgba(26, 26, 46, 0.95);
            border-radius: 10px;
            box-shadow: 
                0 0 20px rgba(255, 75, 43, 0.3), 
                0 0 40px rgba(0, 194, 203, 0.2);
            padding: 2.5rem;
            width: 100%;
            max-width: 420px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(20px);
            flex-shrink: 0;
        }

        .login-container::before {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, transparent 70%, var(--accent-color) 150%);
            opacity: 0.1;
            animation: pulse 4s infinite ease-in-out;
            z-index: -1;
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 0.1; }
            50% { transform: scale(1.1); opacity: 0.15; }
            100% { transform: scale(1); opacity: 0.1; }
        }

        /* Icône The Mind - Carte au-dessus du formulaire */
        .mind-icon {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .card-glow {
            width: 80px;
            height: 120px;
            background: linear-gradient(to bottom, var(--primary-color), var(--secondary-color));
            border-radius: 8px;
            display: inline-block;
            position: relative;
            margin: 0 auto;
            box-shadow: 0 0 15px rgba(255, 75, 43, 0.4);
            animation: cardFloat 3s infinite ease-in-out;
        }

        .card-number {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: var(--dark-color);
            font-size: 1.5rem;
            font-weight: bold;
        }

        @keyframes cardFloat {
            0% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
            100% { transform: translateY(0); }
        }

        /* Container du lapin - bien à droite */
        .rabbit-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .mind-rabbit {
            position: relative;
            margin-bottom: 20px;
        }

        .rabbit-body {
            width: 220px;
            height: 280px;
            background: linear-gradient(145deg, #ffffff, #f0f0f0);
            border-radius: 110px 110px 90px 90px;
            position: relative;
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.4),
                0 0 40px rgba(255, 217, 102, 0.5);
            animation: rabbitBounce 3s infinite ease-in-out;
        }

        /* Oreilles du lapin */
        .rabbit-ears::before,
        .rabbit-ears::after {
            content: '';
            position: absolute;
            width: 48px;
            height: 95px;
            background: linear-gradient(145deg, #ffffff, #f0f0f0);
            border-radius: 35px 35px 12px 12px;
            top: -75px;
            box-shadow: 
                0 15px 30px rgba(0, 0, 0, 0.3),
                inset 0 3px 8px rgba(255, 217, 102, 0.4);
        }

        .rabbit-ears::before {
            left: 35px;
            transform: rotate(-12deg);
        }

        .rabbit-ears::after {
            right: 35px;
            transform: rotate(12deg);
        }

        /* Intérieur des oreilles */
        .rabbit-body::before,
        .rabbit-body::after {
            content: '';
            position: absolute;
            width: 28px;
            height: 48px;
            background: linear-gradient(145deg, var(--primary-color), #ff6b4b);
            border-radius: 20px 20px 8px 8px;
            top: -55px;
            box-shadow: 0 0 15px rgba(255, 75, 43, 0.6);
        }

        .rabbit-body::before {
            left: 44px;
            transform: rotate(-12deg);
        }

        .rabbit-body::after {
            right: 44px;
            transform: rotate(12deg);
        }

        /* Yeux du lapin */
        .eyes {
            position: absolute;
            top: 70px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 35px;
        }

        .eye {
            width: 20px;
            height: 20px;
            background: #333;
            border-radius: 50%;
            animation: rabbitBlink 4s infinite;
            position: relative;
        }

        .eye::after {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 6px;
            height: 6px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 50%;
        }

        /* Nez du lapin */
        .nose {
            position: absolute;
            top: 110px;
            left: 50%;
            transform: translateX(-50%);
            width: 16px;
            height: 12px;
            background: var(--primary-color);
            border-radius: 50%;
            box-shadow: 0 0 20px rgba(255, 75, 43, 0.7);
            animation: noseGlow 2s infinite ease-in-out;
        }

        /* Bouche du lapin */
        .mouth {
            position: absolute;
            top: 130px;
            left: 50%;
            transform: translateX(-50%);
            width: 28px;
            height: 14px;
            border: 3px solid #333;
            border-top: none;
            border-radius: 0 0 28px 28px;
        }

        /* Joues du lapin */
        .cheeks {
            position: absolute;
            top: 95px;
            left: 50%;
            transform: translateX(-50%);
            width: 160px;
            display: flex;
            justify-content: space-between;
        }

        .cheek {
            width: 35px;
            height: 35px;
            background: radial-gradient(circle, rgba(255, 182, 193, 0.7), transparent);
            border-radius: 50%;
            animation: cheekBlush 3s infinite ease-in-out;
        }

        /* Texte sous le lapin */
        .rabbit-text {
            color: var(--secondary-color);
            font-size: 24px;
            font-weight: bold;
            text-align: center;
            letter-spacing: 3px;
            text-shadow: 0 0 15px rgba(255, 217, 102, 0.6);
            animation: textGlow 2s infinite ease-in-out;
        }

        /* Animations */
        @keyframes rabbitBounce {
            0%, 100% { 
                transform: translateY(0) rotate(0deg); 
            }
            25% { 
                transform: translateY(-15px) rotate(-2deg); 
            }
            50% { 
                transform: translateY(-25px) rotate(0deg); 
            }
            75% { 
                transform: translateY(-15px) rotate(2deg); 
            }
        }

        @keyframes rabbitBlink {
            0%, 85%, 100% { 
                transform: scaleY(1); 
            }
            90%, 95% { 
                transform: scaleY(0.1); 
            }
        }

        @keyframes noseGlow {
            0%, 100% { 
                box-shadow: 0 0 15px rgba(255, 75, 43, 0.6);
            }
            50% { 
                box-shadow: 0 0 25px rgba(255, 75, 43, 0.9);
            }
        }

        @keyframes cheekBlush {
            0%, 100% { 
                opacity: 0.6; 
                transform: scale(1);
            }
            50% { 
                opacity: 0.9; 
                transform: scale(1.1);
            }
        }

        @keyframes textGlow {
            0%, 100% { 
                text-shadow: 0 0 10px rgba(255, 217, 102, 0.5);
            }
            50% { 
                text-shadow: 0 0 20px rgba(255, 217, 102, 0.8);
            }
        }

        /* Styles du formulaire */
        h2 {
            color: var(--secondary-color);
            text-align: center;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
            letter-spacing: 2px;
            text-shadow: 0 0 5px rgba(255, 217, 102, 0.5);
        }

        .error {
            background-color: rgba(255, 75, 43, 0.2);
            color: var(--primary-color);
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
            font-size: 0.9rem;
            border-left: 3px solid var(--primary-color);
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.9rem;
            letter-spacing: 1px;
            color: var(--secondary-color);
        }

        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 12px;
            background-color: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 5px;
            color: var(--light-color);
            font-family: 'Orbitron', sans-serif;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        input[type="text"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 10px rgba(0, 194, 203, 0.3);
        }

        button[type="submit"] {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: var(--dark-color);
            border: none;
            border-radius: 5px;
            font-family: 'Orbitron', sans-serif;
            font-weight: bold;
            font-size: 1rem;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 0 10px rgba(255, 75, 43, 0.3);
        }

        button[type="submit"]:hover {
            transform: translateY(-3px);
            box-shadow: 0 0 15px rgba(255, 75, 43, 0.5);
        }

        button[type="submit"]:active {
            transform: translateY(1px);
        }

        /* Responsive design */
        @media (max-width: 1200px) {
            .main-container {
                padding: 20px 25px;
                gap: 35px;
            }
            
            .rabbit-body {
                width: 180px;
                height: 230px;
            }
            
            .rabbit-ears::before,
            .rabbit-ears::after {
                width: 40px;
                height: 80px;
                top: -60px;
            }
            
            .rabbit-body::before,
            .rabbit-body::after {
                width: 24px;
                height: 40px;
                top: -45px;
            }
        }

        @media (max-width: 1024px) {
            .main-container {
                flex-direction: column;
                gap: 50px;
                padding: 20px;
                justify-content: center;
            }
            
            .rabbit-body {
                width: 160px;
                height: 200px;
            }
            
            .rabbit-ears::before,
            .rabbit-ears::after {
                width: 35px;
                height: 70px;
                top: -55px;
            }
            
            .rabbit-body::before,
            .rabbit-body::after {
                width: 20px;
                height: 35px;
                top: -40px;
            }
            
            .rabbit-text {
                font-size: 20px;
            }
        }

        @media (max-width: 768px) {
            .main-container {
                padding: 15px;
                gap: 30px;
            }
            
            .login-container {
                padding: 2rem;
                max-width: 90vw;
            }
            
            .rabbit-body {
                width: 120px;
                height: 150px;
            }
            
            .rabbit-ears::before,
            .rabbit-ears::after {
                width: 25px;
                height: 50px;
                top: -40px;
            }
            
            .rabbit-body::before,
            .rabbit-body::after {
                width: 15px;
                height: 25px;
                top: -30px;
            }
            
            .eyes {
                top: 40px;
                gap: 20px;
            }
            
            .eye {
                width: 12px;
                height: 12px;
            }
            
            .nose {
                top: 65px;
                width: 10px;
                height: 8px;
            }
            
            .mouth {
                top: 80px;
                width: 18px;
                height: 9px;
            }
            
            .cheeks {
                top: 55px;
                width: 90px;
            }
            
            .cheek {
                width: 20px;
                height: 20px;
            }
            
            .rabbit-text {
                font-size: 16px;
                letter-spacing: 2px;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Formulaire de connexion à gauche -->
        <div class="login-container">
            <!-- Carte The Mind au-dessus du formulaire -->
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

        <!-- Lapin The Mind à droite -->
        <div class="rabbit-container">
            <div class="mind-rabbit">
                <div class="rabbit-body">
                    <div class="rabbit-ears"></div>
                    <div class="eyes">
                        <div class="eye"></div>
                        <div class="eye"></div>
                    </div>
                    <div class="cheeks">
                        <div class="cheek"></div>
                        <div class="cheek"></div>
                    </div>
                    <div class="nose"></div>
                    <div class="mouth"></div>
                </div>
            </div>
            <div class="rabbit-text">THE MIND</div>
        </div>
    </div>
</body>
</html>