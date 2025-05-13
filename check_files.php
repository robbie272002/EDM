<?php
$files = [
    'public/assets/css/tailwind.min.css',
    'public/assets/css/styles.css',
    'public/assets/js/config.js',
    'public/assets/js/auth.js'
];

echo "<h1>File Check Results</h1>";
echo "<pre>";

foreach ($files as $file) {
    echo "Checking $file: ";
    if (file_exists($file)) {
        echo "EXISTS\n";
        echo "Permissions: " . substr(sprintf('%o', fileperms($file)), -4) . "\n";
        echo "Size: " . filesize($file) . " bytes\n";
    } else {
        echo "NOT FOUND\n";
    }
    echo "\n";
}

echo "</pre>"; 