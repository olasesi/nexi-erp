<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('prompt');
            $table->text('negative_prompt')->nullable();
            $table->string('style')->nullable();
            $table->string('size')->default('512x512');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->text('url')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('generated_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_images');
    }
};
