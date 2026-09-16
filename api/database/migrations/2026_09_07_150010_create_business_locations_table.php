<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('location_id')->nullable();
            $table->string('landmark')->nullable();
            $table->string('city')->nullable();
            $table->string('zip_code')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('price_group')->nullable();
            $table->string('invoice_scheme')->nullable();
            $table->string('invoice_layout_for_pos')->nullable();
            $table->string('invoice_layout_for_sale')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'location_id']);
            $table->index(['company_id', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_locations');
    }
};
