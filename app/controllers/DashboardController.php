<?php
namespace app\controllers;

use app\models\Sale;
use app\models\Product;
use app\models\User;

class DashboardController {
    private $saleModel;
    private $productModel;
    private $userModel;
    
    public function __construct() {
        $this->saleModel = new Sale();
        $this->productModel = new Product();
        $this->userModel = new User();
    }
    
    public function index() {
        // Get dashboard statistics
        $stats = [
            'todaySales' => $this->saleModel->getTodaySales(),
            'todayTransactions' => $this->saleModel->getTodayTransactions(),
            'lowStockItems' => $this->productModel->getLowStockCount(),
            'activeUsers' => $this->userModel->getActiveUsersCount(),
            'recentTransactions' => $this->saleModel->getRecentTransactions(10),
            'topProducts' => $this->productModel->getTopProducts(5),
            'uniqueVisitors' => $this->userModel->getUniqueVisitors(),
            'totalTransactions' => $this->saleModel->getTotalTransactions(),
            'conversionRate' => $this->saleModel->getConversionRate(),
            'avgTransactionTime' => $this->saleModel->getAverageTransactionTime(),
            'topChannels' => $this->saleModel->getTopChannels(),
            'monthlySales' => $this->saleModel->getMonthlySales(),
            'avgDaily' => $this->userModel->getAverageDailyUsers(),
            'avgWeekly' => $this->userModel->getAverageWeeklyUsers(),
            'avgMonthly' => $this->userModel->getAverageMonthlyUsers()
        ];
        
        // Load the dashboard view
        require_once __DIR__ . '/../views/admin/dashboard.php';
    }
    
    public function getSalesData() {
        $period = $_GET['period'] ?? 'month';
        $data = $this->saleModel->getSalesData($period);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'labels' => $data['labels'],
            'values' => $data['values']
        ]);
    }
    
    public function getProductsData() {
        $period = $_GET['period'] ?? 'week';
        $data = $this->productModel->getTopProducts($period);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'labels' => $data['labels'],
            'values' => $data['values']
        ]);
    }
} 