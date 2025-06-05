document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const settingsModal = document.getElementById('settingsModal');
    const rulesModal = document.getElementById('rulesModal');
    const settingsBtn = document.getElementById('settingsBtn');
    const rulesBtn = document.getElementById('rulesBtn');
    const dashboardBtn = document.getElementById('dashboardBtn');
    const profileBtnModal = document.getElementById('profileBtnModal');
    const closeSettings = document.getElementById('closeSettings');
    const closeRules = document.getElementById('closeRules');
    const volumeSlider = document.getElementById('volumeSlider');
    const languageSelect = document.getElementById('languageSelect');
    const usersTab = document.getElementById('usersTab');
    const gameCreationTab = document.getElementById('gameCreationTab');
    const usersPanel = document.getElementById('usersPanel');
    const gameCreationPanel = document.getElementById('gameCreationPanel');
    const createGameBtn = document.getElementById('createGameBtn');
    const createGameForm = document.getElementById('createGameForm');
    
    // CSRF Token et URL de base
    const csrfToken = document.getElementById('csrf_token') ? document.getElementById('csrf_token').value : '';
    const baseUrl = document.getElementById('base_url') ? document.getElementById('base_url').value : '';
    
    // Volume setting
    let volume = volumeSlider ? volumeSlider.value / 100 : 0.5;
    
    // Event listeners for buttons (vérifier si les éléments existent)
    if (settingsBtn) {
        settingsBtn.addEventListener('click', () => {
            settingsModal.style.display = 'block';
            document.body.style.overflow = 'hidden'; // Empêche le défilement du body
        });
    }
    
    if (rulesBtn) {
        rulesBtn.addEventListener('click', () => {
            rulesModal.style.display = 'block';
            document.body.style.overflow = 'hidden'; // Empêche le défilement du body
        });
    }
    
    if (dashboardBtn) {
        dashboardBtn.addEventListener('click', () => {
            window.location.href = baseUrl + 'dashboard.php';
        });
    }
    
    if (dashboardBtn) {
        dashboardBtn.addEventListener('click', () => {
            window.location.href = 'dashboard.php';
        });
    }

    if (profileBtnModal) {
        profileBtnModal.addEventListener('click', () => {
            settingsModal.style.display = 'none';
            document.body.style.overflow = 'auto'; // Rétablit le défilement
        });
    }
    
    // Make sure close buttons work
    if (closeSettings) {
        closeSettings.addEventListener('click', () => {
            settingsModal.style.display = 'none';
            document.body.style.overflow = 'auto'; // Rétablit le défilement
        });
    }
    
    if (closeRules) {
        closeRules.addEventListener('click', () => {
            rulesModal.style.display = 'none';
            document.body.style.overflow = 'auto'; // Rétablit le défilement
        });
    }
    
    // Close modals when clicking outside
    window.addEventListener('click', (event) => {
        if (settingsModal && event.target === settingsModal) {
            settingsModal.style.display = 'none';
            document.body.style.overflow = 'auto'; // Rétablit le défilement
        }
        if (rulesModal && event.target === rulesModal) {
            rulesModal.style.display = 'none';
            document.body.style.overflow = 'auto'; // Rétablit le défilement
        }
    });
    
    // Volume control with save preference
    if (volumeSlider) {
        // Mise à jour en temps réel
        volumeSlider.addEventListener('input', () => {
            volume = volumeSlider.value / 100;
            // Appliquer le volume à tous les éléments audio
            document.querySelectorAll('audio').forEach(audio => {
                audio.volume = volume;
            });
        });
        
        // Sauvegarde de la préférence quand l'utilisateur relâche le curseur
        volumeSlider.addEventListener('change', () => {
            savePreference('volume', volumeSlider.value);
        });
    }
    
    // Language selector with save preference
    if (languageSelect) {
        languageSelect.addEventListener('change', () => {
            const selectedLanguage = languageSelect.value;
            savePreference('language', selectedLanguage).then(() => {
                // Reload the page to apply the new language
                window.location.reload();
            });
        });
    }
    
    // Function to save user preferences (version simplifiée)
    function savePreference(type, value) {
        // Version simplifiée: stocker dans localStorage pour l'instant
        localStorage.setItem(type, value);
        
        // Version avec XHR pour sauvegarder en session PHP
        const xhr = new XMLHttpRequest();
        xhr.open('POST', baseUrl + 'save/save_preferences.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status !== 200) {
                    console.error('Erreur lors de la sauvegarde des préférences');
                }
            }
        };
        
        const data = 'preference=' + encodeURIComponent(type) + 
                    '&value=' + encodeURIComponent(value) + 
                    '&csrf_token=' + encodeURIComponent(csrfToken);
        xhr.send(data);
        
        return Promise.resolve(); // Pour compatibilité avec .then()
    }
    
    // Tab switching in admin panel
    if (usersTab && gameCreationTab) {
        usersTab.addEventListener('click', () => {
            // Switch active tab
            usersTab.classList.add('active');
            gameCreationTab.classList.remove('active');
            
            // Show relevant panel
            usersPanel.style.display = 'block';
            gameCreationPanel.style.display = 'none';
        });
        
        gameCreationTab.addEventListener('click', () => {
            // Switch active tab
            gameCreationTab.classList.add('active');
            usersTab.classList.remove('active');
            
            // Show relevant panel
            gameCreationPanel.style.display = 'block';
            usersPanel.style.display = 'none';
        });
    }
    
    // Create game button in profile section
    if (createGameBtn && gameCreationTab) {
        createGameBtn.addEventListener('click', () => {
            // Switch to game creation tab
            gameCreationTab.click();
        });
    }
    
    // Form submission
    if (createGameForm) {
        createGameForm.addEventListener('submit', (e) => {
            // Ajouter le CSRF token au formulaire si ce n'est pas déjà fait
            if (!createGameForm.querySelector('input[name="csrf_token"]')) {
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = csrfToken;
                createGameForm.appendChild(csrfInput);
            }
            
            // Pour l'instant, empêcher la soumission réelle et afficher une alerte
            if (e.submitter.getAttribute('formnovalidate') !== 'formnovalidate') {
                e.preventDefault();
                
                const gameTitle = document.getElementById('gameTitle').value;
                const playerCount = document.getElementById('playerCount').value;
                const difficultyLevel = document.getElementById('difficultyLevel').value;
                const gamePrivacy = document.getElementById('gamePrivacy').value;
                
                alert(`Partie "${gameTitle}" créée avec succès!\nJoueurs: ${playerCount}\nDifficulté: ${difficultyLevel}\nConfidentialité: ${gamePrivacy}`);
            }
        });
    }
    
    // Add action to edit and delete buttons
    document.querySelectorAll('.user-action-btn.edit').forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            const email = row.cells[0].textContent;
            alert(`Modification de l'utilisateur: ${email}`);
        });
    });
    
    document.querySelectorAll('.user-action-btn.delete').forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            const email = row.cells[0].textContent;
            if (confirm(`Êtes-vous sûr de vouloir supprimer l'utilisateur: ${email}?`)) {
                row.remove();
            }
        });
    });
});