<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Загрузка заказов, позиций заказов и платежей ...');

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

        $chunkSize = 500;
        $ordersChunk = [];
        $orderItemsChunk = [];
        $paymentsChunk = [];

        $orderStatuses = ['new', 'paid', 'cancelled'];
        $paymentProviders = ['stripe', 'paypal', 'cash'];
        $paymentStatuses = ['pending', 'paid', 'failed'];

        for ($i = 1; $i <= $ordersToCreate; $i++) {
            $createdAt = $faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d H:i:s');
            $status = $faker->randomElement(['new', 'paid', 'paid', 'paid', 'cancelled']); // 'paid' более вероятен

            // 1. Готовим позиции заказа и считаем итоговую сумму
            $currentOrderItems = [];
            $totalAmount = 0;
            for ($j = 0; $j < $itemsPerOrder; $j++) {
                $product = $products[array_rand($products)];
                $qty = rand(1, 3);
                $price = (float)$product->price;
                $totalAmount += $price * $qty;

                $currentOrderItems[] = [
                    // 'order_id' будет добавлен позже
                    'product_id' => $product->id,
                    'qty' => $qty,
                    'price' => $price,
                    'created_at' => $createdAt,
                ];
            }

            // 2. Готовим данные для заказа с уже посчитанной суммой
            $orderData = [
                'user_id' => $userIds[array_rand($userIds)],
                'status' => $status,
                'total_amount' => $totalAmount,
                'created_at' => $createdAt,
            ];
            $ordersChunk[] = $orderData;

            // 3. Готовим платежи (если нужны)
            $currentPayment = null;
            if ($status === 'paid') {
                $currentPayment = [
                    // 'order_id' будет добавлен позже
                    'status' => 'paid',
                    'provider' => $paymentProviders[array_rand($paymentProviders)],
                    'created_at' => $createdAt,
                ];
            } elseif ($status === 'new') {
                if (rand(1, 2) === 1) {
                    $currentPayment = [
                        // 'order_id' будет добавлен позже
                        'status' => ['failed', 'pending'][array_rand(['failed', 'pending'])],
                        'provider' => ['stripe', 'paypal'][array_rand(['stripe', 'paypal'])],
                        'created_at' => $createdAt,
                    ];
                }
            }
            
            // Сохраняем подготовленные данные с временным ключом для связи
            $orderItemsChunk[$i] = $currentOrderItems;
            if ($currentPayment) {
                $paymentsChunk[$i] = $currentPayment;
            }

            // Когда чанк заказов наполнен, вставляем все связанные данные
            if (count($ordersChunk) >= $chunkSize) {
                $this->processChunks($pdo, $ordersChunk, $orderItemsChunk, $paymentsChunk);
            }

            if ($i % 1000 === 0) {
                $this->command->info("Обработано {$i} из {$ordersToCreate} заказов.");
            }
        }

        // Вставляем оставшиеся данные
        $this->processChunks($pdo, $ordersChunk, $orderItemsChunk, $paymentsChunk);

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

    private function processChunks(\PDO $pdo, array &$ordersChunk, array &$orderItemsChunk, array &$paymentsChunk): void
    {
        if (empty($ordersChunk)) {
            return;
        }

        // 1. Вставляем заказы и получаем их ID
        $firstOrderId = $this->insertChunkAndGetFirstId($pdo, 'orders', $ordersChunk);
        $orderIds = range($firstOrderId, $firstOrderId + count($ordersChunk) - 1);

        // 2. Связываем ID заказов с позициями и платежами
        $finalOrderItems = [];
        $finalPayments = [];
        foreach ($orderIds as $index => $orderId) {
            $tempKey = key($orderItemsChunk); // Получаем временный ключ (1..100000)
            foreach ($orderItemsChunk[$tempKey] as $item) {
                $finalOrderItems[] = ['order_id' => $orderId] + $item;
            }
            if (isset($paymentsChunk[$tempKey])) {
                $finalPayments[] = ['order_id' => $orderId] + $paymentsChunk[$tempKey];
            }
            unset($orderItemsChunk[$tempKey], $paymentsChunk[$tempKey]);
        }

        // 3. Вставляем связанные данные
        $this->insertChunk($pdo, 'order_items', $finalOrderItems);
        $this->insertChunk($pdo, 'payments', $finalPayments);
    }
}