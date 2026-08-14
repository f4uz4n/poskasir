<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->text('receipt_footer')->nullable()->change();
            $table->text('receipt_header')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->string('receipt_header')->nullable()->change();
            $table->string('receipt_footer')->nullable()->change();
        });
    }
};
