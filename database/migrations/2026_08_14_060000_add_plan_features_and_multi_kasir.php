<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->boolean('is_free')->default(false)->after('is_active');
            $table->json('feature_flags')->nullable()->after('features');
        });

        Schema::table('store_settings', function (Blueprint $table) {
            $table->boolean('stock_lock_enabled')->default(false)->after('offline_enabled');
            $table->boolean('remote_monitor_enabled')->default(false)->after('stock_lock_enabled');
            $table->string('remote_monitor_token', 64)->nullable()->unique()->after('remote_monitor_enabled');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('cashier_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('stock_locked')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('stock_locked');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cashier_id');
        });

        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn(['stock_lock_enabled', 'remote_monitor_enabled', 'remote_monitor_token']);
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['is_free', 'feature_flags']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
        });
    }
};
