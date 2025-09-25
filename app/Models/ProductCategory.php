<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'products',
        'description'
    ];

    protected $casts = [
        'products' => 'array',
    ];

    /**
     * Relationship dengan products
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get product names berdasarkan kategori
     */
    public function getProductNames(): array
    {
        return $this->products ?? [];
    }

    /**
     * Predefined product categories dengan struktur hierarchical
     */
    public static function getProductCategoryStructure(): array
    {
        return [
            'Tube Test' => [
                'VO' => [],
                'COT' => [
                    'COTO',
                    'COT',
                    'COTO-FR',
                    'COT-FR',
                    'CORUTUBE → PFPY17',
                    'CORUTUBE → PFP-FR-UF-09PL',
                    'CORUTUBE → PFP-FR-HEV-YKA',
                    'CORUTUBE → PFP-FR-HEV-YSA'
                ],
                'RFCOT' => [],
                'HCOT' => []
            ],
            'Wire Test Reguler' => [
                'CAVS' => [
                    'CAVS',
                    'ACCAVS'
                ],
                'CIVUS' => [
                    'CIVUS',
                    'ACCIVUS'
                ],
                'AVSS' => [],
                'AVSSH' => [],
                'AVS' => [],
                'AV' => []
            ],
            'Shield Wire Test' => [
                'CAVSAS' => [
                    'CIVUSAS',
                    'CIVUSAS-S',
                    'CAVSAS-S',
                    'AVSSHCS',
                    'AVSSCS',
                    'AVSSCS-S'
                ]
            ]
        ];
    }

    /**
     * Get flat product list untuk kategori tertentu
     */
    public function getFlatProductList(): array
    {
        $structure = self::getProductCategoryStructure();
        $categoryName = $this->name;
        
        if (!isset($structure[$categoryName])) {
            return [];
        }

        $products = [];
        foreach ($structure[$categoryName] as $subcategory => $subproducts) {
            if (empty($subproducts)) {
                // Jika tidak ada sub-products, tambahkan subcategory sebagai product
                $products[] = $subcategory;
            } else {
                // Jika ada sub-products, tambahkan semuanya
                $products = array_merge($products, $subproducts);
            }
        }

        return array_unique($products);
    }

    /**
     * Seed default product categories
     */
    public static function seedDefaultCategories(): void
    {
        $categories = [
            [
                'name' => 'Tube Test',
                'description' => 'Kategori untuk testing tube dengan berbagai sub-kategori VO, COT, RFCOT, HCOT',
                'products' => ['VO', 'COTO', 'COT', 'COTO-FR', 'COT-FR', 'CORUTUBE → PFPY17', 'CORUTUBE → PFP-FR-UF-09PL', 'CORUTUBE → PFP-FR-HEV-YKA', 'CORUTUBE → PFP-FR-HEV-YSA', 'RFCOT', 'HCOT']
            ],
            [
                'name' => 'Wire Test Reguler',
                'description' => 'Kategori untuk testing wire reguler dengan sub-kategori CAVS, CIVUS, AVSS, dll',
                'products' => ['CAVS', 'ACCAVS', 'CIVUS', 'ACCIVUS', 'AVSS', 'AVSSH', 'AVS', 'AV']
            ],
            [
                'name' => 'Shield Wire Test',
                'description' => 'Kategori untuk testing shield wire dengan sub-kategori CAVSAS',
                'products' => ['CIVUSAS', 'CIVUSAS-S', 'CAVSAS-S', 'AVSSHCS', 'AVSSCS', 'AVSSCS-S']
            ]
        ];

        foreach ($categories as $category) {
            self::updateOrCreate(
                ['name' => $category['name']],
                $category
            );
        }
    }

    /**
     * Check if product name is valid untuk kategori ini
     */
    public function isValidProductName(string $productName): bool
    {
        return in_array($productName, $this->getFlatProductList());
    }
}
