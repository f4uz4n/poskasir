<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('owner','kasir','admin','administrator','keuangan','developer') NOT NULL DEFAULT 'owner'");
        }

        if (! Schema::hasColumn('transactions', 'voided_by')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->foreignId('voided_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('transactions', 'voided_at')) {
            Schema::table('transactions', function (Blueprint $table) {
                $after = Schema::hasColumn('transactions', 'voided_by') ? 'voided_by' : 'status';
                $table->timestamp('voided_at')->nullable()->after($after);
            });
        }

        if (! Schema::hasColumn('transactions', 'void_reason')) {
            Schema::table('transactions', function (Blueprint $table) {
                $after = Schema::hasColumn('transactions', 'voided_at')
                    ? 'voided_at'
                    : (Schema::hasColumn('transactions', 'voided_by') ? 'voided_by' : 'status');
                $table->text('void_reason')->nullable()->after($after);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('transactions', 'void_reason')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn('void_reason');
            });
        }

        if (Schema::hasColumn('transactions', 'voided_at')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn('voided_at');
            });
        }

        if (Schema::hasColumn('transactions', 'voided_by')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('voided_by');
            });
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('owner','kasir','admin','developer') NOT NULL DEFAULT 'owner'");
        }
    }
};
