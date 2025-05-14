<?php

class Chart {
    private $config;
    private $width = 800;
    private $height = 400;
    private $padding = 40;

    public function __construct($config) {
        $this->config = $config;
    }

    public function toBase64() {
        // Validate data structure
        if (!isset($this->config['data']['datasets'][0]['data']) || 
            !is_array($this->config['data']['datasets'][0]['data']) || 
            empty($this->config['data']['datasets'][0]['data'])) {
            // Return a "No Data Available" SVG
            return $this->generateNoDataSVG();
        }

        if ($this->config['type'] === 'bar') {
            return $this->generateBarChart();
        }
        return $this->generateLineChart();
    }

    private function generateNoDataSVG() {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $this->width . '" height="' . $this->height . '">';
        
        // Background
        $svg .= '<rect x="0" y="0" width="' . $this->width . '" height="' . $this->height . '" fill="white"/>';
        
        // Border
        $svg .= '<rect x="' . $this->padding . '" y="' . $this->padding . '" ';
        $svg .= 'width="' . ($this->width - 2 * $this->padding) . '" ';
        $svg .= 'height="' . ($this->height - 2 * $this->padding) . '" ';
        $svg .= 'fill="none" stroke="#eeeeee" stroke-width="1" stroke-dasharray="5,5"/>';
        
        // No Data Message
        $svg .= '<text x="' . ($this->width/2) . '" y="' . ($this->height/2) . '" ';
        $svg .= 'font-family="Arial" font-size="14" fill="#666666" text-anchor="middle">';
        $svg .= 'No Data Available';
        $svg .= '</text>';
        
        $svg .= '</svg>';
        
        return $svg;
    }

    private function generateLineChart() {
        $data = array_map('floatval', $this->config['data']['datasets'][0]['data']);
        $labels = array_map('htmlspecialchars', $this->config['data']['labels'] ?? array_fill(0, count($data), ''));
        
        // Calculate dimensions
        $graphWidth = $this->width - (2 * $this->padding);
        $graphHeight = $this->height - (2 * $this->padding);
        
        // Find min and max values
        $maxValue = max($data);
        $minValue = min($data);
        
        // Adjust range for zero or very close values
        if (abs($maxValue - $minValue) < 0.0001) {
            if ($maxValue == 0) {
                $maxValue = 1;
                $minValue = -1;
            } else {
                $maxValue *= 1.1;
                $minValue *= 0.9;
            }
        }
        
        // Ensure we have a valid range
        $valueRange = $maxValue - $minValue;
        if ($valueRange == 0) {
            $valueRange = 1;
        }
        
        // Generate points
        $points = [];
        $count = count($data);
        if ($count > 1) {
            for ($i = 0; $i < $count; $i++) {
                $x = $this->padding + ($graphWidth * $i / ($count - 1));
                $y = $this->height - $this->padding - ($graphHeight * ($data[$i] - $minValue) / $valueRange);
                $points[] = round($x, 2) . ',' . round($y, 2);
            }
        } else if ($count == 1) {
            $x = $this->padding + ($graphWidth / 2);
            $y = $this->height - $this->padding - ($graphHeight / 2);
            $points[] = round($x, 2) . ',' . round($y, 2);
        }

        // Create SVG
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $this->width . '" height="' . $this->height . '">';
        
        // Background
        $svg .= '<rect x="0" y="0" width="' . $this->width . '" height="' . $this->height . '" fill="white"/>';
        
        // Grid lines and labels
        for ($i = 0; $i <= 5; $i++) {
            $y = round($this->padding + ($graphHeight * $i / 5), 2);
            $value = round($maxValue - ($valueRange * $i / 5), 2);
            $svg .= '<line x1="' . $this->padding . '" y1="' . $y . '" x2="' . ($this->width - $this->padding) . '" y2="' . $y . '" stroke="#eeeeee" stroke-width="1"/>';
            
            // Format number based on its magnitude
            $formattedValue = $this->formatNumber($value);
            $svg .= '<text x="' . ($this->padding - 5) . '" y="' . $y . '" text-anchor="end" font-family="Arial" font-size="12">' . $formattedValue . '</text>';
        }
        
        // X-axis labels
        if ($count > 1) {
            for ($i = 0; $i < $count; $i++) {
                $x = round($this->padding + ($graphWidth * $i / ($count - 1)), 2);
                $svg .= '<text x="' . $x . '" y="' . ($this->height - 10) . '" text-anchor="middle" font-family="Arial" font-size="10">' . ($labels[$i] ?? '') . '</text>';
            }
        } else if ($count == 1) {
            $x = round($this->padding + ($graphWidth / 2), 2);
            $svg .= '<text x="' . $x . '" y="' . ($this->height - 10) . '" text-anchor="middle" font-family="Arial" font-size="10">' . ($labels[0] ?? '') . '</text>';
        }
        
        // Draw line
        if (count($points) > 1) {
            $svg .= '<polyline points="' . implode(' ', $points) . '" fill="none" stroke="#4f46e5" stroke-width="2"/>';
        }
        
        // Draw points
        foreach ($points as $point) {
            list($x, $y) = explode(',', $point);
            $svg .= '<circle cx="' . $x . '" cy="' . $y . '" r="4" fill="white" stroke="#4f46e5" stroke-width="2"/>';
        }
        
        $svg .= '</svg>';
        
        return $svg;
    }

