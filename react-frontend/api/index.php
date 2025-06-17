<?php
// This file serves as a proxy for the PHP backend API
// It allows the React app to make API requests to the PHP backend
// without having to worry about CORS issues

// Set headers to allow cross-origin requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get the request path
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path = str_replace('/api', '', $path);

// Forward the request to the PHP backend
$backend_url = 'http://localhost:8000' . $path;

// Forward query parameters
$query_string = $_SERVER['QUERY_STRING'];
if ($query_string) {
    $backend_url .= '?' . $query_string;
}

// Initialize cURL session
$ch = curl_init($backend_url);

// Set cURL options
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

// Forward request method
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);

// Forward request headers
$headers = [];
foreach (getallheaders() as $name => $value) {
    if ($name !== 'Host') {
        $headers[] = "$name: $value";
    }
}
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Forward request body for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_data = file_get_contents('php://input');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
}

// Execute cURL request
$response = curl_exec($ch);
$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

// Close cURL session
curl_close($ch);

// Set response status code
http_response_code($status_code);

// Set response content type
if ($content_type) {
    header("Content-Type: $content_type");
}

// Output response
echo $response;