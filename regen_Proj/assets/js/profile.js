/**
 * Profile JavaScript - The Mind
 * 
 * Gestion des interactions et fonctionnalités des pages profil utilisateur
 * Compatible avec l'architecture JavaScript modulaire établie
 * 
 * @package TheMind
 * @version 1.0
 * @since Phase 3
 */

(function() {
    'use strict';
    
    // Configuration spécifique au profil
    const ProfileConfig = {
        animations: {
            duration: 300,
            easing: 'ease-in-out'
        },
        validation: {
            usernameMinLength: 3,
            usernameMaxLength: 30,
            passwordMinLength: 8
        },
        avatar: {
            defaultSize: 120,
            hoverScale: 1.1,
            selectedScale: 1.2
        }
    };
    
    // Module de gestion du profil
    window.TheMind.Profile = {
        // Initialisation
        init: function() {
            this.bindEvents();
            this.initAnimations();
            this.initAvatarSelector();
            this.initPasswordValidation();
            this.initFormValidation();
            
            console.log('Profile module initialized');
        },
        
        // Liaison des événements
        bindEvents: function() {
            // Statistiques animées au survol
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => {
                card.addEventListener('mouseenter', this.animateStatCard.bind(this));
                card.addEventListener('mouseleave', this.resetStatCard.bind(this));
            });
            
            // Tooltips pour les badges
            const badges = document.querySelectorAll('.badge, .role-badge');
            badges.forEach(badge => {
                badge.addEventListener('mouseenter', this.showTooltip.bind(this));
                badge.addEventListener('mouseleave', this.hideTooltip.bind(this));
            });
            
            // Confirmation de déconnexion
            const logoutForms = document.querySelectorAll('form[action*="logout"]');
            logoutForms.forEach(form => {
                form.addEventListener('submit', this.confirmLogout.bind(this));
            });
            
            // Navigation fluide vers les sections
            const sectionLinks = document.querySelectorAll('a[href^="#"]');
            sectionLinks.forEach(link => {
                link.addEventListener('click', this.smoothScroll.bind(this));
            });
        },
        
        // Animations d'entrée
        initAnimations: function() {
            // Animation des cartes de statistiques
            this.animateStatsOnLoad();
            
            // Animation du tableau d'historique
            this.animateHistoryTable();
            
            // Animation des achievements
            this.animateAchievements();
        },
        
        // Animation des statistiques au chargement
        animateStatsOnLoad: function() {
            const statCards = document.querySelectorAll('.stat-card');
            
            statCards.forEach((card, index) => {
                // État initial
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px) scale(0.9)';
                
                // Animation avec délai progressif
                setTimeout(() => {
                    card.style.transition = `all ${ProfileConfig.animations.duration * 2}ms ${ProfileConfig.animations.easing}`;
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0) scale(1)';
                    
                    // Animation du nombre
                    this.animateNumber(card.querySelector('.stat-value'));
                }, index * 150);
            });
        },
        
        // Animation des nombres (compteur)
        animateNumber: function(element) {
            if (!element) return;
            
            const finalValue = parseInt(element.textContent) || 0;
            const duration = 1500;
            const increment = Math.ceil(finalValue / (duration / 16));
            let currentValue = 0;
            
            const counter = setInterval(() => {
                currentValue += increment;
                if (currentValue >= finalValue) {
                    currentValue = finalValue;
                    clearInterval(counter);
                }
                
                // Préserver les suffixes (%, h, min, etc.)
                const suffix = element.textContent.replace(/[\d,]/g, '');
                element.textContent = this.formatNumber(currentValue) + suffix;
            }, 16);
        },
        
        // Formatage des nombres
        formatNumber: function(num) {
            return new Intl.NumberFormat().format(num);
        },
        
        // Animation de carte de statistique au survol
        animateStatCard: function(event) {
            const card = event.currentTarget;
            const value = card.querySelector('.stat-value');
            
            card.style.transform = 'translateY(-8px) scale(1.02)';
            card.style.boxShadow = 'var(--shadow-lg)';
            
            if (value) {
                value.style.transform = `scale(${ProfileConfig.avatar.hoverScale})`;
                value.style.color = 'var(--secondary-color)';
            }
        },
        
        // Réinitialisation de l'animation de carte
        resetStatCard: function(event) {
            const card = event.currentTarget;
            const value = card.querySelector('.stat-value');
            
            card.style.transform = 'translateY(0) scale(1)';
            card.style.boxShadow = 'var(--shadow-sm)';
            
            if (value) {
                value.style.transform = 'scale(1)';
                value.style.color = 'var(--primary-color)';
            }
        },
        
        // Animation du tableau d'historique
        animateHistoryTable: function() {
            const rows = document.querySelectorAll('.history-table tbody tr');
            
            rows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateX(-20px)';
                
                setTimeout(() => {
                    row.style.transition = `all ${ProfileConfig.animations.duration}ms ${ProfileConfig.animations.easing}`;
                    row.style.opacity = '1';
                    row.style.transform = 'translateX(0)';
                }, index * 50);
            });
        },
        
        // Animation des achievements
        animateAchievements: function() {
            const achievements = document.querySelectorAll('.achievement-item');
            
            achievements.forEach((achievement, index) => {
                achievement.style.opacity = '0';
                achievement.style.transform = 'scale(0.8)';
                
                setTimeout(() => {
                    achievement.style.transition = `all ${ProfileConfig.animations.duration}ms ${ProfileConfig.animations.easing}`;
                    achievement.style.opacity = '1';
                    achievement.style.transform = 'scale(1)';
                }, index * 100);
            });
        },
        
        // Initialisation du sélecteur d'avatar
        initAvatarSelector: function() {
            const avatarOptions = document.querySelectorAll('.avatar-option');
            const avatarInput = document.getElementById('avatar');
            const currentAvatarDisplay = document.getElementById('currentAvatar');
            
            if (!avatarOptions.length) return;
            
            avatarOptions.forEach(option => {
                // Événements de sélection
                option.addEventListener('click', (e) => {
                    this.selectAvatar(e.target, avatarInput, currentAvatarDisplay, avatarOptions);
                });
                
                // Animation au survol
                option.addEventListener('mouseenter', (e) => {
                    if (!e.target.classList.contains('selected')) {
                        e.target.style.transform = `scale(${ProfileConfig.avatar.hoverScale})`;
                        e.target.style.boxShadow = 'var(--shadow-md)';
                    }
                });
                
                option.addEventListener('mouseleave', (e) => {
                    if (!e.target.classList.contains('selected')) {
                        e.target.style.transform = 'scale(1)';
                        e.target.style.boxShadow = 'var(--shadow-sm)';
                    }
                });
            });
        },
        
        // Sélection d'avatar
        selectAvatar: function(selectedOption, avatarInput, currentAvatarDisplay, allOptions) {
            // Retirer la sélection précédente
            allOptions.forEach(opt => {
                opt.classList.remove('selected');
                opt.style.transform = 'scale(1)';
                opt.style.backgroundColor = 'var(--card-bg)';
                opt.style.color = 'inherit';
            });
            
            // Sélectionner le nouvel avatar
            selectedOption.classList.add('selected');
            selectedOption.style.transform = `scale(${ProfileConfig.avatar.selectedScale})`;
            selectedOption.style.backgroundColor = 'var(--primary-color)';
            selectedOption.style.color = 'white';
            
            const selectedAvatar = selectedOption.dataset.avatar;
            
            // Mettre à jour l'input caché et l'affichage
            if (avatarInput) avatarInput.value = selectedAvatar;
            if (currentAvatarDisplay) {
                currentAvatarDisplay.textContent = selectedAvatar;
                
                // Animation de confirmation
                currentAvatarDisplay.style.transform = `scale(${ProfileConfig.avatar.selectedScale})`;
                setTimeout(() => {
                    currentAvatarDisplay.style.transform = 'scale(1)';
                }, ProfileConfig.animations.duration);
            }
            
            // Effet sonore si disponible
            this.playSound('select');
        },
        
        // Initialisation de la validation des mots de passe
        initPasswordValidation: function() {
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            
            if (!newPasswordInput || !confirmPasswordInput) return;
            
            // Validation en temps réel
            const validatePasswords = () => {
                this.validatePasswordStrength(newPasswordInput);
                this.validatePasswordMatch(newPasswordInput, confirmPasswordInput);
            };
            
            newPasswordInput.addEventListener('input', validatePasswords);
            confirmPasswordInput.addEventListener('input', validatePasswords);
            
            // Indicateur de force du mot de passe
            this.createPasswordStrengthIndicator(newPasswordInput);
        },
        
        // Validation de la force du mot de passe
        validatePasswordStrength: function(input) {
            const password = input.value;
            const strength = this.calculatePasswordStrength(password);
            
            // Mettre à jour l'indicateur visuel
            this.updatePasswordStrengthIndicator(input, strength);
            
            // Validation HTML5
            if (password.length > 0 && password.length < ProfileConfig.validation.passwordMinLength) {
                input.setCustomValidity(`Le mot de passe doit contenir au moins ${ProfileConfig.validation.passwordMinLength} caractères`);
            } else {
                input.setCustomValidity('');
            }
        },
        
        // Calcul de la force du mot de passe
        calculatePasswordStrength: function(password) {
            let score = 0;
            const checks = {
                length: password.length >= 8,
                lowercase: /[a-z]/.test(password),
                uppercase: /[A-Z]/.test(password),
                numbers: /\d/.test(password),
                symbols: /[^A-Za-z0-9]/.test(password)
            };
            
            Object.values(checks).forEach(check => {
                if (check) score++;
            });
            
            if (password.length >= 12) score++;
            
            return {
                score: score,
                level: score < 3 ? 'weak' : score < 5 ? 'medium' : 'strong',
                checks: checks
            };
        },
        
        // Création de l'indicateur de force
        createPasswordStrengthIndicator: function(input) {
            const indicator = document.createElement('div');
            indicator.className = 'password-strength-indicator';
            indicator.innerHTML = `
                <div class="strength-bar">
                    <div class="strength-fill"></div>
                </div>
                <div class="strength-text"></div>
                <div class="strength-checks">
                    <span class="check" data-check="length">8+ caractères</span>
                    <span class="check" data-check="lowercase">Minuscule</span>
                    <span class="check" data-check="uppercase">Majuscule</span>
                    <span class="check" data-check="numbers">Chiffre</span>
                    <span class="check" data-check="symbols">Symbole</span>
                </div>
            `;
            
            input.parentNode.insertBefore(indicator, input.nextSibling);
            
            // Styles CSS inline pour l'indicateur
            const style = document.createElement('style');
            style.textContent = `
                .password-strength-indicator {
                    margin-top: 0.5rem;
                    padding: 1rem;
                    background: var(--bg-secondary);
                    border-radius: var(--border-radius);
                    border-left: 4px solid var(--border-color);
                }
                .strength-bar {
                    height: 6px;
                    background: var(--border-color);
                    border-radius: 3px;
                    overflow: hidden;
                    margin-bottom: 0.5rem;
                }
                .strength-fill {
                    height: 100%;
                    width: 0%;
                    transition: all 0.3s ease;
                    border-radius: 3px;
                }
                .strength-text {
                    font-size: 0.875rem;
                    font-weight: 600;
                    margin-bottom: 0.5rem;
                }
                .strength-checks {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 0.5rem;
                }
                .check {
                    font-size: 0.75rem;
                    padding: 0.25rem 0.5rem;
                    border-radius: var(--border-radius);
                    background: var(--card-bg);
                    border: 1px solid var(--border-color);
                    transition: all 0.3s ease;
                }
                .check.valid {
                    background: var(--success-color);
                    color: white;
                    border-color: var(--success-color);
                }
            `;
            document.head.appendChild(style);
        },
        
        // Mise à jour de l'indicateur de force
        updatePasswordStrengthIndicator: function(input, strength) {
            const indicator = input.parentNode.querySelector('.password-strength-indicator');
            if (!indicator) return;
            
            const fill = indicator.querySelector('.strength-fill');
            const text = indicator.querySelector('.strength-text');
            const checks = indicator.querySelectorAll('.check');
            
            // Barre de progression
            const percentage = (strength.score / 6) * 100;
            fill.style.width = percentage + '%';
            
            // Couleurs selon la force
            const colors = {
                weak: '#ef4444',
                medium: '#f59e0b',
                strong: '#10b981'
            };
            
            fill.style.backgroundColor = colors[strength.level];
            indicator.style.borderLeftColor = colors[strength.level];
            
            // Texte descriptif
            const texts = {
                weak: 'Mot de passe faible',
                medium: 'Mot de passe moyen',
                strong: 'Mot de passe fort'
            };
            
            text.textContent = texts[strength.level];
            text.style.color = colors[strength.level];
            
            // Validation des critères
            checks.forEach(check => {
                const criteria = check.dataset.check;
                if (strength.checks[criteria]) {
                    check.classList.add('valid');
                } else {
                    check.classList.remove('valid');
                }
            });
        },
        
        // Validation de la correspondance des mots de passe
        validatePasswordMatch: function(newPasswordInput, confirmPasswordInput) {
            const newPassword = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (confirmPassword && newPassword !== confirmPassword) {
                confirmPasswordInput.setCustomValidity('Les mots de passe ne correspondent pas');
                this.addFieldError(confirmPasswordInput, 'Les mots de passe ne correspondent pas');
            } else {
                confirmPasswordInput.setCustomValidity('');
                this.removeFieldError(confirmPasswordInput);
            }
        },
        
        // Initialisation de la validation des formulaires
        initFormValidation: function() {
            const forms = document.querySelectorAll('form');
            
            forms.forEach(form => {
                form.addEventListener('submit', (e) => {
                    if (!this.validateForm(form)) {
                        e.preventDefault();
                    }
                });
                
                // Validation en temps réel des champs
                const inputs = form.querySelectorAll('input, select, textarea');
                inputs.forEach(input => {
                    input.addEventListener('blur', () => {
                        this.validateField(input);
                    });
                    
                    input.addEventListener('input', () => {
                        this.removeFieldError(input);
                    });
                });
            });
        },
        
        // Validation d'un formulaire
        validateForm: function(form) {
            let isValid = true;
            const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
            
            inputs.forEach(input => {
                if (!this.validateField(input)) {
                    isValid = false;
                }
            });
            
            return isValid;
        },
        
        // Validation d'un champ
        validateField: function(field) {
            const value = field.value.trim();
            let isValid = true;
            let errorMessage = '';
            
            // Validation selon le type de champ
            switch (field.type) {
                case 'email':
                    if (value && !this.isValidEmail(value)) {
                        errorMessage = 'Adresse email invalide';
                        isValid = false;
                    }
                    break;
                    
                case 'text':
                    if (field.name === 'username') {
                        if (value.length < ProfileConfig.validation.usernameMinLength) {
                            errorMessage = `Le nom d'utilisateur doit contenir au moins ${ProfileConfig.validation.usernameMinLength} caractères`;
                            isValid = false;
                        } else if (value.length > ProfileConfig.validation.usernameMaxLength) {
                            errorMessage = `Le nom d'utilisateur ne peut pas dépasser ${ProfileConfig.validation.usernameMaxLength} caractères`;
                            isValid = false;
                        } else if (!/^[a-zA-Z0-9_-]+$/.test(value)) {
                            errorMessage = 'Le nom d\'utilisateur ne peut contenir que des lettres, chiffres, tirets et underscores';
                            isValid = false;
                        }
                    }
                    break;
                    
                case 'password':
                    if (field.name === 'new_password' && value.length > 0 && value.length < ProfileConfig.validation.passwordMinLength) {
                        errorMessage = `Le mot de passe doit contenir au moins ${ProfileConfig.validation.passwordMinLength} caractères`;
                        isValid = false;
                    }
                    break;
            }
            
            // Affichage de l'erreur
            if (!isValid) {
                this.addFieldError(field, errorMessage);
            } else {
                this.removeFieldError(field);
            }
            
            return isValid;
        },
        
        // Validation email
        isValidEmail: function(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        },
        
        // Ajout d'une erreur de champ
        addFieldError: function(field, message) {
            this.removeFieldError(field);
            
            field.classList.add('error');
            
            const errorDiv = document.createElement('div');
            errorDiv.className = 'form-error';
            errorDiv.textContent = message;
            
            field.parentNode.appendChild(errorDiv);
        },
        
        // Suppression d'une erreur de champ
        removeFieldError: function(field) {
            field.classList.remove('error');
            
            const existingError = field.parentNode.querySelector('.form-error');
            if (existingError) {
                existingError.remove();
            }
        },
        
        // Affichage de tooltip
        showTooltip: function(event) {
            const element = event.currentTarget;
            const tooltip = element.getAttribute('title') || element.getAttribute('data-tooltip');
            
            if (!tooltip) return;
            
            const tooltipElement = document.createElement('div');
            tooltipElement.className = 'tooltip';
            tooltipElement.textContent = tooltip;
            tooltipElement.style.cssText = `
                position: absolute;
                background: var(--dark-color);
                color: var(--light-color);
                padding: 0.5rem 1rem;
                border-radius: var(--border-radius);
                font-size: 0.875rem;
                white-space: nowrap;
                z-index: 1000;
                pointer-events: none;
                box-shadow: var(--shadow-lg);
            `;
            
            document.body.appendChild(tooltipElement);
            
            // Positionnement
            const rect = element.getBoundingClientRect();
            tooltipElement.style.left = rect.left + (rect.width / 2) - (tooltipElement.offsetWidth / 2) + 'px';
            tooltipElement.style.top = rect.top - tooltipElement.offsetHeight - 10 + 'px';
            
            element._tooltip = tooltipElement;
        },
        
        // Masquage de tooltip
        hideTooltip: function(event) {
            const element = event.currentTarget;
            if (element._tooltip) {
                element._tooltip.remove();
                delete element._tooltip;
            }
        },
        
        // Confirmation de déconnexion
        confirmLogout: function(event) {
            if (!confirm('Êtes-vous sûr de vouloir vous déconnecter ?')) {
                event.preventDefault();
            }
        },
        
        // Navigation fluide
        smoothScroll: function(event) {
            const href = event.currentTarget.getAttribute('href');
            if (!href.startsWith('#')) return;
            
            event.preventDefault();
            
            const target = document.querySelector(href);
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        },
        
        // Lecture de son
        playSound: function(soundType) {
            if (!window.TheMind.config.soundEnabled) return;
            
            const audio = new Audio();
            audio.volume = 0.3;
            
            switch (soundType) {
                case 'select':
                    audio.src = window.TheMind.config.assetsUrl + 'sounds/select.mp3';
                    break;
                case 'success':
                    audio.src = window.TheMind.config.assetsUrl + 'sounds/success.mp3';
                    break;
                case 'error':
                    audio.src = window.TheMind.config.assetsUrl + 'sounds/error.mp3';
                    break;
                default:
                    return;
            }
            
            audio.play().catch(() => {
                // Son non disponible, continuer silencieusement
            });
        },
        
        // Notification toast
        showNotification: function(message, type = 'info', duration = 3000) {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <div class="notification-content">
                    <span class="notification-icon">${this.getNotificationIcon(type)}</span>
                    <span class="notification-message">${message}</span>
                </div>
                <button class="notification-close">&times;</button>
            `;
            
            // Styles
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: var(--card-bg);
                border: 1px solid var(--border-color);
                border-radius: var(--border-radius);
                padding: 1rem;
                box-shadow: var(--shadow-lg);
                z-index: 10000;
                display: flex;
                align-items: center;
                gap: 1rem;
                max-width: 400px;
                animation: slideInRight 0.3s ease;
            `;
            
            // Couleurs selon le type
            const colors = {
                success: '#10b981',
                error: '#ef4444',
                warning: '#f59e0b',
                info: '#3b82f6'
            };
            
            notification.style.borderLeftColor = colors[type] || colors.info;
            
            document.body.appendChild(notification);
            
            // Bouton de fermeture
            const closeBtn = notification.querySelector('.notification-close');
            closeBtn.addEventListener('click', () => {
                this.hideNotification(notification);
            });
            
            // Fermeture automatique
            if (duration > 0) {
                setTimeout(() => {
                    this.hideNotification(notification);
                }, duration);
            }
            
            return notification;
        },
        
        // Icône de notification
        getNotificationIcon: function(type) {
            const icons = {
                success: '✅',
                error: '❌',
                warning: '⚠️',
                info: 'ℹ️'
            };
            return icons[type] || icons.info;
        },
        
        // Masquage de notification
        hideNotification: function(notification) {
            notification.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
        }
    };
    
    // Fonctions utilitaires globales pour les pages profil
    window.resetProfileForm = function() {
        if (confirm('Êtes-vous sûr de vouloir réinitialiser le formulaire ?')) {
            const form = document.getElementById('profileForm');
            if (form) {
                form.reset();
                
                // Remettre l'avatar par défaut
                const defaultAvatar = form.dataset.defaultAvatar || '👤';
                const avatarInput = document.getElementById('avatar');
                const currentAvatarDisplay = document.getElementById('currentAvatar');
                
                if (avatarInput) avatarInput.value = defaultAvatar;
                if (currentAvatarDisplay) currentAvatarDisplay.textContent = defaultAvatar;
                
                // Remettre la sélection d'avatar
                document.querySelectorAll('.avatar-option').forEach(opt => {
                    opt.classList.remove('selected');
                    if (opt.dataset.avatar === defaultAvatar) {
                        opt.classList.add('selected');
                    }
                });
                
                window.TheMind.Profile.showNotification('Formulaire réinitialisé', 'info');
            }
        }
    };
    
    window.clearPasswordForm = function() {
        const form = document.getElementById('passwordForm');
        if (form) {
            form.reset();
            
            // Effacer les messages d'erreur
            form.querySelectorAll('.form-error').forEach(error => error.remove());
            form.querySelectorAll('.error').forEach(field => field.classList.remove('error'));
            
            window.TheMind.Profile.showNotification('Champs de mot de passe effacés', 'info');
        }
    };
    
    // Initialisation automatique
    document.addEventListener('DOMContentLoaded', function() {
        if (window.TheMind && window.TheMind.config.pageType && 
            (window.TheMind.config.pageType.includes('profile') || 
             window.TheMind.config.pageType.includes('edit'))) {
            window.TheMind.Profile.init();
        }
    });
    
    // Styles CSS pour les animations
    const profileStyles = document.createElement('style');
    profileStyles.textContent = `
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        
        .form-input.error {
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }
        
        .form-error {
            color: #ef4444;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .form-error::before {
            content: '⚠️';
            font-size: 0.75rem;
        }
        
        .notification {
            border-left-width: 4px;
        }
        
        .notification-content {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex: 1;
        }
        
        .notification-close {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 1.25rem;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .notification-close:hover {
            color: var(--text-primary);
        }
    `;
    document.head.appendChild(profileStyles);
    
})();