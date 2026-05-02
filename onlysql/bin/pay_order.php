<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

function getDbConnection(): PDO
{
    $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $port = $_ENV['DB_PORT'] ?? '3306';
    $db   = $_ENV['DB_DATABASE'] ?? 'laravel';
    $user = $_ENV['DB_USERNAME'] ?? 'root';
    $pass = $_ENV['DB_PASSWORD'] ?? '';
    $charset = 'utf8mb4';


    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        throw new PDOException("Ошибка подключения к БД: " . $e->getMessage(), (int)$e->getCode());
    }
}

function payOrder(int $orderId): void
{
    $pdo = getDbConnection();

    try {
        $pdo->beginTransaction();

        $updateOrderSql = "UPDATE orders SET status = 'paid' WHERE id = :orderId AND status = 'new'";
        $stmt = $pdo->prepare($updateOrderSql);
        $stmt->execute([':orderId' => $orderId]);

        if ($stmt->rowCount() === 0) {
            $checkStmt = $pdo->prepare("SELECT status FROM orders WHERE id = :orderId");
            $checkStmt->execute([':orderId' => $orderId]);
            $currentOrder = $checkStmt->fetch();
            $reason = $currentOrder ? "его текущий статус '{$currentOrder['status']}'" : "он не найден";
            throw new Exception("Заказ {$orderId} не может быть оплачен, так как {$reason}.");
        }

        $paymentSql = "
            INSERT INTO payments (order_id, status, provider, created_at) VALUES (:orderId, 'paid', 'stripe', NOW())
        ";
        $pdo->prepare($paymentSql)->execute([':orderId' => $orderId]);

        $auditSql = "
            INSERT INTO audit_log (entity_type, entity_id, action, meta, created_at)
            VALUES ('order', :orderId, 'payment_processed', :meta, NOW())
        ";
        $meta = json_encode(['old_status' => 'new', 'new_status' => 'paid']);
        $pdo->prepare($auditSql)->execute([':orderId' => $orderId, ':meta' => $meta]);

        $pdo->commit();

        echo "Процесс " . getmypid() . ": Заказ {$orderId} успешно оплачен.\n";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "Процесс " . getmypid() . ": Ошибка оплаты заказа {$orderId}: " . $e->getMessage() . "\n";
    }
}

if (isset($argv[1])) {
    $orderId = (int)$argv[1];
    if ($orderId > 0) {
        payOrder($orderId);
    } else {
        echo "Неверный ID заказа.\n";
    }
}