/**
 * JavaScript spécifique à la page de connexion
 */

document.addEventListener('DOMContentLoaded', function() {
    // Éléments du DOM
    const loginForm = document.querySelector('.login__form');
    const usernameField = document.getElementById('username');
    const passwordField = document.getElementById('password');
    const submitButton = loginForm.querySelector('button[type="submit"]');
    const capsLockWarning = document.getElementById('capsLockWarning');
    const languageSelect = document.getElementById('languageSelect');
    
    // Configuration
    const config = {
        maxAttempts: 5,
        lockoutTime: 300000, // 5 minutes
        animationDuration: 300
    };
    
    // État
    let attempts = parseInt(localStorage.getItem('login_attempts') || '0');
    let lastAttempt = parseInt(localStorage.getItem('last_attempt') || '0');
    let isSubmitting = false;
    
    // Initialisation
    init();
    
    function init() {
        setupFormValidation();
        setupCapslockDetection();
        setupParticleAnimation();
        setupKeyboardShortcuts();
        checkLockout();
        
        // Animation d'entrée différée
        setTimeout(() => {
            document.querySelector('.login__container').classList.add('fade-in');
        }, 100);
    }
    
    /**
     * Configuration de la validation du formulaire
     */
    function setupFormValidation() {
        // Validation en temps réel
        usernameField.addEventListener('input', validateUsername);
        passwordField.addEventListener('input', validatePassword);
        
        // Soumission du formulaire
        loginForm.addEventListener('submit', handleFormSubmit);
        
        // Suppression des messages d'erreur au focus
        [usernameField, passwordField].forEach(field => {
            field.addEventListener('focus', () => {
                clearFieldError(field);
                hideLoginError();
            });
        });
    }
    
    /**
     * Validation du champ nom d'utilisateur
     */
    function validateUsername() {
        const value = usernameField.value.trim();
        
        clearFieldError(usernameField);
        
        if (value.length === 0) {
            return false;
        }
        
        if (value.length < 2) {
            showFieldError(usernameField, 'Le nom d\'utilisateur doit contenir au moins 2 caractères');
            return false;
        }
        
        if (!/^[a-zA-Z0-9_-]+$/.test(value)) {
            showFieldError(usernameField, 'Seuls les lettres, chiffres, tirets et underscores sont autorisés');
            return false;
        }
        
        showFieldSuccess(usernameField);
        return true;
    }
    
    /**
     * Validation du champ mot de passe
     */
    function validatePassword() {
        const value = passwordField.value;
        
        clearFieldError(passwordField);
        
        if (value.length === 0) {
            return false;
        }
        
        if (value.length < 3) {
            showFieldError(passwordField, 'Le mot de passe doit contenir au moins 3 caractères');
            return false;
        }
        
        showFieldSuccess(passwordField);
        return true;
    }
    
    /**
     * Gestion de la soumission du formulaire
     */
    async function handleFormSubmit(e) {
        e.preventDefault();
        
        if (isSubmitting) return;
        
        // Vérifier le lockout
        if (isLockedOut()) {
            const remainingTime = getRemainingLockoutTime();
            showLoginError(`Trop de tentatives. Réessayez dans ${Math.ceil(remainingTime / 60000)} minute(s).`);
            return;
        }
        
        // Valider les champs
        const isUsernameValid = validateUsername();
        const isPasswordValid = validatePassword();
        
        if (!isUsernameValid || !isPasswordValid) {
            TheMind.utils.showNotification('Veuillez corriger les erreurs dans le formulaire', 'error');
            return;
        }
        
        // Marquer comme en cours de soumission
        isSubmitting = true;
        setSubmitButtonLoading(true);
        
        try {
            // Simulation d'un délai pour l'UX
            await new Promise(resolve => setTimeout(resolve, 500));
            
            // Soumettre le formulaire
            const formData = new FormData(loginForm);
            const response = await fetch(loginForm.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const result = await response.text();
            
            // Vérifier si la réponse contient une redirection ou une erreur
            if (result.includes('Location: pages/dashboard.php') || response.redirected) {
                // Connexion réussie
                resetAttempts();
                showSuccessAnimation();
                
                setTimeout(() => {
                    window.location.href = 'pages/dashboard.php';
                }, 1000);
            } else {
                // Erreur de connexion
                incrementAttempts();
                
                // Extraire le message d'erreur du HTML retourné
                const parser = new DOMParser();
                const doc = parser.parseFromString(result, 'text/html');
                const errorElement = doc.querySelector('.login__error');
                const errorMessage = errorElement ? errorElement.textContent.trim() : 'Identifiants incorrects';
                
                showLoginError(errorMessage);
                shakeForm();
                
                // Vider le mot de passe
                passwordField.value = '';
                passwordField.focus();
            }
        } catch (error) {
            console.error('Erreur de connexion:', error);
            showLoginError('Une erreur est survenue. Veuillez réessayer.');
        } finally {
            isSubmitting = false;
            setSubmitButtonLoading(false);
        }
    }
    
    /**
     * Détection du verrouillage majuscules
     */
    function setupCapslockDetection() {
        passwordField.addEventListener('keydown', (e) => {
            if (e.getModifierState && e.getModifierState('CapsLock')) {
                showCapsLockWarning();
            } else {
                hideCapsLockWarning();
            }
        });
        
        passwordField.addEventListener('blur', hideCapsLockWarning);
    }
    
    /**
     * Configuration des raccourcis clavier
     */
    function setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Entrée pour soumettre
            if (e.key === 'Enter' && (e.target === usernameField || e.target === passwordField)) {
                e.preventDefault();
                handleFormSubmit(e);
            }
            
            // Échap pour effacer les champs
            if (e.key === 'Escape') {
                usernameField.value = '';
                passwordField.value = '';
                hideLoginError();
                clearAllFieldErrors();
                usernameField.focus();
            }
        });
    }
    
    /**
     * Animation des particules
     */
    function setupParticleAnimation() {
        const particlesContainer = document.querySelector('.login__particles');
        if (!particlesContainer) return;
        
        // Créer des particules supplémentaires dynamiquement
        for (let i = 0; i < 15; i++) {
            const particle = document.createElement('div');
            particle.className = 'login__particle';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.animationDelay = Math.random() * 6 + 's';
            particle.style.animationDuration = (Math.random() * 4 + 4) + 's';
            particlesContainer.appendChild(particle);
        }
    }
    
    /**
     * Gestion des tentatives de connexion
     */
    function incrementAttempts() {
        attempts++;
        lastAttempt = Date.now();
        localStorage.setItem('login_attempts', attempts.toString());
        localStorage.setItem('last_attempt', lastAttempt.toString());
        
        if (attempts >= config.maxAttempts) {
            const remainingTime = Math.ceil(config.lockoutTime / 60000);
            showLoginError(`Trop de tentatives. Compte bloqué pendant ${remainingTime} minute(s).`);
        }
    }
    
    function resetAttempts() {
        attempts = 0;
        localStorage.removeItem('login_attempts');
        localStorage.removeItem('last_attempt');
    }
    
    function isLockedOut() {
        if (attempts < config.maxAttempts) return false;
        return (Date.now() - lastAttempt) < config.lockoutTime;
    }
    
    function getRemainingLockoutTime() {
        return config.lockoutTime - (Date.now() - lastAttempt);
    }
    
    function checkLockout() {
        if (isLockedOut()) {
            const remainingTime = getRemainingLockoutTime();
            showLoginError(`Compte temporairement bloqué. Réessayez dans ${Math.ceil(remainingTime / 60000)} minute(s).`);
            setSubmitButtonLoading(false);
            
            // Timer pour débloquer automatiquement
            setTimeout(() => {
                hideLoginError();
                resetAttempts();
            }, remainingTime);
        }
    }
    
    /**
     * Gestion des messages d'erreur et de succès
     */
    function showFieldError(field, message) {
        clearFieldError(field);
        
        field.classList.add('form-input--error');
        
        const errorElement = document.createElement('span');
        errorElement.className = 'form-error';
        errorElement.textContent = message;
        
        field.parentNode.appendChild(errorElement);
        
        // Animation d'entrée
        TheMind.utils.animate(errorElement, 'fade-in');
    }
    
    function showFieldSuccess(field) {
        clearFieldError(field);
        field.classList.add('form-input--success');
    }
    
    function clearFieldError(field) {
        field.classList.remove('form-input--error', 'form-input--success');
        
        const errorElement = field.parentNode.querySelector('.form-error');
        if (errorElement) {
            errorElement.remove();
        }
    }
    
    function clearAllFieldErrors() {
        [usernameField, passwordField].forEach(clearFieldError);
    }
    
    function showLoginError(message) {
        let errorElement = document.querySelector('.login__error');
        
        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.className = 'login__error';
            errorElement.setAttribute('role', 'alert');
            
            const form = document.querySelector('.login__form');
            form.parentNode.insertBefore(errorElement, form);
        }
        
        errorElement.textContent = message;
        errorElement.style.display = 'block';
        
        // Animation d'entrée
        TheMind.utils.animate(errorElement, 'fade-in');
        
        // Faire défiler jusqu'à l'erreur
        errorElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    
    function hideLoginError() {
        const errorElement = document.querySelector('.login__error');
        if (errorElement) {
            TheMind.utils.animate(errorElement, 'fade-out').then(() => {
                errorElement.style.display = 'none';
            });
        }
    }
    
    /**
     * Gestion du bouton de soumission
     */
    function setSubmitButtonLoading(loading) {
        if (loading) {
            submitButton.classList.add('btn--loading');
            submitButton.disabled = true;
        } else {
            submitButton.classList.remove('btn--loading');
            submitButton.disabled = false;
        }
    }
    
    /**
     * Animations visuelles
     */
    function shakeForm() {
        const container = document.querySelector('.login__container');
        container.style.animation = 'none';
        setTimeout(() => {
            container.style.animation = 'shake 0.5s ease-in-out';
        }, 10);
        
        setTimeout(() => {
            container.style.animation = '';
        }, 500);
    }
    
    function showSuccessAnimation() {
        const container = document.querySelector('.login__container');
        container.classList.add('success-glow');
        
        // Son de succès
        TheMind.utils.playSound('login_success');
        
        // Particules de succès
        createSuccessParticles();
    }
    
    function createSuccessParticles() {
        const container = document.querySelector('.login__container');
        const rect = container.getBoundingClientRect();
        
        for (let i = 0; i < 20; i++) {
            const particle = document.createElement('div');
            particle.style.cssText = `
                position: fixed;
                left: ${rect.left + rect.width / 2}px;
                top: ${rect.top + rect.height / 2}px;
                width: 4px;
                height: 4px;
                background: #4CAF50;
                border-radius: 50%;
                pointer-events: none;
                z-index: 10000;
                animation: successParticle 1s ease-out forwards;
                animation-delay: ${i * 50}ms;
            `;
            
            const angle = (i / 20) * Math.PI * 2;
            const distance = 50 + Math.random() * 50;
            particle.style.setProperty('--end-x', Math.cos(angle) * distance + 'px');
            particle.style.setProperty('--end-y', Math.sin(angle) * distance + 'px');
            
            document.body.appendChild(particle);
            
            setTimeout(() => particle.remove(), 1500);
        }
    }
    
    /**
     * Gestion du Caps Lock
     */
    function showCapsLockWarning() {
        if (capsLockWarning) {
            capsLockWarning.classList.add('show');
        }
    }
    
    function hideCapsLockWarning() {
        if (capsLockWarning) {
            capsLockWarning.classList.remove('show');
        }
    }
    
    /**
     * Gestion du changement de langue
     */
    if (languageSelect) {
        languageSelect.addEventListener('change', function() {
            const selectedLang = this.value;
            
            // Sauvegarder la préférence
            TheMind.preferences.save('language', selectedLang);
            
            // Recharger la page avec la nouvelle langue
            const url = new URL(window.location);
            url.searchParams.set('lang', selectedLang);
            window.location.href = url.toString();
        });
    }
    
    /**
     * Détection de la soumission automatique du navigateur
     */
    let autoSubmitDetected = false;
    
    // Détecter l'auto-remplissage du navigateur
    setTimeout(() => {
        if (usernameField.value && passwordField.value && !autoSubmitDetected) {
            // Le navigateur a probablement auto-rempli
            validateUsername();
            validatePassword();
        }
    }, 500);
    
    /**
     * Accessibilité - annonces pour les lecteurs d'écran
     */
    function announceToScreenReader(message) {
        const announcement = document.createElement('div');
        announcement.textContent = message;
        announcement.setAttribute('aria-live', 'polite');
        announcement.setAttribute('aria-atomic', 'true');
        announcement.style.cssText = `
            position: absolute;
            left: -10000px;
            width: 1px;
            height: 1px;
            overflow: hidden;
        `;
        
        document.body.appendChild(announcement);
        
        setTimeout(() => {
            document.body.removeChild(announcement);
        }, 1000);
    }
    
    /**
     * Gestion de la visibilité de la page
     */
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && isLockedOut()) {
            checkLockout();
        }
    });
    
    /**
     * Nettoyage à la fermeture
     */
    window.addEventListener('beforeunload', () => {
        // Nettoyer les timers et animations
        const particles = document.querySelectorAll('.login__particle');
        particles.forEach(p => p.remove());
    });
    
    /**
     * Gestion des erreurs de réseau
     */
    window.addEventListener('online', () => {
        hideLoginError();
        TheMind.utils.showNotification('Connexion Internet rétablie', 'success', 2000);
    });
    
    window.addEventListener('offline', () => {
        showLoginError('Pas de connexion Internet. Veuillez vérifier votre connexion.');
    });
});

// Styles CSS dynamiques pour les animations
const styleSheet = document.createElement('style');
styleSheet.textContent = `
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-10px); }
        75% { transform: translateX(10px); }
    }
    
    @keyframes successParticle {
        0% {
            transform: translate(0, 0) scale(1);
            opacity: 1;
        }
        100% {
            transform: translate(var(--end-x), var(--end-y)) scale(0);
            opacity: 0;
        }
    }
    
    .login__container.success-glow {
        box-shadow: 
            0 0 20px rgba(76, 175, 80, 0.6),
            0 0 40px rgba(76, 175, 80, 0.4),
            0 0 60px rgba(76, 175, 80, 0.2);
        border-color: #4CAF50;
    }
    
    .form-input--error {
        animation: inputError 0.3s ease-out;
    }
    
    @keyframes inputError {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }
`;

document.head.appendChild(styleSheet);