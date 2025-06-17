<?php
// Lightweight front controller to display a paste by ID using /paste/{id} URLs
$path = trim($_SERVER['REQUEST_URI'], '/');
$parts = explode('/', $path);
$id = null;
if (count($parts) >= 2 && $parts[0] === 'paste') {
    $id = $parts[1];
} elseif (!empty($_SERVER['PATH_INFO'])) {
    $id = ltrim($_SERVER['PATH_INFO'], '/');
}
if ($id !== null) {
    $_GET['id'] = $id;
}
require __DIR__ . '/index.php';


