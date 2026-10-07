<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('organization')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('source')->default('other');
            $table->string('status')->default('new');
            $table->decimal('value', 15, 2)->default(0);
            $table->date('expected_close_date')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'source']);
            $table->index(['company_id', 'next_follow_up_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_leads');
    }
};
