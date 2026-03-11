<?php

declare(strict_types=1);

namespace Blogme\Controllers;

use Blogme\Core\HttpException;

final class AdminController extends BaseController
{
    public function usersView(array $vars, string $routePattern): void
    {
        $this->guard();
        echo $this->app->view()->renderAdmin('admin_users', [
            ...$this->baseData($routePattern),
            'Users' => $this->app->users()->all(),
        ]);
    }

    public function userCreate(array $vars, string $routePattern): void
    {
        $this->guard(true);
        $email = trim((string) $this->request()->post('email', ''));
        $password = trim((string) $this->request()->post('password', ''));
        if ($email === '' || $password === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new HttpException(400, 'Invalid user input');
        }
        $nickname = explode('@', $email)[0];
        if ($this->app->users()->nicknameExists($nickname)) {
            $nickname .= '-' . time();
        }
        $this->app->users()->create([
            'id' => $this->uuid(),
            'email' => $email,
            'nickname' => $nickname,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'bio' => '',
            'created_at' => time(),
        ]);
        $this->setMessage('notice_user_created');
        \Blogme\Core\redirect('/admin/users');
    }

    public function userEditView(array $vars, string $routePattern): void
    {
        $this->guard();
        $user = $this->app->users()->byId((string) $vars['id']);
        if ($user === null) {
            throw new HttpException(404, 'User not found');
        }
        $users = $this->app->users()->all();
        $count = $this->app->posts()->countByUser((string) $user['ID']);
        echo $this->app->view()->renderAdmin('admin_user_edit', [
            ...$this->baseData($routePattern),
            'Users' => $users,
            'User' => $user,
            'IsOnlyUser' => count($users) === 1,
            'IsDeletable' => true,
            'PostCount' => $count,
        ]);
    }

