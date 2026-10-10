<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('new');
            $table->json('payload');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['form_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
    }
};
