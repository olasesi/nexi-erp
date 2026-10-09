<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_order_id')->constrained()->cascadeOnDelete();
            // A split-tender order has one row per method (cash + card, …).
            $table->string('method')->default('cash');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['pos_order_id', 'method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_payments');
    }
};
