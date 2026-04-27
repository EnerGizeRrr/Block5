<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDO;

class OrderPaginationController extends Controller
{
    private function getPdoConnection(): PDO
    {

        return DB::connection()->getPdo();
    }

    public function getOrdersOffset($userId, Request $request)
    {
        $limit = (int)$request->input('limit', 15);
        $page = (int)$request->input('page', 1);
        $offset = ($page - 1) * $limit;

        $startTime = microtime(true);

        $pdo = $this->getPdoConnection();

        $countSql = "SELECT COUNT(*) FROM orders WHERE user_id = :userId";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute([':userId' => $userId]);
        $total = (int)$countStmt->fetchColumn();

        $dataSql = "SELECT * FROM orders WHERE user_id = :userId ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset";
        $dataStmt = $pdo->prepare($dataSql);
        $dataStmt->bindValue(':userId', $userId, PDO::PARAM_INT);
        $dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT); 
        $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();
        $ordersData = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        $executionTime = (microtime(true) - $startTime) * 1000;
        $executionTime = round($executionTime, 2);

        $paginator = [
            'total' => $total,
            'per_page' => $limit,
            'current_page' => $page,
            'last_page' => $total > 0 ? ceil($total / $limit) : 1,
            'from' => $offset + 1,
            'to' => $offset + count($ordersData),
            'data' => $ordersData,
        ];

        return response()->json([
            'pagination_type' => 'offset (PDO)',
            'performance' => [
                'total_execution_time_ms' => $executionTime,
                'query' => $dataSql,
                'bindings' => [
                    ':userId' => $userId,
                    ':limit' => $limit,
                    ':offset' => $offset,
                ],
            ],
            'data' => $paginator,
        ]);
    }


    public function getOrdersKeyset($userId, Request $request)
    {
        $limit = (int)$request->input('limit', 15);
        $cursor = $request->input('cursor');
        $decodedCursor = $cursor ? json_decode(base64_decode($cursor), true) : null;

        $startTime = microtime(true);
        $pdo = $this->getPdoConnection();

        $bindings = [':userId' => $userId, ':limit' => $limit];
        $whereClause = "user_id = :userId";

        if ($decodedCursor && isset($decodedCursor['created_at']) && isset($decodedCursor['id'])) {
            $whereClause .= " AND (created_at, id) < (:createdAt, :id)";
            $bindings[':createdAt'] = $decodedCursor['created_at'];
            $bindings[':id'] = $decodedCursor['id'];
        }

        $dataSql = "SELECT * FROM orders WHERE {$whereClause} ORDER BY created_at DESC, id DESC LIMIT :limit";


        $dataStmt = $pdo->prepare($dataSql);
        foreach ($bindings as $key => &$val) {
            $dataStmt->bindParam($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $dataStmt->execute();
        $ordersData = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        $executionTime = (microtime(true) - $startTime) * 1000;
        $executionTime = round($executionTime, 2);

        $nextCursor = null;
        if (count($ordersData) === $limit) {
            $lastOrder = end($ordersData);
            $nextCursorData = ['created_at' => $lastOrder['created_at'], 'id' => $lastOrder['id']];
            $nextCursor = base64_encode(json_encode($nextCursorData));
        }

        $paginator = [
            'path' => $request->url(),
            'per_page' => $limit,
            'next_cursor' => $nextCursor,
            'next_page_url' => $nextCursor ? $request->url() . '?cursor=' . $nextCursor : null,
            'data' => $ordersData,
        ];

        return response()->json([
            'pagination_type' => 'keyset (cursor, PDO)',
            'performance' => [
                'total_execution_time_ms' => $executionTime,
                'query' => $dataSql,
                'bindings' => $bindings,
            ],
            'data' => $paginator,
        ]);
    }
}