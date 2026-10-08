<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Front\NewsController as FrontNews;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Deferred;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use App\Support\IndexNow;
use App\Support\Repo;
use App\Support\Sanitizer;
use App\Support\SeoFiles;
use App\Support\Slug;

final class NewsController extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->need('news.write');
    }

    private function canEdit(array $n): bool
    {
        if (Auth::can('news.edit_all')) {
            return true;
        }
        return (int) $n['author_id'] === (int) $this->user['id'] && $n['status'] !== 'published';
    }

    public function index(): void
    {
        $where = ['1=1'];
        $params = [];
        $status = Request::query('status');
        $q = mb_substr(Request::query('q'), 0, 100);
        $cat = Request::int('cat', 0, 'get');
        if (!Auth::can('news.edit_all')) {
            $where[] = 'n.author_id = ?';
            $params[] = $this->user['id'];
        }
        match ($status) {
            'published' => $where[] = "n.status = 'published' AND n.published_at <= NOW()",
            'scheduled' => $where[] = "n.status = 'published' AND n.published_at > NOW()",
            'draft' => $where[] = "n.status = 'draft'",
            'review' => $where[] = "n.status = 'review'",
            default => null,
        };
        if ($q !== '') {
            $where[] = 'n.title LIKE ?';
            $params[] = '%' . addcslashes($q, '\\%_') . '%';
        }
        if ($cat) {
            $where[] = 'n.category_id = ?';
            $params[] = $cat;
        }
        $w = implode(' AND ', $where);
        $total = (int) DB::val("SELECT COUNT(*) FROM news n WHERE $w", $params);
        $p = new Paginator($total, 25, Request::page());
        $rows = DB::all("SELECT n.id, n.title, n.slug, n.status, n.published_at, n.views, n.updated_at, n.seo_description, n.cover_media_id, c.name AS cat_name, u.display_name
            FROM news n LEFT JOIN categories c ON c.id = n.category_id LEFT JOIN users u ON u.id = n.author_id WHERE $w
            ORDER BY COALESCE(n.published_at, n.updated_at) DESC, n.id DESC LIMIT 25 OFFSET {$p->offset}", $params);
        $counts = DB::one("SELECT SUM(status='published' AND published_at <= NOW()) AS pub, SUM(status='published' AND published_at > NOW()) AS sch, SUM(status='draft') AS dr, SUM(status='review') AS rv, COUNT(*) AS al FROM news");
        $this->view('news-list', ['rows' => $rows, 'p' => $p, 'status' => $status, 'q' => $q, 'cat' => $cat, 'counts' => $counts, 'cats' => DB::all('SELECT id, name FROM categories ORDER BY sort_order, name')], 'Новости');
    }

    public function edit(array $a = []): void
    {
        $id = isset($a['id']) ? (int) $a['id'] : 0;
        $n = $id ? DB::one('SELECT * FROM news WHERE id = ?', [$id]) : null;
        if ($id && !$n) {
            $this->go('news', 'err', 'Материал не найден.');
        }
        if ($n && !$this->canEdit($n)) {
            $this->fail(403, 'Вы не можете редактировать этот материал.');
        }
        $errors = [];
        $gallery = $id ? DB::col('SELECT media_id FROM news_media WHERE news_id = ? ORDER BY sort_order', [$id]) : [];
        $tags = $id ? implode(', ', DB::col('SELECT t.name FROM news_tags nt JOIN tags t ON t.id = nt.tag_id WHERE nt.news_id = ? ORDER BY t.name', [$id])) : '';

        if (Request::isPost()) {
            [$data, $tagNames, $galleryIds] = $this->collect($n, $errors);
            if (!$errors) {
                $id = $this->save($n, $data, $tagNames, $galleryIds);
                $this->go('news/' . $id, 'ok', $data['status'] === 'published' ? 'Сохранено и опубликовано.' : 'Сохранено.');
            }
            $n = array_merge($n ?? [], $data);
            $tags = implode(', ', $tagNames);
            $gallery = $galleryIds;
        }
        $n ??= ['status' => 'draft', 'published_at' => null, 'is_featured' => 0, 'is_pinned' => 0, 'title' => '', 'slug' => '', 'excerpt' => '', 'body' => '', 'category_id' => null,
            'cover_media_id' => null, 'source_name' => '', 'source_url' => '', 'seo_title' => '', 'seo_description' => '', 'canonical_url' => '', 'noindex' => 0, 'views' => 0];
        $galleryMedia = $gallery ? DB::all('SELECT * FROM media WHERE id IN (' . DB::in($gallery) . ')', array_values($gallery)) : [];
        usort($galleryMedia, fn($x, $y) => array_search((int) $x['id'], array_map('intval', $gallery), true) <=> array_search((int) $y['id'], array_map('intval', $gallery), true));
        $this->view('news-edit', [
            'n' => $n, 'id' => $id, 'errors' => $errors, 'tags' => $tags, 'galleryMedia' => $galleryMedia, 'cats' => DB::all('SELECT id, name FROM categories ORDER BY sort_order, name'),
            'allTags' => DB::col('SELECT t.name FROM tags t LEFT JOIN news_tags nt ON nt.tag_id = t.id GROUP BY t.id, t.name ORDER BY COUNT(nt.news_id) DESC, t.name LIMIT 500'), 'canPublish' => Auth::can('news.publish'),
        ], $id ? 'Редактирование новости' : 'Новая новость');
    }

    /** @return array{0:array,1:string[],2:int[]} */
    private function collect(?array $old, array &$errors): array
    {
        $canPublish = Auth::can('news.publish');
        $title = Request::line('title', 255);
        $body = Sanitizer::html(Request::post('body'));
        $text = Sanitizer::plain($body);
        $status = Request::post('status');
        $status = in_array($status, ['draft', 'review', 'published'], true) ? $status : 'draft';
        if (!$canPublish && $status === 'published') {
            $status = 'review';
        }
        $publishedAt = $this->datetime('published_at');
        if ($status === 'published' && !$publishedAt) {
            $publishedAt = $old && $old['published_at'] ? $old['published_at'] : date('Y-m-d H:i:s');
        }
        if (mb_strlen($title) < 3) {
            $errors['title'] = 'Введите заголовок (не короче 3 символов).';
        }
        if ($status !== 'draft' && mb_strlen($text) < 20) {
            $errors['body'] = 'Для публикации нужен текст материала.';
        }
        $slug = Slug::make(Request::line('slug', 160) ?: $title, 100);
        $excerpt = Request::text('excerpt', 600);
        if ($excerpt === '' && $text !== '') {
            $excerpt = excerpt($body, 200); // автозаполнение лида
        }
        $tagNames = [];
        foreach (explode(',', Request::line('tags', 600)) as $t) {
            $t = trim(preg_replace('/^#/', '', trim($t)) ?? '');
            if (mb_strlen($t) >= 2 && mb_strlen($t) <= 50 && !in_array(mb_strtolower($t), array_map('mb_strtolower', $tagNames), true)) {
                $tagNames[] = $t;
            }
        }
        $gallery = array_values(array_unique(array_filter(array_map('intval', explode(',', Request::post('gallery'))))));
        $coverId = Request::int('cover_media_id') ?: null;
        if ($coverId && !DB::val('SELECT 1 FROM media WHERE id = ?', [$coverId])) {
            $coverId = null;
        }
        $catId = Request::int('category_id') ?: null;
        $data = [
            'title' => $title, 'slug' => $slug, 'excerpt' => $excerpt, 'body' => $body, 'body_text' => $text, 'category_id' => $catId, 'cover_media_id' => $coverId,
            'status' => $status, 'published_at' => $publishedAt,
            'is_featured' => $canPublish ? Request::bool('is_featured') : (int) ($old['is_featured'] ?? 0),
            'is_pinned' => $canPublish ? Request::bool('is_pinned') : (int) ($old['is_pinned'] ?? 0),
            'seo_title' => Request::line('seo_title', 190) ?: null, 'seo_description' => mb_substr(Request::line('seo_description', 320), 0, 320) ?: null,
            'canonical_url' => Security::safeUrl(Request::line('canonical_url', 500), false) ?: null, 'noindex' => Request::bool('noindex'),
            'source_name' => Request::line('source_name', 190) ?: null, 'source_url' => Security::safeUrl(Request::line('source_url', 500), false) ?: null,
        ];
        return [$data, $tagNames, $gallery];
    }

    private function save(?array $old, array $data, array $tagNames, array $galleryIds): int
    {
        $now = date('Y-m-d H:i:s');
        $data['slug'] = Slug::unique('news', $data['slug'], $old ? (int) $old['id'] : null);
        $wasLive = $old && $old['status'] === 'published' && strtotime((string) $old['published_at']) <= time();
        $isLive = $data['status'] === 'published' && strtotime((string) $data['published_at']) <= time();
        $data['updated_at'] = $now;
        $id = DB::tx(function () use ($old, $data, $tagNames, $galleryIds, $now, $wasLive) {
            if ($old) {
                if ($wasLive && $old['slug'] !== $data['slug']) {
                    DB::exec('DELETE FROM redirects WHERE from_path = ?', ['/news/' . $data['slug']]);
                    DB::exec('INSERT INTO redirects (from_path, to_url, code, note, created_at) VALUES (?,?,301,?,NOW()) ON DUPLICATE KEY UPDATE to_url = VALUES(to_url), code = 301',
                        ['/news/' . $old['slug'], '/news/' . $data['slug'], 'Авто: смена адреса новости']);
                }
                if ($data['status'] === 'published' && ($old['status'] !== 'published' || $old['slug'] !== $data['slug'] || $old['published_at'] !== $data['published_at'])) {
                    $data['indexnow_at'] = null;
                }
                DB::update('news', $data, 'id = ?', [$old['id']]);
                $id = (int) $old['id'];
            } else {
                $id = DB::insert('news', $data + ['author_id' => $this->user['id'], 'created_at' => $now]);
            }
            DB::delete('news_media', 'news_id = ?', [$id]);
            foreach ($galleryIds as $i => $mid) {
                if (DB::val('SELECT 1 FROM media WHERE id = ?', [$mid])) {
                    DB::exec('INSERT IGNORE INTO news_media (news_id, media_id, sort_order) VALUES (?,?,?)', [$id, $mid, $i]);
                }
            }
            DB::delete('news_tags', 'news_id = ?', [$id]);
            foreach (array_slice($tagNames, 0, 10) as $name) {
                $tid = DB::val('SELECT id FROM tags WHERE name = ?', [$name]);
                if (!$tid) {
                    $tid = DB::insert('tags', ['name' => $name, 'slug' => Slug::unique('tags', Slug::make($name, 80))]);
                }
                DB::exec('INSERT IGNORE INTO news_tags (news_id, tag_id) VALUES (?,?)', [$id, $tid]);
            }
            return $id;
        });
        Audit::log($old ? 'update.news' : 'create.news', 'news', $id, $data['title']);
        \App\Core\Cache::flush();
        if ($isLive || $wasLive) {
            SeoFiles::touch();
            if ($isLive) {
                $paths = ['/news/' . $data['slug'], '/'];
                if ($old && $old['slug'] !== $data['slug']) {
                    $paths[] = '/news/' . $old['slug'];
                }
                Deferred::add(function () use ($id, $paths) {
                    IndexNow::submit($paths);
                    DB::exec('UPDATE news SET indexnow_at = NOW() WHERE id = ?', [$id]);
                });
            }
        } else {
            \App\Core\Settings::touch();
        }
        return $id;
    }

    public function delete(array $a): void
    {
        $this->need('news.publish');
        $id = (int) $a['id'];
        $n = DB::one('SELECT * FROM news WHERE id = ?', [$id]);
        if ($n) {
            $this->remove($n);
            $this->go('news', 'ok', 'Материал удалён. Для его адреса включён ответ 410 (удалено), поисковики уберут страницу из индекса.');
        }
        $this->go('news', 'err', 'Материал не найден.');
    }

    private function remove(array $n): void
    {
        $wasLive = $n['status'] === 'published' && strtotime((string) $n['published_at']) <= time();
        DB::delete('news', 'id = ?', [$n['id']]);
        Audit::log('delete.news', 'news', $n['id'], $n['title']);
        if ($wasLive) {
            DB::exec('INSERT INTO redirects (from_path, to_url, code, note, created_at) VALUES (?, \'\', 410, ?, NOW()) ON DUPLICATE KEY UPDATE code = 410, to_url = \'\'', ['/news/' . $n['slug'], 'Авто: материал удалён']);
            Deferred::add(fn() => IndexNow::submit(['/news/' . $n['slug'], '/']));
        }
        SeoFiles::touch();
        \App\Core\Cache::flush();
    }

    public function bulk(): void
    {
        $this->need('news.publish');
        $ids = array_values(array_filter(array_map('intval', Request::postArray('ids'))));
        $action = Request::post('action');
        if (!$ids || !in_array($action, ['publish', 'draft', 'delete'], true)) {
            $this->go('news', 'warn', 'Выберите материалы и действие.');
        }
        $rows = DB::all('SELECT * FROM news WHERE id IN (' . DB::in($ids) . ')', $ids);
        $paths = [];
        foreach ($rows as $n) {
            if ($action === 'delete') {
                $this->remove($n);
            } elseif ($action === 'publish') {
                DB::update('news', ['status' => 'published', 'published_at' => $n['published_at'] ?: date('Y-m-d H:i:s'), 'indexnow_at' => null, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$n['id']]);
                $paths[] = '/news/' . $n['slug'];
            } else {
                DB::update('news', ['status' => 'draft', 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$n['id']]);
            }
        }
        Audit::log('bulk.news.' . $action, 'news', implode(',', $ids));
        SeoFiles::touch();
        if ($paths) {
            Deferred::add(fn() => IndexNow::submit([...$paths, '/']));
        }
        $this->go('news', 'ok', 'Готово: обработано ' . count($rows) . '.');
    }

    public function preview(array $a): void
    {
        $n = DB::one('SELECT n.*, c.name AS cat_name, c.slug AS cat_slug, m.path AS m_path, m.ext AS m_ext, m.width AS m_width, m.height AS m_height, m.widths AS m_widths, m.alt AS m_alt, m.caption AS m_caption, m.credit AS m_credit
            FROM news n LEFT JOIN categories c ON c.id = n.category_id LEFT JOIN media m ON m.id = n.cover_media_id WHERE n.id = ?', [(int) $a['id']]);
        if (!$n || !$this->canEdit($n) && !Auth::can('news.edit_all')) {
            $this->fail(404, 'Материал не найден.');
        }
        (new FrontNews())->renderArticle($n, true);
    }
}
