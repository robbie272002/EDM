// App Configuration
const CONFIG = {
    BASE_URL: window.location.origin,
    AUTH_BASE_URL: '/NEW/app/views/auth',
    API_BASE_URL: '/NEW/app/api',
    // API endpoints
    API: {
        BASE_URL: '/NEW/app/api',
        ENDPOINTS: {
            AUTH: '/auth',
            USERS: '/users',
            PRODUCTS: '/products',
            INVENTORY: '/inventory',
            SALES: '/sales',
            CATEGORIES: '/categories'
        }
    },

    // Chart defaults
    CHARTS: {
        COLORS: {
            PRIMARY: '#3b82f6',
            SUCCESS: '#22c55e',
            WARNING: '#eab308',
            DANGER: '#ef4444'
        },
        DEFAULTS: {
            responsive: true,
            maintainAspectRatio: false
        }
    },

    // Table defaults
    TABLES: {
        PAGE_SIZE: 10,
        SORT_DIRECTIONS: {
            ASC: 'asc',
            DESC: 'desc'
        }
    },

    // Notification settings
    NOTIFICATIONS: {
        AUTO_HIDE: true,
        AUTO_HIDE_DELAY: 5000,
        MAX_VISIBLE: 5
    },

    // Date formats
    DATE_FORMATS: {
        DISPLAY: 'MMM DD, YYYY',
        API: 'YYYY-MM-DD',
        TIME: 'HH:mm:ss'
    }
};

// POS Configuration
const POS_CONFIG = {
    // Store Information
    STORE: {
        NAME: 'RETAIL STORE #042',
        ADDRESS: '123 Main Street, City',
        PHONE: '(555) 123-4567',
        TERMINAL: '1'
    },

    // Tax Settings
    TAX: {
        RATE: 8, // 8%
        ENABLED: true
    },

    // Payment Methods
    PAYMENT_METHODS: {
        CASH: 'Cash',
        CARD: 'Card',
        MOBILE: 'Mobile'
    },

    // Receipt Settings
    RECEIPT: {
        WIDTH: '80mm',
        FONT_SIZE: '12px',
        LINE_HEIGHT: '20px'
    },

    // Transaction Settings
    TRANSACTION: {
        PREFIX: 'TR',
        DATE_FORMAT: 'YYYYMMDD',
        RANDOM_DIGITS: 4
    },

    // UI Settings
    UI: {
        CURRENCY_SYMBOL: '$',
        DECIMAL_PLACES: 2,
        DATE_FORMAT: 'MM/DD/YYYY',
        TIME_FORMAT: 'hh:mm A'
    },

    // Keyboard Shortcuts
    SHORTCUTS: {
        SEARCH: 'Ctrl+F',
        COMPLETE_SALE: 'Enter',
        VOID_TRANSACTION: 'Ctrl+V',
        PRINT_RECEIPT: 'Ctrl+P'
    }
};

// Export configuration
window.CONFIG = { ...CONFIG, ...POS_CONFIG }; 