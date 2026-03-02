<?php

declare(strict_types=1);

namespace Blogme\Controllers;

use Blogme\Core\HttpException;

final class WizardController extends BaseController
{
    public function view(array $vars, string $routePattern): void
    {
        if ($this->app->configExists()) {
            \Blogme\Core\redirect('/admin');
            return;
        }

        $locale = (string) $this->request()->query('locale', '');
        if ($locale === '') {
            $locale = $this->detectLocale();
        }

        echo $this->app->view()->renderPage('wizard', [
            ...$this->baseData($routePattern),
            'Locales' => $this->availableLocales(),
            'DefaultLocale' => $locale,
        ]);
    }

    public function submit(array $vars, string $routePattern): void
    {
        if ($this->app->configExists()) {
            \Blogme\Core\redirect('/admin');
            return;
        }
        $this->checkCsrf();

        $name = trim((string) $this->request()->post('name', ''));
        $description = trim((string) $this->request()->post('description', ''));
        $email = trim((string) $this->request()->post('email', ''));
        $password = (string) $this->request()->post('password', '');
        $nickname = trim((string) $this->request()->post('nickname', ''));
        $timezone = (int) $this->request()->post('timezone', 0);
        $locale = trim((string) $this->request()->post('locale', 'en-us'));

        if ($name === '' || $email === '' || $password === '' || $nickname === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new HttpException(400, 'Invalid wizard input');
        }

        $uid = $this->uuid();
        $this->app->users()->create([
            'id' => $uid,
            'email' => $email,
            'nickname' => $nickname,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'bio' => '',
            'created_at' => time(),
        ]);

        $config = $this->defaultConfig([
            'Name' => $name,
            'Description' => $description,
            'Timezone' => $timezone,
            'Locale' => $locale,
        ]);
        $this->app->saveConfig($config);

        $this->app->posts()->create([
            'id' => $this->uuid(),
            'title' => $this->app->locale()->t('defaultpost_title', $locale),
            'slug' => 'hello-world',
            'excerpt' => '',
            'author_id' => $uid,
            'password' => '',
            'visibility' => 'public',
            'content' => $this->app->locale()->t('defaultpost_content', $locale),
            'published_at' => time() - 60,
            'created_at' => time() - 60,
            'updated_at' => time() - 60,
            'pinned_at' => 0,
            'trashed_at' => 0,
            'tag_ids' => [],
        ]);

        $this->app->setCurrentUser($uid);
        \Blogme\Core\redirect('/admin');
    }

    private function detectLocale(): string
    {
        $header = (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        if ($header === '') {
            return 'en-us';
        }
        $parts = array_map('trim', explode(',', strtolower($header)));
        $all = array_values($this->availableLocales());
        foreach ($parts as $part) {
            $lang = explode(';', $part)[0];
            $lang = str_replace('_', '-', $lang);
            if (in_array($lang, $all, true)) {
                return $lang;
            }
            if (strlen($lang) >= 2) {
                foreach ($all as $candidate) {
                    if (str_starts_with($candidate, substr($lang, 0, 2))) {
                        return $candidate;
                    }
                }
            }
        }
        return 'en-us';
    }
}
