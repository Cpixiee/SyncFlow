<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\ProductCategory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = [
            [
                'name' => 'Tube Test',
                'products' => ['VO', 'COTO', 'COT', 'COTO-FR', 'COT-FR', 'RFCOT', 'HCOT'],
                'description' => 'Kategori untuk testing tube dengan berbagai sub-kategori'
            ],
            [
                'name' => 'Wire Test Reguler',
                'products' => ['CAVS', 'ACCAVS', 'CIVUS', 'ACCIVUS', 'AVSS', 'AVSSH', 'AVS', 'AV'],
                'description' => 'Kategori untuk testing wire reguler'
            ],
            [
                'name' => 'Shield Wire Test',
                'products' => ['CIVUSAS', 'CIVUSAS-S', 'CAVSAS-S', 'AVSSHCS', 'AVSSCS', 'AVSSCS-S'],
                'description' => 'Kategori untuk testing shield wire'
            ]
        ];
        
        $category = $this->faker->randomElement($categories);
        
        return [
            'name' => $category['name'],
            'products' => $category['products'],
            'description' => $category['description']
        ];
    }
}
