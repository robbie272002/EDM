<?php
// Define routes
$routes = [
    // Auth routes
    'GET /' => ['AuthController', 'showLogin'],
    'POST /login' => ['AuthController', 'login'],
    'GET /logout' => ['AuthController', 'logout'],
    
    // Admin routes
    'GET /admin/dashboard' => ['DashboardController', 'index'],
    'GET /admin/products' => ['ProductController', 'index'],
    'GET /admin/products/create' => ['ProductController', 'create'],
    'POST /admin/products/store' => ['ProductController', 'store'],
    'GET /admin/products/edit/{id}' => ['ProductController', 'edit'],
    'POST /admin/products/update/{id}' => ['ProductController', 'update'],
    'POST /admin/products/delete/{id}' => ['ProductController', 'delete'],
    
    // Cashier routes
    'GET /cashier/pos' => ['SaleController', 'pos'],
    'GET /cashier/transactions' => ['SaleController', 'transactions'],
    'POST /cashier/sales/store' => ['SaleController', 'store'],
    'GET /cashier/sales/{id}' => ['SaleController', 'show'],
    'GET /cashier/receipt/{id}' => ['SaleController', 'receipt'],
    
    // API routes
    'GET /api/dashboard/sales' => ['DashboardController', 'getSalesData'],
    'GET /api/dashboard/products' => ['DashboardController', 'getProductsData'],
    'GET /api/products' => ['ProductController', 'getAll'],
    'GET /api/products/{id}' => ['ProductController', 'getOne'],
];

// Route handler
function handleRoute($method, $path) {
    global $routes;
    
    $route = $method . ' ' . $path;
    
    // Check for dynamic routes
    foreach ($routes as $pattern => $handler) {
        $pattern = preg_replace('/\{([a-zA-Z]+)\}/', '([^/]+)', $pattern);
        if (preg_match('#^' . $pattern . '$#', $route, $matches)) {
            array_shift($matches); // Remove the full match
            
            $controller = 'app\\controllers\\' . $handler[0];
            $action = $handler[1];
            
            $instance = new $controller();
            return call_user_func_array([$instance, $action], $matches);
        }
    }
    
    // No route found
    header("HTTP/1.0 404 Not Found");
    echo "404 Not Found";
    exit;
} 