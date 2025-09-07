<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\GiftCode;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator',
                'email' => 'admin@example.com',
                'password' => Hash::make('admin123@'),
                'role' => 'admin',
                'phone' => '11999999999',
            ]
        );

        // Create sample categories
        $categories = [
            [
                'name' => 'Google Play',
                'slug' => 'google-play',
                'description' => 'Gift Cards da Google Play Store',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Netflix',
                'slug' => 'netflix',
                'description' => 'Gift Cards Netflix para assinar ou renovar sua conta',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Free Fire',
                'slug' => 'free-fire',
                'description' => 'Diamantes para Free Fire',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Xbox',
                'slug' => 'xbox',
                'description' => 'Gift Cards Xbox Live',
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($categories as $categoryData) {
            Category::firstOrCreate(
                ['slug' => $categoryData['slug']],
                $categoryData
            );
        }

        // Create sample products
        $googlePlay = Category::where('slug', 'google-play')->first();
        $netflix = Category::where('slug', 'netflix')->first();

        $products = [
            [
                'category_id' => $googlePlay->id,
                'name' => 'Google Play Gift Card',
                'slug' => 'google-play-gift-card',
                'description' => 'Use para comprar apps, jogos, filmes e muito mais na Google Play Store',
                'price_options' => [
                    ['value' => 10, 'price' => 12.00],
                    ['value' => 25, 'price' => 27.50],
                    ['value' => 50, 'price' => 55.00],
                    ['value' => 100, 'price' => 110.00],
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'category_id' => $netflix->id,
                'name' => 'Netflix Gift Card',
                'slug' => 'netflix-gift-card',
                'description' => 'Assine ou renove sua conta Netflix',
                'price_options' => [
                    ['value' => 25, 'price' => 27.50],
                    ['value' => 50, 'price' => 55.00],
                    ['value' => 100, 'price' => 110.00],
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
        ];

        foreach ($products as $productData) {
            $product = Product::firstOrCreate(
                ['slug' => $productData['slug']],
                $productData
            );

            // Create sample gift codes for each product
            foreach ($productData['price_options'] as $priceOption) {
                for ($i = 1; $i <= 3; $i++) {
                    GiftCode::firstOrCreate([
                        'product_id' => $product->id,
                        'code' => strtoupper($productData['slug']) . '-' . $priceOption['value'] . '-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                        'value' => $priceOption['value'],
                        'status' => 'available',
                    ]);
                }
            }
        }

        $this->command->info('Admin user and sample data created successfully!');
        $this->command->info('Admin credentials: admin@example.com / admin123@');
    }
}
