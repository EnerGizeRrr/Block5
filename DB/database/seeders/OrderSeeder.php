<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding orders, order items, and payments...');

        DB::disableQueryLog();
        Order::unsetEventDispatcher();

        $userIds = User::pluck('id');
        $products = Product::all(); 

        $ordersToCreate = 100_000;
        $itemsToCreate = 200_000;
        $itemsPerOrder = floor($itemsToCreate / $ordersToCreate);

        for ($i = 0; $i < $ordersToCreate; $i++) {
            $order = Order::factory()->create([
                'user_id' => $userIds->random(),
            ]);

            $orderItems = [];
            $totalAmount = 0;

            for ($j = 0; $j < $itemsPerOrder; $j++) {
                $product = $products->random(); 
                $qty = rand(1, 3);
                $price = $product->price; 
                $totalAmount += $price * $qty;

                $orderItems[] = [
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'qty' => $qty,
                    'price' => $price,
                    'created_at' => $order->created_at,
                    'updated_at' => $order->created_at,
                ];
            }

            OrderItem::insert($orderItems);

            $order->total_amount = $totalAmount;
            $order->save();

            if ($order->status === 'paid') {
                Payment::create([
                    'order_id' => $order->id,
                    'status' => 'paid',
                    'provider' => ['stripe', 'paypal', 'cash'][array_rand(['stripe', 'paypal', 'cash'])],
                    'created_at' => $order->created_at,
                ]);
            } elseif ($order->status === 'new') {
                $paymentStatus = ['failed', 'pending'][array_rand(['failed', 'pending'])];

                if (rand(1, 2) === 1) {
                    Payment::create([
                        'order_id' => $order->id,
                        'status' => $paymentStatus,
                        'provider' => ['stripe', 'paypal'][array_rand(['stripe', 'paypal'])],
                        'created_at' => $order->created_at,
                    ]);
                }
            }


            if ($i % 1000 === 0) {
                $this->command->info("Seeded {$i} of {$ordersToCreate} orders.");
            }
        }
    }
}