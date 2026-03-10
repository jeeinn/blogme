<?php

declare(strict_types=1);

namespace Blogme\Tests;

use Blogme\Core\Container;
use PHPUnit\Framework\TestCase;

final class ContainerTest extends TestCase
{
    public function testStoresAndResolvesSingleton(): void
    {
        $container = new Container();
        $container->set('value', static fn (Container $c): object => (object) ['id' => 1]);

        self::assertTrue($container->has('value'));
        $first = $container->get('value');
        $second = $container->get('value');

        self::assertSame($first, $second);
        self::assertSame(1, $first->id);
    }
}
