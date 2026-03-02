<?php

declare(strict_types=1);

namespace Blogme\Controllers;

use Blogme\Core\HttpException;

final class PublicController extends BaseController
{
    public function index(array $vars, string $routePattern): void
    {
        $this->requireConfig();
        $config = $this->app->config() ?? [];
        $self = $this->currentUser();
        if (!(bool) ($config['IsPublic'] ?? true) && $self === null) {
            $this->setMessage('notice_site_private');
            \Blogme\Core\redirect('/login');
            return;
        }

        $page = $this->queryPage();
        $perPage = (int) ($config['PostsPerPage'] ?? 10);
        $query = [
            'offset' => ($page - 1) * $perPage,
            'limit' => $perPage,
            'title' => (string) $this->request()->query('title', ''),
            'is_published' => true,
            'is_trashed' => false,
            'visibilities' => $self === null ? ['public', 'password'] : ['public', 'password', 'private'],
        ];
        $filter = ['Tag' => '', 'Author' => '', 'Date' => '', 'IsEmpty' => true];

        if (($vars['tag'] ?? '') !== '') {
            $tag = $this->app->tags()->bySlug((string) $vars['tag']);
            if ($tag === null) {
                throw new HttpException(404, 'Tag not found');
            }
            $query['tag_id'] = $tag['ID'];
            $filter['Tag'] = $tag['Name'];
        }
        if (($vars['author'] ?? '') !== '') {
            $user = $this->app->users()->byId((string) $vars['author']);
            if ($user === null) {
                throw new HttpException(404, 'Author not found');
            }
            $query['author_id'] = $user['ID'];
            $filter['Author'] = $user['Nickname'];
        }
        if (($vars['year'] ?? '') !== '') {
            $query['published_year'] = (string) $vars['year'];
            $filter['Date'] = (string) $vars['year'];
            if (($vars['month'] ?? '') !== '') {
                $query['published_month'] = (string) $vars['month'];
                $filter['Date'] .= '/' . $vars['month'];
                if (($vars['day'] ?? '') !== '') {
                    $query['published_day'] = (string) $vars['day'];
                    $filter['Date'] .= '/' . $vars['day'];
                }
            }
        }
        $filter['IsEmpty'] = ($filter['Tag'] === '' && $filter['Author'] === '' && $filter['Date'] === '');

        $posts = $this->app->posts()->list($query, $config);
        $count = $this->app->posts()->count($query, $config);
        $navs = $this->app->navigations()->all();
        $html = $this->app->view()->renderTheme('index.html', (string) $config['Theme'], [
            ...$this->baseData($routePattern),
            'Posts' => $posts,
            'Pagination' => $this->pagination($page, $count, $perPage),
            'Navigations' => $navs,
            'Filter' => $filter,
        ]);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
    }

    public function singular(array $vars, string $routePattern): void
    {
        $this->requireConfig();
        $config = $this->app->config() ?? [];
        $self = $this->currentUser();
        if (!(bool) ($config['IsPublic'] ?? true) && $self === null) {
            $this->setMessage('notice_site_private');
            \Blogme\Core\redirect('/login');
            return;
        }

        if ($this->request()->method() === 'POST') {
            $this->checkCsrf();
            $this->throttle('post_password');
        }

        $post = $this->app->posts()->bySlug((string) $vars['slug']);
        if ($post === null) {
            $this->noRoute($vars);
            return;
        }
        if ($self === null && !in_array($post['Visibility'], ['public', 'password'], true)) {
            $this->noRoute($vars);
            return;
        }
        if ($self === null && (int) $post['PublishedAt'] > time()) {
            $this->noRoute($vars);
            return;
        }

        $isUnlocked = false;
        if ($self !== null || $post['Visibility'] === 'public') {
            $isUnlocked = true;
        } else {
            $password = (string) $this->request()->post('password', '');
            if ($password !== '' && hash_equals((string) $post['Password'], $password)) {
                $isUnlocked = true;
            } elseif ($this->request()->method() === 'POST') {
                $this->setMessage('notice_post_incorrect');
            }
        }

        $html = $this->app->view()->renderTheme('singular.html', (string) $config['Theme'], [
            ...$this->baseData($routePattern),
            'Post' => $post,
            'Navigations' => $this->app->navigations()->all(),
            'PreviousPost' => $this->app->posts()->previous((string) $post['ID']),
            'NextPost' => $this->app->posts()->next((string) $post['ID']),
            'IsUnlocked' => $isUnlocked,
        ]);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
    }

