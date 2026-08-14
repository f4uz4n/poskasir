<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->string('api_token', 64)->nullable()->unique()->after('remote_monitor_token');
            $table->timestamp('api_token_created_at')->nullable()->after('api_token');
        });
    }

    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn(['api_token', 'api_token_created_at']);
        });
    }
};
