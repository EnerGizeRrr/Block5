<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Загрузка 50,000 пользователей ...');

        $pdo = DB::connection()->getPdo();
        $faker = Faker::create();

        $totalUsers = 50000;
        $chunkSize = 1000;
        $users = [];

        $faker->unique(true);

        for ($i = 1; $i <= $totalUsers; $i++) {
            $users[] = [
                'name' => $faker->name,
                'email' => $faker->unique()->safeEmail,
                'password' => Hash::make('password'),
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];

            if (count($users) === $chunkSize) {
                $this->insertChunk($pdo, 'users', $users);
                $users = [];
                $this->command->info("Загружено {$i} из {$totalUsers} пользователей.");
            }
        }

        if (!empty($users)) {
            $this->insertChunk($pdo, 'users', $users);
        }
    }

    private function insertChunk(\PDO $pdo, string $table, array $data): void
    {
        $columns = array_keys($data[0]);
        $columnList = implode(',', $columns);
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $valuesPlaceholder = implode(',', array_fill(0, count($data), "($placeholders)"));

        $sql = "INSERT INTO {$table} ({$columnList}) VALUES {$valuesPlaceholder}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge(...array_map('array_values', $data)));
    }
}