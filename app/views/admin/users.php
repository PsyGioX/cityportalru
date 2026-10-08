<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
/** @var array $rows */ ?>
<div class="toolbar"><a class="btn btn--primary" href="<?= e(admin_url('users/new')) ?>"><?= icon('plus') ?> Добавить пользователя</a></div>
<div class="table-wrap"><table class="table table--hover"><thead><tr><th>Пользователь</th><th>Роль</th><th>2FA</th><th>Последний вход</th><th>Материалов</th><th>Статус</th></tr></thead><tbody>
<?php foreach ($rows as $u): ?><tr><td><a class="strong" href="<?= e(admin_url('users/' . $u['id'])) ?>"><?= e($u['display_name'] ?: $u['username']) ?></a><div class="small muted"><?= e($u['username']) ?> · <?= e($u['email']) ?></div></td>
  <td><?= e(\App\Core\Auth::ROLES[$u['role']] ?? $u['role']) ?></td><td><?= $u['totp_enabled'] ? '<span class="pill pill--ok">включена</span>' : '<span class="pill pill--warn">нет</span>' ?></td>
  <td class="small"><?= $u['last_login_at'] ? e(pub_date($u['last_login_at'])) . '<br><span class="muted">' . e($u['last_login_ip']) . '</span>' : '—' ?></td><td><?= (int) $u['news_cnt'] ?></td>
  <td><?= $u['is_active'] ? '<span class="pill pill--ok">активен</span>' : '<span class="pill pill--muted">отключён</span>' ?></td></tr><?php endforeach ?>
</tbody></table></div>
<p class="hint"><b>Роли.</b> Администратор — всё. Редактор — публикация, афиша, страницы, модерация, SEO. Автор — пишет свои материалы и отправляет их редактору на проверку.</p>
