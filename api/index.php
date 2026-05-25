<?php
// Vercel PHP front controller.
// Routes public pages and API endpoints through a single function so Hobby deployments
// do not create one serverless function per PHP file.

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$requestPath = rawurldecode($requestPath);
$requestPath = '/' . ltrim($requestPath, '/');

$rootDir = dirname(__DIR__);

function juspos_serve_php(string $filePath, string $scriptName): void
{
    $_SERVER['SCRIPT_NAME'] = $scriptName;
    $_SERVER['PHP_SELF'] = $scriptName;
    $_SERVER['SCRIPT_FILENAME'] = $filePath;

    require $filePath;
}

function juspos_not_found(): void
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo '404 Not Found';
    exit;
}

if ($requestPath === '/' || $requestPath === '') {
    juspos_serve_php($rootDir . '/public/index.php', '/index.php');
    exit;
}

if ($requestPath === '/public') {
    juspos_serve_php($rootDir . '/public/index.php', '/index.php');
    exit;
}

if (str_starts_with($requestPath, '/api/')) {
    $apiRelative = substr($requestPath, strlen('/api/'));

    if ($apiRelative === 'index.php') {
        juspos_not_found();
    }

    if (!preg_match('/^[A-Za-z0-9_\/.-]+\.php$/', $apiRelative) || str_contains($apiRelative, '..')) {
        juspos_not_found();
    }

    $apiFile = __DIR__ . '/' . $apiRelative;
    if (is_file($apiFile)) {
        juspos_serve_php($apiFile, '/api/' . $apiRelative);
        exit;
    }

    juspos_not_found();
}

$publicRelative = ltrim($requestPath, '/');
if (str_starts_with($publicRelative, 'public/')) {
    $publicRelative = substr($publicRelative, strlen('public/'));
}
if (!str_ends_with($publicRelative, '.php')) {
    $candidate = $publicRelative === '' ? 'index.php' : $publicRelative . '.php';
    if (is_file($rootDir . '/public/' . $candidate)) {
        $publicRelative = $candidate;
    }
}

if (!preg_match('/^[A-Za-z0-9_\/.-]+\.php$/', $publicRelative) || str_contains($publicRelative, '..')) {
    juspos_not_found();
}

$publicFile = $rootDir . '/public/' . $publicRelative;
if (is_file($publicFile)) {
    juspos_serve_php($publicFile, '/' . $publicRelative);
    exit;
}

juspos_not_found();
