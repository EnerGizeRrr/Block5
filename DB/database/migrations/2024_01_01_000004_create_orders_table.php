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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->enum('status', ['new', 'paid', 'cancelled'])->default('new');
            $table->decimal('total_amount', 12, 2)->unsigned()->default(0);
            $table->timestamps();
        });
    }
};