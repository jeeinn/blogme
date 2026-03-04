<?php

declare(strict_types=1);

namespace Blogme\Tests;

use Blogme\Core\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testIsTlsServerDetectsHttpsServerVar(): void
    {
        self::assertTrue(Request::isTlsServer([
            'HTTPS' => 'on',
        ]));
    }

    public function testIsTlsServerDetectsForwardedProto(): void
    {
        self::assertTrue(Request::isTlsServer([
            'HTTP_X_FORWARDED_PROTO' => 'https,http',
        ]));
    }

    public function testIsTlsServerDetectsCloudflareVisitorHeader(): void
    {
        self::assertTrue(Request::isTlsServer([
            'HTTP_CF_VISITOR' => '{"scheme":"https"}',
        ]));
    }

    public function testIsTlsServerReturnsFalseWithoutTlsSignals(): void
    {
        self::assertFalse(Request::isTlsServer([
            'SERVER_PORT' => '80',
            'HTTP_X_FORWARDED_PROTO' => 'http',
        ]));
    }
}

