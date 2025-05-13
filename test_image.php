<?php
// List all images in the uploads directory
$upload_dir = 'C:/xampp/htdocs/NEW/app/uploads/products/';
$images = glob($upload_dir . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);

echo "<h1>Testing Image Display</h1>";
echo "<p>Upload directory: " . htmlspecialchars($upload_dir) . "</p>";
echo "<p>Number of images found: " . count($images) . "</p>";

foreach ($images as $image) {
    $filename = basename($image);
    $web_path = '/NEW/app/uploads/products/' . $filename;
    echo "<div style='margin: 20px;'>";
    echo "<h3>Testing: " . htmlspecialchars($filename) . "</h3>";
    echo "<p>Full path: " . htmlspecialchars($image) . "</p>";
    echo "<p>Web path: " . htmlspecialchars($web_path) . "</p>";
    echo "<img src='" . htmlspecialchars($web_path) . "' style='max-width: 200px;'><br>";
    echo "File exists: " . (file_exists($image) ? 'Yes' : 'No') . "<br>";
    echo "File readable: " . (is_readable($image) ? 'Yes' : 'No') . "<br>";
    echo "</div>";
}
?> 