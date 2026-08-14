<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PosSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Gratis',
                'slug' => 'gratis',
                'description' => 'Operasi POS penuh untuk 1 akun pemilik. Selamanya gratis.',
                'price' => 0,
                'duration_days' => 0,
                'is_free' => true,
                'features' => [
                    'POS kasir full',
                    'Offline + PWA',
                    'Printer Bluetooth/USB',
                    'Barcode scanner',
                    'Laporan penjualan & HPP (lokal)',
                    'Backup & restore',
                    '1 akun saja (pemilik)',
                ],
                'feature_flags' => [
                    'multi_kasir' => false,
                    'remote_laporan' => false,
                    'kunci_stok' => false,
                ],
                'sort_order' => 1,
            ],
            [
                'name' => 'Berbayar Bulanan',
                'slug' => 'berbayar-bulanan',
                'description' => 'Pantau laporan jarak jauh, kunci stok, dan multi akun kasir.',
                'price' => 99000,
                'duration_days' => 30,
                'is_free' => false,
                'features' => [
                    'Semua fitur Gratis',
                    'Pantau laporan jarak jauh',
                    'Kunci stok',
                    'Daftar akun kasir (multi user)',
                    'API sinkron (produk, penjualan, laporan)',
                ],
                'feature_flags' => [
                    'multi_kasir' => true,
                    'remote_laporan' => true,
                    'kunci_stok' => true,
                    'api_sync' => true,
                ],
                'sort_order' => 2,
            ],
            [
                'name' => 'Berbayar Tahunan',
                'slug' => 'berbayar-tahunan',
                'description' => 'Hemat — bayar sekali setahun, fitur berbayar penuh.',
                'price' => 990000,
                'duration_days' => 365,
                'is_free' => false,
                'features' => [
                    'Semua fitur berbayar',
                    'API sinkron lengkap',
                    'Hemat 2 bulan',
                    'Prioritas dukungan',
                ],
                'feature_flags' => [
                    'multi_kasir' => true,
                    'remote_laporan' => true,
                    'kunci_stok' => true,
                    'api_sync' => true,
                ],
                'sort_order' => 3,
            ],
        ];

        // Nonaktifkan paket lama
        SubscriptionPlan::whereNotIn('slug', collect($plans)->pluck('slug'))->update(['is_active' => false]);

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(['slug' => $plan['slug']], $plan + ['is_active' => true]);
        }

        $user = User::updateOrCreate(
            ['email' => 'admin@poskasir.test'],
            [
                'name' => 'Admin Toko',
                'password' => Hash::make('password'),
                'phone' => '081234567890',
                'store_name' => 'Toko Sejahtera',
                'store_address' => 'Jl. Merdeka No. 10, Jakarta',
                'role' => 'owner',
                'owner_id' => null,
            ]
        );

        StoreSetting::updateOrCreate(
            ['user_id' => $user->id],
            [
                'store_name' => 'Toko Sejahtera',
                'store_phone' => '081234567890',
                'store_address' => 'Jl. Merdeka No. 10, Jakarta',
                'receipt_header' => 'Toko Sejahtera',
                'receipt_footer' => 'Terima kasih — datang kembali!',
                'tax_percent' => 0,
                'printer_type' => 'bluetooth',
                'paper_width' => 58,
            ]
        );

        $gratis = SubscriptionPlan::where('slug', 'gratis')->first();
        $user->subscriptions()->where('status', 'active')->update(['status' => 'expired']);
        Subscription::updateOrCreate(
            ['user_id' => $user->id, 'subscription_plan_id' => $gratis->id],
            [
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => null,
            ]
        );

        $categories = [
            'Minuman' => [
                ['name' => 'Air Mineral 600ml', 'barcode' => '8999999000011', 'price' => 3000, 'stock' => 100],
                ['name' => 'Teh Botol', 'barcode' => '8999999000012', 'price' => 5000, 'stock' => 80],
                ['name' => 'Kopi Susu', 'barcode' => '8999999000013', 'price' => 12000, 'stock' => 50],
            ],
            'Snack' => [
                ['name' => 'Keripik Kentang', 'barcode' => '8999999000021', 'price' => 8000, 'stock' => 60],
                ['name' => 'Biskuit Coklat', 'barcode' => '8999999000022', 'price' => 7000, 'stock' => 70],
                ['name' => 'Permen Mint', 'barcode' => '8999999000023', 'price' => 2000, 'stock' => 200],
            ],
            'Sembako' => [
                ['name' => 'Beras 1kg', 'barcode' => '8999999000031', 'price' => 15000, 'stock' => 40],
                ['name' => 'Gula 1kg', 'barcode' => '8999999000032', 'price' => 16000, 'stock' => 35],
                ['name' => 'Minyak Goreng 1L', 'barcode' => '8999999000033', 'price' => 18000, 'stock' => 30],
            ],
        ];

        foreach ($categories as $catName => $items) {
            $category = Category::updateOrCreate(
                ['user_id' => $user->id, 'name' => $catName],
                ['slug' => strtolower($catName).'-'.$user->id, 'is_active' => true]
            );

            foreach ($items as $item) {
                Product::updateOrCreate(
                    ['user_id' => $user->id, 'barcode' => $item['barcode']],
                    [
                        'category_id' => $category->id,
                        'name' => $item['name'],
                        'sku' => 'SKU-'.substr($item['barcode'], -4),
                        'price' => $item['price'],
                        'cost' => $item['price'] * 0.7,
                        'stock' => $item['stock'],
                        'unit' => 'pcs',
                        'is_active' => true,
                    ]
                );
            }
        }

        User::updateOrCreate(
            ['email' => 'developer@poskasir.test'],
            [
                'name' => 'Developer POS',
                'password' => Hash::make('password'),
                'phone' => null,
                'store_name' => 'Developer Panel',
                'store_address' => null,
                'role' => 'developer',
                'owner_id' => null,
                'is_active' => true,
            ]
        );

        \App\Models\AppSetting::setMany([
            'deployment_mode' => \App\Models\AppSetting::get('deployment_mode', 'offline'),
            'recaptcha_enabled' => \App\Models\AppSetting::get('recaptcha_enabled', '0'),
            'recaptcha_site_key' => \App\Models\AppSetting::get('recaptcha_site_key', ''),
            'recaptcha_secret_key' => \App\Models\AppSetting::get('recaptcha_secret_key', ''),
        ]);
    }
}
