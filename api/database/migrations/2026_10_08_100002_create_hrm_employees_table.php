<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hrm_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('employee_code');
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('gender')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('hrm_departments')->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained('hrm_designations')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('hrm_employees')->nullOnDelete();
            $table->date('date_of_birth')->nullable();
            $table->date('date_of_joining')->nullable();
            $table->string('employment_status')->default('active');
            $table->decimal('salary', 15, 2)->nullable();
            $table->string('address', 500)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'employee_code']);
            $table->index(['company_id', 'employment_status']);
            $table->index(['company_id', 'department_id']);
            $table->index(['company_id', 'designation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_employees');
    }
};
