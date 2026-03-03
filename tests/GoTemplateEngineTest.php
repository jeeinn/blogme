<?php

declare(strict_types=1);

namespace Blogme\Tests;

use Blogme\Core\GoTemplateEngine;
use Blogme\Services\LocaleService;
use PHPUnit\Framework\TestCase;

final class GoTemplateEngineTest extends TestCase
{
    private GoTemplateEngine $view;

    protected function setUp(): void
    {
        $root = dirname(__DIR__);
        $locale = new LocaleService($root . '/resources/locales', $root . '/data/themes');
        $this->view = new GoTemplateEngine($root, $locale);
    }

    public function testRenderLoginAndWizardWithoutPermissionData(): void
    {
        $base = $this->baseData();

        $login = $this->view->renderPage('login', array_replace($base, [
            'Name' => 'Blogme',
            'Message' => '<script>alert(1)</script>',
        ]));
        self::assertStringContainsString('name="_csrf"', $login);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $login);

        $wizard = $this->view->renderPage('wizard', $base + [
            'Locales' => ['English' => 'en-us'],
            'DefaultLocale' => 'en-us',
        ]);
        self::assertStringContainsString('name="locale"', $wizard);
        self::assertStringContainsString('name="password"', $wizard);
    }

    public function testRenderAdminPostsTemplate(): void
    {
        $html = $this->view->renderAdmin('admin_posts', array_replace($this->baseData(), [
            'Self' => ['ID' => 'u1', 'Nickname' => 'Admin'],
            'Visibility' => '',
            'PostCount' => [
                'All' => 1,
                'NonTrash' => 1,
                'Public' => 1,
                'Draft' => 0,
                'Password' => 0,
                'Private' => 0,
                'Trash' => 0,
            ],
            'Posts' => [[
                'ID' => 'p1',
                'Title' => 'Hello',
                'Slug' => 'hello',
                'Cover' => '',
                'PinnedAt' => 0,
                'TrashedAt' => 0,
                'Visibility' => 'public',
                'Author' => ['Nickname' => 'Admin'],
                'PublishedAtDatetime' => '2026-03-03 12:00',
                'IsPublished' => true,
            ]],
            'Pagination' => ['CurrentPage' => 1, 'TotalPages' => 1, 'Query' => ''],
            'Query' => ['Title' => '', 'AuthorID' => '', 'PublishedDate' => ''],
            'Users' => [['ID' => 'u1', 'Nickname' => 'Admin']],
            'Dates' => ['2026/03'],
            'IsQuerySetted' => false,
        ]));

        self::assertStringContainsString('admin/post/p1', $html);
        self::assertStringContainsString('post/p1/trash', $html);
        self::assertStringNotContainsString('{{', $html);
    }

    public function testRenderThemeTemplatesAndRawHtmlFilter(): void
    {
        $base = $this->baseData();
        $base['Config']['InjectedHead'] = '<meta name="x-test" content="1">';
        $base['Config']['InjectedFoot'] = '<script>window.test=1</script>';

        $themeData = $base + [
            'Navigations' => [['URL' => '/', 'Name' => 'Home']],
            'Filter' => ['Tag' => '', 'Author' => '', 'Date' => '', 'IsEmpty' => true],
            'Pagination' => ['CurrentPage' => 1, 'TotalPages' => 1],
            'Posts' => [[
                'Slug' => 'hello',
                'Visibility' => 'public',
                'Title' => 'Hello',
                'PinnedAt' => 0,
                'Excerpt' => 'Excerpt',
                'PublishedAt' => time(),
                'PublishedYear' => '2026',
                'PublishedMonth' => '03',
                'Author' => ['ID' => 'u1', 'Nickname' => 'Admin'],
                'Tags' => [['Slug' => 'php', 'Name' => 'php']],
            ]],
            'Post' => [
                'ID' => 'p1',
                'Title' => 'Hello',
                'Excerpt' => 'Excerpt',
                'Content' => 'content',
                'Cover' => '',
                'PublishedDate' => '2026-03-03',
                'PublishedAt' => time(),
                'PublishedYear' => '2026',
                'PublishedMonth' => '03',
                'PublishedDay' => '03',
                'PinnedAt' => 0,
                'Visibility' => 'public',
                'IsPublished' => true,
                'Author' => ['ID' => 'u1', 'Nickname' => 'Admin', 'Bio' => '', 'Gravatar' => 'https://example.com/a.png'],
                'Tags' => [['Slug' => 'php', 'Name' => 'php']],
            ],
            'PreviousPost' => null,
            'NextPost' => null,
            'IsUnlocked' => true,
        ];

        $index = $this->view->renderTheme('index.html', 'default', $themeData);
        self::assertStringContainsString('/post/hello', $index);
        self::assertStringContainsString('<meta name="x-test" content="1">', $index);

        $singular = $this->view->renderTheme('singular.html', 'default', $themeData);
        self::assertStringContainsString('<dialog id="singular-drawer"', $singular);
        self::assertStringContainsString('#php', $singular);

        $notFound = $this->view->renderTheme('404.html', 'default', $themeData);
        self::assertStringContainsString('https://example.com/', $notFound);
    }

    private function baseData(): array
    {
        return [
            'Self' => null,
            'Config' => [
                'Name' => 'Blogme',
                'Description' => 'desc',
                'Locale' => 'en-us',
                'DateFormat' => '2006-01-02',
                'TimeFormat' => '15:04',
                'Timezone' => 0,
                'InjectedHead' => '',
                'InjectedFoot' => '',
                'InjectedPostStart' => '',
                'InjectedPostEnd' => '',
                'FooterText' => '',
                'ContainerWidth' => 'medium',
                'FontSize' => 'medium',
                'FontFamily' => 'sans',
                'ColorScheme' => '',
                'HighlightJS' => true,
                'AuthorBlock' => 'start',
                'Theme' => 'default',
            ],
            'Message' => '',
            'CSRF' => 'csrf-token',
            'URL' => [
                'Root' => 'https://example.com/',
                'Absolute' => 'https://example.com/post/demo',
                'RelativeRoot' => '/',
                'AbsoluteHost' => 'https://example.com/',
                'PageType' => 'post',
            ],
        ];
    }
}
