<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title')->nullable();
            $table->text('prompt');
            $table->longText('content')->nullable();
            $table->string('template')->nullable();
            $table->string('language')->default('en');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('tokens')->nullable();
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
        Schema::dropIfExists('ai_documents');
    }
};
