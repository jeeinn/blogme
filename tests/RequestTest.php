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

    public function testPathFromServerUsesRequestUriPath(): void
    {
        self::assertSame('/post/foo', Request::pathFromServer([
            'REQUEST_URI' => '/post/foo?x=1',
        ]));
    }

    public function testPathFromServerStripsScriptNamePrefix(): void
    {
        self::assertSame('/post/foo', Request::pathFromServer([
            'REQUEST_URI' => '/index.php/post/foo',
            'SCRIPT_NAME' => '/index.php',
        ]));
    }

    public function testPathFromServerStripsSubdirScriptPrefix(): void
    {
        self::assertSame('/post/foo', Request::pathFromServer([
            'REQUEST_URI' => '/blog/index.php/post/foo',
            'SCRIPT_NAME' => '/blog/index.php',
        ]));
    }

    public function testPathFromServerFallsBackToPathInfo(): void
    {
        self::assertSame('/post/foo', Request::pathFromServer([
            'REQUEST_URI' => '/index.php',
            'SCRIPT_NAME' => '/index.php',
            'PATH_INFO' => '/post/foo',
        ]));
    }

    public function testPathFromServerNormalizesRoot(): void
    {
        self::assertSame('/', Request::pathFromServer([
            'REQUEST_URI' => '/index.php',
            'SCRIPT_NAME' => '/index.php',
        ]));
        self::assertSame('/', Request::pathFromServer([
            'REQUEST_URI' => '/',
        ]));
    }

    public function testPathFromServerNormalizesTrailingSlash(): void
    {
        self::assertSame('/post/foo', Request::pathFromServer([
            'REQUEST_URI' => '/post/foo/',
        ]));
    }

    public function testPathFromServerKeepsPathWhenScriptNameEqualsRequestPath(): void
    {
        // `php -S` router mode sets SCRIPT_NAME/PHP_SELF to the requested path.
        self::assertSame('/admin/posts', Request::pathFromServer([
            'REQUEST_URI' => '/admin/posts',
            'SCRIPT_NAME' => '/admin/posts',
            'PHP_SELF' => '/admin/posts',
        ]));
    }

    public function testPathFromServerKeepsStaticAssetPathInBuiltinServer(): void
    {
        self::assertSame('/themes/default/assets/style.css', Request::pathFromServer([
            'REQUEST_URI' => '/themes/default/assets/style.css',
            'SCRIPT_NAME' => '/themes/default/assets/style.css',
            'PHP_SELF' => '/themes/default/assets/style.css',
        ]));
    }

    public function testPathFromServerKeepsPathWhenRouterScriptDoesNotMatch(): void
    {
        self::assertSame('/admin/posts', Request::pathFromServer([
            'REQUEST_URI' => '/admin/posts',
            'SCRIPT_NAME' => '/dev-router.php',
            'PHP_SELF' => '/dev-router.php',
        ]));
    }
}

