<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
stats_headers();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail(405, 'Только POST.');
require_https();
$cfg = settings();
if (($_SERVER['HTTP_ORIGIN'] ?? '') !== $cfg['origin']) fail(403, 'Источник не разрешён.');
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) fail(415, 'Нужен JSON.');
$raw = file_get_contents('php://input', false, null, 0, 1025);
if ($raw === false || strlen($raw) > 1024) fail(413, 'Слишком большой запрос.');
$item = json_decode($raw, true);
if (!is_array($item) || array_diff(array_keys($item), ['id','session','event','page','source','device'])) fail(400, 'Неизвестные поля.');
foreach (['id','session','event','page','source','device'] as $field) if (!is_string($item[$field] ?? null)) fail(400, 'Некорректное событие.');
foreach (['id','session'] as $field) if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $item[$field])) fail(400, 'Некорректный идентификатор.');
if (!in_array($item['event'], array_merge(['pageview'], array_keys(event_names())), true) || !isset($cfg['pages'][$item['page']]) || !isset(source_names()[$item['source']]) || !in_array($item['device'], ['mobile','tablet','desktop'], true)) fail(400, 'Событие, страница или категория не разрешены.');
$lock = lock_store();
$data = read_store();
$tz = new DateTimeZone($cfg['timezone']); prune($data, $tz);
$now = time(); $minute = intdiv($now, 60);
if (isset($data['seen'][$item['id']])) { write_store($data); unlock_store($lock); http_response_code(204); exit; }
$sid = $item['session']; $fresh = !isset($data['sessions'][$sid]);
// Public collection endpoints cannot authenticate a real visitor. This bounds memory and work, not fraud.
if (($fresh && count($data['sessions']) >= 1000) || count($data['seen']) >= 5000) fail(429, 'Лимит событий.');
$session = $data['sessions'][$sid] ?? ['last'=>$now,'minute'=>$minute,'count'=>0,'source'=>$item['source'],'device'=>$item['device']];
if ($session['minute'] !== $minute) { $session['minute'] = $minute; $session['count'] = 0; }
if ($session['count'] >= 60) fail(429, 'Лимит событий сессии.');
$session['count']++; $session['last'] = $now; $data['sessions'][$sid] = $session; $data['seen'][$item['id']] = $now;
$date = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
$day = $data['days'][$date] ?? ['totals'=>[], 'sources'=>[], 'pages'=>[], 'devices'=>[]];
$source = $session['source']; $event = $item['event'] === 'pageview' ? 'views' : $item['event'];
$day['totals'][$event] = ($day['totals'][$event] ?? 0) + 1;
$day['sources'][$source][$event] = ($day['sources'][$source][$event] ?? 0) + 1;
if ($fresh) {
    $day['totals']['sessions'] = ($day['totals']['sessions'] ?? 0) + 1;
    $day['sources'][$source]['sessions'] = ($day['sources'][$source]['sessions'] ?? 0) + 1;
    $day['devices'][$session['device']] = ($day['devices'][$session['device']] ?? 0) + 1;
}
if ($event === 'views') $day['pages'][$item['page']] = ($day['pages'][$item['page']] ?? 0) + 1;
$data['days'][$date] = $day; write_store($data); unlock_store($lock);
http_response_code(204);
