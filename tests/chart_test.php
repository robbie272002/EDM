<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/libraries/Chart.php';

// Test cases with descriptions
$testCases = [
    'normal' => [
        'title' => 'Standard Line Chart',
        'description' => 'A typical line chart showing monthly sales data with multiple data points.',
        'config' => [
            'type' => 'line',
            'data' => [
                'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May'],
                'datasets' => [[
                    'label' => 'Monthly Sales',
                    'data' => [1000, 1500, 1200, 1700, 1600]
                ]]
            ]
        ]
    ],
    'single_point' => [
        'title' => 'Single Data Point',
        'description' => 'Edge case: Chart with only one data point, centered in the visualization.',
        'config' => [
            'type' => 'line',
            'data' => [
                'labels' => ['Single Point'],
                'datasets' => [[
                    'label' => 'Single Value',
                    'data' => [100]
                ]]
            ]
        ]
    ],
    'zero_values' => [
        'title' => 'Zero Values Chart',
        'description' => 'Edge case: Chart handling multiple zero values with proper scaling.',
        'config' => [
            'type' => 'line',
            'data' => [
                'labels' => ['A', 'B', 'C'],
                'datasets' => [[
                    'label' => 'Zero Values',
                    'data' => [0, 0, 0]
                ]]
            ]
        ]
    ],
    'negative_values' => [
        'title' => 'Profit/Loss Chart',
        'description' => 'Chart showing both positive and negative values, useful for financial data.',
        'config' => [
            'type' => 'line',
            'data' => [
                'labels' => ['Q1', 'Q2', 'Q3'],
                'datasets' => [[
                    'label' => 'Profit/Loss',
                    'data' => [-500, 1000, -250]
                ]]
            ]
        ]
    ],
    'large_numbers' => [
        'title' => 'Large Value Bar Chart',
        'description' => 'Bar chart handling large numbers with automatic K/M suffix formatting.',
        'config' => [
            'type' => 'bar',
            'data' => [
                'labels' => ['Product X', 'Product Y'],
                'datasets' => [[
                    'label' => 'Revenue',
                    'data' => [1000000, 2500000]
                ]]
            ]
        ]
    ],
    'small_decimals' => [
        'title' => 'Small Decimal Values',
        'description' => 'Chart handling very small decimal values with appropriate precision.',
        'config' => [
            'type' => 'bar',
            'data' => [
                'labels' => ['Rate 1', 'Rate 2', 'Rate 3'],
                'datasets' => [[
                    'label' => 'Conversion Rates',
                    'data' => [0.025, 0.037, 0.019]
                ]]
            ]
        ]
    ]
];

// Create PDF
class MYPDF extends TCPDF {
    protected $headerTitle = '';
    protected $headerSubtitle = '';
    
    public function setHeaderTitles($title, $subtitle) {
        $this->headerTitle = $title;
        $this->headerSubtitle = $subtitle;
    }
    
    public function Header() {
        // Logo (if you have one)
        // $this->Image('logo.png', 10, 10, 30);
        
        // Title
        $this->SetFont('helvetica', 'B', 20);
        $this->Cell(0, 15, $this->headerTitle, 0, true, 'C');
        
        // Subtitle
        if ($this->headerSubtitle) {
            $this->SetFont('helvetica', 'I', 12);
            $this->Cell(0, 10, $this->headerSubtitle, 0, true, 'C');
        }
        
        // Line break
        $this->Ln(5);
        
        // Line
        $this->Line(15, $this->GetY(), $this->getPageWidth() - 15, $this->GetY());
        $this->Ln(10);
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, false, 'C');
    }
}

// Initialize PDF
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Chart Test System');
$pdf->SetAuthor('System Administrator');
$pdf->SetTitle('Chart Visualization Report');

// Set header titles
$pdf->setHeaderTitles('Chart Visualization Report', 'Test Cases and Edge Cases');

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP + 10, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

try {
    // Introduction page
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Overview', 0, true, 'L');
    $pdf->SetFont('helvetica', '', 11);
    $pdf->MultiCell(0, 10, 'This report demonstrates various chart visualization capabilities, including handling of different data scenarios and edge cases. Each chart is accompanied by a description of its purpose and key features.', 0, 'L');
    
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Test Cases Included:', 0, true, 'L');
    $pdf->SetFont('helvetica', '', 11);
    
    // List of test cases
    foreach ($testCases as $name => $case) {
        $pdf->Cell(0, 8, '• ' . $case['title'], 0, true, 'L');
    }
    
    // Charts
    foreach ($testCases as $name => $case) {
        $pdf->AddPage();
        
        // Chart title
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, $case['title'], 0, true, 'L');
        
        // Description
        $pdf->SetFont('helvetica', '', 11);
        $pdf->MultiCell(0, 10, $case['description'], 0, 'L');
        $pdf->Ln(5);
        
        try {
            // Generate and add chart
            $chart = new Chart($case['config']);
            $svg = $chart->toBase64();
            $pdf->ImageSVG('@' . $svg, 15, $pdf->GetY(), 180, 100);
            
            // Move pointer below chart
            $pdf->SetY($pdf->GetY() + 110);
            
            // Add data table
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(0, 10, 'Data Values:', 0, true, 'L');
            $pdf->SetFont('helvetica', '', 10);
            
            // Table header
            $pdf->Cell(60, 7, 'Label', 1, 0, 'C');
            $pdf->Cell(60, 7, 'Value', 1, 1, 'C');
            
            // Table data
            $data = $case['config']['data'];
            for ($i = 0; $i < count($data['labels']); $i++) {
                $pdf->Cell(60, 7, $data['labels'][$i], 1, 0, 'L');
                $pdf->Cell(60, 7, number_format($data['datasets'][0]['data'][$i], 2), 1, 1, 'R');
            }
            
            echo "Generated chart for: " . $name . "\n";
        } catch (Exception $e) {
            $pdf->SetTextColor(255, 0, 0);
            $pdf->Cell(0, 10, 'Error generating chart: ' . $e->getMessage(), 0, true, 'L');
            $pdf->SetTextColor(0, 0, 0);
            echo "Failed to generate chart for " . $name . ": " . $e->getMessage() . "\n";
        }
    }
    
    // Save PDF
    $outputPath = __DIR__ . '/chart_visualization_report.pdf';
    $pdf->Output($outputPath, 'F');
    
    if (file_exists($outputPath)) {
        echo "\nReport generated successfully!\n";
        echo "File saved as: " . $outputPath . "\n";
        
        $fileSize = filesize($outputPath);
        echo "PDF file size: " . $fileSize . " bytes\n";
        
        if ($fileSize > 1000) {
            echo "PDF appears to contain data (file size is reasonable)\n";
        } else {
            echo "WARNING: PDF file size is unusually small, please check the content\n";
        }
    } else {
        echo "ERROR: Failed to generate report\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
} 