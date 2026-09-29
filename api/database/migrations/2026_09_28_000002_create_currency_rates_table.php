<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->char('base_currency', 3)->default('USD');
            $table->char('currency', 3);
            $table->decimal('rate', 15, 6);
            $table->timestamps();

            $table->unique(['company_id', 'base_currency', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_rates');
    }
};
