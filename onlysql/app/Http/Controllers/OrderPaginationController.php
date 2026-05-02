<?php

namespace App\Http\Controllers;
use PDO;

class OrderPaginationController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getOrdersOffset(int $userId, array $queryParams): array
    {
        $limit = (int)($queryParams['limit'] ?? 15);
        $page = (int)($queryParams['page'] ?? 1);
        $offset = ($page - 1) * $limit;

        $startTime = microtime(true);

        $countSql = "SELECT COUNT(*) FROM orders WHERE user_id = :userId";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute([':userId' => $userId]);
        $total = (int)$countStmt->fetchColumn();

        $dataSql = "SELECT * FROM orders WHERE user_id = :userId ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset";
        $dataStmt = $this->pdo->prepare($dataSql);
        $dataStmt->bindValue(':userId', $userId, PDO::PARAM_INT);
        $dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT); 
        $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();
        $ordersData = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);

        $paginator = [
            'total' => $total,
            'per_page' => $limit,
            'current_page' => $page,
            'last_page' => $total > 0 ? (int)ceil($total / $limit) : 1,
            'from' => $offset + 1,
            'to' => $offset + count($ordersData),
            'data' => $ordersData,
        ];

        return [
            'pagination_type' => 'offset (PDO)',
            'performance' => [
                'total_execution_time_ms' => $executionTimeMs,
                'query' => $dataSql,
                'bindings' => [
                    ':userId' => $userId,
                    ':limit' => $limit,
                    ':offset' => $offset,
                ],
            ],
            'data' => $paginator,
        ];
    }

    public function getOrdersKeyset(int $userId, array $queryParams): array
    {
        $limit = (int)($queryParams['limit'] ?? 15);
        $cursor = $queryParams['cursor'] ?? null;
        $decodedCursor = $cursor ? json_decode(base64_decode($cursor), true) : null;

        $startTime = microtime(true);

        $bindings = [':userId' => $userId, ':limit' => $limit];
        $whereClause = "user_id = :userId";

        if ($decodedCursor && isset($decodedCursor['created_at']) && isset($decodedCursor['id'])) {
            $whereClause .= " AND (created_at, id) < (:createdAt, :id)";
            $bindings[':createdAt'] = (string)$decodedCursor['created_at'];
            $bindings[':id'] = (int)$decodedCursor['id'];
        }

        $dataSql = "SELECT * FROM orders WHERE {$whereClause} ORDER BY created_at DESC, id DESC LIMIT :limit";

        $dataStmt = $this->pdo->prepare($dataSql);
        foreach ($bindings as $key => &$val) {
            $dataStmt->bindParam($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $dataStmt->execute();
        $ordersData = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);

        $nextCursor = null;
        if (count($ordersData) === $limit) {
            $lastOrder = end($ordersData);
            $nextCursorData = ['created_at' => $lastOrder['created_at'], 'id' => (int)$lastOrder['id']];
            $nextCursor = base64_encode(json_encode($nextCursorData));
        }

        $paginator = [
            'per_page' => $limit,
            'next_cursor' => $nextCursor,
            'data' => $ordersData,
        ];

        return [
            'pagination_type' => 'keyset (cursor, PDO)',
            'performance' => [
                'total_execution_time_ms' => $executionTimeMs,
                'query' => $dataSql,
                'bindings' => $bindings,
            ],
            'data' => $paginator,
        ];
    }

    private function sendJsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}