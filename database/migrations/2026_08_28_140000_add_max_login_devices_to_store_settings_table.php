<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('store_settings', 'max_login_devices')) {
            Schema::table('store_settings', function (Blueprint $table) {
                $table->unsignedTinyInteger('max_login_devices')->default(1)->after('offline_enabled');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('store_settings', 'max_login_devices')) {
            Schema::table('store_settings', function (Blueprint $table) {
                $table->dropColumn('max_login_devices');
            });
        }
    }
};
