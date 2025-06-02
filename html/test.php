<?php try { new PDO("mysql:host=localhost;dbname=bddthemind", "user", "Eloi2023*");
     echo "BDD OK"; } catch(PDOException $e) { echo "ERREUR"; } ?>
<?php
// check_files.php - Script de vérification de l'existence des fichiers critiques

// Liste des fichiers à vérifier
$files_to_check = [
    // Fichiers PHP
    'plateau/actions/admin_action.php',
    'plateau/actions/get_game_state.php',
    'plateau/actions/play_card.php',
    'plateau/actions/use_shuriken.php',
    
    // Fichiers JavaScript
    'java/jsJeu.js',
    
    // Fichiers sons
    'sounds/card_select.mp3',
    'sounds/card_play.mp3',
    'sounds/error.mp3',
    'sounds/shuriken.mp3',
    'sounds/game_start.mp3',
    'sounds/level_up.mp3',
    'sounds/level_complete.mp3',
    'sounds/win.mp3',
    'sounds/lose.mp3',
    
    // Fichiers images
    'images/heart.jpg',
    'images/heart-empty.jpg',
    'images/shuriken.png',
    'images/card-background.png',
    'images/background.jpg',
    
    // Fichiers CSS
    'style/styleJeu.css',
    'style/styleMenu.css',
    
    // Fichiers de langue
    'languages/fr.php',
    'languages/en.php',
];

// Vérification de chaque fichier
echo "<h1>Vérification des fichiers du jeu</h1>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Fichier</th><th>Statut</th><th>Action suggérée</th></tr>";

$all_files_exist = true;

foreach ($files_to_check as $file) {
    $exists = file_exists($file);
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($file) . "</td>";
    
    if ($exists) {
        echo "<td style='color: green;'>Existe</td>";
        echo "<td>-</td>";
    } else {
        $all_files_exist = false;
        echo "<td style='color: red;'>Manquant</td>";
        
        // Suggérer une action en fonction du type de fichier
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        $suggestion = "";
        
        switch ($extension) {
            case 'php':
                $suggestion = "Créer ou déplacer le fichier PHP. Vérifiez que le chemin du dossier est correct.";
                break;
            case 'js':
                $suggestion = "Créer ou déplacer le fichier JavaScript.";
                break;
            case 'mp3':
                $suggestion = "Télécharger ou créer le fichier son.";
                break;
            case 'png':
            case 'jpg':
                $suggestion = "Télécharger ou créer l'image.";
                break;
            case 'css':
                $suggestion = "Créer ou déplacer le fichier CSS.";
                break;
            default:
                $suggestion = "Créer ou déplacer le fichier.";
        }
        
        echo "<td>" . $suggestion . "</td>";
    }
    
    echo "</tr>";
}

echo "</table>";

// Conseils basés sur les résultats
echo "<h2>Conseils pour corriger les problèmes</h2>";

if (!$all_files_exist) {
    echo "<ol>";
    echo "<li>Vérifiez que tous les dossiers requis existent (plateau/actions, java, sounds, images, style, languages).</li>";
    echo "<li>Créez les dossiers manquants si nécessaire.</li>";
    echo "<li>Déplacez les fichiers PHP d'actions dans le dossier plateau/actions/.</li>";
    echo "<li>Vérifiez que les fichiers sons sont dans le dossier sounds/.</li>";
    echo "<li>Vérifiez que les fichiers images sont dans le dossier images/.</li>";
    echo "</ol>";
    
    echo "<p>Structure recommandée des dossiers:</p>";
    echo "<pre>
    racine_du_site/
    ├── plateau/
    │   └── actions/
    │       ├── admin_action.php
    │       ├── get_game_state.php
    │       ├── play_card.php
    │       └── use_shuriken.php
    ├── java/
    │   └── jsJeu.js
    ├── sounds/
    │   ├── card_select.mp3
    │   ├── card_play.mp3
    │   ├── error.mp3
    │   └── etc...
    ├── images/
    │   ├── heart.jpg
    │   ├── heart-empty.jpg
    │   └── etc...
    ├── style/
    │   └── styleJeu.css
    └── languages/
        ├── fr.php
        └── en.php
    </pre>";
} else {
    echo "<p>Tous les fichiers nécessaires existent. Si vous rencontrez encore des problèmes, vérifiez:</p>";
    echo "<ol>";
    echo "<li>Les permissions des fichiers (ils doivent être lisibles par le serveur web).</li>";
    echo "<li>La syntaxe des fichiers pour détecter d'éventuelles erreurs.</li>";
    echo "<li>Les conflits de noms de fonctions ou de variables.</li>";
    echo "</ol>";
}
?>