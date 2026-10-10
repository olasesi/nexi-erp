<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('ticket_number');
            $table->string('subject');
            $table->text('description')->nullable();
            $table->string('status')->default('open');
            $table->string('priority')->default('medium');
            $table->string('category')->nullable();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'ticket_number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
