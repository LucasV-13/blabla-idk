<?php
// Script de debug pour vérifier les permissions et l'état des cartes
session_start();
include('../connexion/connexion.php');

$partie_id = isset($_GET['partie_id']) ? (int)$_GET['partie_id'] : 1; // Remplacez par votre ID de partie
$user_id = $_SESSION['user_id'];

echo "<h2>Debug - Vérification des permissions pour jouer une carte</h2>";
echo "<p><strong>Utilisateur connecté:</strong> " . $_SESSION['username'] . " (ID: $user_id)</p>";
echo "<p><strong>Partie:</strong> $partie_id</p>";

// 1. Vérifier l'état de la partie
echo "<h3>1. État de la partie</h3>";
$partieQuery = "SELECT * FROM Parties WHERE id = :partie_id";
$partieStmt = $conn->prepare($partieQuery);
$partieStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
$partieStmt->execute();
$partie = $partieStmt->fetch(PDO::FETCH_ASSOC);

if ($partie) {
    echo "<p>✅ Partie trouvée</p>";
    echo "<p><strong>Statut:</strong> " . $partie['status'] . "</p>";
    echo "<p><strong>Niveau:</strong> " . $partie['niveau'] . "</p>";
    echo "<p><strong>Vies restantes:</strong> " . $partie['vies_restantes'] . "</p>";
    
    if ($partie['status'] !== 'en_cours') {
        echo "<p>❌ <strong>PROBLÈME:</strong> La partie n'est pas en cours</p>";
    } else {
        echo "<p>✅ La partie est en cours</p>";
    }
} else {
    echo "<p>❌ <strong>ERREUR:</strong> Partie non trouvée</p>";
    exit;
}

// 2. Vérifier si l'utilisateur participe à la partie
echo "<h3>2. Participation à la partie</h3>";
$participationQuery = "SELECT * FROM Utilisateurs_parties WHERE id_partie = :partie_id AND id_utilisateur = :user_id";
$participationStmt = $conn->prepare($participationQuery);
$participationStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
$participationStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$participationStmt->execute();
$participation = $participationStmt->fetch(PDO::FETCH_ASSOC);

if ($participation) {
    echo "<p>✅ Utilisateur participe à la partie</p>";
    echo "<p><strong>Position:</strong> " . $participation['position'] . "</p>";
} else {
    echo "<p>❌ <strong>PROBLÈME:</strong> Utilisateur ne participe pas à la partie</p>";
}

// 3. Vérifier les cartes de l'utilisateur
echo "<h3>3. Cartes de l'utilisateur</h3>";
$cartesQuery = "SELECT * FROM Cartes WHERE id_partie = :partie_id AND id_utilisateur = :user_id ORDER BY valeur";
$cartesStmt = $conn->prepare($cartesQuery);
$cartesStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
$cartesStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$cartesStmt->execute();
$cartes = $cartesStmt->fetchAll(PDO::FETCH_ASSOC);

