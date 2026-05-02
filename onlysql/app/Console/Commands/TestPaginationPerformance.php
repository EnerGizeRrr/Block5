<?php
namespace App\Console\Commands;

use PDO;

class TestPaginationPerformance
{
    private PDO $pdo;
    private array $options;

    public function __construct(array $options = [])
    {
        $dbHost = '127.0.1.31';
        $dbPort = '3306';
        $dbName = 'laravel';
        $dbUser = 'root';
        $dbPass = '';

        $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
        $this->pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->options = $options;
    }

    public function handle(): int
    {
        $this->line("Тестирование производительности пагинации на таблице 'orders'...");

        $limit = (int)($this->options['limit'] ?? 15);
        $iterations = (int)($this->options['iterations'] ?? 3);
        $pagesToTest = [1, 10, 50, 100, 500, 1000, 2000];

        $results = [];

        foreach ($pagesToTest as $page) {
            $this->line("\n=== Testing PAGE {$page} ===");

            // Test OFFSET
            $offsetTime = $this->measureOffsetPerformance($page, $limit, $iterations);

            // Test KEYSET
            $keysetTime = $this->measureKeysetPerformance($page, $limit, $iterations);

            $ratio = $keysetTime > 0 ? round($offsetTime / $keysetTime, 2) : 'N/A';
            $results[] = [
                'page' => $page,
                'offset_ms' => $offsetTime,
                'keyset_ms' => $keysetTime,
                'ratio' => is_numeric($ratio) ? "{$ratio}x" : $ratio,
            ];

            $this->line("✓ OFFSET: {$offsetTime} ms");
            $this->line("✓ KEYSET: {$keysetTime} ms");
            if (is_numeric($ratio)) {
                $this->line("→ Keyset-пагинация в {$ratio}x раз быстрее");
            } else {
                $this->line("→ Производительность Keyset не может быть сравнена.");
            }
        }

        $this->line("\n");
        $this->printTable(
            ['Страница', 'OFFSET (мс)', 'KEYSET (мс)', 'Превосходство в скорости'],
            $results
        );

        return 0;
    }

    private function measureOffsetPerformance(int $page, int $limit, int $iterations): float
    {
        $offset = ($page - 1) * $limit;

        $times = [];
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);

            $sql = "SELECT * FROM orders ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $orders = $stmt->fetchAll();

            $end = microtime(true);
            $times[] = ($end - $start) * 1000;

            $duration = round(($end - $start) * 1000, 2);
            $this->line("  Итерация OFFSET: {$duration} мс, получено: " . count($orders) . " строк");
        }

        return round(array_sum($times) / count($times), 2);
    }

    private function measureKeysetPerformance(int $page, int $limit, int $iterations): float
    {
        $cursor = null;

        if ($page > 1) {
            $offsetToFindCursor = ($page - 1) * $limit - 1;

            $cursorSql = "SELECT created_at, id FROM orders ORDER BY created_at DESC, id DESC LIMIT 1 OFFSET :offset";
            $stmt = $this->pdo->prepare($cursorSql);
            $stmt->bindValue(':offset', $offsetToFindCursor, PDO::PARAM_INT);
            $stmt->execute();
            $cursorOrder = $stmt->fetch();

            if (!$cursorOrder) {
                return 0; 
            }
            $cursor = $cursorOrder;
        }

        $times = [];
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);

            $bindings = [];
            $whereClause = '1=1'; 

            if ($cursor !== null) {
                $whereClause = "(created_at, id) < (:createdAt, :id)";
                $bindings[':createdAt'] = (string)$cursor['created_at'];
                $bindings[':id'] = (int)$cursor['id'];
            }

            $sql = "SELECT * FROM orders WHERE {$whereClause} ORDER BY created_at DESC, id DESC LIMIT :limit";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            foreach ($bindings as $key => &$val) {
                $stmt->bindParam($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            $orders = $stmt->fetchAll();

            $end = microtime(true);
            $times[] = ($end - $start) * 1000;

            $duration = round(($end - $start) * 1000, 2);
            $this->line("  Итерация KEYSET: {$duration} мс, получено: " . count($orders) . " строк");
        }

        return round(array_sum($times) / count($times), 2);
    }

    private function line(string $message): void { echo $message . PHP_EOL; }

    private function printTable(array $headers, array $rows): void
    {
        $widths = [];
        foreach ($headers as $i => $header) {
            $widths[$i] = strlen($header);
        }
        foreach ($rows as $row) {
            $cells = array_values($row);
            foreach ($cells as $i => $cell) {
                $widths[$i] = max($widths[$i], strlen((string)$cell));
            }
        }

        $headerLine = '';
        foreach ($headers as $i => $header) {
            $headerLine .= str_pad($header, $widths[$i]) . ' | ';
        }
        $this->line(rtrim($headerLine));

        $separatorLine = '';
        foreach ($widths as $width) {
            $separatorLine .= str_repeat('-', $width) . '-+-';
        }
        $this->line(rtrim($separatorLine, '-+'));

        foreach ($rows as $row) {
            $rowLine = '';
            $cells = array_values($row);
            foreach ($cells as $i => $cell) {
                $rowLine .= str_pad((string)$cell, $widths[$i] ?? 0) . ' | ';
            }
            $this->line(rtrim($rowLine));
        }
    }
}

if (php_sapi_name() === 'cli') {
    parse_str(implode('&', array_slice($argv, 1)), $options);
    $test = new TestPaginationPerformance($options);
    $test->handle();
}