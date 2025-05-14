<?php
// Start output buffering
ob_start();

require_once __DIR__ . '/../../../../app/config/database.php';
require_once __DIR__ . '/../../../../app/config/auth.php';
require_once __DIR__ . '/../../../../vendor/autoload.php'; // For TCPDF

// Add Chart.php library
require_once __DIR__ . '/../../../../app/libraries/Chart.php';

// Function to generate narrative analysis
function generateNarrativeAnalysis($data, $totalRevenue, $totalTransactions, $growth) {
    $narrative = "";
    
    // Overall Performance
    $narrative .= "Overall Performance:\n";
    $narrative .= "The total revenue for this period was $" . number_format($totalRevenue, 2);
    $narrative .= " with " . number_format($totalTransactions) . " transactions. ";
    
    // Growth Analysis
    if ($growth > 0) {
        $narrative .= "This represents a positive growth of " . round($growth, 1) . "% compared to the previous period. ";
    } elseif ($growth < 0) {
        $narrative .= "This shows a decline of " . abs(round($growth, 1)) . "% compared to the previous period. ";
    } else {
        $narrative .= "Performance remained stable compared to the previous period. ";
    }
    
    // Trend Analysis
    if (count($data) > 1) {
        $revenueValues = array_column($data, 'total_revenue');
        $lastValue = end($revenueValues);
        $firstValue = reset($revenueValues);
        
        if ($lastValue > $firstValue) {
            $narrative .= "\nThe trend shows an upward movement in revenue throughout the period. ";
        } elseif ($lastValue < $firstValue) {
            $narrative .= "\nThe trend shows a downward movement in revenue throughout the period. ";
        } else {
            $narrative .= "\nRevenue remained relatively stable throughout the period. ";
        }
        
        // Average Transaction Value
        $avgTransactionValue = $totalRevenue / max($totalTransactions, 1);
        $narrative .= "\nThe average transaction value for this period was $" . number_format($avgTransactionValue, 2) . ". ";
    }
    
    return $narrative;
}

// Check if user is logged in
requireLogin();

// Get parameters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');
$reportType = $_GET['report_type'] ?? 'sales';
$exportType = $_GET['export'] ?? 'pdf';

// Get sales data based on report type
switch ($reportType) {
    case 'products':
        $query = "SELECT 
                    p.name as product_name,
                    c.name as category_name,
                    COUNT(DISTINCT s.id) as total_sales,
                    SUM(si.quantity) as total_quantity,
                    SUM(s.subtotal) as total_subtotal,
                    SUM(s.total_amount) as total_revenue,
                    AVG(si.price) as avg_sale_price
                  FROM sales s
                  JOIN sale_items si ON s.id = si.sale_id
                  JOIN products p ON si.product_id = p.id
                  JOIN categories c ON p.category_id = c.id
                  WHERE s.created_at BETWEEN ? AND ?
                  GROUP BY p.id
                  ORDER BY total_revenue DESC";
        break;
    
    case 'categories':
        $query = "SELECT 
                    c.name as category_name,
                    COUNT(DISTINCT p.id) as total_products,
                    COUNT(DISTINCT s.id) as total_sales,
                    SUM(si.quantity) as total_quantity,
                    SUM(s.subtotal) as total_subtotal,
                    SUM(s.total_amount) as total_revenue
                  FROM sales s
                  JOIN sale_items si ON s.id = si.sale_id
                  JOIN products p ON si.product_id = p.id
                  JOIN categories c ON p.category_id = c.id
                  WHERE s.created_at BETWEEN ? AND ?
                  GROUP BY c.id
                  ORDER BY total_revenue DESC";
        break;
    
    default: // sales
        $query = "SELECT 
                    DATE(s.created_at) as sale_date,
                    COUNT(DISTINCT s.id) as total_transactions,
                    SUM(s.total_amount) as total_revenue,
                    COUNT(DISTINCT si.product_id) as unique_products
                  FROM sales s
                  LEFT JOIN sale_items si ON s.id = si.sale_id
                  WHERE s.created_at BETWEEN ? AND ?
                  GROUP BY DATE(s.created_at)
                  ORDER BY sale_date DESC";
}

