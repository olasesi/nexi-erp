<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_health_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('score_date');
            $table->unsignedTinyInteger('overall_score')->nullable();
            $table->unsignedTinyInteger('financial_score')->nullable();
            $table->unsignedTinyInteger('sales_score')->nullable();
            $table->unsignedTinyInteger('operations_score')->nullable();
            $table->unsignedTinyInteger('team_score')->nullable();
            $table->unsignedTinyInteger('projects_score')->nullable();
            $table->json('signals')->nullable();
            $table->json('alerts')->nullable();
            $table->json('insights')->nullable();
            $table->json('recommendations')->nullable();
            $table->string('generated_by')->default('rules');
            $table->timestamps();

            $table->unique(['company_id', 'score_date']);
            $table->index(['company_id', 'score_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_health_snapshots');
    }
};
