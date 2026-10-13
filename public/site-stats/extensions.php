<?php
declare(strict_types=1);
/** Stable server-side contract. Credentials and caches belong outside the public document root. */
interface SiteStatsIntegration {
    public function id(): string;
    public function title(): string;
    /** Return ['note'=>string, 'metrics'=>[['label'=>string, 'value'=>int|float, 'unit'=>'RUB'|'count'|'percent']]]. */
    public function report(DateTimeImmutable $from, DateTimeImmutable $to): array;
}
final class JsonBudgetIntegration implements SiteStatsIntegration {
    public function __construct(private string $file) {}
    public function id(): string { return 'expenses'; }
    public function title(): string { return 'Расходы'; }
    public function report(DateTimeImmutable $from, DateTimeImmutable $to): array {
        if (!is_file($this->file) || filesize($this->file) > 1024*1024) throw new RuntimeException('Budget file missing or too large');
        $items=json_decode(file_get_contents($this->file), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($items) || !array_is_list($items)) throw new RuntimeException('Invalid budget file');
        $spend=0.0; $budget=0.0;
        foreach ($items as $row) {
            if (!is_array($row) || !is_string($row['date'] ?? null) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $row['date']) || !checkdate((int)substr($row['date'],5,2),(int)substr($row['date'],8,2),(int)substr($row['date'],0,4))) throw new RuntimeException('Invalid date');
            foreach (['spend','budget'] as $key) if ((!is_int($row[$key] ?? null) && !is_float($row[$key] ?? null)) || !is_finite((float)$row[$key]) || $row[$key] < 0 || $row[$key] > 1e9) throw new RuntimeException('Invalid amount');
            if ($row['date'] < $from->format('Y-m-d') || $row['date'] > $to->format('Y-m-d')) continue;
            $spend+=$row['spend']; $budget+=$row['budget'];
        }
        return ['note'=>'Сумма дневных планов и расходов из вашего локального файла. Данные не загружаются из рекламного кабинета автоматически.', 'metrics'=>[
            ['label'=>'План на период','value'=>$budget,'unit'=>'RUB'],
            ['label'=>'Потрачено','value'=>$spend,'unit'=>'RUB'],
            ['label'=>'Остаток плана','value'=>$budget-$spend,'unit'=>'RUB'],
        ]];
    }
}
function integrations(): array {
    $path=private_dir().'/integrations.php';
    if (!is_file($path)) return [];
    // This is owner-controlled PHP, never uploadable through the dashboard.
    try { $list=require $path; } catch (Throwable $e) { error_log('SiteStats: integration registry failed'); return []; }
    if (!is_array($list)) return [];
    $result=[];
    foreach (array_slice($list,0,8) as $provider) if ($provider instanceof SiteStatsIntegration && preg_match('/^[a-z][a-z0-9_-]{1,30}$/D', $provider->id()) && $provider->id() !== 'site' && !isset($result[$provider->id()])) $result[$provider->id()]=$provider;
    return $result;
}
function integration_report(SiteStatsIntegration $provider, DateTimeImmutable $from, DateTimeImmutable $to): ?array {
    try {
        $report=$provider->report($from, $to);
        if (!is_string($report['note'] ?? null) || strlen($report['note']) > 1500 || !is_array($report['metrics'] ?? null) || count($report['metrics']) > 8) throw new RuntimeException('Invalid report');
        foreach ($report['metrics'] as $metric) if (!is_string($metric['label'] ?? null) || strlen($metric['label']) > 120 || (!is_int($metric['value'] ?? null) && !is_float($metric['value'] ?? null)) || !is_finite((float)$metric['value']) || !in_array($metric['unit'] ?? null,['RUB','count','percent'],true)) throw new RuntimeException('Invalid metric');
        return $report;
    } catch (Throwable $e) {
        // Do not leak tokens, API URLs, paths or raw provider errors to the browser.
        error_log('SiteStats: integration report failed'); return null;
    }
}
