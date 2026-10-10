<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goal_key_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goal_id')->constrained('goals')->cascadeOnDelete();
            $table->string('title');
            $table->decimal('target_value', 15, 2)->default(0);
            $table->decimal('current_value', 15, 2)->default(0);
            $table->string('unit')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['goal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_key_results');
    }
};
