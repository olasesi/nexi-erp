<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('status')->default('configuration_required');
            $table->boolean('is_connected')->default(false);
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
