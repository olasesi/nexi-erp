<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Namespace the settings store so application-level settings ("app") and
     * business settings ("business") can coexist without key collisions.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('namespace', 20)->default('app')->after('id');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'group', 'key']);
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->unique(['company_id', 'namespace', 'group', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'namespace', 'group', 'key']);
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->unique(['company_id', 'group', 'key']);
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('namespace');
        });
    }
};
