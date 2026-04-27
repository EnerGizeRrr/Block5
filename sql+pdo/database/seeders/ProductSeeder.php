<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Загрузка 20,000 продуктов ...');

        $pdo = DB::connection()->getPdo();
        $faker = Faker::create();

        $totalProducts = 20000;
        $chunkSize = 1000;
        $products = [];

        for ($i = 1; $i <= $totalProducts; $i++) {
            $products[] = [
                'name' => $faker->words(3, true),
                'slug' => Str::slug($faker->unique()->words(3, true)),
                'description' => $faker->paragraph,
                'price' => $faker->randomFloat(2, 10, 1000),
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];

            if (count($products) === $chunkSize) {
                $this->insertChunk($pdo, 'products', $products);
                $products = [];
                $this->command->info("Загружено {$i} из {$totalProducts} продуктов.");
            }
        }

        if (!empty($products)) {
            $this->insertChunk($pdo, 'products', $products);
        }
    }

    private function insertChunk(\PDO $pdo, string $table, array $data): void
    {
        if (empty($data)) {
            return;
        }
        $columns = array_keys($data[0]);
        $columnList = implode(',', $columns);
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $valuesPlaceholder = implode(',', array_fill(0, count($data), "($placeholders)"));

        $sql = "INSERT INTO {$table} ({$columnList}) VALUES {$valuesPlaceholder}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge(...array_map('array_values', $data)));
    }
}