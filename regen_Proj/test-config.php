<?php
/**
 * Fichier de test pour vérifier la configuration
 * À placer à la racine du projet pour tester
 */

echo "<h1>Test de Configuration - The Mind</h1>";

// Test 1: Vérifier l'existence des fichiers
echo "<h2>1. Vérification des fichiers</h2>";
$files_to_check = [
    'config/database.php',
    'config/session.php', 
    'config/constants.php',
    'languages/fr.php',
    'languages/en.php'
];

foreach ($files_to_check as $file) {
    $exists = file_exists($file);
    $color = $exists ? 'green' : 'red';
    $status = $exists ? '✅ Existe' : '❌ Manquant';
    echo "<p style='color: $color;'>$file - $status</p>";
}

// Test 2: Inclusion des fichiers de config
echo "<h2>2. Test d'inclusion des fichiers</h2>";

try {
    require_once 'config/constants.php';
    echo "<p style='color: green;'>✅ Constants chargées</p>";
    echo "<p>SITE_NAME: " . (defined('SITE_NAME') ? SITE_NAME : 'Non défini') . "</p>";
    echo "<p>BASE_URL: " . (defined('BASE_URL') ? BASE_URL : 'Non défini') . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur constants: " . $e->getMessage() . "</p>";
}

try {
    require_once 'config/database.php';
    echo "<p style='color: green;'>✅ Database classe chargée</p>";
    echo "<p>Classe Database: " . (class_exists('Database') ? 'Existe' : 'N\'existe pas') . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur database: " . $e->getMessage() . "</p>";
}

try {
    require_once 'config/session.php';
    echo "<p style='color: green;'>✅ SessionManager classe chargée</p>";
    echo "<p>Classe SessionManager: " . (class_exists('SessionManager') ? 'Existe' : 'N\'existe pas') . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur session: " . $e->getMessage() . "</p>";
}

// Test 3: Test de la base de données
echo "<h2>3. Test de connexion à la base de données</h2>";

try {
    if (class_exists('Database')) {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        echo "<p style='color: green;'>✅ Connexion DB réussie</p>";
        
        // Test d'une requête simple
        $stmt = $conn->query("SELECT 1 as test");
        $result = $stmt->fetch();
        echo "<p style='color: green;'>✅ Requête test réussie: " . $result['test'] . "</p>";
    } else {
        echo "<p style='color: red;'>❌ Classe Database non disponible</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur DB: " . $e->getMessage() . "</p>";
}

// Test 4: Test du SessionManager
echo "<h2>4. Test du SessionManager</h2>";

try {
    if (class_exists('SessionManager')) {
        $sessionManager = SessionManager::getInstance();
        echo "<p style='color: green;'>✅ SessionManager instancié</p>";
        
        // Test des méthodes
        $methods = ['startSession', 'generateCSRFToken', 'isAuthenticated'];
        foreach ($methods as $method) {
            if (method_exists($sessionManager, $method)) {
                echo "<p style='color: green;'>✅ Méthode $method existe</p>";
            } else {
                echo "<p style='color: red;'>❌ Méthode $method manquante</p>";
            }
        }
    } else {
        echo "<p style='color: red;'>❌ Classe SessionManager non disponible</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur SessionManager: " . $e->getMessage() . "</p>";
}

// Test 5: Variables d'environnement
echo "<h2>5. Variables d'environnement</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>Script Path: " . __FILE__ . "</p>";
echo "<p>Working Directory: " . getcwd() . "</p>";

// Test 6: Permissions
echo "<h2>6. Test des permissions</h2>";
$dirs_to_check = ['config', 'languages', 'assets', 'pages'];
foreach ($dirs_to_check as $dir) {
    if (is_dir($dir)) {
        $readable = is_readable($dir);
        $writable = is_writable($dir);
        echo "<p>$dir - Lecture: " . ($readable ? '✅' : '❌') . " Écriture: " . ($writable ? '✅' : '❌') . "</p>";
    } else {
        echo "<p style='color: red;'>$dir - ❌ Dossier manquant</p>";
    }
}

echo "<h2>✅ Test terminé</h2>";
echo "<p><strong>Instructions:</strong></p>";
echo "<ul>";
echo "<li>Placez ce fichier à la racine de votre projet</li>";
echo "<li>Accédez-y via votre navigateur</li>";
echo "<li>Vérifiez que tous les tests passent</li>";
echo "<li>Supprimez ce fichier après les tests</li>";
echo "</ul>";
?>