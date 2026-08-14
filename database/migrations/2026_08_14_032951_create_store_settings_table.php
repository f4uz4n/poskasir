<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('store_name')->nullable();
            $table->string('store_phone')->nullable();
            $table->text('store_address')->nullable();
            $table->string('receipt_header')->nullable();
            $table->string('receipt_footer')->nullable();
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->string('currency')->default('IDR');
            $table->string('printer_type')->default('bluetooth');
            $table->string('printer_name')->nullable();
            $table->integer('paper_width')->default(58);
            $table->boolean('offline_enabled')->default(false);
            $table->timestamp('offline_installed_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('extra')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_settings');
    }
};
