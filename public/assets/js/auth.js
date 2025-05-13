document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const errorMessage = document.getElementById('error-message');
    
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }
});

async function handleLogin(e) {
    e.preventDefault();
    
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    const errorMessage = document.getElementById('error-message');
    const submitButton = e.target.querySelector('button[type="submit"]');
    
    // Show loading state
    submitButton.disabled = true;
    submitButton.innerHTML = '<span class="spinner"></span> Signing in...';
    errorMessage.classList.add('hidden');
    
    try {
        // Use absolute path with /NEW/ prefix
        const loginUrl = '/NEW/app/views/auth/login.php';
        console.log('Attempting login to:', loginUrl);
        
        const response = await fetch(loginUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}`
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            // Redirect based on role
            if (data.user.role === 'admin') {
                window.location.href = '/NEW/app/views/admin/dashboard.php';
            } else {
                window.location.href = '/NEW/app/views/cashier/pos.php';
            }
        } else {
            errorMessage.textContent = data.message || 'Login failed';
            errorMessage.classList.remove('hidden');
        }
    } catch (error) {
        console.error('Login error:', error);
        errorMessage.textContent = 'An error occurred during login. Please try again.';
        errorMessage.classList.remove('hidden');
    } finally {
        // Reset button state
        submitButton.disabled = false;
        submitButton.innerHTML = `
            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                <i class="fas fa-lock"></i>
            </span>
            Sign in
        `;
    }
} 