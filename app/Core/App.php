<?php

declare(strict_types=1);

namespace Blogme\Core;

use Blogme\Controllers\AdminController;
use Blogme\Controllers\AuthController;
use Blogme\Controllers\PublicController;
use Blogme\Controllers\WizardController;
use Blogme\Repositories\NavigationRepository;
use Blogme\Repositories\PostRepository;
use Blogme\Repositories\TagRepository;
use Blogme\Repositories\UserRepository;
use Blogme\Services\ConfigService;
use Blogme\Services\LocaleService;
use Blogme\Support\AppData;

final class App
{
    private Csrf $csrf;
    private ConfigService $configService;
    private ?array $config = null;

    public function __construct(
        private readonly string               $root,
        private readonly Request              $request,
        private readonly Router               $router,
        private readonly Session              $session,
        private readonly Flash                $flash,
        private readonly GoTemplateEngine     $view,
        private readonly LocaleService        $locale,
        private readonly RateLimiter          $rateLimiter,
        private readonly Database             $db,
        private readonly UserRepository       $users,
        private readonly TagRepository        $tags,
        private readonly NavigationRepository $navigations,
        private readonly PostRepository       $posts
    )
    {
        $this->csrf = new Csrf($session);
        $this->configService = new ConfigService($root);
        $this->config = $this->loadConfig();
        if ($this->config !== null) {
            $this->locale->loadThemeLocale((string)$this->config['Theme']);
        }
        $this->registerRoutes();
    }

    public function run(): void
    {
        $this->clearExpiredTrashWhenDue();
        if ($this->serveStaticIfMatched()) {
            return;
        }

        $match = $this->router->match($this->request->method(), $this->request->path());
        if ($match === null) {
            (new PublicController($this))->noRoute([]);
            return;
        }
        $handler = $match['handler'];
        $handler($match['vars'], $match['pattern'], $match['name']);
    }

    public function root(): string
    {
        return $this->root;
    }

    public function request(): Request
    {
        return $this->request;
    }

    public function session(): Session
    {
        return $this->session;
    }

    public function flash(): Flash
    {
        return $this->flash;
    }

    public function view(): GoTemplateEngine
    {
        return $this->view;
    }

    public function locale(): LocaleService
    {
        return $this->locale;
    }

    public function csrf(): Csrf
    {
        return $this->csrf;
    }

    public function rateLimiter(): RateLimiter
    {
        return $this->rateLimiter;
    }

    public function users(): UserRepository
    {
        return $this->users;
    }

    public function tags(): TagRepository
    {
        return $this->tags;
    }

    public function navigations(): NavigationRepository
    {
        return $this->navigations;
    }

    public function posts(): PostRepository
    {
        return $this->posts;
    }

    public function config(): ?array
    {
        return $this->config;
    }

    public function saveConfig(array $config): void
    {
        $this->config = $config;
        $this->configService->save($this->configToJson($config));
        $this->locale->loadThemeLocale((string)$config['Theme']);
    }

    public function configExists(): bool
    {
        return $this->config !== null;
    }

    /** @return array<int,string> */
    public function themes(): array
    {
        return $this->configService->listThemes();
    }

    public function themeExists(string $theme): bool
    {
        return $this->configService->themeExists($theme);
    }

    public function translate(string $key): string
    {
        $locale = (string)(($this->config['Locale'] ?? 'en-us'));
        return $this->locale->t($key, $locale);
    }

    public function currentUserId(): string
    {
        return (string)$this->session->get('user_id', '');
    }

    public function setCurrentUser(string $userId): void
    {
        $this->session->set('user_id', $userId);
    }

    public function clearCurrentUser(): void
    {
        $this->session->remove('user_id');
    }

    public function routePageType(string $pattern): string
    {
        return AppData::PAGE_TYPES[$pattern] ?? '';
    }

    public function routeRelativeRoot(string $pattern): string
    {
        if (isset(AppData::RELATIVE_ROOTS[$pattern])) {
            return AppData::RELATIVE_ROOTS[$pattern];
        }
        $path = $this->request->path();
        if ($path === '/') {
            return '';
        }
        $depth = substr_count(trim($path, '/'), '/');
        return str_repeat('../', $depth + 1);
    }

