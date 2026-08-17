<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('manual_verified_by')->nullable()->after('email_verified_at')->constrained('users')->nullOnDelete();
            $table->timestamp('manual_verified_at')->nullable()->after('manual_verified_by');
            $table->text('admin_notes')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manual_verified_by');
            $table->dropColumn(['manual_verified_at', 'admin_notes']);
        });
    }
};
