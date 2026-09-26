<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('method', 20); // cash,qris,transfer,card,voucher,other
            $table->decimal('amount', 15, 2);
            $table->foreignId('voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            $table->string('voucher_code', 40)->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['transaction_id', 'method']);
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE transactions MODIFY payment_method ENUM('cash','qris','transfer','card','credit','voucher','mixed','other') NOT NULL DEFAULT 'cash'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_payments');

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE transactions MODIFY payment_method ENUM('cash','qris','transfer','card','credit','voucher','other') NOT NULL DEFAULT 'cash'");
        }
    }
};
