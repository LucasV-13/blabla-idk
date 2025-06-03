/**
 * JavaScript pour la page de connexion The Mind
 */

class LoginManager {
    constructor() {
        this.form = null;
        this.submitButton = null;
        this.usernameField = null;
        this.passwordField = null;
        this.isSubmitting = false;
        
        this.init();
    }

    init() {
        utils.ready(() => {
            this.setupElements();
            this.setupEventListeners();
            this.setupValidation();
            this.setupAccessibility();
            this.loadSavedData();
        });
    }

    setupElements() {
        this.form = utils.$('.login-form');
        this.submitButton = utils.$('.login-form .btn');
        this.usernameField = utils.$('#username');
        this.passwordField = utils.$('#password');
        
        if (!this.form || !this.submitButton || !this.usernameField || !this.passwordField) {
            utils.error('Éléments de connexion manquants');
            return;
        }
    }

    setupEventListeners() {
        if (!this.form) return;

        // Soumission du formulaire
        this.form.addEventListener('submit', (e) => {
            this.handleSubmit(e);
        });

        // Validation en temps réel
        this.usernameField.addEventListener('input', utils.debounce(() => {
            this.validateUsername();
        }, 300));

        this.passwordField.addEventListener('input', utils.debounce(() => {
            this.validatePassword();
        }, 300));

        // Effets visuels
        [this.usernameField, this.passwordField].forEach(field => {
            field.addEventListener('focus', () => {
                this.addFieldFocus(field);
            });

            field.addEventListener('blur', () => {
                this.removeFieldFocus(field);
            });
        });

        // Raccourcis clavier
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !this.isSubmitting) {
                if (document.activeElement === this.usernameField) {
                    this.passwordField.focus();
                    e.preventDefault();
                } else if (document.activeElement === this.passwordField) {
                    this.form.requestSubmit();
                    e.preventDefault();
                }
            }
        });

        // Gestion de la visibilité du mot de passe
        this.setupPasswordToggle();
    }

    setupPasswordToggle() {
        // Créer un bouton pour afficher/masquer le mot de passe
        const toggleButton = utils.createElement('button', {
            type: 'button',
            className: 'password-toggle',
            innerHTML: '👁️'
        });

        toggleButton.addEventListener('click', () => {
            if (this.passwordField.type === 'password') {
                this.passwordField.type = 'text';
                toggleButton.innerHTML = '🙈';
                toggleButton.setAttribute('aria-label', 'Masquer le mot de passe');
            } else {
                this.passwordField.type = 'password';
                toggleButton.innerHTML = '👁️';
                toggleButton.setAttribute('aria-label', 'Afficher le mot de passe');
            }
        });

        // Ajouter le bouton au champ mot de passe
        const passwordGroup = this.passwordField.parentElement;
        passwordGroup.style.position = 'relative';
        
        Object.assign(toggleButton.style, {
            position: 'absolute',
            right: '15px',
            top: '50%',
            transform: 'translateY(-50%)',
            background: 'none',
            border: 'none',
            color: 'var(--light-color)',
            cursor: 'pointer',
            fontSize: '18px',
            zIndex: '10'
        });

        passwordGroup.appendChild(toggleButton);
    }

    setupValidation() {
        // Règles de validation
        this.validationRules = {
            username: {
                required: true,
                minLength: 3,
                maxLength: 20,
                pattern: /^[a-zA-Z0-9_-]+$/
            },
            password: {
                required: true,
                minLength: 6
            }
        };
    }

    validateUsername() {
        const value = this.usernameField.value.trim();
        const rules = this.validationRules.username;
        
        // Nettoyer la valeur
        const cleanValue = utils.sanitizeInput(value, 'username');
        if (cleanValue !== value) {
            this.usernameField.value = cleanValue;
        }

        const errors = [];

        if (rules.required && !cleanValue) {
            errors.push('Le nom d\'utilisateur est requis');
        } else {
            if (cleanValue.length < rules.minLength) {
                errors.push(`Au moins ${rules.minLength} caractères`);
            }
            if (cleanValue.length > rules.maxLength) {
                errors.push(`Maximum ${rules.maxLength} caractères`);
            }
            if (!rules.pattern.test(cleanValue)) {
                errors.push('Seuls les lettres, chiffres, - et _ sont autorisés');
            }
        }

        this.showFieldValidation(this.usernameField, errors);
        return errors.length === 0;
    }

    validatePassword() {
        const value = this.passwordField.value;
        const rules = this.validationRules.password;
        const errors = [];

        if (rules.required && !value) {
            errors.push('Le mot de passe est requis');
        } else if (value.length < rules.minLength) {
            errors.push(`Au moins ${rules.minLength} caractères`);
        }

        this.showFieldValidation(this.passwordField, errors);
        return errors.length === 0;
    }

    showFieldValidation(field, errors) {
        // Supprimer les anciens messages d'erreur
        const existingError = field.parentElement.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }

        // Réinitialiser les styles
        field.classList.remove('field-valid', 'field-invalid');

        if (errors.length > 0) {
            field.classList.add('field-invalid');
            
            const errorElement = utils.createElement('div', {
                className: 'field-error',
                textContent: errors[0]
            });

            Object.assign(errorElement.style, {
                color: 'var(--error-color)',
                fontSize: '0.8rem',
                marginTop: '5px',
                opacity: '0',
                transition: 'opacity 0.3s ease'
            });

            field.parentElement.appendChild(errorElement);
            
            // Animation d'apparition
            setTimeout(() => {
                errorElement.style.opacity = '1';
            }, 10);
        } else if (field.value.trim()) {
            field.classList.add('field-valid');
        }
    }

    addFieldFocus(field) {
        field.parentElement.classList.add('field-focused');
    }

    removeFieldFocus(field) {
        field.parentElement.classList.remove('field-focused');
    }

    setupAccessibility() {
        // Attributs ARIA
        this.usernameField.setAttribute('aria-describedby', 'username-help');
        this.passwordField.setAttribute('aria-describedby', 'password-help');

        // Messages d'aide
        const usernameHelp = utils.createElement('div', {
            id: 'username-help',
            className: 'sr-only',
            textContent: 'Entrez votre nom d\'utilisateur. 3 à 20 caractères, lettres, chiffres, tirets et underscores autorisés.'
        });

        const passwordHelp = utils.createElement('div', {
            id: 'password-help',
            className: 'sr-only',
            textContent: 'Entrez votre mot de passe. Minimum 6 caractères.'
        });

        this.usernameField.parentElement.appendChild(usernameHelp);
        this.passwordField.parentElement.appendChild(passwordHelp);
    }

    loadSavedData() {
        // Charger le nom d'utilisateur sauvegardé (si l'utilisateur l'a autorisé)
        const savedUsername = utils.getLocalStorage('themind_username');
        if (savedUsername && this.usernameField.value === '') {
            this.usernameField.value = savedUsername;
        }
    }

    saveUsername() {
        // Sauvegarder le nom d'utilisateur pour la prochaine fois
        const username = this.usernameField.value.trim();
        if (username) {
            utils.setLocalStorage('themind_username', username);
        }
    }

    async handleSubmit(e) {
        e.preventDefault();

        if (this.isSubmitting) {
            return;
        }

        // Validation finale
        const isUsernameValid = this.validateUsername();
        const isPasswordValid = this.validatePassword();

        if (!isUsernameValid || !isPasswordValid) {
            toast('Veuillez corriger les erreurs dans le formulaire', 'error');
            return;
        }

        this.isSubmitting = true;
        this.setLoadingState(true);

        try {
            // Sauvegarder le nom d'utilisateur
            this.saveUsername();

            // Préparer les données
            const formData = new FormData(this.form);

            // Envoyer la requête
            const response = await utils.request(this.form.action, {
                method: 'POST',
                body: formData
            });

            // Si la réponse est une chaîne (HTML), c'est probablement une redirection ou une erreur
            if (typeof response === 'string') {
                // Rechercher les erreurs dans la réponse HTML
                const parser = new DOMParser();
                const doc = parser.parseFromString(response, 'text/html');
                const errorAlert = doc.querySelector('.alert-error');
                
                if (errorAlert) {
                    throw new Error(errorAlert.textContent.trim());
                } else {
                    // Pas d'erreur trouvée, recharger la page
                    window.location.reload();
                }
            } else if (response.success) {
                // Succès
                toast('Connexion réussie !', 'success');
                
                // Redirection
                setTimeout(() => {
                    window.location.href = response.redirect || utils.getBaseUrl() + 'pages/dashboard.php';
                }, 1000);
            } else {
                throw new Error(response.message || 'Erreur de connexion');
            }

        } catch (error) {
            utils.error('Erreur de connexion', error);
            toast(error.message || 'Erreur lors de la connexion', 'error');
            
            // Secouer le formulaire pour indiquer l'erreur
            this.shakeForm();
        } finally {
            this.isSubmitting = false;
            this.setLoadingState(false);
        }
    }

    setLoadingState(loading) {
        if (loading) {
            this.form.classList.add('loading');
            this.submitButton.disabled = true;
            this.submitButton.textContent = 'Connexion...';
        } else {
            this.form.classList.remove('loading');
            this.submitButton.disabled = false;
            this.submitButton.textContent = window.THEMIND?.TEXTS?.login_button || 'ENTRER DANS L\'ESPRIT';
        }
    }

    shakeForm() {
        this.form.style.animation = 'shake 0.5s ease-in-out';
        setTimeout(() => {
            this.form.style.animation = '';
        }, 500);
    }
}

