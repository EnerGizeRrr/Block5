<?php

declare(strict_types=1);


if (PHP_SAPI !== 'cli') {
    die('Этот скрипт можно запускать только из командной строки.');
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

if ($argc < 2 || !filter_var($argv[1], FILTER_VALIDATE_INT)) {
    die("Использование: php bin/pay_order.php <order_id>\n");
}

$orderId = (int)$argv[1];
$projectRoot = dirname(__DIR__);

// --- Конфигурация и подключение к БД ---
$dbConfig = [
    'host' => '127.0.1.31',
    'port' => '3306',
    'dbname' => 'test_db',
    'user' => 'root',
    'password' => '',
];

try {
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage() . "\n");
}

/**
 * Обрабатывает оплату для указанного заказа в рамках транзакции.
 *
 * @param PDO $pdo Соединение с базой данных.
 * @param int $orderId ID заказа для оплаты.
 * @throws Exception если заказ не может быть оплачен.
 */
function payOrder(PDO $pdo, int $orderId): void
{
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT id, status FROM orders WHERE id = ? FOR UPDATE");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            throw new Exception("Заказ с ID {$orderId} не найден.");
        }

        if ($order['status'] !== 'new') {
            throw new Exception("Заказ {$orderId} не может быть оплачен. Текущий статус: {$order['status']}.");
        }

        $updateOrderStmt = $pdo->prepare("UPDATE orders SET status = 'paid' WHERE id = ?");
        $updateOrderStmt->execute([$orderId]);

        $paymentStmt = $pdo->prepare(
            "INSERT INTO payments (order_id, status, provider) VALUES (?, 'paid', 'system_payment') 
             ON DUPLICATE KEY UPDATE status = 'paid', provider = 'system_payment'"
        );
        $paymentStmt->execute([$orderId]);

        $auditMeta = json_encode(['from_status' => $order['status'], 'to_status' => 'paid']);
        $auditStmt = $pdo->prepare("INSERT INTO audit_log (entity_type, entity_id, action, meta) VALUES ('order', ?, 'payment_processed', ?)");
        $auditStmt->execute([$orderId, $auditMeta]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e; 
    }
}

try {
    payOrder($pdo, $orderId);
    echo "Процесс " . getmypid() . ": Успешно оплачен заказ {$orderId}.\n";
} catch (Throwable $e) {
    echo "Процесс " . getmypid() . ": Не удалось оплатить заказ {$orderId}. Причина: " . $e->getMessage() . "\n";
    exit(1); 
}