    public function userEdit(array $vars, string $routePattern): void
    {
        $this->guard(true);
        $id = (string) $vars['id'];
        $user = $this->app->users()->byId($id);
        if ($user === null) {
            throw new HttpException(404, 'User not found');
        }
        $email = trim((string) $this->request()->post('email', ''));
        $nickname = trim((string) $this->request()->post('nickname', ''));
        $bio = trim((string) $this->request()->post('bio', ''));
        $password = trim((string) $this->request()->post('password', ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $nickname === '') {
            throw new HttpException(400, 'Invalid user input');
        }
        if ($password !== '') {
            $this->app->users()->updatePassword($id, password_hash($password, PASSWORD_BCRYPT));
        }
        $this->app->users()->update($id, $nickname, $bio, $email);
        $this->setMessage('notice_user_updated');
        \Blogme\Core\redirect('/admin/users');
    }

    public function userDelete(array $vars, string $routePattern): void
    {
        $this->guard(true);
        $id = (string) $vars['id'];
        $transferToId = trim((string) $this->request()->post('transfer_to_id', ''));
        if ($transferToId !== '') {
            $transferTo = $this->app->users()->byId($transferToId);
            if ($transferTo === null) {
                throw new HttpException(404, 'Transfer user not found');
            }
            $this->app->posts()->transferPosts($id, $transferToId);
            $this->setMessage('notice_user_deletedwithposts');
        } else {
            $this->app->posts()->deleteByUser($id);
            $this->setMessage('notice_user_deleted');
        }
        $this->app->users()->delete($id);
        \Blogme\Core\redirect('/admin/users');
    }

    public function navigationsView(array $vars, string $routePattern): void
    {
        $this->guard();
        echo $this->app->view()->renderAdmin('admin_navigations', [
            ...$this->baseData($routePattern),
            'Navigations' => $this->app->navigations()->all(),
        ]);
    }

    public function navigationCreate(array $vars, string $routePattern): void
    {
        $this->guard(true);
        $name = trim((string) $this->request()->post('name', ''));
        $url = trim((string) $this->request()->post('url', ''));
        if ($name === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new HttpException(400, 'Invalid navigation');
        }
        $navs = $this->app->navigations()->all();
        $items = [];
        foreach ($navs as $i => $n) {
            $items[] = [
                'id' => (string) $n['ID'],
                'name' => (string) $n['Name'],
                'url' => (string) $n['URL'],
                'sequence' => $i + 1,
            ];
        }
        $items[] = [
            'id' => $this->uuid(),
            'name' => $name,
            'url' => $url,
            'sequence' => count($navs) + 1,
        ];
        $this->app->navigations()->replaceAll($items);
        $this->setMessage('notice_nagivation_created');
        \Blogme\Core\redirect('/admin/navigations');
    }

    public function navigationEdit(array $vars, string $routePattern): void
    {
        $this->guard(true);
        $names = $this->request()->allPost()['name'] ?? $this->request()->allPost()['name[]'] ?? [];
        $urls = $this->request()->allPost()['url'] ?? $this->request()->allPost()['url[]'] ?? [];
        $sequences = $this->request()->allPost()['sequence'] ?? $this->request()->allPost()['sequence[]'] ?? [];
        $isDeleted = $this->request()->allPost()['is_deleted'] ?? $this->request()->allPost()['is_deleted[]'] ?? [];
        $names = is_array($names) ? $names : [];
        $urls = is_array($urls) ? $urls : [];
        $sequences = is_array($sequences) ? $sequences : [];
        $isDeleted = is_array($isDeleted) ? $isDeleted : [];

        $items = [];
        foreach ($names as $i => $name) {
            if ($this->toBool($isDeleted[$i] ?? false)) {
                continue;
            }
            $items[] = [
                'id' => $this->uuid(),
                'name' => trim((string) $name),
                'url' => trim((string) ($urls[$i] ?? '')),
                'sequence' => (int) ($sequences[$i] ?? 0),
            ];
        }
        usort($items, static fn (array $a, array $b): int => $a['sequence'] <=> $b['sequence']);

        foreach ($items as $i => &$item) {
            $item['sequence'] = $i + 1;
        }
        unset($item);
        $this->app->navigations()->replaceAll($items);
        $this->setMessage('notice_nagivation_updated');
        \Blogme\Core\redirect('/admin/navigations');
    }

    public function tagsView(array $vars, string $routePattern): void
    {
        $this->guard();
        $page = $this->queryPage();
        $perPage = 50;
        $keyword = (string) $this->request()->query('keyword', '');
        $tags = $this->app->tags()->list(($page - 1) * $perPage, $perPage, $keyword);
        $count = $this->app->tags()->count($keyword);

        echo $this->app->view()->renderAdmin('admin_tags', [
            ...$this->baseData($routePattern),
            'Keyword' => $keyword,
            'Tags' => $tags,
            'Pagination' => $this->pagination($page, $count, $perPage),
        ]);
    }

    public function tagCreate(array $vars, string $routePattern): void
    {
        $this->guard(true);
        $name = trim((string) $this->request()->post('name', ''));
        $desc = trim((string) $this->request()->post('description', ''));
        if ($name === '') {
            throw new HttpException(400, 'Tag name required');
        }
        $this->app->tags()->create([
            'id' => $this->uuid(),
            'slug' => $this->slugify($name),
            'name' => str_replace(',', '', $name),
            'description' => $desc,
            'created_at' => time(),
        ]);
        $this->setMessage('notice_tag_created');
        \Blogme\Core\redirect('/admin/tags');
    }

    public function tagEditView(array $vars, string $routePattern): void
    {
        $this->guard();
        $tag = $this->app->tags()->byId((string) $vars['id']);
        if ($tag === null) {
            throw new HttpException(404, 'Tag not found');
        }
        echo $this->app->view()->renderAdmin('admin_tag_edit', [
            ...$this->baseData($routePattern),
            'Tag' => $tag,
        ]);
    }

    public function tagEdit(array $vars, string $routePattern): void
    {
        $this->guard(true);
        $id = (string) $vars['id'];
        $tag = $this->app->tags()->byId($id);
        if ($tag === null) {
            throw new HttpException(404, 'Tag not found');
        }
        $slug = trim((string) $this->request()->post('slug', ''));
        $name = trim((string) $this->request()->post('name', ''));
        $desc = trim((string) $this->request()->post('description', ''));
        if ($slug === '' || $name === '') {
            throw new HttpException(400, 'Invalid tag');
        }
        $this->app->tags()->update([
            'id' => $id,
            'slug' => $this->slugify($slug),
            'name' => str_replace(',', '', $name),
            'description' => $desc,
            'created_at' => (int) $tag['CreatedAt'],
        ]);
        $this->setMessage('notice_tag_updated');
        \Blogme\Core\redirect('/admin/tags');
    }

    public function tagDelete(array $vars, string $routePattern): void
    {
        $this->guard(true);
        $this->app->tags()->delete((string) $vars['id']);
        $this->setMessage('notice_tag_deleted');
        \Blogme\Core\redirect('/admin/tags');
    }

    public function settingsView(array $vars, string $routePattern): void
    {
        $this->guard();
        $config = $this->app->config() ?? [];
        $now = time();
        echo $this->app->view()->renderAdmin('admin_settings', [
            ...$this->baseData($routePattern),
            'Version' => '0.4.0-php',
            'RuntimeVersion' => PHP_VERSION,
            'Timezones' => $this->availableTimezones(),
            'Locales' => $this->availableLocales(),
            'IsCustomTimeFormat' => !in_array((string) ($config['TimeFormat'] ?? ''), ['PM 03:04', '15:04', '03:04 PM'], true),
            'IsCustomDateFormat' => !in_array((string) ($config['DateFormat'] ?? ''), ['2006-01-02', '01/02/2006', '02/01/2006'], true),
            'Year' => gmdate('Y', $now),
            'Month' => gmdate('m', $now),
            'Day' => gmdate('d', $now),
            'Hour' => gmdate('h', $now),
            'Hour24' => gmdate('H', $now),
            'Minute' => gmdate('i', $now),
            'Clock' => gmdate('A', $now),
        ]);
    }

    public function settingsEdit(array $vars, string $routePattern): void
    {
        $this->guard(true);
        $config = $this->app->config() ?? [];
        $config['Name'] = trim((string) $this->request()->post('name', ''));
        $config['Description'] = trim((string) $this->request()->post('description', ''));
        $config['IsPublic'] = $this->toBool($this->request()->post('is_public', ''));
        $config['Timezone'] = (int) $this->request()->post('timezone', 0);
        $config['Locale'] = trim((string) $this->request()->post('locale', 'en-us'));

        $dateFormat = (string) $this->request()->post('date_format', '2006-01-02');
        $timeFormat = (string) $this->request()->post('time_format', '15:04');
        $config['DateFormat'] = $dateFormat === 'custom' ? trim((string) $this->request()->post('date_format_custom', '2006-01-02')) : $dateFormat;
        $config['TimeFormat'] = $timeFormat === 'custom' ? trim((string) $this->request()->post('time_format_custom', '15:04')) : $timeFormat;
        $this->app->saveConfig($config);
        $this->setMessage('notice_settings_updated');
        \Blogme\Core\redirect('/admin/settings');
    }

    public function appearancesView(array $vars, string $routePattern): void
    {
        $this->guard();
        echo $this->app->view()->renderAdmin('admin_appearances', [
            ...$this->baseData($routePattern),
            'Themes' => $this->app->themes(),
        ]);
    }

    public function appearancesEdit(array $vars, string $routePattern): void
    {
        $this->guard(true);
        $config = $this->app->config() ?? [];
        $theme = trim((string) $this->request()->post('theme', 'default'));
        if (!$this->app->themeExists($theme)) {
            throw new HttpException(400, 'Invalid theme');
        }
        $config['FooterText'] = trim((string) $this->request()->post('footer_text', ''));
        $config['ColorScheme'] = trim((string) $this->request()->post('color_scheme', ''));
        $config['ContainerWidth'] = trim((string) $this->request()->post('container_width', 'medium'));
        $config['FontFamily'] = trim((string) $this->request()->post('font_family', 'sans'));
        $config['FontSize'] = trim((string) $this->request()->post('font_size', 'medium'));
        $config['HighlightJS'] = $this->toBool($this->request()->post('highlight_js', ''));
        $config['AuthorBlock'] = trim((string) $this->request()->post('author_block', 'start'));
        $config['PostsPerPage'] = (int) $this->request()->post('posts_per_page', 10);
        $config['Theme'] = $theme;
        $this->app->saveConfig($config);
        $this->setMessage('notice_appearances_updated');
        \Blogme\Core\redirect('/admin/appearances');
    }

    public function appearancesInjectedEdit(array $vars, string $routePattern): void
    {
        $this->guard(true);
        $config = $this->app->config() ?? [];
        $config['InjectedHead'] = trim((string) $this->request()->post('injected_head', ''));
        $config['InjectedFoot'] = trim((string) $this->request()->post('injected_foot', ''));
        $config['InjectedPostStart'] = trim((string) $this->request()->post('injected_post_start', ''));
        $config['InjectedPostEnd'] = trim((string) $this->request()->post('injected_post_end', ''));
        $this->app->saveConfig($config);
        $this->setMessage('notice_injected_updated');
        \Blogme\Core\redirect('/admin/appearances');
    }

    public function postsView(array $vars, string $routePattern): void
    {
        $this->guard();
        $config = $this->app->config() ?? [];
        $page = $this->queryPage();
        $countPerPage = 30;
        $visibility = (string) $this->request()->query('visibility', '');
        $query = [
            'offset' => ($page - 1) * $countPerPage,
            'limit' => $countPerPage,
            'title' => (string) $this->request()->query('title', ''),
            'author_id' => (string) $this->request()->query('author_id', ''),
            'visibilities' => ['public', 'password', 'private', 'draft'],
            'is_trashed' => false,
            'published_date' => (string) $this->request()->query('published_date', ''),
        ];
        if ($visibility !== '' && $visibility !== 'trash') {
            $query['visibilities'] = [$visibility];
            $query['is_trashed'] = false;
        }
        if ($visibility === 'trash') {
            $query['is_trashed'] = true;
        }

        $posts = $this->app->posts()->list($query, $config);
        $count = $this->app->posts()->count($query, $config);
        $counts = $this->app->posts()->countByType();
        $dates = $this->app->posts()->listDates($config);
        echo $this->app->view()->renderAdmin('admin_posts', [
            ...$this->baseData($routePattern),
            'Query' => [
                'Title' => $query['title'],
                'AuthorID' => $query['author_id'],
                'PublishedDate' => $query['published_date'],
            ],
            'IsQuerySetted' => $query['title'] !== '' || $query['author_id'] !== '' || $query['published_date'] !== '',
            'Posts' => $posts,
            'Dates' => $dates,
            'Users' => $this->app->users()->all(),
            'PostCount' => $counts,
            'Visibility' => $visibility,
            'Pagination' => $this->pagination($page, $count, $countPerPage),
        ]);
    }

    public function postCreateView(array $vars, string $routePattern): void
    {
        $this->guard();
        echo $this->app->view()->renderAdmin('admin_post_create', [
            ...$this->baseData($routePattern),
            'Users' => $this->app->users()->all(),
            'Tags' => $this->app->tags()->list(0, 999, ''),
            'MostUsedTags' => $this->app->tags()->mostUsed(),
        ]);
    }

    public function postCreate(array $vars, string $routePattern): void
    {
        $this->guard(true);
        $config = $this->app->config() ?? [];
        $siteTimezone = (int) ($config['Timezone'] ?? 0);
        $id = $this->uuid();
        $authorId = trim((string) $this->request()->post('author_id', ''));
        if ($this->app->users()->byId($authorId) === null) {
            throw new HttpException(400, 'Invalid author');
        }
        $this->app->media()->saveCover($id, $this->request()->file('cover_file'));
        $tagIds = $this->createTags((string) $this->request()->post('tags', ''));
        $publishedAt = $this->app->postDates()->parsePublishedAtFromPost(
            $this->request()->post('published_at', null),
            0
        );
        if ($publishedAt <= 0 && $this->toBool($this->request()->post('is_scheduled', ''))) {
            $publishedAt = $this->app->postDates()->parsePublishedDatetimeAsSiteTimezone(
                $this->request()->post('published_datetime', null),
                $siteTimezone,
                0
            );
        }
        if ($publishedAt <= 0) {
            $publishedAt = time();
        }
        $visibility = (string) $this->request()->post('visibility', 'public');
        $password = '';
        if ($visibility === 'password') {
            $password = trim((string) $this->request()->post('password', ''));
        }
        $this->app->posts()->create([
            'id' => $id,
            'title' => trim((string) $this->request()->post('title', '')),
            'slug' => $this->slugify((string) $this->request()->post('slug', '')),
            'excerpt' => trim((string) $this->request()->post('excerpt', '')),
            'author_id' => $authorId,
            'password' => $password,
            'visibility' => $visibility,
            'content' => trim((string) $this->request()->post('content', '')),
            'published_at' => $publishedAt,
            'created_at' => time(),
            'updated_at' => time(),
            'pinned_at' => $this->toBool($this->request()->post('is_pinned', '')) ? time() : 0,
            'trashed_at' => 0,
            'tag_ids' => $tagIds,
        ]);
        $this->setMessage('notice_post_created');
        \Blogme\Core\redirect('/admin/post/' . $id, 303);
    }

    public function postEditView(array $vars, string $routePattern): void
    {
        $this->guard();
        $post = $this->app->posts()->byId((string) $vars['id'], $this->app->config() ?? []);
        if ($post === null) {
            throw new HttpException(404, 'Post not found');
        }
        $coverImageUrl = (string) $post['Cover'];
        if (
            $coverImageUrl !== ''
            && !str_starts_with($coverImageUrl, '/')
            && !preg_match('#^(?:[a-z]+:)?//#i', $coverImageUrl)
            && !str_starts_with($coverImageUrl, 'data:')
            && !str_starts_with($coverImageUrl, 'blob:')
        ) {
            $coverImageUrl = $this->app->routeRelativeRoot($routePattern) . ltrim($coverImageUrl, '/');
        }
        $jsonData = json_encode([
            'visibility' => $post['Visibility'],
            'cover_image_url' => $coverImageUrl,
            'tags' => array_map(static fn (array $t): string => (string) $t['Name'], $post['Tags']),
            'tags_str' => $post['TagsStr'],
            'slug' => $post['Slug'],
            'tag_input_value' => '',
            'published_datetime' => $post['PublishedAtISO'],
            'published_at' => (string) $post['PublishedAt'],
            'is_clear_cover' => false,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo $this->app->view()->renderAdmin('admin_post_edit', [
            ...$this->baseData($routePattern),
            'Users' => $this->app->users()->all(),
            'Tags' => $this->app->tags()->list(0, 999, ''),
            'MostUsedTags' => $this->app->tags()->mostUsed(),
            'Post' => $post,
            'JSONData' => $jsonData,
        ]);
    }

    public function postEdit(array $vars, string $routePattern): void
    {
        $this->guard(true);
        $config = $this->app->config() ?? [];
        $siteTimezone = (int) ($config['Timezone'] ?? 0);
        $id = (string) $vars['id'];
        $post = $this->app->posts()->byId($id, $config);
        if ($post === null) {
            throw new HttpException(404, 'Post not found');
        }
        $authorId = trim((string) $this->request()->post('author_id', ''));
        if ($this->app->users()->byId($authorId) === null) {
            throw new HttpException(400, 'Invalid author');
        }

        $clearCover = $this->toBool($this->request()->post('is_clear_cover', ''));
        $coverPath = $this->app->root() . '/public/uploads/covers/' . $id . '.jpg';
        if ($clearCover && is_file($coverPath)) {
            @unlink($coverPath);
        } elseif (!$clearCover) {
            $this->app->media()->saveCover($id, $this->request()->file('cover_file'));
        }

        $visibility = (string) $this->request()->post('visibility', 'public');
        $password = trim((string) $this->request()->post('password', ''));
        if ($visibility === 'password' && $password === '') {
            $password = (string) $post['Password'];
        }
        if ($visibility !== 'password') {
            $password = '';
        }

        $existingPublishedAt = (int) $post['PublishedAt'];
        $publishedAt = $this->app->postDates()->parsePublishedAtFromPost(
            $this->request()->post('published_at', null),
            0
        );
        if ($publishedAt <= 0) {
            $publishedAt = $this->app->postDates()->parsePublishedDatetimeAsSiteTimezone(
                $this->request()->post('published_datetime', null),
                $siteTimezone,
                0
            );
        }
        if ($publishedAt <= 0) {
            $publishedAt = $existingPublishedAt > 0 ? $existingPublishedAt : time();
        }

        $this->app->posts()->update([
            'id' => $id,
            'title' => trim((string) $this->request()->post('title', '')),
            'slug' => $this->slugify((string) $this->request()->post('slug', '')),
            'excerpt' => trim((string) $this->request()->post('excerpt', '')),
            'author_id' => $authorId,
            'password' => $password,
            'visibility' => $visibility,
            'content' => trim((string) $this->request()->post('content', '')),
            'published_at' => $publishedAt,
            'created_at' => (int) $post['CreatedAt'],
            'updated_at' => time(),
            'pinned_at' => $this->toBool($this->request()->post('is_pinned', '')) ? time() : 0,
            'tag_ids' => $this->createTags((string) $this->request()->post('tags', '')),
        ]);
        $this->setMessage('notice_post_updated');
        \Blogme\Core\redirect('/admin/post/' . $id, 303);
    }

    public function postTrash(array $vars, string $routePattern): void
    {
        $this->guard(true);
        $this->app->posts()->trash((string) $vars['id']);
        $this->setMessage('notice_post_trashed');
        \Blogme\Core\redirect('/admin/posts');
    }

    public function postUntrash(array $vars, string $routePattern): void
    {
        $this->guard(true);
        $this->app->posts()->untrash((string) $vars['id']);
        $this->setMessage('notice_post_untrashed');
        \Blogme\Core\redirect('/admin/posts');
    }

    public function trashClear(array $vars, string $routePattern): void
    {
        $this->guard(true);
        $this->app->posts()->clearTrash();
        $this->setMessage('notice_post_clear');
        \Blogme\Core\redirect('/admin/posts');
    }

    public function postDelete(array $vars, string $routePattern): void
    {
        $this->guard(true);
        $this->app->posts()->delete((string) $vars['id']);
        $this->setMessage('notice_post_deleted');
        \Blogme\Core\redirect('/admin/posts');
    }

    public function photosView(array $vars, string $routePattern): void
    {
        $this->guard();
        $page = $this->queryPage();
        $countPerPage = 100;
        $files = $this->app->media()->collectPhotoFiles();
        usort($files, static fn (array $a, array $b): int => strcmp($b['Filename'], $a['Filename']));
        $count = count($files);
        $offset = ($page - 1) * $countPerPage;
        $files = array_slice($files, $offset, $countPerPage);

        $groups = [];
        foreach ($files as $file) {
            $key = $file['Year'] . '-' . $file['Month'];
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'Year' => $file['Year'],
                    'Month' => $file['Month'],
                    'Filenames' => [],
                ];
            }
            $groups[$key]['Filenames'][] = $file['Filename'];
        }

        echo $this->app->view()->renderAdmin('admin_photos', [
            ...$this->baseData($routePattern),
            'Files' => array_values($groups),
            'Pagination' => $this->pagination($page, $count, $countPerPage),
        ]);
    }

    public function photoCreateApi(array $vars, string $routePattern): void
    {
        $this->guard(true);
        $file = $this->request()->file('photo_file');
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new HttpException(400, 'photo_file is required');
        }
        $path = $this->app->media()->savePhoto($file);
        header('Content-Type: application/json');
        echo json_encode(['path' => $path], JSON_UNESCAPED_SLASHES);
    }

