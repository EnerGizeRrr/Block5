<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestPaginationPerformance extends Command
{
    protected $signature = 'app:test-pagination 
                            {--iterations=3 : Number of test iterations}
                            {--limit=15 : Items per page}';

    protected $description = 'Tests offset vs keyset pagination performance (direct DB queries)';

    public function handle()
    {
        $this->info("Testing pagination on the entire 'orders' table...");

        $limit = $this->option('limit');
        $iterations = $this->option('iterations');
        $pagesToTest = [1, 10, 50, 100, 500, 2000];

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
                $this->warn("→ KEYSET is " . $results[$page]['ratio'] . "x faster");
            } else {
                $this->warn("→ KEYSET performance cannot be compared (division by zero).");
            }
        }
        
        $this->newLine();
        $this->table(['Page', 'OFFSET (ms)', 'KEYSET (ms)', 'Speed Ratio'], 
            collect($results)->map(fn($r, $page) => [
                $page,
                $r['offset_ms'],
                $r['keyset_ms'],
                is_numeric($r['ratio']) ? "{$r['ratio']}x" : $r['ratio']
            ])->toArray()
        );

        return 0;
    }

    private function measureOffsetPerformance(int $page, int $limit, int $iterations): float
    {
        $offset = ($page - 1) * $limit;

        $times = [];
        for ($i = 0; $i < $iterations; $i++) {
            DB::flushQueryLog();
            DB::enableQueryLog();

            $start = microtime(true);

            $orders = DB::table('orders')
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->skip($offset)
                ->take($limit)
                ->get();

            $end = microtime(true);
            $times[] = ($end - $start) * 1000;

            $queryLog = DB::getQueryLog();
            $this->line("OFFSET Query: " . ($queryLog[0]['time'] ?? 0) . " ms");
        }

        return round(array_sum($times) / count($times), 2);
    }

    private function measureKeysetPerformance(int $page, int $limit, int $iterations): float
    {
        $cursor = null;
        if ($page > 1) {
            $cursorQuery = DB::table('orders')
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc');

            $offsetToFindCursor = ($page - 1) * $limit - 1;

            $cursorOrder = $cursorQuery->skip($offsetToFindCursor)->take(1)->first();

            if (!$cursorOrder) {
                return 0; 
            }
            $cursor = [
                'created_at' => $cursorOrder->created_at,
                'id' => $cursorOrder->id
            ];
        }

        $times = [];
        for ($i = 0; $i < $iterations; $i++) {
            DB::flushQueryLog();
            DB::enableQueryLog();

            $start = microtime(true);

            $query = DB::table('orders');

            if ($cursor) {
                $query->where(function ($q) use ($cursor) {
                    $q->where('created_at', '<', $cursor['created_at'])
                      ->orWhere(function ($subQuery) use ($cursor) {
                          $subQuery->where('created_at', '=', $cursor['created_at'])
                                   ->where('id', '<', $cursor['id']);
                      });
                });
            }

            $orders = $query->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->take($limit)
                ->get();

            $end = microtime(true);
            $times[] = ($end - $start) * 1000;

            $queryLog = DB::getQueryLog();
            $this->line("KEYSET Query: " . ($queryLog[0]['time'] ?? 0) . " ms");
        }

        return round(array_sum($times) / count($times), 2);
    }
}