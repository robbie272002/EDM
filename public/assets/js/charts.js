// Initialize charts when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeCharts();
});

function initializeCharts() {
    // Sales Overview Chart
    const salesCtx = document.getElementById('salesChart');
    if (salesCtx) {
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Sales',
                    data: [1200, 1900, 1500, 1800, 2100, 2400, 1800],
                    borderColor: '#3b82f6',
                    tension: 0.4,
                    fill: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Top Products Chart
    const productsCtx = document.getElementById('productsChart');
    if (productsCtx) {
        new Chart(productsCtx, {
            type: 'bar',
            data: {
                labels: ['Headphones', 'Mouse', 'Keyboard', 'Monitor', 'Laptop'],
                datasets: [{
                    label: 'Units Sold',
                    data: [45, 38, 32, 28, 25],
                    backgroundColor: '#3b82f6'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Inventory Status Chart
    const inventoryCtx = document.getElementById('inventoryStatusChart');
    if (inventoryCtx) {
        new Chart(inventoryCtx, {
            type: 'doughnut',
            data: {
                labels: ['In Stock', 'Low Stock', 'Out of Stock'],
                datasets: [{
                    data: [65, 20, 15],
                    backgroundColor: ['#22c55e', '#eab308', '#ef4444']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    // Product Performance Chart
    const performanceCtx = document.getElementById('productPerformanceChart');
    if (performanceCtx) {
        new Chart(performanceCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Revenue',
                    data: [12000, 19000, 15000, 18000, 21000, 24000],
                    borderColor: '#3b82f6',
                    tension: 0.4,
                    fill: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
} 