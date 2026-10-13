<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
require __DIR__ . '/extensions.php';
stats_headers(); $cfg = settings(); authenticated($cfg);
header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$lock = lock_store(); $data = read_store(); $tz = new DateTimeZone($cfg['timezone']); prune($data, $tz); write_store($data); unlock_store($lock);
$period = (int)($_GET['days'] ?? 7); if (!in_array($period, [7,30,90], true)) $period = 7;
$today = new DateTimeImmutable('today', $tz); $from = $today->modify('-' . ($period - 1) . ' days');
$providers=integrations();
$dashboard=is_string($_GET['dashboard'] ?? null) ? $_GET['dashboard'] : 'site';
if ($dashboard !== 'site') {
    if (!isset($providers[$dashboard])) fail(404, 'Дашборд не подключён.');
    $provider=$providers[$dashboard]; require __DIR__.'/extension-view.php'; exit;
}
$series = []; $totals = array_fill_keys(counters(), 0); $sources = []; $pages = []; $devices = [];
for ($date=$from; $date <= $today; $date=$date->modify('+1 day')) {
    $key = $date->format('Y-m-d'); $day = $data['days'][$key] ?? []; $series[$key] = (int)($day['totals']['views'] ?? 0);
    foreach ($totals as $name=>$_) $totals[$name] += (int)($day['totals'][$name] ?? 0);
    foreach ($day['sources'] ?? [] as $source=>$counts) foreach (counters() as $name) $sources[$source][$name] = ($sources[$source][$name] ?? 0) + (int)($counts[$name] ?? 0);
    foreach ($day['pages'] ?? [] as $id=>$count) $pages[$id] = ($pages[$id] ?? 0) + (int)$count;
    foreach ($day['devices'] ?? [] as $id=>$count) $devices[$id] = ($devices[$id] ?? 0) + (int)$count;
}
uasort($sources, fn($a,$b)=>($b['sessions'] ?? 0) <=> ($a['sessions'] ?? 0)); arsort($pages); arsort($devices);
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="site-stats-' . $today->format('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Дата','Источник','Сессии','Просмотры','Telegram','WhatsApp','Телефон','Почта','Успешная заявка','Целевая кнопка'], ';', '"', '');
    foreach ($series as $date=>$_) foreach ($data['days'][$date]['sources'] ?? [] as $source=>$counts) {
        $row = [$date, source_names()[$source] ?? 'Другой'];
        foreach (['sessions','views','telegram','whatsapp','phone','email','lead','cta'] as $key) $row[] = (int)($counts[$key] ?? 0);
        fputcsv($out, $row, ';', '"', '');
    }
    fclose($out); exit;
}
$values = array_values($series); $max = max(1, ...$values); $points = []; $n = count($values);
foreach ($values as $i=>$value) $points[] = round(16 + $i / max(1,$n-1)*968, 1) . ',' . round(176 - $value/$max*140, 1);
$actions = array_sum(array_intersect_key($totals, event_names()));
$base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/site-stats/index.php'), '/');
$snippet = '<script defer src="' . $base . '/tracker.php"></script>';
require __DIR__ . '/view.php';
