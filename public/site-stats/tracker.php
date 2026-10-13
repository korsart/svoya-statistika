<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
stats_headers(); require_https(); $cfg = settings();
header('Content-Type: application/javascript; charset=utf-8');
$public = ['origin'=>$cfg['origin'], 'pages'=>array_map(fn($page)=>$page['path'], $cfg['pages'])];
echo 'window.__siteStatsConfig = ' . json_encode($public, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) . ";\n";
readfile(__DIR__ . '/tracker.js');
