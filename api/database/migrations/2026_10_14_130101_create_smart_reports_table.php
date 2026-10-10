<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smart_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('source');
            $table->json('columns')->nullable();
            $table->json('filters')->nullable();
            $table->string('group_by')->nullable();
            $table->string('aggregate')->nullable();
            $table->string('aggregate_field')->nullable();
            $table->string('chart_type')->default('table');
            $table->boolean('is_shared')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_reports');
    }
};
