<?php
// Test the login endpoint
$url = 'http://localhost/NEW/app/views/auth/login.php';
$data = [
    'username' => 'admin',
    'password' => 'password'
];

$options = [
    'http' => [
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n" .
                    "X-Requested-With: XMLHttpRequest\r\n",
        'method'  => 'POST',
        'content' => http_build_query($data)
    ]
];

$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);

echo "<h1>Login Test</h1>";
echo "<pre>";
echo "URL: $url\n";
echo "Request Data: " . print_r($data, true) . "\n";
echo "Response:\n";
echo $result;
echo "</pre>"; 