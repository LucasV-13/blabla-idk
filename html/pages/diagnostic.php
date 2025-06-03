<?php
/**
 * SCRIPT DE DIAGNOSTIC POUR LE JEU THE MIND
 * À placer dans le dossier html/pages/ temporairement
 */

session_start();
echo "<h1>🔍 Diagnostic du Jeu The Mind</h1>";

// 1. VÉRIFICATION DES FICHIERS PHP D'ACTIONS
echo "<h2>📁 Vérification des fichiers d'actions</h2>";
$action_files = [
    'plateau/actions/play_card.php',
    'plateau/actions/use_shuriken.php', 
    'plateau/actions/admin_action.php',
    'plateau/actions/get_game_state.php'
];

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Fichier</th><th>Statut</th><th>Taille</th><th>Dernière modification</th></tr>";

$all_files_exist = true;
foreach ($action_files as $file) {
    $exists = file_exists($file);
    echo "<tr>";
    echo "<td>$file</td>";
    
    if ($exists) {
        echo "<td style='color: green;'>✅ Existe</td>";
        echo "<td>" . filesize($file) . " bytes</td>";
        echo "<td>" . date('Y-m-d H:i:s', filemtime($file)) . "</td>";
    } else {
        $all_files_exist = false;
        echo "<td style='color: red;'>❌ Manquant</td>";
        echo "<td colspan='2'>-</td>";
    }
    echo "</tr>";
}
echo "</table>";

// 2. VÉRIFICATION DE LA SESSION
echo "<h2>🔐 Vérification de la session</h2>";
echo "<ul>";
echo "<li>Session ID: " . session_id() . "</li>";
echo "<li>User ID: " . ($_SESSION['user_id'] ?? 'NON DÉFINI') . "</li>";
echo "<li>Username: " . ($_SESSION['username'] ?? 'NON DÉFINI') . "</li>";
echo "<li>Role: " . ($_SESSION['role'] ?? 'NON DÉFINI') . "</li>";
echo "<li>CSRF Token: " . ($_SESSION['csrf_token'] ?? 'NON DÉFINI') . "</li>";
echo "</ul>";

// 3. TEST DE CONNEXION À LA BASE DE DONNÉES
echo "<h2>🗃️ Test de connexion à la base de données</h2>";
try {
    include('../connexion/connexion.php');
    echo "<p style='color: green;'>✅ Connexion à la base de données réussie</p>";
    
    // Test d'une requête simple
    $test_query = "SELECT COUNT(*) as count FROM Parties";
    $stmt = $conn->prepare($test_query);
    $stmt->execute();
    $result = $stmt->fetch();
    echo "<p>Nombre de parties dans la base : " . $result['count'] . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur de connexion à la base de données: " . $e->getMessage() . "</p>";
}

// 4. SIMULATION D'UN APPEL AJAX
echo "<h2>🌐 Test des endpoints d'action</h2>";

if ($all_files_exist && isset($_SESSION['user_id'])) {
    echo "<h3>Test de play_card.php</h3>";
    
    // Simuler les données POST
    $_POST['csrf_token'] = $_SESSION['csrf_token'] ?? 'test';
    $_POST['card_id'] = 1;
    $_POST['partie_id'] = 1;
    
    // Capturer la sortie
    ob_start();
    
    try {
        // Inclure le fichier sans redirection
        $original_exit = 'exit';
        
        // Remplacer temporairement exit par une exception
        function custom_exit($status = 0) {
            throw new Exception("Script ended with exit($status)");
        }
        
        include('plateau/actions/play_card.php');
        $output = ob_get_contents();
        
    } catch (Exception $e) {
        $output = ob_get_contents();
        if (strpos($e->getMessage(), 'Script ended') === false) {
            $output .= "ERREUR: " . $e->getMessage();
        }
    }
    
    ob_end_clean();
    
    echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>";
    echo "Sortie de play_card.php:\n";
    echo htmlspecialchars($output);
    echo "</pre>";
    
    // Vérifier si c'est du JSON valide
    $json_data = json_decode($output, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<p style='color: green;'>✅ Sortie JSON valide</p>";
        echo "<pre>" . print_r($json_data, true) . "</pre>";
    } else {
        echo "<p style='color: red;'>❌ Sortie JSON invalide. Erreur: " . json_last_error_msg() . "</p>";
    }
    
} else {
    echo "<p style='color: orange;'>⚠️ Impossible de tester - fichiers manquants ou session invalide</p>";
}

