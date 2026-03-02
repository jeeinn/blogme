<?php

declare(strict_types=1);

namespace Blogme\Tests;

use Blogme\Core\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testPatternMatchAndVars(): void
    {
        $router = new Router();
        $router->get('/admin/post/{id}', static fn (): null => null, 'post.view');
        $match = $router->match('GET', '/admin/post/abc-123');

        self::assertNotNull($match);
        self::assertSame('/admin/post/{id}', $match['pattern']);
        self::assertSame('abc-123', $match['vars']['id']);
    }
}
