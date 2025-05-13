// Load components
document.addEventListener('DOMContentLoaded', function() {
    // Load sidebar
    fetch('components/sidebar.html')
        .then(response => response.text())
        .then(data => {
            document.getElementById('sidebar-container').innerHTML = data;
        });

    // Load header
    fetch('components/header.html')
        .then(response => response.text())
        .then(data => {
            document.getElementById('header-container').innerHTML = data;
        });

    // Load initial dashboard content
    showSection('dashboard');
});

// Navigation functions
function showSection(sectionId) {
    // Hide all sections
    document.querySelectorAll('.section-content').forEach(section => {
        section.classList.add('hidden');
    });

    // Show selected section
    const selectedSection = document.getElementById(sectionId);
    if (selectedSection) {
        selectedSection.classList.remove('hidden');
    }

    // Update section title
    const sectionTitle = document.getElementById('section-title');
    if (sectionTitle) {
        sectionTitle.textContent = sectionId.charAt(0).toUpperCase() + sectionId.slice(1);
    }

    // Update active sidebar item
    document.querySelectorAll('.sidebar-item').forEach(item => {
        item.classList.remove('active');
    });
    const activeItem = document.querySelector(`[onclick="showSection('${sectionId}')"]`);
    if (activeItem) {
        activeItem.classList.add('active');
    }
}

// Notification functions
function toggleNotifications() {
    const dropdown = document.getElementById('notifications-dropdown');
    if (dropdown) {
        dropdown.classList.toggle('hidden');
    }
}

// Logout function
function logout() {
    // Add logout logic here
    console.log('Logging out...');
}

// Sales tab functions
function showSalesTab(tabId) {
    // Hide all sales tabs
    document.querySelectorAll('.sales-tab').forEach(tab => {
        tab.classList.add('hidden');
    });

    // Show selected tab
    const selectedTab = document.getElementById(`${tabId}-sales`);
    if (selectedTab) {
        selectedTab.classList.remove('hidden');
    }

    // Update active tab styling
    document.querySelectorAll('.tab-active').forEach(tab => {
        tab.classList.remove('tab-active');
    });
    const activeTab = document.querySelector(`[onclick="showSalesTab('${tabId}')"]`);
    if (activeTab) {
        activeTab.classList.add('tab-active');
    }
}

// Modal functions
function showInventoryModal() {
    // Add inventory modal logic
    console.log('Showing inventory modal...');
}

function showProductModal() {
    // Add product modal logic
    console.log('Showing product modal...');
}

function showCategoryModal() {
    // Add category modal logic
    console.log('Showing category modal...');
}

function showUserModal() {
    // Add user modal logic
    console.log('Showing user modal...');
} 