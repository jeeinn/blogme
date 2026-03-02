<?php

declare(strict_types=1);

use Blogme\Core\HttpException;

require dirname(__DIR__) . '/bootstrap/app.php';

$app = bootstrap_app();

try {
    $app->run();
} catch (HttpException $e) {
    if (in_array($e->getStatusCode(), [301, 302, 303, 307, 308], true)) {
        return;
    }
    http_response_code($e->getStatusCode());
    echo $e->getMessage();
} catch (Throwable $e) {
    http_response_code(500);
    error_log($e->getMessage());
    echo 'Internal Server Error';
}
