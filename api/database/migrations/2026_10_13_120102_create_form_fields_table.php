<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->string('label');
            $table->string('name');
            $table->string('type')->default('text');
            $table->string('placeholder')->nullable();
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['form_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_fields');
    }
};
