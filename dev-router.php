<?php

declare(strict_types=1);

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($uriPath) && $uriPath !== '' ? $uriPath : '/';

$scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
if ($scriptName !== '' && str_starts_with($path, $scriptName)) {
    $path = substr($path, strlen($scriptName));
} else {
    $phpSelf = (string)($_SERVER['PHP_SELF'] ?? '');
    if ($phpSelf !== '' && str_starts_with($path, $phpSelf)) {
        $path = substr($path, strlen($phpSelf));
    }
}

if (($path === '' || $path === '/') && isset($_SERVER['PATH_INFO']) && is_string($_SERVER['PATH_INFO'])) {
    $path = $_SERVER['PATH_INFO'] !== '' ? $_SERVER['PATH_INFO'] : $path;
}

if ($path === '') {
    $path = '/';
}

$publicRoot = __DIR__ . '/public';
$target = realpath($publicRoot . $path);
$realPublicRoot = realpath($publicRoot);

if (
    $path !== '/' &&
    $target !== false &&
    $realPublicRoot !== false &&
    str_starts_with($target, $realPublicRoot) &&
    is_file($target)
) {
    return false;
}

require $publicRoot . '/index.php';
