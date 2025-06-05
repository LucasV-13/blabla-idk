<?php
/**
 * Script de debug pour vérifier l'état des sessions
 * À placer temporairement dans le dossier racine pour diagnostiquer
 */

session_start();

echo "<h1>Debug Session - The Mind</h1>";
echo "<h2>État de la session PHP</h2>";

echo "<h3>Informations générales</h3>";
echo "<ul>";
echo "<li>Session ID: " . session_id() . "</li>";
echo "<li>Session status: " . session_status() . " (1=disabled, 2=active)</li>";
echo "<li>Session name: " . session_name() . "</li>";
echo "<li>Timestamp actuel: " . time() . " (" . date('Y-m-d H:i:s') . ")</li>";
echo "</ul>";

echo "<h3>Contenu de \$_SESSION</h3>";
if (empty($_SESSION)) {
    echo "<p style='color: red;'><strong>SESSION VIDE !</strong></p>";
} else {
    echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>";
    print_r($_SESSION);
    echo "</pre>";
}

echo "<h3>Vérifications d'authentification</h3>";

// Test 1: Vérification basique
$hasUserId = isset($_SESSION['user_id']);
$hasUsername = isset($_SESSION['username']);
$hasExpires = isset($_SESSION['expires']);

echo "<ul>";
echo "<li>user_id présent: " . ($hasUserId ? "✅ Oui (" . $_SESSION['user_id'] . ")" : "❌ Non") . "</li>";
echo "<li>username présent: " . ($hasUsername ? "✅ Oui (" . $_SESSION['username'] . ")" : "❌ Non") . "</li>";
echo "<li>expires présent: " . ($hasExpires ? "✅ Oui (" . $_SESSION['expires'] . " - " . date('Y-m-d H:i:s', $_SESSION['expires']) . ")" : "❌ Non") . "</li>";

if ($hasExpires) {
    $isExpired = $_SESSION['expires'] < time();
    echo "<li>Session expirée: " . ($isExpired ? "❌ Oui" : "✅ Non") . "</li>";
}
echo "</ul>";

// Test 2: Simulation de la logique de Security::checkAuthentication
echo "<h3>Simulation Security::checkAuthentication()</h3>";

$isAuthenticated = isset($_SESSION['user_id']) && 
                  !empty($_SESSION['user_id']) &&
                  isset($_SESSION['username']) &&
                  !empty($_SESSION['username']);

if ($isAuthenticated && isset($_SESSION['expires'])) {
    if ($_SESSION['expires'] < time()) {
        $isAuthenticated = false;
        echo "<p style='color: orange;'>Session expirée, authentification échouée</p>";
    }
}

echo "<p>Résultat d'authentification: " . ($isAuthenticated ? "✅ AUTHENTIFIÉ" : "❌ NON AUTHENTIFIÉ") . "</p>";

echo "<h3>Configuration PHP</h3>";
echo "<ul>";
echo "<li>session.cookie_httponly: " . ini_get('session.cookie_httponly') . "</li>";
echo "<li>session.cookie_secure: " . ini_get('session.cookie_secure') . "</li>";
echo "<li>session.use_strict_mode: " . ini_get('session.use_strict_mode') . "</li>";
echo "<li>session.save_path: " . session_save_path() . "</li>";
echo "</ul>";

echo "<h3>Cookies</h3>";
if (empty($_COOKIE)) {
    echo "<p>Aucun cookie présent</p>";
} else {
    echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>";
    print_r($_COOKIE);
    echo "</pre>";
}

echo "<h3>Headers envoyés</h3>";
$headers = headers_list();
if (empty($headers)) {
    echo "<p>Aucun header envoyé</p>";
} else {
    echo "<ul>";
    foreach ($headers as $header) {
        echo "<li>" . htmlspecialchars($header) . "</li>";
    }
    echo "</ul>";
}

echo "<h3>Actions de test</h3>";
echo "<form method='post' style='margin: 20px 0;'>";
echo "<button type='submit' name='action' value='clear_session'>Vider la session</button> ";
echo "<button type='submit' name='action' value='create_test_session'>Créer session test</button>";
echo "</form>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'clear_session') {
        session_destroy();
        echo "<p style='color: green;'>Session détruite. <a href=''>Rafraîchir</a></p>";
    } elseif ($_POST['action'] === 'create_test_session') {
        $_SESSION['user_id'] = 1;
        $_SESSION['username'] = 'test_user';
        $_SESSION['role'] = 'joueur';
        $_SESSION['email'] = 'test@example.com';
        $_SESSION['avatar'] = '👤';
        $_SESSION['expires'] = time() + 3600;
        echo "<p style='color: green;'>Session test créée. <a href=''>Rafraîchir</a></p>";
    }
}

echo "<p style='margin-top: 30px; font-size: 12px; color: #666;'>";
echo "Pour tester votre connexion :<br>";
echo "1. Connectez-vous normalement<br>";
echo "2. Revenez sur cette page pour voir l'état de la session<br>";
echo "3. Supprimez ce fichier une fois le debug terminé";
echo "</p>";
?>