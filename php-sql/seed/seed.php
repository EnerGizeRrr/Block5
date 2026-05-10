<?php

echo "Запуск скрипта наполнения базы данных...\n";

// --- Конфигурация ---
$dbConfig = [
    'host' => '127.0.1.31',
    'port' => '3306',
    'dbname' => 'test_db', 
    'user' => 'root',       
    'password' => '',       
];

$counts = [
    'users' => 50000,
    'products' => 20000,
    'orders' => 100000,
    'order_items' => 200000,
    'payments' => 100000,
];

mt_srand(1234);

// --- Подключение к БД ---
try {
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("DB Connection Error: " . $e->getMessage() . "\n");
}

function batchInsert(PDO $pdo, string $table, array $columns, array $data, int $batchSize = 1000): void
{
    if (empty($data)) {
        return;
    }

    $pdo->beginTransaction();
    try {
        $chunks = array_chunk($data, $batchSize);
        $columnCount = count($columns);
        $columnNames = '`' . implode('`, `', $columns) . '`';

        foreach ($chunks as $chunk) {
            $placeholders = [];
            $values = [];
            foreach ($chunk as $row) {
                $placeholders[] = '(' . implode(',', array_fill(0, $columnCount, '?')) . ')';
                array_push($values, ...array_values($row));
            }

            $sql = "INSERT INTO `$table` ($columnNames) VALUES " . implode(', ', $placeholders);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

echo "Генерация {$counts['users']} пользователей...\n";
$usersData = [];
for ($i = 1; $i <= $counts['users']; $i++) {
    $usersData[] = [
        'email' => "user{$i}@example.com",
        'name' => "User Name {$i}",
    ];
}
batchInsert($pdo, 'users', ['email', 'name'], $usersData);
echo "Пользователи созданы.\n";

echo "Генерация {$counts['products']} товаров...\n";
$productsData = [];
for ($i = 1; $i <= $counts['products']; $i++) {
    $productsData[] = [
        'sku' => "SKU-" . str_pad((string)$i, 8, '0', STR_PAD_LEFT),
        'title' => "Product Title {$i}",
        'price' => round(mt_rand(1000, 50000) / 100, 2),
    ];
}
batchInsert($pdo, 'products', ['sku', 'title', 'price'], $productsData);
echo "Товары созданы.\n";

echo "Генерация {$counts['orders']} заказов...\n";
$ordersData = [];
$orderStatuses = ['new', 'paid', 'paid', 'paid', 'paid', 'cancelled'];
for ($i = 1; $i <= $counts['orders']; $i++) {
    $ordersData[] = [
        'user_id' => mt_rand(1, $counts['users']),
        'status' => $orderStatuses[array_rand($orderStatuses)],
        'total_amount' => 0.00,
    ];
}
batchInsert($pdo, 'orders', ['user_id', 'status', 'total_amount'], $ordersData);
echo "Заказы созданы.\n";

echo "Генерация {$counts['order_items']} позиций заказов...\n";
$productPrices = $pdo->query("SELECT id, price FROM products")->fetchAll(PDO::FETCH_KEY_PAIR);

$orderItemsBatchSize = 5000;
$ordersToUpdate = [];

for ($i = 0; $i < $counts['order_items']; $i += $orderItemsBatchSize) {
    $orderItemsData = [];
    $limit = min($orderItemsBatchSize, $counts['order_items'] - $i);

    for ($j = 0; $j < $limit; $j++) {
        $productId = mt_rand(1, $counts['products']);
        $orderId = mt_rand(1, $counts['orders']);
        $qty = mt_rand(1, 5);
        $price = $productPrices[$productId];

        $orderItemsData[] = [
            'order_id' => $orderId,
            'product_id' => $productId,
            'qty' => $qty,
            'price' => $price,
        ];

        if (!isset($ordersToUpdate[$orderId])) {
            $ordersToUpdate[$orderId] = 0;
        }
        $ordersToUpdate[$orderId] += $price * $qty;
    }
    batchInsert($pdo, 'order_items', ['order_id', 'product_id', 'qty', 'price'], $orderItemsData, $orderItemsBatchSize);
    echo "Вставлено " . ($i + $limit) . " / " . $counts['order_items'] . " позиций заказов.\n";
}
echo "Позиции заказов созданы.\n";

echo "Обновление итоговой суммы в заказах...\n";
$updateSql = "UPDATE orders SET total_amount = ? WHERE id = ?";
$stmt = $pdo->prepare($updateSql);
foreach ($ordersToUpdate as $orderId => $total) {
    $stmt->execute([$total, $orderId]);
}
echo "Итоговые суммы в заказах обновлены.\n";


echo "Генерация {$counts['payments']} платежей...\n";
$paymentsData = [];
$paymentStatuses = ['pending', 'paid', 'paid', 'paid', 'failed'];
$providers = ['stripe', 'paypal', 'cash'];

$orderIdsForPayment = $pdo->query("
    SELECT o.id FROM orders o
    LEFT JOIN payments p ON o.id = p.order_id
    WHERE p.id IS NULL
    ORDER BY RAND({$counts['users']})
    LIMIT {$counts['payments']}
")->fetchAll(PDO::FETCH_COLUMN);

foreach ($orderIdsForPayment as $orderId) {
    $paymentsData[] = [
        'order_id' => $orderId,
        'status' => $paymentStatuses[array_rand($paymentStatuses)],
        'provider' => $providers[array_rand($providers)],
    ];
}
batchInsert($pdo, 'payments', ['order_id', 'status', 'provider'], $paymentsData);
echo "Платежи созданы.\n";

echo "Наполнение базы данных успешно завершено!\n";