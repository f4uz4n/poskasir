<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('store_name')->nullable()->after('phone');
            $table->string('store_address')->nullable()->after('store_name');
            $table->enum('role', ['owner', 'kasir', 'admin'])->default('owner')->after('store_address');
            $table->boolean('is_active')->default(true)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'store_name', 'store_address', 'role', 'is_active']);
        });
    }
};