$stmt = $pdo->prepare($query);
$stmt->execute([$startDate, $endDate]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$totalRevenue = array_sum(array_column($data, 'total_revenue'));
$totalTransactions = array_sum(array_column($data, 'total_transactions'));

// Get previous period data for comparison
$prevStartDate = date('Y-m-d', strtotime($startDate . ' -1 month'));
$prevEndDate = date('Y-m-d', strtotime($endDate . ' -1 month'));

$stmt->execute([$prevStartDate, $prevEndDate]);
$prevData = $stmt->fetchAll(PDO::FETCH_ASSOC);
$prevTotalRevenue = array_sum(array_column($prevData, 'total_revenue'));

// Calculate growth
$growth = $prevTotalRevenue > 0 ? (($totalRevenue - $prevTotalRevenue) / $prevTotalRevenue) * 100 : 0;

// Clear any previous output
ob_clean();

if ($exportType === 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sales_report_' . date('Y-m-d') . '.csv"');
    
    // Create CSV file
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for proper Excel encoding
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add report header
    fputcsv($output, ['Sales Report']);
    fputcsv($output, ['Period:', date('M d, Y', strtotime($startDate)) . ' - ' . date('M d, Y', strtotime($endDate))]);
    fputcsv($output, ['Generated:', date('Y-m-d H:i:s')]);
    fputcsv($output, []);
    
    // Add summary
    fputcsv($output, ['Summary']);
    fputcsv($output, ['Total Revenue', '$' . number_format($totalRevenue, 2)]);
    fputcsv($output, ['Total Transactions', number_format($totalTransactions)]);
    fputcsv($output, ['Growth vs Previous Period', round($growth, 1) . '%']);
    fputcsv($output, []);
    
    // Add detailed data
    fputcsv($output, ['Detailed Data']);
    
    // Add headers based on report type
    switch ($reportType) {
        case 'products':
            fputcsv($output, ['Product Name', 'Category', 'Total Sales', 'Total Quantity', 'Total Revenue', 'Average Sale Price']);
            foreach ($data as $row) {
                fputcsv($output, [
                    $row['product_name'],
                    $row['category_name'],
                    $row['total_sales'],
                    $row['total_quantity'],
                    '$' . number_format($row['total_revenue'], 2),
                    '$' . number_format($row['avg_sale_price'], 2)
                ]);
            }
            break;
        
        case 'categories':
            fputcsv($output, ['Category', 'Total Products', 'Total Sales', 'Total Quantity', 'Total Revenue']);
            foreach ($data as $row) {
                fputcsv($output, [
                    $row['category_name'],
                    $row['total_products'],
                    $row['total_sales'],
                    $row['total_quantity'],
                    '$' . number_format($row['total_revenue'], 2)
                ]);
            }
            break;
        
        default: // sales
            fputcsv($output, ['Date', 'Transactions', 'Revenue', 'Unique Products', 'Average Order Value']);
            foreach ($data as $row) {
                fputcsv($output, [
                    date('M d, Y', strtotime($row['sale_date'])),
                    $row['total_transactions'],
                    '$' . number_format($row['total_revenue'], 2),
                    $row['unique_products'],
                    '$' . number_format($row['total_revenue'] / max($row['total_transactions'], 1), 2)
                ]);
            }
    }
    
    fclose($output);
    exit;
} else {
    // Clear any previous output
    ob_clean();
    
    // Create PDF
    class MYPDF extends TCPDF {
        public function Header() {
            $this->SetFont('helvetica', 'B', 20);
            $this->Cell(0, 15, 'Sales Report', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        }
        
        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        }
    }

    // Create new PDF document
    $pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Admin Panel');
    $pdf->SetTitle('Sales Report');
    
    // Set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    
    // Add a page
    $pdf->AddPage();
    
    // Add report header
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Sales Report', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'Period: ' . date('M d, Y', strtotime($startDate)) . ' - ' . date('M d, Y', strtotime($endDate)), 0, 1, 'C');
    $pdf->Cell(0, 10, 'Generated: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
    $pdf->Ln(10);
    
    // Add summary
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Executive Summary', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 12);
    
    // Add narrative analysis
    $narrative = generateNarrativeAnalysis($data, $totalRevenue, $totalTransactions, $growth);
    $pdf->MultiCell(0, 10, $narrative, 0, 'L');
    $pdf->Ln(10);

    // Add key metrics
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Key Metrics', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 12);
    
    $pdf->Cell(60, 10, 'Total Revenue:', 0, 0);
    $pdf->Cell(0, 10, '$' . number_format($totalRevenue, 2), 0, 1);
    
    $pdf->Cell(60, 10, 'Total Transactions:', 0, 0);
    $pdf->Cell(0, 10, number_format($totalTransactions), 0, 1);
    
    $pdf->Cell(60, 10, 'Growth vs Previous Period:', 0, 0);
    $pdf->Cell(0, 10, round($growth, 1) . '%', 0, 1);
    $pdf->Ln(10);

    // Add charts for sales report
    if ($reportType === 'sales') {
        // Add Revenue Trend Chart
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'Revenue Trend Analysis', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 12);
        
        // Create Revenue Chart
        $chart = new Chart([
            'type' => 'line',
            'data' => [
                'labels' => array_map(function($row) {
                    return date('M d', strtotime($row['sale_date']));
                }, $data),
                'datasets' => [[
                    'label' => 'Daily Revenue',
                    'data' => array_column($data, 'total_revenue'),
                    'borderColor' => '#4f46e5',
                    'fill' => false
                ]]
            ]
        ]);
        
        // Add chart to PDF
        $chartImage = $chart->toBase64();
        $pdf->ImageSVG('@'.$chartImage, 15, $pdf->GetY(), 180);
        $pdf->Ln(110); // Space for the chart
        
        // Add chart description
        $pdf->MultiCell(0, 10, 'The above chart shows the daily revenue trend over the selected period. ' . 
            'This visualization helps identify patterns, peaks, and trends in your sales performance.', 0, 'L');
        $pdf->Ln(10);
        
        // Add Transaction Volume Chart
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'Transaction Volume Analysis', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 12);
        
        // Create Transaction Chart
        $chart = new Chart([
            'type' => 'bar',
            'data' => [
                'labels' => array_map(function($row) {
                    return date('M d', strtotime($row['sale_date']));
                }, $data),
                'datasets' => [[
                    'label' => 'Daily Transactions',
                    'data' => array_column($data, 'total_transactions'),
                    'backgroundColor' => '#10b981'
                ]]
            ]
        ]);
        
        // Add chart to PDF
        $chartImage = $chart->toBase64();
        $pdf->ImageSVG('@'.$chartImage, 15, $pdf->GetY(), 180);
        $pdf->Ln(110); // Space for the chart
        
        // Add chart description
        $pdf->MultiCell(0, 10, 'This chart displays the daily transaction volume. ' . 
            'It helps visualize customer activity and identify busy periods in your business.', 0, 'L');
        $pdf->Ln(10);
    }

    // Add detailed data table
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Detailed Data', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 12);
    
    // Add table headers based on report type
    switch ($reportType) {
        case 'products':
            $header = ['Product Name', 'Category', 'Total Sales', 'Total Quantity', 'Total Revenue', 'Avg Sale Price'];
            $widths = [50, 30, 25, 25, 30, 30];
            break;
        
        case 'categories':
            $header = ['Category', 'Total Products', 'Total Sales', 'Total Quantity', 'Total Revenue'];
            $widths = [50, 30, 30, 30, 30];
            break;
        
        default: // sales
            $header = ['Date', 'Transactions', 'Revenue', 'Unique Products', 'Avg Order Value'];
            $widths = [40, 30, 40, 30, 40];
    }
    
    // Add table headers
    $pdf->SetFont('helvetica', 'B', 12);
    for ($i = 0; $i < count($header); $i++) {
        $pdf->Cell($widths[$i], 10, $header[$i], 1, 0, 'C');
    }
    $pdf->Ln();
    
    // Add table data
    $pdf->SetFont('helvetica', '', 12);
    foreach ($data as $row) {
        switch ($reportType) {
            case 'products':
                $pdf->Cell($widths[0], 10, $row['product_name'], 1, 0);
                $pdf->Cell($widths[1], 10, $row['category_name'], 1, 0);
                $pdf->Cell($widths[2], 10, $row['total_sales'], 1, 0, 'R');
                $pdf->Cell($widths[3], 10, $row['total_quantity'], 1, 0, 'R');
                $pdf->Cell($widths[4], 10, '$' . number_format($row['total_revenue'], 2), 1, 0, 'R');
                $pdf->Cell($widths[5], 10, '$' . number_format($row['avg_sale_price'], 2), 1, 0, 'R');
                break;
            
            case 'categories':
                $pdf->Cell($widths[0], 10, $row['category_name'], 1, 0);
                $pdf->Cell($widths[1], 10, $row['total_products'], 1, 0, 'R');
                $pdf->Cell($widths[2], 10, $row['total_sales'], 1, 0, 'R');
                $pdf->Cell($widths[3], 10, $row['total_quantity'], 1, 0, 'R');
                $pdf->Cell($widths[4], 10, '$' . number_format($row['total_revenue'], 2), 1, 0, 'R');
                break;
            
            default: // sales
                $pdf->Cell($widths[0], 10, date('M d, Y', strtotime($row['sale_date'])), 1, 0);
                $pdf->Cell($widths[1], 10, $row['total_transactions'], 1, 0, 'R');
                $pdf->Cell($widths[2], 10, '$' . number_format($row['total_revenue'], 2), 1, 0, 'R');
                $pdf->Cell($widths[3], 10, $row['unique_products'], 1, 0, 'R');
                $pdf->Cell($widths[4], 10, '$' . number_format($row['total_revenue'] / max($row['total_transactions'], 1), 2), 1, 0, 'R');
        }
        $pdf->Ln();
    }
    
    // Output PDF
    $pdf->Output('sales_report_' . date('Y-m-d') . '.pdf', 'D');
    exit;
} 