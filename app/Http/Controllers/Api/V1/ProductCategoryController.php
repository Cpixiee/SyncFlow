<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get list of product categories
     */
    public function index(Request $request)
    {
        try {
            $categories = ProductCategory::select('id', 'name', 'products', 'description')
                ->orderBy('name')
                ->get();

            return $this->successResponse([
                'categories' => $categories->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'products' => $category->products,
                        'description' => $category->description
                    ];
                })
            ], 'Product categories retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error retrieving product categories: ' . $e->getMessage(),
                'CATEGORY_RETRIEVAL_ERROR',
                500
            );
        }
    }

    /**
     * Get products by category ID
     */
    public function getProducts(Request $request, int $categoryId)
    {
        try {
            $category = ProductCategory::find($categoryId);

            if (!$category) {
                return $this->notFoundResponse('Product category not found');
            }

            return $this->successResponse([
                'category_id' => $category->id,
                'category_name' => $category->name,
                'products' => $category->products
            ], 'Category products retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error retrieving category products: ' . $e->getMessage(),
                'CATEGORY_PRODUCTS_ERROR',
                500
            );
        }
    }
}