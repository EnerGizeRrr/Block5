<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Загрузка заказов, позиций заказов и платежей с использованием чистого SQL...');

        DB::disableQueryLog();

        $pdo = DB::connection()->getPdo();
        $faker = Faker::create();

        $this->command->info('Получение ID пользователей и продуктов...');
        $userIds = DB::table('users')->pluck('id')->all();
        $products = DB::table('products')->select('id', 'price')->get()->all();
        $this->command->info('Получение ID завершено.');

        $ordersToCreate = 100_000;
        $itemsToCreate = 200_000;
        $itemsPerOrder = floor($itemsToCreate / $ordersToCreate);

        $chunkSize = 500; // Меньший размер чанка из-за сложности
        $ordersChunk = [];
        $orderItemsChunk = [];
        $paymentsChunk = [];

        $orderStatuses = ['new', 'paid', 'cancelled'];
        $paymentProviders = ['stripe', 'paypal', 'cash'];
        $paymentStatuses = ['pending', 'paid', 'failed'];

        for ($i = 0; $i < $ordersToCreate; $i++) {
            $createdAt = $faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d H:i:s');
            $status = $faker->randomElement(['new', 'paid', 'paid', 'paid', 'cancelled']); // 'paid' более вероятен

            // 1. Готовим данные для заказа
            $orderData = [
                'user_id' => $userIds[array_rand($userIds)],
                'status' => $status,
                'total_amount' => 0, // Будет обновлено позже
                'created_at' => $createdAt,
            ];

            // Вставляем заказ и получаем его ID
            $stmt = $pdo->prepare("INSERT INTO orders (user_id, status, total_amount, created_at) VALUES (?, ?, ?, ?)");
            $stmt->execute(array_values($orderData));
            $orderId = $pdo->lastInsertId();

            // 2. Готовим позиции заказа
            $totalAmount = 0;
            for ($j = 0; $j < $itemsPerOrder; $j++) {
                $product = $products[array_rand($products)];
                $qty = rand(1, 3);
                $price = $product->price;
                $totalAmount += $price * $qty;

                $orderItemsChunk[] = [
                    'order_id' => $orderId,
                    'product_id' => $product->id,
                    'qty' => $qty,
                    'price' => $price,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt, // В сидере можно использовать одно и то же значение
                ];
            }

            // Обновляем сумму заказа
            $stmt = $pdo->prepare("UPDATE orders SET total_amount = ? WHERE id = ?");
            $stmt->execute([$totalAmount, $orderId]);

            // 3. Готовим платежи
            if ($status === 'paid') {
                $paymentsChunk[] = [
                    'order_id' => $orderId,
                    'status' => 'paid',
                    'provider' => $paymentProviders[array_rand($paymentProviders)],
                    'created_at' => $createdAt,
                ];
            } elseif ($status === 'new') {
                // Для новых заказов создаем платеж в статусе pending или failed с вероятностью 50%
                if (rand(1, 2) === 1) {
                    $paymentsChunk[] = [
                        'order_id' => $orderId,
                        'status' => ['failed', 'pending'][array_rand(['failed', 'pending'])],
                        'provider' => ['stripe', 'paypal'][array_rand(['stripe', 'paypal'])],
                        'created_at' => $createdAt,
                    ];
                }
            }

            // Вставляем накопленные данные пачками
            if (count($orderItemsChunk) >= $chunkSize * $itemsPerOrder) {
                $this->insertChunk($pdo, 'order_items', $orderItemsChunk);
                $orderItemsChunk = [];
            }
            if (count($paymentsChunk) >= $chunkSize) {
                $this->insertChunk($pdo, 'payments', $paymentsChunk);
                $paymentsChunk = [];
            }

            if (($i + 1) % 1000 === 0) {
                $this->command->info("Обработано " . ($i + 1) . " из {$ordersToCreate} заказов.");
            }
        }

        // Вставляем оставшиеся данные
        $this->insertChunk($pdo, 'order_items', $orderItemsChunk);
        $this->insertChunk($pdo, 'payments', $paymentsChunk);

        $this->command->info('Загрузка заказов, позиций и платежей завершена.');
    }

    private function insertChunk(\PDO $pdo, string $table, array &$data): void
    {
        if (empty($data)) return;
        $columns = implode(',', array_keys($data[0]));
        $placeholders = implode(',', array_fill(0, count($data[0]), '?'));
        $valuesPlaceholder = implode(',', array_fill(0, count($data), "($placeholders)"));
        $sql = "INSERT INTO {$table} ({$columns}) VALUES $valuesPlaceholder";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge(...array_map('array_values', $data)));
        $data = []; // Очищаем массив после вставки
    }
}