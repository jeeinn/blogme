<?php

declare(strict_types=1);

namespace Blogme\Tests;

use Blogme\Core\Session;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    /**
     * @runInSeparateProcess
     */
    public function testSessionStartsAndRegenerates(): void
    {
        $session = new Session();

        self::assertSame(PHP_SESSION_ACTIVE, session_status());
        self::assertSame('blogme', session_name());

        $oldId = session_id();
        self::assertNotSame('', $oldId);

        self::assertTrue($session->regenerate());
        $newId = session_id();
        self::assertNotSame($oldId, $newId);
    }
}
