<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rental_agreement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->string('billing_period')->default('day');
            $table->dateTime('returned_at')->nullable();
            $table->string('return_condition')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'rental_agreement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_items');
    }
};
