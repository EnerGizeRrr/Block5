<?php
require_once __DIR__ . '/../app/Http/Controllers/OrderPaginationController.php';

use App\Http\Controllers\OrderPaginationController;

$dbHost = '127.0.1.31'; 
$dbPort = '3306';
$dbName = 'laravel';   
$dbUser = 'root';      
$dbPass = '';     

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}

$controller = new OrderPaginationController($pdo);

// --- ТЕСТЫ ---

echo "--- Тест 1: Offset-пагинация (страница 2) ---\n";
$userId = 456; 
$queryParamsOffset = ['page' => 2, 'limit' => 5];
$result1 = $controller->getOrdersOffset($userId, $queryParamsOffset);
echo json_encode($result1, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo "\n\n";


echo "--- Тест 2: Keyset-пагинация (первая страница) ---\n";
$queryParamsKeyset1 = ['limit' => 3];
$result2 = $controller->getOrdersKeyset($userId, $queryParamsKeyset1);
echo json_encode($result2, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo "\n\n";

echo "--- Тест 3: Keyset-пагинация (используем курсор из предыдущего теста) ---\n";
$queryParamsKeyset2 = [
    'limit' => 3,
    'cursor' => 'eyJjcmVhdGVkX2F0IjoiMjAyNi0wNC0xNCAyMTo0ODowOCIsImlkIjo5OTk4Nn0='
];
$result3 = $controller->getOrdersKeyset($userId, $queryParamsKeyset2);
echo json_encode($result3, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo "\n";
