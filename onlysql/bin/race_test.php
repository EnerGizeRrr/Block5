<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

function createTestOrder(): ?int
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
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        echo "Ошибка подключения к БД: " . $e->getMessage() . "\n";
        return null;
    }

    $user = $pdo->query("SELECT id FROM users LIMIT 1")->fetch();
    if (!$user) {
        echo "Не найден ни один пользователь. Запустите сидеры.\n";
        return null;
    }

    $sql = "INSERT INTO orders (user_id, status, total_amount, created_at) VALUES (?, 'new', 0, NOW())";
    $pdo->prepare($sql)->execute([$user['id']]);
    $orderId = (int)$pdo->lastInsertId();

    echo "Создан тестовый заказ с ID: {$orderId}\n";
    return $orderId;
}

$orderId = createTestOrder();

if (!$orderId) {
    exit(1);
}

echo "Запускаем 10 параллельных попыток оплаты...\n\n";

$concurrentProcesses = 10;
$phpPath = PHP_BINARY;
$scriptPath = __DIR__ . '/pay_order.php';

if (function_exists('pcntl_fork')) {
    $pids = [];
    for ($i = 0; $i < $concurrentProcesses; $i++) {
        $pid = pcntl_fork();
        if ($pid == -1) {
            die("Не удалось создать дочерний процесс");
        } elseif ($pid) {
            $pids[] = $pid;
        } else {
            exec("{$phpPath} {$scriptPath} {$orderId}");
            exit();
        }
    }

    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
    }
} else {
    $processes = [];
    $pipes = [];
    $descriptorSpec = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    for ($i = 0; $i < $concurrentProcesses; $i++) {
        $process = proc_open("{$phpPath} {$scriptPath} {$orderId}", $descriptorSpec, $pipe);
        if (is_resource($process)) {
            $processes[] = $process;
            $pipes[] = $pipe;
        }
    }

    foreach ($pipes as $pipe) {
        $output = stream_get_contents($pipe[1]);
        $errorOutput = stream_get_contents($pipe[2]);
        echo $output;
        if (!empty($errorOutput)) {
            echo "STDERR: " . $errorOutput;
        }
        fclose($pipe[1]);
        fclose($pipe[2]);
    }
    foreach ($processes as $process) {
        proc_close($process);
    }
}


echo "\nТест на состояние гонки завершен.\n";
