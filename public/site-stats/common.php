<?php
declare(strict_types=1);
// MIT. Private files live beside public_html, never inside it.
function stats_headers(): void {
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Frame-Options: DENY');
}
function fail(int $status, string $text): never {
    http_response_code($status); header('Content-Type: text/plain; charset=utf-8'); exit($text);
}
function esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function private_dir(): string {
    $dir = dirname(__DIR__, 2) . '/site-stats-private';
    $root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: realpath(dirname(__DIR__));
    $real = realpath($dir);
    if ($real !== false && $root !== false && ($real === $root || str_starts_with($real, $root . DIRECTORY_SEPARATOR))) {
        fail(503, 'Приватная папка оказалась внутри сайта. Перенесите установку по инструкции.');
    }
    return $dir;
}
function require_https(): void {
    // Do not trust arbitrary X-Forwarded-Proto headers. For a trusted proxy set HTTPS in its server configuration.
    if (($_SERVER['HTTPS'] ?? '') !== 'on' && ($_SERVER['HTTPS'] ?? '') !== '1') fail(403, 'Нужен HTTPS. Откройте адрес с https:// или настройте HTTPS на сервере.');
}
function settings(): array {
    $file = private_dir() . '/config.php';
    if (!is_file($file)) fail(503, 'Статистика ещё не настроена. Откройте install.php по инструкции.');
    $cfg = require $file;
    if (!is_array($cfg) || empty($cfg['password_hash']) || empty($cfg['origin']) || empty($cfg['pages'])) fail(503, 'Проверьте приватный config.php.');
    return $cfg;
}
function lock_store() {
    $dir = private_dir();
    if (!is_dir($dir) || !is_writable($dir)) fail(503, 'Приватная папка недоступна для записи.');
    $lock = fopen($dir . '/store.lock', 'c');
    if (!$lock) fail(503, 'Не удалось открыть хранилище.');
    chmod($dir . '/store.lock', 0600);
    // Bound waiting, so busy shared hosting does not hold every PHP worker indefinitely.
    $until = microtime(true) + 2;
    do { if (flock($lock, LOCK_EX | LOCK_NB)) return $lock; usleep(10000); } while (microtime(true) < $until);
    fclose($lock); fail(503, 'Хранилище занято. Повторите позже.');
}
function unlock_store($lock): void { flock($lock, LOCK_UN); fclose($lock); }
function read_store(): array {
    $path = private_dir() . '/counts.json';
    if (!is_file($path)) return ['days'=>[], 'sessions'=>[], 'seen'=>[]];
    if (filesize($path) > 16 * 1024 * 1024) fail(503, 'Хранилище превысило лимит. Сохраните копию и проверьте установку.');
    $raw = file_get_contents($path);
    $data = $raw === false ? null : json_decode($raw, true);
    if (!is_array($data) || !is_array($data['days'] ?? null) || !is_array($data['sessions'] ?? null) || !is_array($data['seen'] ?? null)) fail(503, 'Хранилище повреждено. Оно не перезаписано; восстановите резервную копию.');
    return $data;
}
function write_store(array $data): void {
    $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (strlen($encoded) > 16 * 1024 * 1024) fail(503, 'Достигнут лимит хранилища.');
    $tmp = tempnam(private_dir(), 'write-');
    if ($tmp === false) fail(503, 'Не удалось записать статистику.');
    chmod($tmp, 0600);
    if (file_put_contents($tmp, $encoded) !== strlen($encoded) || !rename($tmp, private_dir() . '/counts.json')) {
        @unlink($tmp); fail(503, 'Запись не завершена. Предыдущие данные сохранены.');
    }
}
function prune(array &$data, DateTimeZone $tz): void {
    $now = time();
    $cutoff = (new DateTimeImmutable('today', $tz))->modify('-89 days')->format('Y-m-d');
    foreach ($data['days'] as $day=>$_) if ($day < $cutoff) unset($data['days'][$day]);
    foreach ($data['sessions'] as $id=>$session) if (($session['last'] ?? 0) <= $now - 1800) unset($data['sessions'][$id]);
    foreach ($data['seen'] as $id=>$at) if ($at <= $now - 300) unset($data['seen'][$id]);
}
function authenticated(array $cfg): void {
    require_https();
    // Some CGI hosts pass only the Authorization header.
    $user = $_SERVER['PHP_AUTH_USER'] ?? ''; $pass = $_SERVER['PHP_AUTH_PW'] ?? '';
    $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!$user && str_starts_with($authorization, 'Basic ')) {
        $decoded = base64_decode(substr($authorization, 6), true);
        if (is_string($decoded) && str_contains($decoded, ':')) [$user, $pass] = explode(':', $decoded, 2);
    }
    if ($user !== 'admin' || !password_verify($pass, $cfg['password_hash'])) {
        header('WWW-Authenticate: Basic realm="Site statistics", charset="UTF-8"');
        fail(401, 'Отчёт закрыт. Введите логин admin и пароль, заданный при установке.');
    }
}
function counters(): array { return ['views','sessions','telegram','whatsapp','phone','email','lead','cta']; }
function source_names(): array { return ['direct'=>'Прямые / неизвестно','referral'=>'Другие сайты','search'=>'Поиск','other_campaign'=>'Другие UTM','telegram'=>'Telegram','youtube'=>'YouTube','vk'=>'ВКонтакте','instagram'=>'Instagram','threads'=>'Threads','yandex'=>'Яндекс','google'=>'Google']; }
function event_names(): array { return ['telegram'=>'Telegram','whatsapp'=>'WhatsApp','phone'=>'Телефон','email'=>'Почта','lead'=>'Успешная заявка','cta'=>'Целевая кнопка']; }
