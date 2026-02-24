<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crypto_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 10)->default('BTC'); // BTC, ETH, USDT, etc.
            $table->decimal('balance', 20, 8)->default(0);
            $table->decimal('locked_balance', 20, 8)->default(0); // Заблокировано для выводов
            $table->timestamps();

            $table->unique(['user_id', 'currency']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crypto_wallets');
    }
};
