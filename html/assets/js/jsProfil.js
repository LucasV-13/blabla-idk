document.addEventListener('DOMContentLoaded', function() {
    // Éléments DOM principaux
    const usersTab = document.getElementById('usersTab');
    const addUserTab = document.getElementById('addUserTab');
    const gameCreationTab = document.getElementById('gameCreationTab');
    const usersPanel = document.getElementById('usersPanel');
    const addUserPanel = document.getElementById('addUserPanel');
    const gameCreationPanel = document.getElementById('gameCreationPanel');
    const createGameBtn = document.getElementById('createGameBtn');
    const addUserForm = document.getElementById('addUserForm');
    const formMessage = document.getElementById('form-message');
    const generateRandomUsernameBtn = document.getElementById('generateRandomUsername');
    
    // Champs du formulaire
    const newUsernameField = document.getElementById('newUsername');
    const newPasswordField = document.getElementById('newPassword');
    const confirmPasswordField = document.getElementById('confirmPassword');
    const newEmailField = document.getElementById('newEmail');
    const userRoleField = document.getElementById('userRole');
    const userAvatarField = document.getElementById('userAvatar');
    
    // CSRF Token et URL de base
    const csrfToken = document.getElementById('csrf_token') ? document.getElementById('csrf_token').value : '';
    const baseUrl = document.getElementById('base_url') ? document.getElementById('base_url').value : '';
    
    // ==== Gestion des onglets ====
    if (usersTab && addUserTab && gameCreationTab) {
        usersTab.addEventListener('click', () => {
            // Activer l'onglet et afficher le panel correspondant
            setActiveTab(usersTab, usersPanel);
        });
        
        addUserTab.addEventListener('click', () => {
            // Activer l'onglet et afficher le panel correspondant
            setActiveTab(addUserTab, addUserPanel);
            // Réinitialiser le formulaire et effacer les valeurs indésirables
            if (addUserForm) {
                addUserForm.reset();
                resetFormFields();
            }
        });
        
        gameCreationTab.addEventListener('click', () => {
            // Activer l'onglet et afficher le panel correspondant
            setActiveTab(gameCreationTab, gameCreationPanel);
        });
    }
    
    // Fonction pour définir l'onglet actif et afficher le panel correspondant
    function setActiveTab(activeTab, activePanel) {
        // Désactiver tous les onglets
        [usersTab, addUserTab, gameCreationTab].forEach(tab => {
            if (tab) tab.classList.remove('active');
        });
        
        // Cacher tous les panels
        [usersPanel, addUserPanel, gameCreationPanel].forEach(panel => {
            if (panel) panel.style.display = 'none';
        });
        
        // Activer l'onglet et le panel sélectionnés
        if (activeTab) activeTab.classList.add('active');
        if (activePanel) activePanel.style.display = 'block';
    }
    
    // ==== Fonction pour générer un identifiant mémorisable ====
    function generateMemoableUsername() {
        // Liste d'adjectifs simples et positifs
        const adjectives = [
            'super', 'grand', 'petit', 'rapide', 'fort', 'brave', 
            'vif', 'agile', 'sympa', 'cool', 'smart', 'pro', 
            'top', 'zen', 'tech', 'mega', 'ultra', 'hyper'
        ];
        
        // Liste de noms communs courts
        const nouns = [
            'joueur', 'hero', 'ninja', 'panda', 'aigle', 'tigre', 
            'lion', 'loup', 'ours', 'robot', 'pilote', 'agent', 
            'gamer', 'master', 'expert', 'champion', 'star', 'ace'
        ];
        
        // Choisir un élément aléatoire de chaque liste
        const adjective = adjectives[Math.floor(Math.random() * adjectives.length)];
        const noun = nouns[Math.floor(Math.random() * nouns.length)];
        
        // Générer un nombre aléatoire à 3 chiffres
        const randomNum = Math.floor(Math.random() * 900) + 100;
        
        // Assembler l'identifiant
        return `${adjective}${noun}${randomNum}`;
    }
    
    // ==== Génération d'identifiant mémorisable ====
    if (generateRandomUsernameBtn && newUsernameField) {
        generateRandomUsernameBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Générer un nouvel identifiant mémorisable
            newUsernameField.value = generateMemoableUsername();
            
            // Mettre en évidence le champ
            highlightField(newUsernameField);
        });
        
        // Changer le texte du bouton pour plus de clarté
        generateRandomUsernameBtn.textContent = 'Générer un identifiant mémorisable';
    }
    
    // Générer automatiquement un identifiant au chargement
    if (newUsernameField) {
        // Générer immédiatement un identifiant mémorisable
        newUsernameField.value = generateMemoableUsername();
    }
    
    // Fonction pour mettre en évidence un champ
    function highlightField(field) {
        if (!field) return;
        
        // Ajouter la classe d'animation
        field.classList.add('highlight');
        
        // Supprimer la classe après l'animation
        setTimeout(() => {
            field.classList.remove('highlight');
        }, 1500);
    }
    
    // ==== Gestion du formulaire d'ajout d'utilisateur ====
    if (addUserForm) {
        // Au chargement du formulaire, réinitialiser les champs
        resetFormFields();
        
        // Lors de la soumission du formulaire
        addUserForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Vérifier les valeurs par défaut problématiques
            if (newUsernameField && newUsernameField.value === 'user') {
                showFormMessage('L\'identifiant "user" n\'est pas autorisé. Veuillez en choisir un autre ou utiliser le bouton de génération.', 'error');
                highlightField(newUsernameField);
                return;
            }
            
            if (newPasswordField && newPasswordField.value === 'Eloi2023*') {
                showFormMessage('Le mot de passe "Eloi2023*" n\'est pas autorisé. Veuillez en choisir un autre.', 'error');
                highlightField(newPasswordField);
                return;
            }
            
            // Vérifier que les mots de passe correspondent
            if (newPasswordField && confirmPasswordField && newPasswordField.value !== confirmPasswordField.value) {
                showFormMessage('Les mots de passe ne correspondent pas.', 'error');
                highlightField(confirmPasswordField);
                return;
            }
            
            // Si l'identifiant est vide ou "user", le remplacer
            if (!newUsernameField.value || newUsernameField.value === 'user') {
                newUsernameField.value = generateMemoableUsername();
            }
            
            // Créer un objet FormData pour envoyer les données
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('email', newEmailField ? newEmailField.value : '');
            formData.append('username', newUsernameField ? newUsernameField.value : '');
            formData.append('password', newPasswordField ? newPasswordField.value : '');
            formData.append('confirm_password', confirmPasswordField ? confirmPasswordField.value : '');
            formData.append('role', userRoleField ? userRoleField.value : '');
            formData.append('avatar', userAvatarField ? userAvatarField.value : '👤');
            
            // Envoyer les données via fetch API
            fetch(baseUrl + 'profil/add_user.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erreur réseau: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Succès
                    showFormMessage(data.message, 'success');
                    addUserForm.reset();
                    userAvatarField.value = '👤';
                    
                    // Rafraîchir la page après un délai
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    // Erreur
                    showFormMessage(data.message, 'error');
                    
                    // Mettre en évidence le champ problématique s'il est spécifié
                    if (data.field) {
                        const fieldMap = {
                            'username': newUsernameField,
                            'email': newEmailField,
                            'password': newPasswordField,
                            'role': userRoleField
                        };
                        
                        if (fieldMap[data.field]) {
                            highlightField(fieldMap[data.field]);
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showFormMessage('Une erreur est survenue lors du traitement de la demande.', 'error');
            });
        });
        
        // Ajouter des écouteurs d'événements pour effacer les valeurs indésirables
        [newUsernameField, newPasswordField, confirmPasswordField].forEach(field => {
            if (field) {
                field.addEventListener('focus', function() {
                    // Effacer les valeurs par défaut problématiques au focus
                    if (this === newUsernameField && this.value === 'user') {
                        this.value = '';
                    } else if ((this === newPasswordField || this === confirmPasswordField) && 
                                this.value === 'Eloi2023*') {
                        this.value = '';
                    }
                });
                
                field.addEventListener('input', function() {
                    // Effacer immédiatement si la valeur par défaut est saisie
                    if (this === newUsernameField && this.value === 'user') {
                        this.value = '';
                    } else if ((this === newPasswordField || this === confirmPasswordField) && 
                                this.value === 'Eloi2023*') {
                        this.value = '';
                    }
                });
            }
        });
    }
    
    // Fonction pour réinitialiser les champs du formulaire
    function resetFormFields() {
        // Effacer les valeurs par défaut problématiques
        if (newUsernameField && newUsernameField.value === 'user') {
            newUsernameField.value = '';
        }
        
        if (newPasswordField && newPasswordField.value === 'Eloi2023*') {
            newPasswordField.value = '';
        }
        
        if (confirmPasswordField && confirmPasswordField.value === 'Eloi2023*') {
            confirmPasswordField.value = '';
        }
        
        // Réinitialiser l'avatar à 👤
        if (userAvatarField) {
            userAvatarField.value = '👤';
        }
        
        // Cacher le message de formulaire
        if (formMessage) {
            formMessage.style.display = 'none';
        }
    }
    
    // Fonction pour afficher un message dans le formulaire
    function showFormMessage(message, type) {
        if (!formMessage) return;
        
        formMessage.textContent = message;
        formMessage.className = 'form-message';
        formMessage.classList.add(type === 'success' ? 'success-message' : 'error-message');
        formMessage.style.display = 'block';
        
        // Faire défiler jusqu'au message
        formMessage.scrollIntoView({ behavior: 'smooth', block: 'start' });
        
        // Cacher le message après un délai si c'est un succès
        if (type === 'success') {
            setTimeout(() => {
                formMessage.style.display = 'none';
            }, 5000);
        }
    }
    
    // ==== Gestion de la suppression d'utilisateurs ====
    // Ajouter un gestionnaire d'événements pour les boutons de suppression
    document.querySelectorAll('.user-action-btn.delete').forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            const email = row.cells[0].textContent;
            const username = row.cells[1].textContent;
            const userId = this.getAttribute('data-id');
            
            if (confirm(`Êtes-vous sûr de vouloir supprimer l'utilisateur ${username} (${email}) ?`)) {
                // Créer un objet FormData pour envoyer les données
                const formData = new FormData();
                formData.append('csrf_token', csrfToken);
                formData.append('id', userId);
                
                // Envoyer la requête via fetch API
                fetch(baseUrl + 'profil/delete_user.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erreur réseau: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Supprimer la ligne du tableau
                        row.remove();
                        alert('Utilisateur supprimé avec succès.');
                    } else {
                        alert('Erreur lors de la suppression: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Une erreur est survenue lors de la suppression.');
                });
            }
        });
    });
    
    // ==== Bouton de création de partie ====
    if (createGameBtn && gameCreationTab) {
        createGameBtn.addEventListener('click', () => {
            // Basculer vers l'onglet de création de partie
            setActiveTab(gameCreationTab, gameCreationPanel);
        });
    }
    
    // ==== Désactiver l'autocomplétion ====
    // Fonction pour désactiver l'autocomplétion sur tous les champs
    function disableAutocomplete() {
        document.querySelectorAll('input, select, textarea').forEach(input => {
            input.setAttribute('autocomplete', 'new-' + Math.random().toString(36).substring(2));
        });
    }
    
    // Appliquer la désactivation de l'autocomplétion
    disableAutocomplete();
    
    // Nettoyage immédiat des valeurs par défaut indésirables
    window.setTimeout(resetFormFields, 100);
    window.setTimeout(resetFormFields, 500);
    window.setTimeout(resetFormFields, 1000);
});