    private function registerRoutes(): void
    {
        $wizard = new WizardController($this);
        $auth = new AuthController($this);
        $admin = new AdminController($this);
        $public = new PublicController($this);

        $this->router->get('/wizard', fn(array $vars, string $pattern): mixed => $wizard->view($vars, $pattern), 'wizard.view');
        $this->router->post('/wizard', fn(array $vars, string $pattern): mixed => $wizard->submit($vars, $pattern), 'wizard.submit');

        $this->router->get('/login', fn(array $vars, string $pattern): mixed => $auth->view($vars, $pattern), 'auth.view');
        $this->router->post('/login', fn(array $vars, string $pattern): mixed => $auth->login($vars, $pattern), 'auth.login');
        $this->router->post('/admin/logout', fn(array $vars, string $pattern): mixed => $auth->logout($vars, $pattern), 'auth.logout');

        $this->router->get('/admin', fn(): mixed => redirect('/admin/posts'), 'admin.root');
        $this->router->get('/admin/users', fn(array $vars, string $pattern): mixed => $admin->usersView($vars, $pattern), 'admin.users.view');
        $this->router->post('/admin/users', fn(array $vars, string $pattern): mixed => $admin->userCreate($vars, $pattern), 'admin.users.create');
        $this->router->get('/admin/user/{id}', fn(array $vars, string $pattern): mixed => $admin->userEditView($vars, $pattern), 'admin.user.view');
        $this->router->post('/admin/user/{id}', fn(array $vars, string $pattern): mixed => $admin->userEdit($vars, $pattern), 'admin.user.edit');
        $this->router->post('/admin/user/{id}/delete', fn(array $vars, string $pattern): mixed => $admin->userDelete($vars, $pattern), 'admin.user.delete');

        $this->router->get('/admin/navigations', fn(array $vars, string $pattern): mixed => $admin->navigationsView($vars, $pattern), 'admin.navigations.view');
        $this->router->post('/admin/navigations', fn(array $vars, string $pattern): mixed => $admin->navigationCreate($vars, $pattern), 'admin.navigations.create');
        $this->router->post('/admin/navigations/edit', fn(array $vars, string $pattern): mixed => $admin->navigationEdit($vars, $pattern), 'admin.navigations.edit');

        $this->router->get('/admin/tags', fn(array $vars, string $pattern): mixed => $admin->tagsView($vars, $pattern), 'admin.tags.view');
        $this->router->post('/admin/tags', fn(array $vars, string $pattern): mixed => $admin->tagCreate($vars, $pattern), 'admin.tags.create');
        $this->router->get('/admin/tag/{id}', fn(array $vars, string $pattern): mixed => $admin->tagEditView($vars, $pattern), 'admin.tag.view');
        $this->router->post('/admin/tag/{id}', fn(array $vars, string $pattern): mixed => $admin->tagEdit($vars, $pattern), 'admin.tag.edit');
        $this->router->post('/admin/tag/{id}/delete', fn(array $vars, string $pattern): mixed => $admin->tagDelete($vars, $pattern), 'admin.tag.delete');

        $this->router->get('/admin/settings', fn(array $vars, string $pattern): mixed => $admin->settingsView($vars, $pattern), 'admin.settings.view');
        $this->router->post('/admin/settings', fn(array $vars, string $pattern): mixed => $admin->settingsEdit($vars, $pattern), 'admin.settings.edit');

        $this->router->get('/admin/appearances', fn(array $vars, string $pattern): mixed => $admin->appearancesView($vars, $pattern), 'admin.appearances.view');
        $this->router->post('/admin/appearances', fn(array $vars, string $pattern): mixed => $admin->appearancesEdit($vars, $pattern), 'admin.appearances.edit');
        $this->router->post('/admin/appearances/injected', fn(array $vars, string $pattern): mixed => $admin->appearancesInjectedEdit($vars, $pattern), 'admin.appearances.injected');

        $this->router->get('/admin/post/create', fn(array $vars, string $pattern): mixed => $admin->postCreateView($vars, $pattern), 'admin.post.create.view');
        $this->router->post('/admin/post/create', fn(array $vars, string $pattern): mixed => $admin->postCreate($vars, $pattern), 'admin.post.create');
        $this->router->get('/admin/posts', fn(array $vars, string $pattern): mixed => $admin->postsView($vars, $pattern), 'admin.posts.view');
        $this->router->post('/admin/trashes/clear', fn(array $vars, string $pattern): mixed => $admin->trashClear($vars, $pattern), 'admin.trashes.clear');
        $this->router->get('/admin/post/{id}', fn(array $vars, string $pattern): mixed => $admin->postEditView($vars, $pattern), 'admin.post.view');
        $this->router->post('/admin/post/{id}', fn(array $vars, string $pattern): mixed => $admin->postEdit($vars, $pattern), 'admin.post.edit');
        $this->router->post('/admin/post/{id}/delete', fn(array $vars, string $pattern): mixed => $admin->postDelete($vars, $pattern), 'admin.post.delete');
        $this->router->post('/admin/post/{id}/trash', fn(array $vars, string $pattern): mixed => $admin->postTrash($vars, $pattern), 'admin.post.trash');
        $this->router->post('/admin/post/{id}/untrash', fn(array $vars, string $pattern): mixed => $admin->postUntrash($vars, $pattern), 'admin.post.untrash');

        $this->router->post('/admin/photos/api', fn(array $vars, string $pattern): mixed => $admin->photoCreateApi($vars, $pattern), 'admin.photos.api');
        $this->router->get('/admin/photos', fn(array $vars, string $pattern): mixed => $admin->photosView($vars, $pattern), 'admin.photos.view');
        $this->router->post('/admin/photos', fn(array $vars, string $pattern): mixed => $admin->photoUpload($vars, $pattern), 'admin.photos.upload');
        $this->router->post('/admin/photo/delete', fn(array $vars, string $pattern): mixed => $admin->photoDelete($vars, $pattern), 'admin.photos.delete');

        $this->router->get('/', fn(array $vars, string $pattern): mixed => $public->index($vars, $pattern), 'public.index');
        $this->router->get('/sitemap.xml', fn(array $vars, string $pattern): mixed => $public->sitemap($vars, $pattern), 'public.sitemap');
        $this->router->get('/rss.xml', fn(array $vars, string $pattern): mixed => $public->rss($vars, $pattern), 'public.rss');
        $this->router->get('/tag/{tag}', fn(array $vars, string $pattern): mixed => $public->index($vars, $pattern), 'public.tag');
        $this->router->get('/author/{author}', fn(array $vars, string $pattern): mixed => $public->index($vars, $pattern), 'public.author');
        $this->router->get('/archive/{year}', fn(array $vars, string $pattern): mixed => $public->index($vars, $pattern), 'public.archive.year');
        $this->router->get('/archive/{year}/{month}', fn(array $vars, string $pattern): mixed => $public->index($vars, $pattern), 'public.archive.month');
        $this->router->get('/archive/{year}/{month}/{day}', fn(array $vars, string $pattern): mixed => $public->index($vars, $pattern), 'public.archive.day');
        $this->router->get('/post/{slug}', fn(array $vars, string $pattern): mixed => $public->singular($vars, $pattern), 'public.post.get');
        $this->router->post('/post/{slug}', fn(array $vars, string $pattern): mixed => $public->singular($vars, $pattern), 'public.post.post');
    }

