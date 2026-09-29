<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE payments MODIFY method ENUM('cash', 'bank_transfer', 'credit_card', 'check', 'other', 'payment_link') NOT NULL DEFAULT 'bank_transfer'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE payments MODIFY method ENUM('cash', 'bank_transfer', 'credit_card', 'check', 'other') NOT NULL DEFAULT 'bank_transfer'");
    }
};
