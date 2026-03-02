<?php

declare(strict_types=1);

namespace Blogme\Controllers;

final class AuthController extends BaseController
{
    public function view(array $vars, string $routePattern): void
    {
        $this->requireConfig();
        $self = $this->currentUser();
        if ($self !== null) {
            \Blogme\Core\redirect('/admin/posts');
            return;
        }
        echo $this->app->view()->renderPage('login', [
            ...$this->baseData($routePattern),
            'Name' => $this->app->config()['Name'],
        ]);
    }

    public function login(array $vars, string $routePattern): void
    {
        $this->requireConfig();
        $this->checkCsrf();
        $this->throttle('login');

        $email = trim((string) $this->request()->post('email', ''));
        $password = (string) $this->request()->post('password', '');
        $user = $this->app->users()->byEmail($email);

        if ($user === null || !password_verify($password, (string) $user['Password'])) {
            $this->setMessage('notice_login_incorrect');
            \Blogme\Core\redirect('/login');
            return;
        }

        $this->app->setCurrentUser((string) $user['ID']);
        \Blogme\Core\redirect('/admin/posts');
    }

    public function logout(array $vars, string $routePattern): void
    {
        $this->requireConfig();
        $this->checkCsrf();
        $this->app->clearCurrentUser();
        $this->setMessage('notice_loggedout');
        \Blogme\Core\redirect('/login');
    }
}
