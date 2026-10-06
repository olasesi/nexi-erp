<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs the dashboard "Pending Shipments" card: invoices can now track a
     * fulfilment state, when they left, their tracking reference, and who was
     * responsible. Sales orders gain created_by for the "Added By" column.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('shipping_status')->default('pending')->after('status');
            $table->timestamp('shipped_at')->nullable()->after('shipping_status');
            $table->string('tracking_number')->nullable()->after('shipped_at');
            $table->foreignId('service_staff_id')->nullable()->after('tracking_number')
                ->constrained('users')->nullOnDelete();

            $table->index(['company_id', 'shipping_status']);
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('contact_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'shipping_status']);
            $table->dropConstrainedForeignId('service_staff_id');
            $table->dropColumn(['shipping_status', 'shipped_at', 'tracking_number']);
        });
    }
};
