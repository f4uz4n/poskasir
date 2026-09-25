<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 40)->unique();
            $table->string('title')->nullable();
            $table->decimal('amount', 15, 2);
            $table->enum('status', ['active', 'used', 'cancelled', 'expired'])->default('active')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('used_at')->nullable();
            $table->foreignId('used_on_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'voucher_id')) {
                $table->foreignId('voucher_id')->nullable()->after('payment_method')->constrained('vouchers')->nullOnDelete();
            }
            if (! Schema::hasColumn('transactions', 'voucher_code')) {
                $table->string('voucher_code', 40)->nullable()->after('voucher_id');
            }
            if (! Schema::hasColumn('transactions', 'voucher_amount')) {
                $table->decimal('voucher_amount', 15, 2)->nullable()->after('voucher_code');
            }
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE transactions MODIFY payment_method ENUM('cash','qris','transfer','card','credit','voucher','other') NOT NULL DEFAULT 'cash'");
        }
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'voucher_amount')) {
                $table->dropColumn('voucher_amount');
            }
            if (Schema::hasColumn('transactions', 'voucher_code')) {
                $table->dropColumn('voucher_code');
            }
            if (Schema::hasColumn('transactions', 'voucher_id')) {
                $table->dropConstrainedForeignId('voucher_id');
            }
        });

        Schema::dropIfExists('vouchers');

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE transactions MODIFY payment_method ENUM('cash','qris','transfer','card','credit','other') NOT NULL DEFAULT 'cash'");
        }
    }
};
