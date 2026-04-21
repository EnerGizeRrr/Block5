<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Индексы для таблицы orders
        Schema::table('orders', function (Blueprint $table) {
            // Ускоряет фильтрацию по статусу и дате
            $table->index(['status', 'created_at']);
            // Ускоряет выборку заказов конкретного пользователя с сортировкой по дате.
            $table->index(['user_id', 'created_at', 'id']);
            // Ускоряет сортировку и фильтрацию по дате по всей таблице
            $table->index(['created_at', 'id']);
        });

        // Индексы для order_items
        Schema::table('order_items', function (Blueprint $table) {
            // Ускоряет по дате
            $table->index('created_at');
        });

        // Индексы для payments
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['user_id', 'created_at', 'id']);
            $table->dropIndex(['created_at', 'id']);
        });
        Schema::table('order_items', function (Blueprint $table) { $table->dropIndex(['created_at']); });
        Schema::table('payments', function (Blueprint $table) { $table->dropIndex(['status', 'created_at']); });
    }
};