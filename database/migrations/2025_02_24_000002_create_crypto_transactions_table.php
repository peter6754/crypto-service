<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crypto_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained('crypto_wallets')->cascadeOnDelete();
            $table->string('type');
            $table->string('currency', 10);
            $table->decimal('amount', 20, 8);
            $table->decimal('balance_before', 20, 8);
            $table->decimal('balance_after', 20, 8);
            $table->string('tx_hash')->nullable();
            $table->string('status')->default('pending');
            $table->string('external_id')->nullable()->unique();
            $table->text('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('tx_hash');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crypto_transactions');
    }
};
