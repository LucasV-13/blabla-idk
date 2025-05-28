<?php
session_start();

// Vérification de l'authentification et expiration de session
if (!isset($_SESSION['user_id']) || (isset($_SESSION['expires']) && $_SESSION['expires'] < time())) {
    // Détruire la session si elle a expiré
    session_destroy();
    header("Location: index.php");
    exit();
}

// Prolonger la session d'une heure
$_SESSION['expires'] = time() + (60 * 60);

// Protection CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include('connexion/connexion.php');

// Gestion des textes multilingues
$language = isset($_SESSION['language']) ? $_SESSION['language'] : 'fr';
include('languages/' . $language . '.php');

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
    
} catch (PDOException $e) {
    error_log("Erreur dashboard: " . $e->getMessage());
    // Continuer avec un résultat vide si nécessaire
    $parties = [];
}

// Inclure le menu après avoir défini les variables
include('menu/menu.php');
?>

<!DOCTYPE html>
<html lang="<?php echo $language; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $texts['dashboard']; ?></title>
    <link rel="stylesheet" href="style/styleDash.css">
    <link rel="stylesheet" href="style/styleMenu.css">
    <script src="java/jsMenu.js" defer></script>
</head>
<body>
<!---- Tableau de Bord --->
    <h1><?php echo $texts['dashboard']; ?></h1>

    <!-- Champ caché pour le jeton CSRF -->
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

    <div class="table-container">
        <table>
          <!--- Colonnes du Tableau --->
          <thead>
            <tr>
              <th><?php echo $texts['game_name_column']; ?></th>
              <th><?php echo $texts['players_column']; ?></th>
              <th><?php echo $texts['admin_column']; ?></th>
              <th><?php echo $texts['level_column']; ?></th>
              <th><?php echo $texts['status_column']; ?></th>
              <th><?php echo $texts['action_column']; ?></th>
            </tr>
          </thead>
          
          <!--- Lignes du Tableau --->
          <tbody>
            <?php
            if (count($parties) > 0) {
                foreach($parties as $row) {
                    echo "<tr>";
                    echo "<td>" . $texts['game_name_column'] . " " . htmlspecialchars($row["id"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["joueurs_actuels"]) . "/" . htmlspecialchars($row["nombre_joueurs"]) . "</td>";
                    echo "<td>" . ($row["admin_nom"] ? htmlspecialchars($row["admin_nom"]) : "Non assigné") . "</td>";
                    echo "<td>" . htmlspecialchars($row["niveau"]) . "</td>";
                    
                    // Traduire le statut
                    $statut = $texts['status_waiting']; // Valeur par défaut
                    switch($row["status"]) {
                        case "en_attente": $statut = $texts['status_waiting']; break;
                        case "en_cours": $statut = $texts['status_in_progress']; break;
                        case "terminee": $statut = $texts['status_completed']; break;
                        case "complete": $statut = $texts['status_full']; break;
                        case "pause": $statut = $texts['status_paused']; break;
                        case "annulee": $statut = $texts['status_cancelled']; break;
                    }
                    echo "<td>" . $statut . "</td>";
                    
                    // Bouton d'action
                    echo "<td>";
                    if ($row["status"] == "en_attente" && $row["joueurs_actuels"] < $row["nombre_joueurs"]) {
                        echo "<button onclick=\"joinSpecificGame(" . htmlspecialchars($row["id"]) . ")\" class=\"join-btn\">" . $texts['join'] . "</button>";
                    } else if ($row["status"] == "en_cours") {
                        // Vérifier si l'utilisateur est dans cette partie avec PDO
                        try {
                            $check_user = $conn->prepare("SELECT * FROM Utilisateurs_Parties WHERE id_utilisateur = :user_id AND id_partie = :partie_id");
                            $check_user->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
                            $check_user->bindParam(':partie_id', $row["id"], PDO::PARAM_INT);
                            $check_user->execute();
                            
                            if ($check_user->rowCount() > 0) {
                                echo "<a href=\"plateau-jeu.php?partie_id=" . htmlspecialchars($row["id"]) . "\" class=\"play-btn\">" . $texts['play_card'] . "</a>";
                            } else {
                                echo "<span>" . $texts['status_in_progress'] . "</span>";
                            }
                        } catch (PDOException $e) {
                            error_log("Erreur vérification utilisateur: " . $e->getMessage());
                            echo "<span>" . $texts['error'] . "</span>";
                        }
                    } else {
                        echo "<span>-</span>";
                    }
                    echo "</td>";
                    
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='6'>" . $texts['no_games_available'] . "</td></tr>";
            }
            ?>
          </tbody>
        </table>
      </div>

<!---- Boutons Inférieurs de Rafraîchir, Rejoindre et Filtrer --->
      <div class="button-container">
        <button onclick="refreshPage()"><?php echo $texts['refresh']; ?></button>
        <button onclick="joinGame()"><?php echo $texts['join_game']; ?></button>
        <button onclick="filterGames()"><?php echo $texts['filter_games']; ?></button>
        <?php if (strtolower($_SESSION['role']) == 'admin'): ?>
        <button onclick="window.location.href='test_plateau.php'" class="admin-btn"><?php echo $texts['administration']; ?></button>
        <?php endif; ?>
      </div>

      <?php
      // Modal pour rejoindre une partie (sera affiché par JavaScript)
      ?>
      <div id="joinGameModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('joinGameModal')">&times;</span>
            <h2><?php echo $texts['join_game']; ?></h2>
            <form id="joinGameForm" method="post" action="join_game.php">
                <div class="form-group">
                    <label for="gameSelect"><?php echo $texts['select_game']; ?></label>
                    <select id="gameSelect" name="game_id">
                        <?php
                        // Parcourir les parties disponibles
                        foreach($parties as $row) {
                            if ($row["status"] == "en_attente" && $row["joueurs_actuels"] < $row["nombre_joueurs"]) {
                                echo "<option value='" . htmlspecialchars($row["id"]) . "'>" . $texts['game_name_column'] . " " . 
                                     htmlspecialchars($row["id"]) . " (" . $texts['level'] . " " . 
                                     htmlspecialchars($row["niveau"]) . ")</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <!-- Ajouter un jeton CSRF pour sécuriser le formulaire -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <button type="submit"><?php echo $texts['join']; ?></button>
            </form>
        </div>
      </div>
      
      <script>
      // Intervalle de rafraîchissement (en millisecondes)
      const REFRESH_INTERVAL = 30000; // 30 secondes
      let refreshTimer;

      // Fonction pour actualiser les données des parties sans recharger la page entière
      function loadParties() {
          fetch('get_parties.php')
              .then(response => {
                  if (!response.ok) {
                      throw new Error('Erreur réseau');
                  }
                  return response.json();
              })
              .then(parties => {
                  updatePartiesTable(parties);
                  updateJoinGameModal(parties);
                  setupRowSelection(); // Rétablir les écouteurs d'événements après la mise à jour
              })
              .catch(error => {
                  console.error('Erreur lors du chargement des parties:', error);
              });
      }

      // Fonction pour mettre à jour le tableau des parties
      function updatePartiesTable(parties) {
          const tbody = document.querySelector('table tbody');
          
          // Vider le tableau
          tbody.innerHTML = '';
          
          if (parties.length > 0) {
              parties.forEach(partie => {
                  const row = document.createElement('tr');
                  
                  // Traduire le statut
                  let statut = "<?php echo $texts['status_waiting']; ?>"; // Valeur par défaut
                  switch(partie.status) {
                      case "en_attente": statut = "<?php echo $texts['status_waiting']; ?>"; break;
                      case "en_cours": statut = "<?php echo $texts['status_in_progress']; ?>"; break;
                      case "terminee": statut = "<?php echo $texts['status_completed']; ?>"; break;
                      case "complete": statut = "<?php echo $texts['status_full']; ?>"; break;
                      case "pause": statut = "<?php echo $texts['status_paused']; ?>"; break;
                      case "annulee": statut = "<?php echo $texts['status_cancelled']; ?>"; break;
                  }
                  
                  // Contenu de la ligne
                  row.innerHTML = `
                      <td><?php echo $texts['game_name_column']; ?> ${partie.id}</td>
                      <td>${partie.joueurs_actuels}/${partie.nombre_joueurs}</td>
                      <td>${partie.admin_nom ? partie.admin_nom : "Non assigné"}</td>
                      <td>${partie.niveau}</td>
                      <td>${statut}</td>
                      <td>${getActionButton(partie)}</td>
                  `;
                  
                  tbody.appendChild(row);
              });
          } else {
              const row = document.createElement('tr');
              row.innerHTML = '<td colspan="6"><?php echo $texts['no_games_available']; ?></td>';
              tbody.appendChild(row);
          }
      }

      // Fonction pour générer le bouton d'action approprié
      function getActionButton(partie) {
          if (partie.status === "en_attente" && parseInt(partie.joueurs_actuels) < parseInt(partie.nombre_joueurs)) {
              return `<button onclick="joinSpecificGame(${partie.id})" class="join-btn"><?php echo $texts['join']; ?></button>`;
          } else if (partie.status === "en_cours") {
              if (partie.user_joined) {
                  return `<a href="plateau-jeu.php?partie_id=${partie.id}" class="play-btn"><?php echo $texts['play_card']; ?></a>`;
              } else {
                  return `<span><?php echo $texts['status_in_progress']; ?></span>`;
              }
          } else {
              return `<span>-</span>`;
          }
      }

      // Fonction pour mettre à jour le modal de sélection de partie
      function updateJoinGameModal(parties) {
          const select = document.getElementById('gameSelect');
          if (!select) return; // Si l'élément n'existe pas, on sort de la fonction
          
          // Vider le select
          select.innerHTML = '';
          
          // Ajouter les options pour les parties disponibles
          parties.forEach(partie => {
              if (partie.status === "en_attente" && parseInt(partie.joueurs_actuels) < parseInt(partie.nombre_joueurs)) {
                  const option = document.createElement('option');
                  option.value = partie.id;
                  option.textContent = `<?php echo $texts['game_name_column']; ?> ${partie.id} (<?php echo $texts['level']; ?> ${partie.niveau})`;
                  select.appendChild(option);
              }
          });
      }

      // Configuration des écouteurs d'événements pour la sélection des lignes
      function setupRowSelection() {
          document.querySelectorAll('tbody tr').forEach(row => {
              row.addEventListener('click', function () {
                  document.querySelectorAll('tr').forEach(r => r.classList.remove('selected'));
                  this.classList.add('selected');
                  this.style.backgroundColor = '#777'; // Mettre en surbrillance
              });
          });
      }

      // Fonction pour rafraîchir la page (modifiée pour utiliser loadParties)
      function refreshPage() {
          // Utiliser l'actualisation AJAX au lieu de recharger la page
          loadParties();
          
          // Affichage d'une notification temporaire
          const notification = document.createElement('div');
          notification.textContent = '<?php echo $texts['data_updated']; ?>';
          notification.style.position = 'fixed';
          notification.style.top = '10px';
          notification.style.right = '10px';
          notification.style.backgroundColor = '#28a745';
          notification.style.color = 'white';
          notification.style.padding = '10px';
          notification.style.borderRadius = '5px';
          notification.style.zIndex = '1000';
          
          document.body.appendChild(notification);
          
          // Supprimer la notification après 2 secondes
          setTimeout(() => {
              notification.style.opacity = '0';
              notification.style.transition = 'opacity 0.5s';
              setTimeout(() => notification.remove(), 500);
          }, 2000);
      }

      // Fonction pour rejoindre une partie
      function joinGame() {
          // Ouvrir la modal
          document.getElementById('joinGameModal').style.display = 'block';
      }

      // Fonction pour fermer une modal
      function closeModal(modalId) {
          document.getElementById(modalId).style.display = 'none';
      }

      // Fonction pour rejoindre une partie spécifique
      function joinSpecificGame(gameId) {
          const csrfToken = document.querySelector('input[name="csrf_token"]').value;
          window.location.href = `join_game.php?game_id=${gameId}&csrf_token=${csrfToken}`;
      }

      // Fonction pour filtrer les parties
      function filterGames() {
          const adminName = prompt("<?php echo $texts['filter_by_admin']; ?>") || '';
          const rows = document.querySelectorAll('tbody tr');

          rows.forEach(row => {
              const adminCell = row.children[2].textContent.toLowerCase();
              if (adminName && !adminCell.includes(adminName.toLowerCase())) {
                  row.style.display = 'none';
              } else {
                  row.style.display = '';
              }
          });
      }

      // Démarrer l'actualisation automatique
      function startAutoRefresh() {
          // Configurer la sélection des lignes au démarrage
          setupRowSelection();
          
          // Configurer l'actualisation périodique
          refreshTimer = setInterval(loadParties, REFRESH_INTERVAL);
      }

      // Arrêter l'actualisation automatique
      function stopAutoRefresh() {
          clearInterval(refreshTimer);
      }

      // Démarrer l'actualisation automatique quand la page est chargée
      document.addEventListener('DOMContentLoaded', startAutoRefresh);

      // Gérer la visibilité de page (arrêter l'actualisation quand l'onglet est inactif)
      document.addEventListener('visibilitychange', () => {
          if (document.hidden) {
              stopAutoRefresh();
          } else {
              startAutoRefresh();
          }
      });
      </script>

</body>
</html>