    private function loadConfig(): ?array
    {
        $raw = $this->configService->load();
        if ($raw === null) {
            return null;
        }
        $mapped = [];
        foreach ($raw as $key => $value) {
            $mapped[$this->snakeToPascal((string)$key)] = $value;
        }
        return $mapped;
    }

    private function configToJson(array $config): array
    {
        $json = [];
        foreach ($config as $key => $value) {
            $json[$this->pascalToSnake((string)$key)] = $value;
        }
        return $json;
    }

    private function snakeToPascal(string $key): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
    }

    private function pascalToSnake(string $key): string
    {
        $step1 = (string)preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $key);
        $step2 = (string)preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $step1);
        return strtolower($step2);
    }

    private function clearExpiredTrashWhenDue(): void
    {
        $flagPath = $this->root . '/storage/runtime/trash_cleanup_at.txt';
        $last = is_file($flagPath) ? (int)file_get_contents($flagPath) : 0;
        if (time() - $last < 86400) {
            return;
        }
        $this->posts->clearExpiredTrash();
        file_put_contents($flagPath, (string)time());
    }

    private function serveStaticIfMatched(): bool
    {
        $path = $this->request->path();
        if (str_starts_with($path, '/uploads/')) {
            $this->serveFile($this->root . '/public' . $path, $this->root . '/public/uploads');
            return true;
        }
        if (str_starts_with($path, '/post/uploads/')) {
            $this->serveFile($this->root . '/public/uploads/' . substr($path, strlen('/post/uploads/')), $this->root . '/public/uploads');
            return true;
        }
        if (str_starts_with($path, '/admin/post/uploads/')) {
            $this->serveFile($this->root . '/public/uploads/' . substr($path, strlen('/admin/post/uploads/')), $this->root . '/public/uploads');
            return true;
        }
        if (str_starts_with($path, '/themes/')) {
            $relative = substr($path, strlen('/themes/'));
            $base = $this->root . '/public/themes';
            $this->serveFile($base . '/' . $relative, $base);
            return true;
        }
        if (str_starts_with($path, '/assets/')) {
            $relative = substr($path, strlen('/assets/'));
            $theme = $this->activeTheme();
            $base = $this->root . '/public/themes/' . $theme . '/assets';
            if (!is_dir($base)) {
                $base = $this->root . '/public/themes/default/assets';
            }
            $this->serveFile($base . '/' . $relative, $base);
            return true;
        }
        if (str_starts_with($path, '/admin/assets/')) {
            $relative = substr($path, strlen('/admin/assets/'));
            $base = $this->root . '/public/admin/assets';
            $this->serveFile($base . '/' . $relative, $base);
            return true;
        }
        if (str_starts_with($path, '/admin/uploads/')) {
            $relative = substr($path, strlen('/admin/uploads/'));
            $this->serveFile($this->root . '/public/uploads/' . $relative, $this->root . '/public/uploads');
            return true;
        }
        return false;
    }

    private function activeTheme(): string
    {
        $theme = (string) ($this->config['Theme'] ?? 'default');
        if ($theme === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $theme)) {
            return 'default';
        }
        return $theme;
    }

    private function serveFile(string $target, string $base): void
    {
        $realBase = realpath($base);
        $realTarget = realpath($target);
        if ($realBase === false || $realTarget === false || !str_starts_with($realTarget, $realBase) || !is_file($realTarget)) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }
        $this->outputFile($realTarget);
    }

    private function outputFile(string $file): void
    {
        $ext = strtolower((string)pathinfo($file, PATHINFO_EXTENSION));
        $mimeMap = [
            'css'   => 'text/css; charset=utf-8',
            'js'    => 'application/javascript; charset=utf-8',
            'svg'   => 'image/svg+xml',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'webp'  => 'image/webp',
            'woff2' => 'font/woff2',
            'woff'  => 'font/woff',
            'ttf'   => 'font/ttf',
            'otf'   => 'font/otf',
            'eot'   => 'application/vnd.ms-fontobject',
            'ico'   => 'image/x-icon',
            'xml'   => 'application/xml; charset=utf-8',
        ];
        if (isset($mimeMap[$ext])) {
            header('Content-Type: ' . $mimeMap[$ext]);
            readfile($file);
            return;
        }

        $mime = '';
        if (function_exists('mime_content_type')) {
            $detected = mime_content_type($file);
            if (is_string($detected) && $detected !== '' && strtolower($detected) !== 'application/octet-stream') {
                $mime = $detected;
            }
        }
        if ($mime === '' && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = finfo_file($finfo, $file);
                finfo_close($finfo);
                if (is_string($detected) && $detected !== '' && strtolower($detected) !== 'application/octet-stream') {
                    $mime = $detected;
                }
            }
        }
        if ($mime === '') {
            $mime = 'application/octet-stream';
        }
        header('Content-Type: ' . $mime);
        readfile($file);
    }
}

function redirect(string $location, int $code = 302): void
{
    header('Location: ' . $location, true, $code);
}