// 5. VÉRIFICATION DES FICHIERS JAVASCRIPT
echo "<h2>📜 Vérification des fichiers JavaScript</h2>";
$js_files = [
    '../assets/js/jsJeu.js',
    'assets/js/jsJeu.js',
    '../java/jsJeu.js' // Ancien chemin possible
];

foreach ($js_files as $js_file) {
    if (file_exists($js_file)) {
        echo "<p style='color: green;'>✅ Trouvé: $js_file (" . filesize($js_file) . " bytes)</p>";
        
        // Vérifier le contenu du fichier
        $content = file_get_contents($js_file);
        if (strpos($content, 'play_card.php') !== false) {
            echo "<p>Le fichier contient des références aux actions PHP</p>";
            
            // Vérifier les chemins dans le JS
            if (strpos($content, "'plateau/actions/") !== false) {
                echo "<p style='color: orange;'>⚠️ PROBLÈME DÉTECTÉ: Chemins relatifs incorrects dans le JS</p>";
                echo "<p>Le fichier utilise 'plateau/actions/' mais devrait utiliser '../plateau/actions/' ou 'pages/plateau/actions/'</p>";
            }
        }
    } else {
        echo "<p style='color: red;'>❌ Non trouvé: $js_file</p>";
    }
}

// 6. RECOMMANDATIONS
echo "<h2>💡 Recommandations de correction</h2>";
echo "<div style='background: #e3f2fd; padding: 15px; border-left: 4px solid #2196f3;'>";

if (!$all_files_exist) {
    echo "<h4>1. Fichiers manquants :</h4>";
    echo "<p>Créez le dossier <code>plateau/actions/</code> et placez-y les fichiers PHP d'actions.</p>";
}

echo "<h4>2. Correction des chemins JavaScript :</h4>";
echo "<p>Dans votre fichier <code>jsJeu.js</code>, remplacez :</p>";
echo "<ul>";
echo "<li><code>fetch('plateau/actions/play_card.php'</code> par <code>fetch('../plateau/actions/play_card.php'</code></li>";
echo "<li><code>fetch('plateau/actions/use_shuriken.php'</code> par <code>fetch('../plateau/actions/use_shuriken.php'</code></li>";
echo "<li><code>fetch('plateau/actions/admin_action.php'</code> par <code>fetch('../plateau/actions/admin_action.php'</code></li>";
echo "</ul>";

echo "<h4>3. Debug en temps réel :</h4>";
echo "<p>Ajoutez ce code dans votre page de jeu pour debug :</p>";
echo "<pre style='background: #fff; padding: 10px;'>";
echo htmlspecialchars("
<script>
// Debug console pour voir les erreurs
console.log('=== DEBUG THE MIND ===');
console.log('CSRF Token:', document.getElementById('csrf_token')?.value);
console.log('Partie ID:', document.getElementById('partie_id')?.value);
console.log('User ID:', document.getElementById('user_id')?.value);

// Intercepter les erreurs fetch
const originalFetch = window.fetch;
window.fetch = function(...args) {
    console.log('FETCH REQUEST:', args[0], args[1]);
    return originalFetch.apply(this, args)
        .then(response => {
            console.log('FETCH RESPONSE:', response.status, response.url);
            return response;
        })
        .catch(error => {
            console.error('FETCH ERROR:', error);
            throw error;
        }); 3
};
</script>
");
echo "</pre>";

echo "</div>";

// 7. TEST RAPIDE DES PERMISSIONS
echo "<h2>🔑 Test des permissions</h2>";
$test_dirs = ['plateau', 'plateau/actions', 'assets', 'assets/js'];
foreach ($test_dirs as $dir) {
    if (is_dir($dir)) {
        $writable = is_writable($dir);
        echo "<p>" . ($writable ? "✅" : "⚠️") . " $dir - " . ($writable ? "Écriture OK" : "Lecture seule") . "</p>";
    } else {
        echo "<p>❌ Dossier manquant: $dir</p>";
    }
}

echo "<hr>";
echo "<p><strong>Pour utiliser ce diagnostic :</strong></p>";
echo "<ol>";
echo "<li>Regardez les sections en rouge (❌) - ce sont les problèmes à corriger</li>";
echo "<li>Suivez les recommandations de correction</li>";
echo "<li>Testez votre jeu après chaque correction</li>";
echo "<li>Supprimez ce fichier de diagnostic une fois terminé</li>";
echo "</ol>";
?>