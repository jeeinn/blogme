<?php

declare(strict_types=1);

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($uriPath) && $uriPath !== '' ? $uriPath : '/';

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
