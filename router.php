<?php
/**
 * Development Router for Static Files & PHP Requests
 * Use with: php -S localhost:2000 router.php
 */

$requested_uri = $_SERVER['REQUEST_URI'];
$request_path = parse_url($requested_uri, PHP_URL_PATH);

// Remove query string for file checking
$clean_path = $request_path;
if (strpos($clean_path, '?') !== false) {
    $clean_path = substr($clean_path, 0, strpos($clean_path, '?'));
}

// For the root path, serve index.php
if ($clean_path === '/' || $clean_path === '') {
    require __DIR__ . '/index.php';
    return true;
}

$requested_file = __DIR__ . $clean_path;

// Check if file exists (without realpath to avoid issues with symlinks)
if (file_exists($requested_file) && is_file($requested_file)) {
    // Security: basic check
    if (strpos(realpath($requested_file), realpath(__DIR__)) === 0) {
        $extension = strtolower(pathinfo($requested_file, PATHINFO_EXTENSION));
        
        // PHP files should be executed, not served as static files
        if ($extension === 'php') {
            require $requested_file;
            return true;
        }
        
        $mimeTypes = [
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            'html' => 'text/html; charset=utf-8',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject'
        ];
        
        $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';
        header('Content-Type: ' . $mimeType);
        header('Cache-Control: public, max-age=3600');
        
        if (file_exists($requested_file)) {
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s T', filemtime($requested_file)));
        }
        
        readfile($requested_file);
        return true;
    }
}

// Route all requests to index.php for PHP routing
require __DIR__ . '/index.php';
return true;

