<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    die('Этот скрипт можно запускать только из командной строки.');
}

echo "Запуск теста состояния гонки...\n";

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
    ]);
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage() . "\n");
}

echo "Подготовка тестового заказа...\n";
$stmt = $pdo->query("SELECT id FROM orders WHERE status = 'new' LIMIT 1");
$orderId = $stmt->fetchColumn();

if (!$orderId) {
    echo "Заказы со статусом 'new' не найдены. Создание нового для теста.\n";
    $userIdStmt = $pdo->query("SELECT id FROM users ORDER BY RAND() LIMIT 1");
    $userId = $userIdStmt->fetchColumn();
    if (!$userId) {
        die("В базе данных не найдены пользователи. Пожалуйста, сначала запустите сидер.\n");
    }

    $pdo->prepare("INSERT INTO orders (user_id, status, total_amount) VALUES (?, 'new', 123.45)")
        ->execute([$userId]);
    $orderId = (int)$pdo->lastInsertId();
}

echo "Тест будет выполнен для заказа с ID: {$orderId}\n";
echo "----------------------------------------\n";

$parallelAttempts = 10;
$childPids = [];
$projectRoot = dirname(__DIR__);
 
$processes = [];
$pipes = [];
 
for ($i = 0; $i < $parallelAttempts; $i++) {
    $command = sprintf(
        '%s %s/bin/pay_order.php %d',
        PHP_BINARY,
        escapeshellarg($projectRoot),
        $orderId
    );
 
    $descriptorspec = [
       1 => ['pipe', 'w'], 
       2 => ['pipe', 'w'], 
    ];
 
    $process = proc_open($command, $descriptorspec, $pipe_set);
 
    if (is_resource($process)) {
        $processes[] = $process;
        $pipes[] = $pipe_set;
    }
}
 

foreach ($pipes as $i => $pipe_set) {
    echo stream_get_contents($pipe_set[1]);
    fclose($pipe_set[1]);

    $errors = stream_get_contents($pipe_set[2]);
    fclose($pipe_set[2]);
    if (!empty($errors)) {
        echo "Ошибки от процесса $i: $errors\n";
    }
}
 
foreach ($processes as $process) {
    proc_close($process);
}

echo "----------------------------------------\n";
echo "Тест состояния гонки завершен.\n\n";

$finalOrder = $pdo->query("SELECT status FROM orders WHERE id = {$orderId}")->fetch();
$paymentCount = $pdo->query("SELECT COUNT(*) FROM payments WHERE order_id = {$orderId} AND status = 'paid'")->fetchColumn();
$auditCount = $pdo->query("SELECT COUNT(*) FROM audit_log WHERE entity_type = 'order' AND entity_id = {$orderId} AND action = 'payment_processed'")->fetchColumn();

echo "Проверка конечного состояния для заказа ID {$orderId}:\n";
echo "- Статус заказа: " . ($finalOrder['status'] ?? 'НЕ НАЙДЕН') . "\n";
echo "- Количество 'оплаченных' платежей: {$paymentCount}\n";
echo "- Записей в журнале аудита: {$auditCount}\n\n";

if (($finalOrder['status'] ?? '') === 'paid' && $paymentCount == 1 && $auditCount == 1) {
    echo "УСПЕХ: Заказ был оплачен ровно один раз. Состояние гонки обработано корректно.\n";
} else {
    echo "ОШИБКА: Конечное состояние базы данных неконсистентно.\n";
}