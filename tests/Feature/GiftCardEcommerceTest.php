<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\GiftCode;
use App\Models\Order;
use Illuminate\Support\Facades\Hash;

class GiftCardEcommerceTest extends TestCase
{
    use RefreshDatabase;

    private $adminUser;
    private $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users
        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => Hash::make('password123@'),
            'role' => 'admin',
        ]);
        
        $this->regularUser = User::create([
            'name' => 'Regular User',
            'email' => 'user@test.com',
            'password' => Hash::make('password123@'),
            'role' => 'user',
        ]);
        
        // Create test data
        $this->createTestData();
    }

    private function createTestData()
    {
        // Create category
        $category = Category::create([
            'name' => 'Google Play',
            'slug' => 'google-play',
            'description' => 'Google Play Gift Cards',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Create product
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Google Play Gift Card',
            'slug' => 'google-play-gift-card',
            'description' => 'Digital Google Play Gift Card',
            'price_options' => [
                ['value' => 10, 'price' => 12.00],
                ['value' => 25, 'price' => 27.50],
            ],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Create gift codes
        for ($i = 1; $i <= 5; $i++) {
            GiftCode::create([
                'product_id' => $product->id,
                'value' => 10,
                'code' => 'TEST-10-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'status' => 'available',
            ]);
        }
        
        for ($i = 1; $i <= 3; $i++) {
            GiftCode::create([
                'product_id' => $product->id,
                'value' => 25,
                'code' => 'TEST-25-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'status' => 'available',
            ]);
        }
    }

    public function test_user_registration()
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'New User',
            'email' => 'new@test.com',
            'password' => 'StrongPass123@',
            'password_confirmation' => 'StrongPass123@',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'role'],
                'access_token',
                'token_type'
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'new@test.com',
            'role' => 'user'
        ]);
    }

    public function test_user_login()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@test.com',
            'password' => 'password123@',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'role'],
                'access_token',
                'token_type'
            ]);
    }

    public function test_guest_can_view_categories()
    {
        $response = $this->getJson('/api/categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'categories' => [
                    '*' => ['id', 'name', 'slug', 'description', 'is_active']
                ]
            ]);
    }

    public function test_guest_can_view_products()
    {
        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug', 'description', 'price_options', 'category']
                ]
            ]);
    }

    public function test_admin_can_create_product()
    {
        $token = $this->adminUser->createToken('test')->plainTextToken;
        $category = Category::first();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/products', [
            'category_id' => $category->id,
            'name' => 'Netflix Gift Card',
            'description' => 'Digital Netflix Gift Card',
            'price_options' => [
                ['value' => 50, 'price' => 55.00]
            ],
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'product' => ['id', 'name', 'slug', 'price_options']
            ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Netflix Gift Card',
            'slug' => 'netflix-gift-card'
        ]);
    }

    public function test_regular_user_cannot_create_product()
    {
        $token = $this->regularUser->createToken('test')->plainTextToken;
        $category = Category::first();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/products', [
            'category_id' => $category->id,
            'name' => 'Xbox Gift Card',
            'description' => 'Digital Xbox Gift Card',
            'price_options' => [
                ['value' => 30, 'price' => 35.00]
            ],
        ]);

        $response->assertStatus(403);
    }

    public function test_authenticated_user_can_create_order()
    {
        $token = $this->regularUser->createToken('test')->plainTextToken;
        $product = Product::first();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/orders', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'value' => 10,
                    'quantity' => 2,
                ]
            ],
            'payment_method' => 'pix',
            'customer_name' => 'Test Customer',
            'customer_email' => 'customer@test.com',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'order' => [
                    'id', 'order_number', 'status', 'total_amount', 
                    'payment_method', 'order_items'
                ]
            ]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->regularUser->id,
            'status' => 'pending',
            'payment_method' => 'pix',
            'total_amount' => 24.00, // 2 * 12.00
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'gift_card_value' => 10,
            'quantity' => 2,
            'total_price' => 24.00,
        ]);
    }

    public function test_order_creation_fails_with_insufficient_codes()
    {
        $token = $this->regularUser->createToken('test')->plainTextToken;
        $product = Product::first();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/orders', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'value' => 10,
                    'quantity' => 10, // More than available codes
                ]
            ],
            'payment_method' => 'pix',
            'customer_name' => 'Test Customer',
            'customer_email' => 'customer@test.com',
        ]);

        $response->assertStatus(400)
            ->assertJsonStructure(['message']);
    }

    public function test_admin_can_upload_gift_codes()
    {
        $token = $this->adminUser->createToken('test')->plainTextToken;
        $product = Product::first();

        // Create a temporary CSV file
        $csvContent = "NEWCODE001\nNEWCODE002\nNEWCODE003";
        $csvFile = tmpfile();
        fwrite($csvFile, $csvContent);
        $csvPath = stream_get_meta_data($csvFile)['uri'];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/giftcodes/upload', [
            'product_id' => $product->id,
            'value' => 50,
            'csv_file' => new \Illuminate\Http\UploadedFile($csvPath, 'codes.csv', 'text/csv', null, true),
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'imported',
                'errors'
            ]);

        fclose($csvFile);
    }

    public function test_user_can_view_own_orders()
    {
        $token = $this->regularUser->createToken('test')->plainTextToken;
        
        // Create a test order
        $order = Order::create([
            'user_id' => $this->regularUser->id,
            'order_number' => 'TEST-ORDER-001',
            'status' => 'pending',
            'total_amount' => 24.00,
            'payment_method' => 'pix',
            'customer_name' => 'Test Customer',
            'customer_email' => 'customer@test.com',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/orders/user/' . $this->regularUser->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'order_number', 'status', 'total_amount']
                ]
            ]);
    }

    public function test_user_cannot_view_other_users_orders()
    {
        $token = $this->regularUser->createToken('test')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/orders/user/' . $this->adminUser->id);

        $response->assertStatus(403);
    }

    public function test_admin_can_view_all_orders()
    {
        $token = $this->adminUser->createToken('test')->plainTextToken;

        // Create a test order
        Order::create([
            'user_id' => $this->regularUser->id,
            'order_number' => 'TEST-ORDER-002',
            'status' => 'pending',
            'total_amount' => 27.50,
            'payment_method' => 'boleto',
            'customer_name' => 'Test Customer',
            'customer_email' => 'customer@test.com',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/orders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'order_number', 'status', 'total_amount', 'user']
                ]
            ]);
    }
}
