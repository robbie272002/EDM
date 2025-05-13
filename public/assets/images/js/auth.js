// Authentication Controller
class AuthController {
    constructor() {
        this.currentUser = null;
        this.initializeEventListeners();
    }

    initializeEventListeners() {
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', (e) => this.handleLogin(e));
        }
    }

    async handleLogin(event) {
        event.preventDefault();
        
        const username = document.getElementById('username').value;
        const password = document.getElementById('password').value;

        try {
            const response = await fetch(`${CONFIG.AUTH_BASE_URL}/login.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}`
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.setCurrentUser(data.user);
                this.redirectBasedOnRole(data.user.role);
            } else {
                alert(data.message || 'Login failed');
            }
        } catch (error) {
            console.error('Login error:', error);
            alert('An error occurred during login');
        }
    }

    async authenticateUser(username, password) {
        // This is a mock authentication
        // In a real application, this would be an API call to your backend
        const users = {
            'admin': { password: 'admin123', role: 'admin' },
            'cashier': { password: 'cashier123', role: 'cashier' }
        };

        if (users[username] && users[username].password === password) {
            return {
                username,
                role: users[username].role,
                name: username.charAt(0).toUpperCase() + username.slice(1)
            };
        }
        return null;
    }

    setCurrentUser(user) {
        this.currentUser = user;
        localStorage.setItem('currentUser', JSON.stringify(user));
    }

    redirectBasedOnRole(user) {
        if (user.role === 'admin') {
            window.location.href = 'admin/dashboard.php';
        } else if (user.role === 'cashier') {
            window.location.href = 'cashier/pos.php';
        }
    }

    logout() {
        this.currentUser = null;
        localStorage.removeItem('currentUser');
        window.location.href = '/index.php';
    }

    checkAuth() {
        const user = localStorage.getItem('currentUser');
        if (!user) {
            window.location.href = '/index.php';
            return null;
        }
        return JSON.parse(user);
    }
}

// Initialize auth controller
const auth = new AuthController();

// Authentication handling
document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }
});

async function handleLogin(e) {
    e.preventDefault();
    
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    
    try {
        const response = await fetch(`${CONFIG.AUTH_BASE_URL}/login.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            window.location.href = data.user.role === 'admin' ? 'admin/dashboard.php' : 'cashier/pos.php';
        } else {
            alert(data.message || 'Login failed');
        }
    } catch (error) {
        console.error('Login error:', error);
        alert('An error occurred during login');
    }
}

async function handleLogout() {
    try {
        const response = await fetch(`${CONFIG.AUTH_BASE_URL}/logout.php`);
        const data = await response.json();
        
        if (data.success) {
            window.location.href = '/index.php';
        } else {
            alert('Logout failed');
        }
    } catch (error) {
        console.error('Logout error:', error);
        alert('An error occurred during logout');
    }
} 