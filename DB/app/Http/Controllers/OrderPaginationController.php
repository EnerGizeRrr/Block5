<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderPaginationController extends Controller
{
    // Получение заказов пользователя с использованием Offset-пагинации.
    public function getOrdersOffset(User $user, Request $request)
    {
        $perPage = $request->input('limit', 15);

        $startTime = microtime(true);
        DB::enableQueryLog();

        $orders = $user->orders()
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);


        $executionTime = (microtime(true) - $startTime) * 1000; 
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $executionTime = round($executionTime, 2);

        $mainQuery = !empty($queries) ? end($queries) : null;

        return response()->json([
            'pagination_type' => 'offset',
            'performance' => [
                'total_execution_time_ms' => $executionTime,
                'db_query_time_ms' => $mainQuery['time'] ?? 0,
                'query' => $mainQuery['query'] ?? 'Query not executed (e.g., empty page)',
                'bindings' => $mainQuery['bindings'] ?? [],
            ],
            'data' => $orders,
        ]);
    }


    // Получение заказов пользователя с использованием Keyset пагинации.
    public function getOrdersKeyset(User $user, Request $request)
    {
        $perPage = $request->input('limit', 15);

        $startTime = microtime(true);
        DB::enableQueryLog();

        $orders = $user->orders()
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->cursorPaginate($perPage);

        $executionTime = (microtime(true) - $startTime) * 1000; 
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $executionTime = round($executionTime, 2);

        $mainQuery = $queries[0] ?? null;

        return response()->json([
            'pagination_type' => 'keyset (cursor)',
            'performance' => [
                'total_execution_time_ms' => $executionTime,
                'db_query_time_ms' => $mainQuery['time'] ?? 0,
                'query' => $mainQuery['query'] ?? 'Query not executed (e.g., empty page)',
                'bindings' => $mainQuery['bindings'] ?? [],
            ],
            'data' => $orders,
        ]);
    }
}