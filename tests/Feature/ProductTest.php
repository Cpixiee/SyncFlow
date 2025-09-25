<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\LoginUser;
use App\Models\Quarter;
use App\Models\ProductCategory;
use App\Models\Product;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $adminToken;
    protected $quarter;
    protected $productCategory;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin user
        $this->adminUser = LoginUser::factory()->create([
            'username' => 'testadmin',
            'role' => 'admin',
            'password' => bcrypt('testpassword')
        ]);
        
        // Generate JWT token
        $this->adminToken = JWTAuth::fromUser($this->adminUser);
        
        // Create test quarter
        $this->quarter = Quarter::create([
            'name' => 'Q4',
            'year' => 2024,
            'start_month' => 10,
            'end_month' => 12,
            'start_date' => '2024-10-01',
            'end_date' => '2024-12-31',
            'is_active' => true
        ]);
        
        // Create test product category
        $this->productCategory = ProductCategory::create([
            'name' => 'Tube Test',
            'products' => ['VO', 'COT', 'COTO'],
            'description' => 'Test category'
        ]);
    }

    public function test_can_create_simple_product()
    {
        $productData = [
            'basic_info' => [
                'product_category_id' => $this->productCategory->id,
                'product_name' => 'VO',
                'ref_spec_number' => 'SPEC-001'
            ],
            'measurement_points' => [
                [
                    'setup' => [
                        'name' => 'Inside Diameter',
                        'name_id' => 'inside_diameter',
                        'sample_amount' => 5,
                        'source' => 'MANUAL',
                        'type' => 'SINGLE',
                        'nature' => 'QUANTITATIVE'
                    ],
                    'evaluation_type' => 'PER_SAMPLE',
                    'evaluation_setting' => [
                        'per_sample_setting' => [
                            'is_raw_data' => true
                        ]
                    ],
                    'rule_evaluation_setting' => [
                        'rule' => 'BETWEEN',
                        'unit' => 'mm',
                        'value' => 14.4,
                        'tolerance_minus' => 0.3,
                        'tolerance_plus' => 0.3
                    ]
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type' => 'application/json'
        ])->postJson('/api/v1/products', $productData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'http_code',
                    'message',
                    'error_id',
                    'data' => [
                        'product_id',
                        'basic_info',
                        'measurement_points'
                    ]
                ]);

        $this->assertDatabaseHas('products', [
            'product_name' => 'VO',
            'product_category_id' => $this->productCategory->id
        ]);
    }

    public function test_can_get_product_by_id()
    {
        $product = Product::create([
            'quarter_id' => $this->quarter->id,
            'product_category_id' => $this->productCategory->id,
            'product_name' => 'VO',
            'measurement_points' => [
                [
                    'setup' => [
                        'name' => 'Test Measurement',
                        'name_id' => 'test_measurement'
                    ]
                ]
            ]
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->getJson('/api/v1/products/' . $product->product_id);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'http_code',
                    'message', 
                    'error_id',
                    'data' => [
                        'id',
                        'basic_info',
                        'measurement_points'
                    ]
                ]);
    }

    public function test_can_get_products_list()
    {
        // Create test products
        Product::factory()->count(3)->create([
            'quarter_id' => $this->quarter->id,
            'product_category_id' => $this->productCategory->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->getJson('/api/v1/products?page=1&limit=10');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'http_code',
                    'message',
                    'error_id',
                    'data' => [
                        'docs',
                        'metadata'
                    ]
                ]);
    }

    public function test_can_check_product_exists()
    {
        Product::create([
            'quarter_id' => $this->quarter->id,
            'product_category_id' => $this->productCategory->id,
            'product_name' => 'VO',
            'ref_spec_number' => 'SPEC-001',
            'measurement_points' => []
        ]);

        $checkData = [
            'product_category_id' => $this->productCategory->id,
            'product_name' => 'VO',
            'ref_spec_number' => 'SPEC-001'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->getJson('/api/v1/products/is-product-exists?' . http_build_query($checkData));

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        'is_product_exists' => true
                    ]
                ]);
    }

    public function test_can_get_product_categories()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->getJson('/api/v1/products/categories');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'http_code',
                    'message',
                    'error_id',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'products',
                            'description'
                        ]
                    ]
                ]);
    }

    public function test_validation_error_on_invalid_product_data()
    {
        $invalidData = [
            'basic_info' => [
                'product_category_id' => 999, // Invalid ID
                'product_name' => ''  // Empty name
            ],
            'measurement_points' => [] // Empty array
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type' => 'application/json'
        ])->postJson('/api/v1/products', $invalidData);

        $response->assertStatus(400)
                ->assertJsonStructure([
                    'http_code',
                    'message',
                    'error_id',
                    'data'
                ]);
    }

    public function test_unauthorized_access_without_token()
    {
        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(401);
    }

    public function test_quarter_relationship()
    {
        $product = Product::create([
            'quarter_id' => $this->quarter->id,
            'product_category_id' => $this->productCategory->id,
            'product_name' => 'VO',
            'measurement_points' => []
        ]);

        $this->assertEquals($this->quarter->id, $product->quarter->id);
        $this->assertEquals('Q4 2024', $product->quarter->display_name);
    }

    public function test_product_category_relationship()
    {
        $product = Product::create([
            'quarter_id' => $this->quarter->id,
            'product_category_id' => $this->productCategory->id,
            'product_name' => 'VO',
            'measurement_points' => []
        ]);

        $this->assertEquals($this->productCategory->id, $product->productCategory->id);
        $this->assertEquals('Tube Test', $product->productCategory->name);
    }

    public function test_auto_generate_product_id()
    {
        $product = Product::create([
            'quarter_id' => $this->quarter->id,
            'product_category_id' => $this->productCategory->id,
            'product_name' => 'VO',
            'measurement_points' => []
        ]);

        $this->assertNotEmpty($product->product_id);
        $this->assertStringStartsWith('PRD-', $product->product_id);
        $this->assertEquals(12, strlen($product->product_id)); // PRD- + 8 chars
    }
}