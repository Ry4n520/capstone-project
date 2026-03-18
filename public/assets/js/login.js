/**
 * Login Page JavaScript
 * 
 * Handles client-side validation and user interactions
 */

document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');

    // Form submission handler
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            // Validate email format
            if (!isValidEmail(emailInput.value)) {
                e.preventDefault();
                showError('Please enter a valid email address.');
                return false;
            }

            // Validate password is not empty
            if (passwordInput.value.trim() === '') {
                e.preventDefault();
                showError('Password is required.');
                return false;
            }

            // Form will submit normally if validation passes
        });

        // Add input focus effects
        emailInput.addEventListener('focus', function() {
            this.style.borderColor = '#667eea';
        });

        emailInput.addEventListener('blur', function() {
            this.style.borderColor = '#e2e8f0';
        });

        passwordInput.addEventListener('focus', function() {
            this.style.borderColor = '#667eea';
        });

        passwordInput.addEventListener('blur', function() {
            this.style.borderColor = '#e2e8f0';
        });
    }
});

/**
 * Validate email format
 * @param {string} email - Email address to validate
 * @returns {boolean} - True if valid email format
 */
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

/**
 * Show error message to user
 * @param {string} message - Error message to display
 */
function showError(message) {
    let errorDiv = document.querySelector('.error-message');
    
    if (!errorDiv) {
        errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        const form = document.getElementById('loginForm');
        form.parentNode.insertBefore(errorDiv, form);
    }
    
    errorDiv.textContent = message;
    errorDiv.style.display = 'block';
}