    public function photoUpload(array $vars, string $routePattern): void
    {
        $this->guard(true);
        $files = $this->app->media()->normalizeFilesArray($this->request()->file('photo_file') ?? $this->request()->file('photo_file[]'));
        if ($files === []) {
            throw new HttpException(400, 'photo_file[] is required');
        }
        foreach ($files as $file) {
            $this->app->media()->savePhoto($file);
        }
        $this->setMessage('notice_photo_uploaded');
        \Blogme\Core\redirect('/admin/photos');
    }

    public function photoDelete(array $vars, string $routePattern): void
    {
        $this->guard(true);
        $path = trim((string) $this->request()->post('path', ''));
        if ($path === '') {
            throw new HttpException(400, 'path required');
        }
        $base = realpath($this->app->root() . '/public/uploads/images');
        $target = realpath($this->app->root() . '/public/uploads/images/' . ltrim(str_replace('\\', '/', $path), '/'));
        if ($base === false || $target === false || !str_starts_with($target, $base)) {
            throw new HttpException(403, 'Forbidden');
        }
        @unlink($target);
        $this->setMessage('notice_photo_deleted');
        $query = $this->request()->rawQueryString();
        \Blogme\Core\redirect('/admin/photos' . ($query !== '' ? ('?' . $query) : ''));
    }

    private function guard(bool $csrf = false): void
    {
        $this->requireConfig();
        $this->requireLogin();
        if ($csrf && $this->request()->method() === 'POST') {
            $this->checkCsrf();
        }
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value !== 0;
        }
        return in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes'], true);
    }
}