    private function generateBarChart() {
        // Check for empty data
        if (empty($this->config['data']['datasets'][0]['data'])) {
            return $this->generateNoDataSVG();
        }

        $data = array_map('floatval', $this->config['data']['datasets'][0]['data']);
        $labels = array_map('htmlspecialchars', $this->config['data']['labels'] ?? array_fill(0, count($data), ''));
        
        // Calculate dimensions
        $graphWidth = $this->width - (2 * $this->padding);
        $graphHeight = $this->height - (2 * $this->padding);
        
        // Find max and min values
        $maxValue = max($data);
        $minValue = min($data);
        
        // Adjust range for zero or very close values
        if (abs($maxValue - $minValue) < 0.0001) {
            if ($maxValue == 0) {
                $maxValue = 1;
                $minValue = 0;
            } else {
                $maxValue *= 1.1;
                $minValue *= 0.9;
            }
        }
        
        // Ensure we have a valid range
        $valueRange = $maxValue - $minValue;
        if ($valueRange == 0) {
            $valueRange = 1;
        }
        
        // Calculate bar width
        $count = count($data);
        if ($count > 1) {
            $barWidth = ($graphWidth / $count) * 0.8;
            $barSpacing = ($graphWidth / $count) * 0.2;
        } else {
            $barWidth = $graphWidth * 0.2;
            $barSpacing = ($graphWidth - $barWidth) / 2;
        }

        // Create SVG
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $this->width . '" height="' . $this->height . '">';
        
        // Background
        $svg .= '<rect x="0" y="0" width="' . $this->width . '" height="' . $this->height . '" fill="white"/>';
        
        // Grid lines
        for ($i = 0; $i <= 5; $i++) {
            $y = round($this->padding + ($graphHeight * $i / 5), 2);
            $value = round($maxValue - ($valueRange * $i / 5), 2);
            $svg .= '<line x1="' . $this->padding . '" y1="' . $y . '" x2="' . ($this->width - $this->padding) . '" y2="' . $y . '" stroke="#eeeeee" stroke-width="1"/>';
            
            // Format number based on its magnitude
            $formattedValue = $this->formatNumber($value);
            $svg .= '<text x="' . ($this->padding - 5) . '" y="' . $y . '" text-anchor="end" font-family="Arial" font-size="12">' . $formattedValue . '</text>';
        }
        
        // Draw bars and labels
        for ($i = 0; $i < $count; $i++) {
            $x = $count > 1 ? 
                round($this->padding + ($graphWidth * $i / $count) + ($barSpacing / 2), 2) :
                round($this->padding + $barSpacing, 2);
            
            $barHeight = round(($graphHeight * ($data[$i] - $minValue)) / $valueRange, 2);
            $y = round($this->height - $this->padding - $barHeight, 2);
            
            $svg .= '<rect x="' . $x . '" y="' . $y . '" width="' . round($barWidth, 2) . '" height="' . $barHeight . '" fill="#10b981"/>';
            $svg .= '<text x="' . round($x + $barWidth/2, 2) . '" y="' . ($this->height - 10) . '" text-anchor="middle" font-family="Arial" font-size="10">' . ($labels[$i] ?? '') . '</text>';
        }
        
        $svg .= '</svg>';
        
        return $svg;
    }

    private function formatNumber($value) {
        // Handle small decimals
        if (abs($value) < 0.01) {
            return number_format($value, 4);
        }
        
        // Handle large numbers
        if (abs($value) >= 1000000) {
            return number_format($value / 1000000, 1) . 'M';
        }
        if (abs($value) >= 1000) {
            return number_format($value / 1000, 1) . 'K';
        }
        
        // Handle regular numbers
        return number_format($value, abs($value) < 1 ? 3 : 1);
    }
} 