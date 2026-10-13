<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
stats_headers(); require_https();
header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$dir = private_dir();
if (is_file($dir . '/config.php')) fail(409, 'Установка уже выполнена. Откройте index.php. Удалите install.php и install-key.php с сервера.');
$host = $_SERVER['HTTP_HOST'] ?? '';
if (!preg_match('/^[a-z0-9.-]+(?::[0-9]{1,5})?$/iD', $host)) fail(400, 'Некорректный адрес сайта.');
$origin = 'https://' . strtolower($host);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_SERVER['HTTP_ORIGIN'] ?? '') !== $origin) fail(403, 'Откройте установку на том же домене.');
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 10000) fail(413, 'Слишком большой запрос.');
    $key = is_file(__DIR__ . '/install-key.php') ? require __DIR__ . '/install-key.php' : '';
    if (!is_string($key) || strlen($key) < 32 || str_contains($key, 'REPLACE_')) fail(503, 'Сначала задайте свой случайный ключ в install-key.php.');
    if (!is_string($_POST['key'] ?? null) || !hash_equals($key, $_POST['key'])) fail(403, 'Неверный ключ установки.');
    $password = $_POST['password'] ?? '';
    if (!is_string($_POST['title'] ?? null)) fail(400, 'Проверьте название сайта.');
    $title = trim($_POST['title']);
    if (!is_string($password) || strlen($password) < 14 || strlen($password) > 72) fail(400, 'Пароль должен содержать от 14 до 72 байт. Используйте случайный пароль из менеджера паролей.');
    if (!$title || strlen($title) > 120) fail(400, 'Название должно быть не длиннее 120 байт.');
    $timezone = $_POST['timezone'] ?? 'Europe/Moscow';
    if (!is_string($timezone) || !in_array($timezone, DateTimeZone::listIdentifiers(), true)) fail(400, 'Неизвестный часовой пояс.');
    $input = $_POST['pages'] ?? '';
    if (!is_string($input) || strlen($input) > 5000) fail(400, 'Проверьте список страниц.');
    $pages = []; $paths = [];
    foreach (preg_split('/\r?\n/', trim($input)) as $line) {
        if (!trim($line)) continue;
        [$path, $label] = array_pad(explode('|', $line, 2), 2, '');
        $path = rtrim(trim($path), '/') ?: '/'; $label = trim($label);
        if (!preg_match('~^/(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_-]*$~D', $path) || strlen($path) > 120 || !$label || strlen($label) > 120 || isset($paths[$path])) fail(400, 'Строка страницы: /path | Название. Без параметров, личных кабинетов, дублей и персональных данных. Для кириллических путей нужна адаптация.');
        $paths[$path] = true; $id = 'p' . count($pages); $pages[$id] = ['path'=>$path,'label'=>$label];
    }
    if (!$pages || count($pages) > 20) fail(400, 'Добавьте от 1 до 20 публичных страниц.');
    if (!is_dir($dir) && !mkdir($dir, 0700, true)) fail(503, 'Не удалось создать приватную папку. Создайте её рядом с public_html и дайте PHP право записи.');
    chmod($dir, 0700); private_dir();
    $lock = lock_store();
    if (is_file($dir . '/config.php')) fail(409, 'Другой запрос уже выполнил установку.');
    $cfg = ['title'=>$title,'origin'=>$origin,'timezone'=>$timezone,'password_hash'=>password_hash($password, PASSWORD_DEFAULT),'pages'=>$pages];
    $output = "<?php\n// Keep outside the public document root. MIT.\nreturn " . var_export($cfg, true) . ";\n";
    $tmp = tempnam($dir, 'setup-');
    if ($tmp === false) fail(503, 'Не удалось создать настройки.');
    chmod($tmp, 0600);
    if (file_put_contents($tmp, $output) !== strlen($output) || !rename($tmp, $dir . '/config.php')) { @unlink($tmp); fail(503, 'Не удалось сохранить настройки.'); }
    unlock_store($lock);
    header('Location: index.php', true, 303); exit;
}
?><!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Установка · Своя статистика</title><link rel="stylesheet" href="ui.css"></head>
<body class="setup"><main class="setup-card"><a class="wordmark" href="../../start.html">своя<span>статистика</span></a><p class="eyebrow">Установка / 01</p><h1>Всё остаётся<br>на вашем сервере.</h1><p class="muted">Один сайт, свой счётчик, закрытый отчёт. Без аккаунта в стороннем сервисе.</p>
<form method="post" autocomplete="off"><label>Ключ установки<input name="key" type="password" minlength="32" maxlength="256" required autocomplete="off"><small>Тот, который вы записали в install-key.php.</small></label><label>Название сайта<input name="title" value="Мой сайт" maxlength="60" required></label><label>Пароль отчёта<input name="password" type="password" minlength="14" maxlength="72" required autocomplete="new-password"><small>От 14 символов. Логин будет admin.</small></label><label>Публичные страницы<textarea name="pages" rows="5" required>/ | Главная
/services | Услуги
/contacts | Контакты</textarea><small>Одна строка: путь | название. Удалите примеры и впишите реальные страницы. До 20 страниц.</small></label><label>Часовой пояс<input name="timezone" value="Europe/Moscow" required></label><p class="notice">Установка для <?=esc($origin)?>. Используйте этот же вариант домена на сайте: с www или без него.</p><button class="button" type="submit">Создать закрытый отчёт <span aria-hidden="true">↗</span></button></form><p class="footnote">После установки удалите install.php и install-key.php. Файлы данных создаются вне публичной папки.</p></main></body></html>
