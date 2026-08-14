<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('order_type', ['dine_in', 'takeaway'])->default('dine_in')->after('customer_name');
            $table->string('table_number')->nullable()->after('order_type');
        });

        Schema::table('transaction_items', function (Blueprint $table) {
            $table->decimal('cost', 15, 2)->default(0)->after('price');
        });

        // Isi HPP dari harga modal produk untuk transaksi lama
        if (Schema::hasTable('products')) {
            DB::statement('
                UPDATE transaction_items
                SET cost = COALESCE((
                    SELECT products.cost FROM products WHERE products.id = transaction_items.product_id
                ), 0)
                WHERE product_id IS NOT NULL
            ');
        }
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['order_type', 'table_number']);
        });

        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropColumn('cost');
        });
    }
};
