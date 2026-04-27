<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

class TestPaginationPerformance extends Command
{
    protected $signature = 'app:test-pagination 
                            {--iterations=3 : Number of test iterations}
                            {--limit=15 : Items per page}';

    protected $description = 'Tests offset vs keyset pagination';

    public function handle()
    {
        $this->info("Тестирование производительности пагинации на таблице 'orders'...");

        $limit = $this->option('limit');
        $iterations = $this->option('iterations');
        $pagesToTest = [1, 10, 50, 100, 500, 1000, 2000];

        $results = [];

        foreach ($pagesToTest as $page) {
            $this->newLine();
            $this->line("=== Testing PAGE {$page} ===");

            // Test OFFSET
            $offsetTime = $this->measureOffsetPerformance($page, $limit, $iterations);

            // Test KEYSET
            $keysetTime = $this->measureKeysetPerformance($page, $limit, $iterations);

            $results[$page] = [
                'offset_ms' => $offsetTime,
                'keyset_ms' => $keysetTime,
                'ratio' => $keysetTime > 0 ? round($offsetTime / $keysetTime, 2) : 'N/A',
            ];

            $this->info("✓ OFFSET: {$offsetTime} ms");
            $this->info("✓ KEYSET: {$keysetTime} ms");
            if (is_numeric($results[$page]['ratio'])) {
                $this->warn("→ Keyset-пагинация в " . $results[$page]['ratio'] . "x раз быстрее");
            } else {
                $this->warn("→ Производительность Keyset не может быть сравнена.");
            }
        }
        
        $this->newLine();
        $this->table(['Страница', 'OFFSET (мс)', 'KEYSET (мс)', 'Превосходство в скорости'], 
            collect($results)->map(fn($r, $page) => [
                $page,
                $r['offset_ms'],
                $r['keyset_ms'],
                is_numeric($r['ratio']) ? "{$r['ratio']}x" : $r['ratio']
            ])->toArray()
        );

        return 0;
    }

    private function getPdoConnection(): PDO
    {
        return DB::connection()->getPdo();
    }

    private function measureOffsetPerformance(int $page, int $limit, int $iterations): float
    {
        $offset = ($page - 1) * $limit;
        $pdo = $this->getPdoConnection();

        $times = [];
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);

            $sql = "SELECT * FROM orders ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $end = microtime(true);
            $times[] = ($end - $start) * 1000;

            $duration = round(($end - $start) * 1000, 2);
            $this->line("Итерация OFFSET: {$duration} мс, получено: " . count($orders) . " строк");
        }

        return round(array_sum($times) / count($times), 2);
    }

    private function measureKeysetPerformance(int $page, int $limit, int $iterations): float
    {
        $pdo = $this->getPdoConnection();
        $cursor = null;

        if ($page > 1) {
            $offsetToFindCursor = ($page - 1) * $limit - 1;

            $cursorSql = "SELECT created_at, id FROM orders ORDER BY created_at DESC, id DESC LIMIT 1 OFFSET :offset";
            $stmt = $pdo->prepare($cursorSql);
            $stmt->bindValue(':offset', $offsetToFindCursor, PDO::PARAM_INT);
            $stmt->execute();
            $cursorOrder = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$cursorOrder) {
                return 0; 
            }
            $cursor = $cursorOrder;
        }

        $times = [];
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);

            $bindings = [':limit' => $limit];
            $whereClause = '1=1'; 

            if ($cursor !== null) {
                $whereClause = "(created_at, id) < (:createdAt, :id)";
                $bindings[':createdAt'] = $cursor['created_at'];
                $bindings[':id'] = $cursor['id'];
            }

            $sql = "SELECT * FROM orders WHERE {$whereClause} ORDER BY created_at DESC, id DESC LIMIT :limit";
            $stmt = $pdo->prepare($sql);
            foreach ($bindings as $key => &$val) {
                $stmt->bindParam($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $end = microtime(true);
            $times[] = ($end - $start) * 1000;

            $duration = round(($end - $start) * 1000, 2);
            $this->line("Итерация KEYSET: {$duration} мс, получено: " . count($orders) . " строк");
        }

        return round(array_sum($times) / count($times), 2);
    }
}