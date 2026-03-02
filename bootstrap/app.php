<?php

declare(strict_types=1);

use Blogme\Core\App;
use Blogme\Core\Database;
use Blogme\Core\Flash;
use Blogme\Core\GoTemplateEngine;
use Blogme\Core\RateLimiter;
use Blogme\Core\Request;
use Blogme\Core\Router;
use Blogme\Core\Session;
use Blogme\Repositories\NavigationRepository;
use Blogme\Repositories\PostRepository;
use Blogme\Repositories\TagRepository;
use Blogme\Repositories\UserRepository;
use Blogme\Services\AppSetupService;
use Blogme\Services\LocaleService;

require __DIR__ . '/autoload.php';

function bootstrap_app(): App
{
    $root = dirname(__DIR__);

    $setup = new AppSetupService($root);
    $setup->bootstrapFilesystem();
    $setup->bootstrapTheme();

    $request = Request::capture();
    $session = new Session();
    $flash = new Flash($session);

    $db = new Database($root . '/db.sqlite');
    $db->migrate();

    $userRepo = new UserRepository($db);
    $tagRepo = new TagRepository($db);
    $navRepo = new NavigationRepository($db);
    $postRepo = new PostRepository($db, $tagRepo);
    $locale = new LocaleService($root . '/resources/locales', $root . '/data/themes');
    $view = new GoTemplateEngine($root, $locale);
    $rateLimiter = new RateLimiter($root . '/storage/rate_limit');
    $router = new Router();

    return new App(
        $root,
        $request,
        $router,
        $session,
        $flash,
        $view,
        $locale,
        $rateLimiter,
        $db,
        $userRepo,
        $tagRepo,
        $navRepo,
        $postRepo
    );
}