// Ajouter les styles CSS pour les animations et validations
utils.ready(() => {
    if (!document.querySelector('#login-styles')) {
        const style = document.createElement('style');
        style.id = 'login-styles';
        style.textContent = `
            .field-focused {
                transform: scale(1.02);
            }
            
            .field-valid input {
                border-color: var(--success-color) !important;
                box-shadow: 0 0 10px rgba(76, 175, 80, 0.3) !important;
            }
            
            .field-invalid input {
                border-color: var(--error-color) !important;
                box-shadow: 0 0 10px rgba(244, 67, 54, 0.3) !important;
            }
            
            .field-error {
                animation: errorSlide 0.3s ease;
            }
            
            @keyframes errorSlide {
                from { transform: translateY(-10px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
            
            @keyframes shake {
                0%, 100% { transform: translateX(0); }
                25% { transform: translateX(-10px); }
                75% { transform: translateX(10px); }
            }
            
            .password-toggle:hover {
                opacity: 0.7;
                transform: translateY(-50%) scale(1.1);
            }
            
            .sr-only {
                position: absolute;
                width: 1px;
                height: 1px;
                padding: 0;
                margin: -1px;
                overflow: hidden;
                clip: rect(0, 0, 0, 0);
                white-space: nowrap;
                border: 0;
            }
            
            .login-form.loading::after {
                content: "";
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.1);
                border-radius: var(--border-radius-large);
                pointer-events: none;
            }
        `;
        document.head.appendChild(style);
    }
});

// Initialiser le gestionnaire de connexion
let loginManager;
utils.ready(() => {
    loginManager = new LoginManager();
    utils.info('Login manager initialisé');
});

// Export pour les tests ou usage externe
if (typeof module !== 'undefined' && module.exports) {
    module.exports = LoginManager;
}