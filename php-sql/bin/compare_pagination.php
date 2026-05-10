<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    die('Этот скрипт можно запускать только из командной строки.');
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

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

// --- Параметры теста ---
$limit = 20;
$pagesToTest = [1, 500, 2000];
$isGlobalTest = in_array('--global', $argv, true);
$userId = null;

echo "--------------------------------------------------\n";
echo "Сравнение производительности пагинации\n";
echo "Заказов на странице: {$limit}\n";

if ($isGlobalTest) {
    echo "Режим: Глобальный (все заказы в таблице)\n";
    echo "Использование: php bin/compare_pagination.php --global\n";
    echo "--------------------------------------------------\n\n";
    echo "Для этого режима используется PRIMARY KEY по `id`.\n\n";

    $totalOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $maxPage = max($pagesToTest);
    $requiredOrders = $maxPage * $limit;
    if ($totalOrders < $requiredOrders) {
        echo "ПРЕДУПРЕЖДЕНИЕ: В таблице всего {$totalOrders} заказов.\n";
        echo "Для полного теста на странице {$maxPage} требуется как минимум {$requiredOrders} заказов.\n";
        echo "Результаты для глубоких страниц могут быть нерепрезентативными.\n\n";
    }
} else {
    $args = array_values(array_filter($argv, fn($v) => !str_starts_with($v, '-')));
    $userIdArg = $args[1] ?? null;

    if ($userIdArg && filter_var($userIdArg, FILTER_VALIDATE_INT)) {
        $userId = (int)$userIdArg;
    } else {
        echo "User ID не указан. Ищем пользователя с наибольшим количеством заказов...\n";
        $stmt = $pdo->query("SELECT user_id, COUNT(*) as order_count FROM orders GROUP BY user_id ORDER BY order_count DESC LIMIT 1");
        $result = $stmt->fetch();
        if (!$result) {
            die("Не удалось найти пользователей с заказами. Пожалуйста, заполните базу данных (сидер).\n");
        }
        $userId = (int)$result['user_id'];
        echo "Тест будет выполнен для пользователя ID {$userId} (всего заказов: {$result['order_count']}).\n";
    }

    echo "Режим: Для пользователя ID {$userId}\n";
    echo "Использование: php bin/compare_pagination.php [user_id]\n";
    echo "Для этого режима рекомендуется создать индекс: \nALTER TABLE `orders` ADD INDEX `orders_user_id_id_desc_index` (`user_id`, `id` DESC);\n";
    echo "--------------------------------------------------\n\n";

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
    $countStmt->execute([$userId]);
    $totalUserOrders = (int)$countStmt->fetchColumn();
    $maxPage = max($pagesToTest);
    $requiredOrders = $maxPage * $limit;
    if ($totalUserOrders < $requiredOrders) {
        echo "ПРЕДУПРЕЖДЕНИЕ: У пользователя {$userId} всего {$totalUserOrders} заказов.\n";
        echo "Для полного теста на странице {$maxPage} требуется как минимум {$requiredOrders} заказов.\n";
        echo "Результаты для глубоких страниц могут быть нерепрезентативными.\n\n";
    }
}


/**
 * Выполняет запрос с OFFSET-пагинацией и измеряет время.
 */
function testOffsetPagination(PDO $pdo, int $page, int $limit, ?int $userId = null): float
{
    $offset = ($page - 1) * $limit;

    $whereClause = '';
    $params = [];
    if ($userId !== null) {
        $whereClause = 'WHERE user_id = ?';
        $params[] = $userId;
    }

    $sql = "SELECT id FROM orders {$whereClause} ORDER BY id DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $start = hrtime(true);
    $stmt->execute($params);
    $stmt->fetchAll();
    $end = hrtime(true);

    return ($end - $start) / 1e9; // в секундах
}

/**
 * Выполняет запрос с Keyset-пагинацией и измеряет время.
 */
function testKeysetPagination(PDO $pdo, int $page, int $limit, ?int $userId = null): float
{
    $cursorId = null;

    if ($page > 1) {
        $offsetForCursor = ($page - 1) * $limit - 1;

        $whereClause = '';
        $cursorParams = [];
        if ($userId !== null) {
            $whereClause = 'WHERE user_id = ?';
            $cursorParams[] = $userId;
        }

        $sqlCursor = "SELECT id FROM orders {$whereClause} ORDER BY id DESC LIMIT 1 OFFSET ?";
        $cursorParams[] = $offsetForCursor;

        $stmtCursor = $pdo->prepare($sqlCursor);
        $stmtCursor->execute($cursorParams);
        $cursorId = $stmtCursor->fetchColumn();
    }

    $whereClauses = [];
    $params = [];
    if ($userId !== null) {
        $whereClauses[] = 'user_id = ?';
        $params[] = $userId;
    }
    if ($cursorId !== null) {
        $whereClauses[] = 'id < ?';
        $params[] = $cursorId;
    }

    $whereSql = empty($whereClauses) ? '' : 'WHERE ' . implode(' AND ', $whereClauses);
    $sql = "SELECT id FROM orders {$whereSql} ORDER BY id DESC LIMIT ?";
    $params[] = $limit;

    $stmt = $pdo->prepare($sql);

    $start = hrtime(true);
    $stmt->execute($params);
    $stmt->fetchAll();
    $end = hrtime(true);

    return ($end - $start) / 1e9; 
}

// --- Запуск тестов ---
foreach ($pagesToTest as $page) {
    echo "Тестирование страницы: {$page}\n";

    $timeOffset = testOffsetPagination($pdo, $page, $limit, $userId);
    printf("  - OFFSET-пагинация: %.6f сек.\n", $timeOffset);

    $timeKeyset = testKeysetPagination($pdo, $page, $limit, $userId);
    printf("  - KEYSET-пагинация: %.6f сек.\n", $timeKeyset);

    if ($page > 1 && $timeKeyset > 0 && $timeOffset > $timeKeyset) {
        $improvement = $timeOffset / $timeKeyset;
        printf("  - Keyset-пагинация быстрее в %.1f раз\n", $improvement);
    }

    echo "\n";
}

echo "--------------------------------------------------\n";
echo "Выводы:\n";
echo "OFFSET-пагинация становится значительно медленнее с увеличением номера страницы,\n";
echo "так как база данных должна просканировать и отбросить все строки, указанные в OFFSET.\n\n";
echo "KEYSET-пагинация (курсорная) сохраняет стабильно высокую производительность независимо\n";
echo "от глубины страницы, так как она использует индекс для мгновенного 'прыжка' к нужной позиции.\n";
echo "Это предпочтительный метод для высоконагруженных систем и бесконечной прокрутки.\n";
echo "--------------------------------------------------\n";