if (count($cartes) > 0) {
    echo "<p>✅ Cartes trouvées: " . count($cartes) . "</p>";
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Valeur</th><th>État</th><th>Date Action</th></tr>";
    foreach ($cartes as $carte) {
        echo "<tr>";
        echo "<td>" . $carte['id'] . "</td>";
        echo "<td>" . $carte['valeur'] . "</td>";
        echo "<td>" . $carte['etat'] . "</td>";
        echo "<td>" . $carte['date_action'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Compter les cartes en main
    $cartesEnMain = array_filter($cartes, function($carte) {
        return $carte['etat'] === 'en_main';
    });
    
    echo "<p><strong>Cartes en main:</strong> " . count($cartesEnMain) . "</p>";
    
    if (count($cartesEnMain) === 0) {
        echo "<p>❌ <strong>PROBLÈME:</strong> Aucune carte en main</p>";
    }
} else {
    echo "<p>❌ <strong>PROBLÈME:</strong> Aucune carte trouvée pour cet utilisateur</p>";
}

// 4. Vérifier tous les joueurs de la partie
echo "<h3>4. Tous les joueurs de la partie</h3>";
$joueursQuery = "SELECT u.id, u.identifiant, up.position,
                 (SELECT COUNT(*) FROM Cartes WHERE id_utilisateur = u.id AND id_partie = :partie_id AND etat = 'en_main') as cartes_en_main
                 FROM Utilisateurs u
                 JOIN Utilisateurs_parties up ON u.id = up.id_utilisateur
                 WHERE up.id_partie = :partie_id
                 ORDER BY up.position";
$joueursStmt = $conn->prepare($joueursQuery);
$joueursStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
$joueursStmt->execute();
$joueurs = $joueursStmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1'>";
echo "<tr><th>ID</th><th>Nom</th><th>Position</th><th>Cartes en main</th><th>Rôle</th></tr>";
foreach ($joueurs as $joueur) {
    $isCurrentUser = ($joueur['id'] == $user_id) ? " (VOUS)" : "";
    echo "<tr>";
    echo "<td>" . $joueur['id'] . "</td>";
    echo "<td>" . $joueur['identifiant'] . $isCurrentUser . "</td>";
    echo "<td>" . $joueur['position'] . "</td>";
    echo "<td>" . $joueur['cartes_en_main'] . "</td>";
    echo "<td>" . ($joueur['position'] == 1 ? "Admin" : "Joueur") . "</td>";
    echo "</tr>";
}
echo "</table>";

// 5. Vérifier la session
echo "<h3>5. Informations de session</h3>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>User ID:</strong> " . ($_SESSION['user_id'] ?? 'NON DÉFINI') . "</p>";
echo "<p><strong>Username:</strong> " . ($_SESSION['username'] ?? 'NON DÉFINI') . "</p>";
echo "<p><strong>Role:</strong> " . ($_SESSION['role'] ?? 'NON DÉFINI') . "</p>";
echo "<p><strong>CSRF Token:</strong> " . (isset($_SESSION['csrf_token']) ? 'DÉFINI' : 'NON DÉFINI') . "</p>";

// 6. Test de requête de jeu de carte (simulation)
echo "<h3>6. Simulation de jeu de carte</h3>";
$carteEnMain = null;
foreach ($cartes as $carte) {
    if ($carte['etat'] === 'en_main') {
        $carteEnMain = $carte;
        break;
    }
}

if ($carteEnMain) {
    echo "<p>🎯 Tentative de jeu de la carte ID: " . $carteEnMain['id'] . " (valeur: " . $carteEnMain['valeur'] . ")</p>";
    
    // Simuler les vérifications de play_card.php
    
    // Vérifier la dernière carte jouée
    $derniereCarteQuery = "SELECT MAX(valeur) as max_valeur FROM Cartes WHERE id_partie = :partie_id AND etat = 'jouee'";
    $derniereCarteStmt = $conn->prepare($derniereCarteQuery);
    $derniereCarteStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $derniereCarteStmt->execute();
    $maxValeurJouee = $derniereCarteStmt->fetch(PDO::FETCH_ASSOC)['max_valeur'] ?: 0;
    
    echo "<p><strong>Dernière carte jouée:</strong> $maxValeurJouee</p>";
    
    if ($carteEnMain['valeur'] <= $maxValeurJouee) {
        echo "<p>❌ <strong>PROBLÈME:</strong> Cette carte ({$carteEnMain['valeur']}) est inférieure ou égale à la dernière jouée ($maxValeurJouee)</p>";
    } else {
        echo "<p>✅ Cette carte peut être jouée selon l'ordre</p>";
    }
    
    // Vérifier s'il y a des cartes plus petites chez d'autres joueurs
    $cartesPlusPetitesQuery = "SELECT COUNT(*) as nb FROM Cartes WHERE id_partie = :partie_id AND etat = 'en_main' AND valeur < :valeur";
    $cartesPlusPetitesStmt = $conn->prepare($cartesPlusPetitesQuery);
    $cartesPlusPetitesStmt->bindParam(':partie_id', $partie_id, PDO::PARAM_INT);
    $cartesPlusPetitesStmt->bindParam(':valeur', $carteEnMain['valeur'], PDO::PARAM_INT);
    $cartesPlusPetitesStmt->execute();
    $nbPlusPetites = $cartesPlusPetitesStmt->fetch(PDO::FETCH_ASSOC)['nb'];
    
    echo "<p><strong>Cartes plus petites en jeu:</strong> $nbPlusPetites</p>";
    
    if ($nbPlusPetites > 0) {
        echo "<p>⚠️ Il y a des cartes plus petites en jeu - cela causera une perte de vie</p>";
    } else {
        echo "<p>✅ Aucune carte plus petite - jeu sécurisé</p>";
    }
} else {
    echo "<p>❌ Aucune carte en main pour tester</p>";
}

echo "<h3>7. URL et chemins</h3>";
echo "<p><strong>URL actuelle:</strong> " . $_SERVER['REQUEST_URI'] . "</p>";
echo "<p><strong>Document root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p><strong>Script name:</strong> " . $_SERVER['SCRIPT_NAME'] . "</p>";

// 8. Recommandations
echo "<h3>🔧 Actions recommandées</h3>";
if ($partie['status'] !== 'en_cours') {
    echo "<p>1. Démarrer la partie (bouton admin)</p>";
}
if (!$participation) {
    echo "<p>2. Ajouter l'utilisateur à la partie</p>";
}
if (count($cartesEnMain) === 0) {
    echo "<p>3. Distribuer des cartes à l'utilisateur</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; margin: 10px 0; }
th, td { padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
h2 { color: #333; }
h3 { color: #666; border-bottom: 1px solid #ccc; }
p { margin: 5px 0; }
</style>