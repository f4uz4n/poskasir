<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('unit_code', 10)->nullable()->after('amount');
            $table->decimal('expected_amount', 15, 2)->nullable()->after('unit_code');
            $table->string('bank_transaction_ref')->nullable()->unique()->after('expected_amount');
            $table->timestamp('email_verified_at')->nullable()->after('paid_at');
            $table->timestamp('expires_at')->nullable()->after('email_verified_at');
        });

        Schema::create('payment_email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('message_uid')->unique();
            $table->string('bank_transaction_ref')->nullable()->index();
            $table->decimal('amount', 15, 2)->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status'); // matched, duplicate, unmatched, skipped
            $table->text('raw_snippet')->nullable();
            $table->timestamp('email_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_email_logs');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'unit_code',
                'expected_amount',
                'bank_transaction_ref',
                'email_verified_at',
                'expires_at',
            ]);
        });
    }
};
