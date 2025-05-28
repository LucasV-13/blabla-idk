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
            
            // Traduire le statut en français
            let statut = "Inconnu";
            switch(partie.status) {
                case "en_attente": statut = "En attente"; break;
                case "en_cours": statut = "En cours"; break;
                case "terminee": statut = "Terminée"; break;
                case "complete": statut = "Complète"; break;
            }
            
            // Contenu de la ligne
            row.innerHTML = `
                <td>Partie ${partie.id}</td>
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
        row.innerHTML = '<td colspan="6">Aucune partie disponible pour le moment.</td>';
        tbody.appendChild(row);
    }
}

// Fonction pour générer le bouton d'action approprié
function getActionButton(partie) {
    if (partie.status === "en_attente" && parseInt(partie.joueurs_actuels) < parseInt(partie.nombre_joueurs)) {
        return `<button onclick="joinSpecificGame(${partie.id})" class="join-btn">Rejoindre</button>`;
    } else if (partie.status === "en_cours") {
        if (partie.user_joined) {
            return `<a href="jeu.php?partie_id=${partie.id}" class="play-btn">Jouer</a>`;
        } else {
            return `<span>En cours</span>`;
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
            option.textContent = `Partie ${partie.id} (Niveau ${partie.niveau})`;
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
    notification.textContent = 'Données actualisées';
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

// Fonction pour rejoindre une partie (conservée de votre code)
function joinGame() {
    // Ouvrir la modal existante si elle existe
    const modal = document.getElementById('joinGameModal');
    if (modal) {
        modal.style.display = 'block';
        return;
    }
    
    // Sinon, utiliser votre logique originale
    const selectedRow = document.querySelector('tr.selected');
    if (selectedRow) {
        const gameName = selectedRow.children[0].textContent;
        alert(`Vous avez rejoint la partie : ${gameName}`);
    } else {
        alert('Veuillez sélectionner une partie.');
    }
}

// Fonction pour fermer une modal
function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Fonction pour rejoindre une partie spécifique
function joinSpecificGame(gameId) {
    // Récupérer le jeton CSRF depuis un élément caché dans la page
    const csrfToken = document.querySelector('input[name="csrf_token"]') ? 
                       document.querySelector('input[name="csrf_token"]').value : '';
    
    window.location.href = `join_game.php?game_id=${gameId}${csrfToken ? `&csrf_token=${csrfToken}` : ''}`;
}

// Fonction pour filtrer les parties (conservée de votre code)
function filterGames() {
    const adminName = prompt("Entrez le nom de l'administrateur pour filtrer :") || '';
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
    // Charger les parties immédiatement au chargement de la page
    setupRowSelection(); // Configurer la sélection des lignes au démarrage
    
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