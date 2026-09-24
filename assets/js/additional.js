// Password visibility toggle
const passwordInput = document.getElementById('loginPassword');
const toggleIcon = document.getElementById('toggleLoginPassword');
const eyeOpen = document.getElementById('eyeOpen');
const eyeClosed = document.getElementById('eyeClosed');

function updateEyeIcon() {
    if (passwordInput.type === 'password') {
        eyeOpen.style.display = 'none';
        eyeClosed.style.display = 'inline';
    } else {
        eyeOpen.style.display = 'inline';
        eyeClosed.style.display = 'none';
    }
}

if(passwordInput && toggleIcon && eyeOpen && eyeClosed) {
    // Set initial state
    updateEyeIcon();

    toggleIcon.addEventListener('click', function() {
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
        } else {
            passwordInput.type = 'password';
        }
        updateEyeIcon();
    });

    passwordInput.addEventListener('input', function() {
        updateEyeIcon();
    });
}

// Reset password page eye icon logic
const resetPasswordInput = document.getElementById('resetPassword');
const resetToggleIcon = document.getElementById('toggleResetPassword');
const resetEyeOpen = document.getElementById('resetEyeOpen');
const resetEyeClosed = document.getElementById('resetEyeClosed');

function updateResetEyeIcon() {
    if (resetPasswordInput.type === 'password') {
        resetEyeOpen.style.display = 'none';
        resetEyeClosed.style.display = 'inline';
    } else {
        resetEyeOpen.style.display = 'inline';
        resetEyeClosed.style.display = 'none';
    }
}

if(resetPasswordInput && resetToggleIcon && resetEyeOpen && resetEyeClosed) {
    updateResetEyeIcon();

    resetToggleIcon.addEventListener('click', function() {
        if (resetPasswordInput.type === 'password') {
            resetPasswordInput.type = 'text';
        } else {
            resetPasswordInput.type = 'password';
        }
        updateResetEyeIcon();
    });

    resetPasswordInput.addEventListener('input', function() {
        updateResetEyeIcon();
    });
}

// Password Reset Enhancement Script
document.addEventListener('DOMContentLoaded', function() {
    // Password strength checker for reset password page
    const resetPasswordInput = document.getElementById('loginPassword');
    if (resetPasswordInput && window.location.pathname.includes('reset_password.php')) {
        resetPasswordInput.addEventListener('input', function() {
            checkPasswordStrength(this.value);
        });
    }
    
    // Close alert functionality
    const closeButtons = document.querySelectorAll('.close');
    closeButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const alert = this.closest('.alert, .static-alerts');
            if (alert) {
                alert.style.display = 'none';
            }
            const loginErrRow = document.getElementById('loginErrorRow');
            if (loginErrRow && alert && loginErrRow.contains(alert)) {
                loginErrRow.style.display = 'none';
            }
        });
    });
});

function checkPasswordStrength(password) {
    const strengthIndicator = document.getElementById('password-strength');
    
    if (!strengthIndicator) {
        const passwordField = document.getElementById('loginPassword');
        if (passwordField) {
            const indicator = document.createElement('div');
            indicator.id = 'password-strength';
            indicator.className = 'password-strength';
            indicator.setAttribute('aria-live', 'polite');
            const eyesRow = passwordField.closest('.eyes-row');
            if (eyesRow) {
                eyesRow.insertAdjacentElement('afterend', indicator);
            } else {
                passwordField.insertAdjacentElement('afterend', indicator);
            }
        }
    }
    
    const indicator = document.getElementById('password-strength');
    if (!indicator) return;
    
    let strength = 0;
    let feedback = '';
    
    // Check length
    if (password.length >= 8) strength += 1;
    if (password.length >= 12) strength += 1;
    
    // Check for different character types
    if (/[a-z]/.test(password)) strength += 1;
    if (/[A-Z]/.test(password)) strength += 1;
    if (/[0-9]/.test(password)) strength += 1;
    if (/[^A-Za-z0-9]/.test(password)) strength += 1;
    
    // Determine strength level
    if (password.length === 0) {
        indicator.style.display = 'none';
        return;
    }
    
    indicator.style.display = 'block';
    
    if (strength < 3) {
        indicator.className = 'password-strength weak';
        feedback = 'Weak password. Add more characters and variety.';
    } else if (strength < 5) {
        indicator.className = 'password-strength medium';
        feedback = 'Medium strength password. Consider adding more complexity.';
    } else {
        indicator.className = 'password-strength strong';
        feedback = 'Strong password!';
    }
    
    indicator.textContent = feedback;
}

// Form validation for password reset
function validatePasswordResetForm() {
    const password = document.getElementById('loginPassword');
    if (password && password.value.length < 8) {
        alert('Password must be at least 8 characters long.');
        return false;
    }
    return true;
}

// Auto-hide success alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert, .static-alerts');
    alerts.forEach(function(alert) {
        if (alert.classList.contains('alert-success') || alert.classList.contains('success')) {
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.style.display = 'none';
            }, 500);
        }
    });
}, 5000);

// ---------------------------------------------------------------------------
// Login page: AJAX submit + spinner (keeps user on page; single redirect on success)
// Google login button is an <a> and is NOT affected.
// ---------------------------------------------------------------------------
document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('form.login-form');
    var btn = document.getElementById('loginSubmitBtn');

    if (!form || !btn) return;

    function setLoginError(msg) {
        var row = document.getElementById('loginErrorRow');
        var text = document.getElementById('loginErrorText');
        var alertBox = row ? row.querySelector('.alert') : null;
        if (!row || !text) return;
        text.textContent = msg || '';
        if (msg) {
            row.style.removeProperty('display');
            if (alertBox) {
                alertBox.style.removeProperty('display');
                alertBox.style.removeProperty('opacity');
            }
        } else {
            row.style.display = 'none';
        }
    }

    function setButtonLoading(isLoading) {
        btn.disabled = !!isLoading;
        var spinner = btn.querySelector('.login-spinner') || btn.querySelector('.spinner-icon');
        if (spinner) spinner.style.display = isLoading ? 'inline-block' : 'none';
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        setLoginError('');
        setButtonLoading(true);

        var formData = new FormData(form);
        formData.append('ajax', '1');

        var postUrl = window.location.href;

        function goTo(path) {
            var redirectUrl = new URL(path || 'admin/index.php', window.location.href);
            window.location.replace(redirectUrl.toString());
        }

        fetch(postUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            redirect: 'manual',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(function (res) {
            if (res.type === 'opaqueredirect' || (res.status >= 300 && res.status < 400)) {
                var loc = res.headers.get('Location');
                goTo(loc || 'admin/index.php');
                return null;
            }
            return res.text().then(function (text) {
                return { res: res, text: text };
            });
        })
        .then(function (pack) {
            if (!pack) return;
            var data = null;
            try { data = JSON.parse(pack.text); } catch (e) {}
            if (data && data.success) {
                goTo(data.redirect || 'admin/index.php');
                return;
            }
            if (data && data.success === false) {
                setLoginError(data.message || 'Login failed');
                setButtonLoading(false);
                return;
            }
            var html = pack.text || '';
            var finalUrl = pack.res.url || '';
            if (/admin\/|staff\/|client\//i.test(finalUrl) || /Redirecting to:/i.test(html)) {
                goTo('admin/index.php');
                return;
            }
            setLoginError('Login failed. Please try again.');
            setButtonLoading(false);
        })
        .catch(function () {
            setLoginError('Login failed. Please try again.');
            setButtonLoading(false);
        });
    });
});