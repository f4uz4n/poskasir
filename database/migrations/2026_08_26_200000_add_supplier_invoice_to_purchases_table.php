<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('purchases', 'supplier_invoice')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->string('supplier_invoice')->nullable()->after('notes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('purchases', 'supplier_invoice')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->dropColumn('supplier_invoice');
            });
        }
    }
};
