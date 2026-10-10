<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_category_id')->nullable()->constrained('asset_categories')->nullOnDelete();
            $table->string('asset_tag');
            $table->string('name');
            $table->string('serial_number')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 12, 2)->default(0);
            $table->decimal('current_value', 12, 2)->default(0);
            $table->string('condition')->default('good');
            $table->string('status')->default('available');
            $table->string('location')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'asset_tag']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'asset_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
