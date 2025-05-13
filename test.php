<?php
$base_path = __DIR__ . '/public/assets';
$files = [
    'css/tailwind.min.css',
    'css/styles.css',
    'js/config.js',
    'js/auth.js'
];

echo "<h1>Asset File Check</h1>";
echo "<pre>";

foreach ($files as $file) {
    $full_path = $base_path . '/' . $file;
    echo "Checking $file:\n";
    echo "Full path: $full_path\n";
    if (file_exists($full_path)) {
        echo "Status: EXISTS\n";
        echo "Permissions: " . substr(sprintf('%o', fileperms($full_path)), -4) . "\n";
        echo "Size: " . filesize($full_path) . " bytes\n";
        echo "URL: http://" . $_SERVER['HTTP_HOST'] . "/NEW/public/assets/$file\n";
    } else {
        echo "Status: NOT FOUND\n";
    }
    echo "\n";
}

echo "</pre>"; 