<?php

declare(strict_types=1);

use Blogme\Core\App;
use Blogme\Core\Container;
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
use Blogme\Services\MediaService;
use Blogme\Services\PostDateService;

require __DIR__ . '/autoload.php';

function bootstrap_app(): App
{
    $root = dirname(__DIR__);

    $container = new Container();
    $container->set(AppSetupService::class, static fn (Container $c): AppSetupService => new AppSetupService($root));
    $container->set(Request::class, static fn (Container $c): Request => Request::capture());
    $container->set(Session::class, static fn (Container $c): Session => new Session());
    $container->set(Flash::class, static fn (Container $c): Flash => new Flash($c->get(Session::class)));
    $container->set(Database::class, static fn (Container $c): Database => new Database($root . '/db.sqlite'));
    $container->set(UserRepository::class, static fn (Container $c): UserRepository => new UserRepository($c->get(Database::class)));
    $container->set(TagRepository::class, static fn (Container $c): TagRepository => new TagRepository($c->get(Database::class)));
    $container->set(NavigationRepository::class, static fn (Container $c): NavigationRepository => new NavigationRepository($c->get(Database::class)));
    $container->set(PostRepository::class, static fn (Container $c): PostRepository => new PostRepository($c->get(Database::class), $c->get(TagRepository::class)));
    $container->set(LocaleService::class, static fn (Container $c): LocaleService => new LocaleService($root . '/resources/locales', $root . '/public/themes'));
    $container->set(GoTemplateEngine::class, static fn (Container $c): GoTemplateEngine => new GoTemplateEngine($root, $c->get(LocaleService::class)));
    $container->set(RateLimiter::class, static fn (Container $c): RateLimiter => new RateLimiter($root . '/storage/rate_limit'));
    $container->set(Router::class, static fn (Container $c): Router => new Router());
    $container->set(MediaService::class, static fn (Container $c): MediaService => new MediaService($root));
    $container->set(PostDateService::class, static fn (Container $c): PostDateService => new PostDateService());
    $container->set(App::class, static fn (Container $c): App => new App(
        $root,
        $c->get(Request::class),
        $c->get(Router::class),
        $c->get(Session::class),
        $c->get(Flash::class),
        $c->get(GoTemplateEngine::class),
        $c->get(LocaleService::class),
        $c->get(RateLimiter::class),
        $c->get(Database::class),
        $c->get(UserRepository::class),
        $c->get(TagRepository::class),
        $c->get(NavigationRepository::class),
        $c->get(PostRepository::class),
        $c->get(MediaService::class),
        $c->get(PostDateService::class)
    ));

    $setup = $container->get(AppSetupService::class);
    $setup->bootstrapFilesystem();
    $setup->bootstrapTheme();
    $setup->bootstrapDatabase($container->get(Database::class));

    return $container->get(App::class);
}
