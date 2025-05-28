<?php
session_start();

// Vérification de l'authentification
if (!isset($_SESSION['user_id']) || (isset($_SESSION['expires']) && $_SESSION['expires'] < time())) {
    http_response_code(401); // Non autorisé
    echo json_encode(["error" => "Session expirée"]);
    exit();
}

include('connexion/connexion.php');

// Récupérer la liste des parties disponibles
try {
    $sql = "SELECT Parties.id, Parties.niveau, Parties.status, Parties.nombre_joueurs,
       (SELECT COUNT(*) FROM Utilisateurs_Parties WHERE id_partie = Parties.id) as joueurs_actuels,
       (SELECT identifiant FROM Utilisateurs WHERE id = 
            (SELECT id_utilisateur FROM Utilisateurs_Parties WHERE id_partie = Parties.id AND
            EXISTS (
                SELECT 1 FROM Roles_Permissions as Role_perm
                JOIN Permissions as Perm ON Role_perm.id_permissions = Perm.id
                WHERE Role_perm.id_role = Utilisateurs.id_role AND Perm.nom = 'admin_partie'
            ) LIMIT 1)
       ) as admin_nom
        FROM Parties
        ORDER BY Parties.date_début DESC;";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $parties = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Pour chaque partie, vérifier si l'utilisateur y participe
    foreach ($parties as &$partie) {
        $check_user = $conn->prepare("SELECT * FROM Utilisateurs_Parties WHERE id_utilisateur = :user_id AND id_partie = :partie_id");
        $check_user->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $check_user->bindParam(':partie_id', $partie["id"], PDO::PARAM_INT);
        $check_user->execute();
        $partie['user_joined'] = ($check_user->rowCount() > 0);
    }
    
    // Renvoyer les données en JSON
    header('Content-Type: application/json');
    echo json_encode($parties);
    
} catch (PDOException $e) {
    http_response_code(500); // Erreur serveur
    echo json_encode(["error" => "Erreur de récupération des données"]);
    error_log("Erreur get_parties: " . $e->getMessage());
}
?>