    public function rss(array $vars, string $routePattern): void
    {
        $this->requireConfig();
        $config = $this->app->config() ?? [];
        $posts = $this->app->posts()->list([
            'is_published' => true,
            'is_trashed' => false,
            'visibilities' => ['public'],
        ], $config);
        $root = $this->request()->scheme() . '://' . $this->request()->host();

        $items = '';
        foreach ($posts as $post) {
            $url = $root . '/post/' . rawurlencode((string) $post['Slug']);
            $items .= '<item>';
            $items .= '<guid>' . $this->xml($url) . '</guid>';
            $items .= '<title>' . $this->xml((string) $post['Title']) . '</title>';
            $items .= '<link>' . $this->xml($url) . '</link>';
            $items .= '<description>' . $this->xml((string) $post['Content']) . '</description>';
            $items .= '<pubDate>' . gmdate(DATE_RSS, (int) $post['UpdatedAt']) . '</pubDate>';
            $items .= '</item>';
        }

        header('Content-Type: application/rss+xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>';
        echo '<atom:link href="' . $this->xml($root . '/rss.xml') . '" rel="self" type="application/rss+xml"/>';
        echo '<title>' . $this->xml((string) ($config['Name'] ?? 'Blog')) . '</title>';
        echo '<link>' . $this->xml($root) . '</link>';
        echo '<description>' . $this->xml((string) ($config['Description'] ?? '')) . '</description>';
        echo '<language>' . $this->xml((string) ($config['Locale'] ?? 'en-us')) . '</language>';
        echo '<pubDate>' . gmdate(DATE_RSS) . '</pubDate>';
        echo $items;
        echo '</channel></rss>';
    }

    public function sitemap(array $vars, string $routePattern): void
    {
        $this->requireConfig();
        $posts = $this->app->posts()->list([
            'is_published' => true,
            'is_trashed' => false,
            'visibilities' => ['public'],
        ], $this->app->config() ?? []);
        $root = $this->request()->scheme() . '://' . $this->request()->host();

        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($posts as $post) {
            echo '<url>';
            echo '<loc>' . $this->xml($root . '/post/' . rawurlencode((string) $post['Slug'])) . '</loc>';
            echo '<lastmod>' . gmdate('Y-m-d', (int) $post['UpdatedAt']) . '</lastmod>';
            echo '</url>';
        }
        echo '</urlset>';
    }

    public function asset(array $vars, string $routePattern): void
    {
        $theme = 'default';
        if ($this->app->configExists()) {
            $theme = (string) (($this->app->config() ?? [])['Theme'] ?? 'default');
        }
        $base = realpath($this->app->root() . '/data/themes/' . $theme . '/assets');
        $asset = basename((string) $vars['asset']);
        $file = realpath($this->app->root() . '/data/themes/' . $theme . '/assets/' . $asset);
        if ($base === false || $file === false || !str_starts_with($file, $base) || !is_file($file)) {
            throw new HttpException(404, 'asset not found');
        }
        $mime = 'application/octet-stream';
        if (function_exists('mime_content_type')) {
            $detected = mime_content_type($file);
            if (is_string($detected) && $detected !== '') {
                $mime = $detected;
            }
        } elseif (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = finfo_file($finfo, $file);
                finfo_close($finfo);
                if (is_string($detected) && $detected !== '') {
                    $mime = $detected;
                }
            }
        }
        header('Content-Type: ' . $mime);
        readfile($file);
    }

    public function noRoute(array $vars): void
    {
        if (!$this->app->configExists()) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }
        $config = $this->app->config() ?? [];
        $file = $this->app->root() . '/data/themes/' . $config['Theme'] . '/404.html';
        if (!is_file($file)) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }
        http_response_code(404);
        echo $this->app->view()->renderTheme('404.html', (string) $config['Theme'], [
            ...$this->baseData('/'),
            'Navigations' => $this->app->navigations()->all(),
        ]);
    }

    private function xml(string $v): string
    {
        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
