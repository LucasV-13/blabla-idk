// jsCreateGame.js - Script corrigé pour la création de partie

document.addEventListener('DOMContentLoaded', function() {
    // Éléments du formulaire de création de partie
    const createGameForm = document.getElementById('createGameForm');
    const createGameBtn = document.getElementById('createGameBtn');
    const gameCreationTab = document.getElementById('gameCreationTab');
    const gameCreationPanel = document.getElementById('gameCreationPanel');
    const usersPanel = document.getElementById('usersPanel');
    
    // CSRF Token
    const csrfToken = document.getElementById('csrf_token') ? document.getElementById('csrf_token').value : '';
    const baseUrl = document.getElementById('base_url') ? document.getElementById('base_url').value : '';
    
    // Basculer vers l'onglet de création de partie quand on clique sur le bouton
    if (createGameBtn && gameCreationTab) {
        createGameBtn.addEventListener('click', () => {
            // Activer l'onglet de création de partie
            if (gameCreationTab && gameCreationPanel && usersPanel) {
                document.querySelectorAll('.panel-tab').forEach(tab => tab.classList.remove('active'));
                gameCreationTab.classList.add('active');
                
                document.querySelectorAll('.panel-content').forEach(panel => panel.style.display = 'none');
                gameCreationPanel.style.display = 'block';
            }
        });
    }
    
    // Gestion de la soumission du formulaire de création de partie
    if (createGameForm) {
        createGameForm.addEventListener('submit', function(e) {
            // Ne pas bloquer la soumission du formulaire, mais s'assurer que le CSRF token est présent
            if (!createGameForm.querySelector('input[name="csrf_token"]')) {
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = csrfToken;
                createGameForm.appendChild(csrfInput);
            }
            
            // S'assurer que l'action du formulaire est correctement définie
            if (!createGameForm.action || createGameForm.action === '') {
                createGameForm.action = 'create_game.php';
            }
            
            // Correction pour le chemin d'accès
            const currentPath = window.location.pathname;
            if (currentPath.includes('/profil/profil.php')) {
                // Si nous sommes dans le dossier profil
                createGameForm.action = '../create_game.php';
            }
            
            // Validation des champs
            const gameTitle = document.getElementById('gameTitle').value;
            const playerCount = document.getElementById('playerCount').value;
            
            if (!gameTitle || gameTitle.trim() === '') {
                e.preventDefault();
                alert('Veuillez entrer un nom pour la partie.');
                return;
            }
            
            // Afficher un indicateur de chargement (optionnel)
            const submitBtn = createGameForm.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = 'Création en cours...';
                submitBtn.disabled = true;
            }
            
            // Laisser le formulaire continuer sa soumission normale
            console.log('Formulaire soumis avec succès');
        });
    }
    
    // Correction pour les scripts précédents qui bloquent la soumission
    // Trouver et désactiver tout code qui utilise formnovalidate
    const formButtons = document.querySelectorAll('button[formnovalidate]');
    formButtons.forEach(button => {
        button.removeAttribute('formnovalidate');
    });
});