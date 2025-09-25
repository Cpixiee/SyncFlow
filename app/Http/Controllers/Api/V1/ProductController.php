<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quarter;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    use ApiResponseTrait;

    /**
     * Create new product
     */
    public function store(Request $request)
    {
        try {
            // Basic validation - support both category_id and category_name
            $validator = Validator::make($request->all(), [
                'basic_info.product_category_name' => 'required_without:basic_info.product_category_id|string',
                'basic_info.product_category_id' => 'required_without:basic_info.product_category_name|exists:product_categories,id',
                'basic_info.product_name' => 'required|string',
                'measurement_points' => 'required|array|min:1',
                'measurement_points.*.setup.nature' => 'required|in:QUANTITATIVE,QUALITATIVE',
                'measurement_groups' => 'nullable|array',
                'measurement_groups.*.group_name' => 'required_with:measurement_groups|string',
                'measurement_groups.*.measurement_items' => 'required_with:measurement_groups|array',
                'measurement_groups.*.order' => 'required_with:measurement_groups|integer',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors());
            }

            $basicInfo = $request->input('basic_info');
            $measurementPoints = $request->input('measurement_points');
            $measurementGroups = $request->input('measurement_groups', []);

            // Convert product_category_name to product_category_id if needed
            if (isset($basicInfo['product_category_name']) && !isset($basicInfo['product_category_id'])) {
                $category = ProductCategory::where('name', $basicInfo['product_category_name'])->first();
                if (!$category) {
                    return $this->errorResponse('Product category tidak ditemukan: ' . $basicInfo['product_category_name'], 'CATEGORY_NOT_FOUND', 400);
                }
                $basicInfo['product_category_id'] = $category->id;
            }

            // Process measurement groups if provided
            $processedMeasurementPoints = $this->processMeasurementGrouping($measurementPoints, $measurementGroups);

            // Additional validation for measurement points
            $validationErrors = $this->validateMeasurementPoints($measurementPoints);
            if (!empty($validationErrors)) {
                return $this->errorResponse('Measurement points validation failed', 'MEASUREMENT_VALIDATION_ERROR', 400, $validationErrors);
            }

            // Get active quarter
            $activeQuarter = Quarter::getActiveQuarter();
            if (!$activeQuarter) {
                return $this->errorResponse('Tidak ada quarter aktif', 'NO_ACTIVE_QUARTER', 400);
            }

            // Create product
            $product = Product::create([
                'quarter_id' => $activeQuarter->id,
                'product_category_id' => $basicInfo['product_category_id'],
                'product_name' => $basicInfo['product_name'],
                'ref_spec_number' => $basicInfo['ref_spec_number'] ?? null,
                'nom_size_vo' => $basicInfo['nom_size_vo'] ?? null,
                'article_code' => $basicInfo['article_code'] ?? null,
                'no_document' => $basicInfo['no_document'] ?? null,
                'no_doc_reference' => $basicInfo['no_doc_reference'] ?? null,
                'measurement_points' => $processedMeasurementPoints,
                'measurement_groups' => $measurementGroups,
            ]);

            $product->load(['quarter', 'productCategory']);

            return $this->successResponse([
                'product_id' => $product->product_id,
                'basic_info' => $basicInfo,
                'measurement_points' => $product->measurement_points,
                'measurement_groups' => $product->measurement_groups,
            ], 'Product berhasil dibuat', 201);

        } catch (\Exception $e) {
            return $this->errorResponse('Error: ' . $e->getMessage(), 'CREATION_ERROR', 500);
        }
    }

    /**
     * Get product by ID
     */
    public function show(string $productId)
    {
        try {
            $product = Product::with(['quarter', 'productCategory'])
                ->where('product_id', $productId)
                ->first();

            if (!$product) {
                return $this->notFoundResponse('Product tidak ditemukan');
            }

            return $this->successResponse([
                'id' => $product->product_id,
                'basic_info' => [
                    'product_category_id' => $product->product_category_id,
                    'product_name' => $product->product_name,
                    'ref_spec_number' => $product->ref_spec_number,
                    'nom_size_vo' => $product->nom_size_vo,
                    'article_code' => $product->article_code,
                    'no_document' => $product->no_document,
                    'no_doc_reference' => $product->no_doc_reference,
                ],
                'measurement_points' => $product->measurement_points,
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Error: ' . $e->getMessage(), 'FETCH_ERROR', 500);
        }
    }

    /**
     * Get products list
     */
    public function index(Request $request)
    {
        try {
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 10);

            $products = Product::with(['quarter', 'productCategory'])
                ->paginate($limit, ['*'], 'page', $page);

            $transformedProducts = $products->getCollection()->map(function ($product) {
                return [
                    'id' => $product->product_id,
                    'product_category_id' => $product->product_category_id,
                    'product_category_name' => $product->productCategory->name,
                    'product_name' => $product->product_name,
                    'ref_spec_number' => $product->ref_spec_number,
                    'nom_size_vo' => $product->nom_size_vo,
                    'article_code' => $product->article_code,
                    'no_document' => $product->no_document,
                    'no_doc_reference' => $product->no_doc_reference,
                ];
            });

            return $this->paginationResponse(
                $transformedProducts,
                [
                    'current_page' => $products->currentPage(),
                    'total_page' => $products->lastPage(),
                    'limit' => $products->perPage(),
                    'total_docs' => $products->total(),
                ]
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Error: ' . $e->getMessage(), 'FETCH_ERROR', 500);
        }
    }

    /**
     * Check if product exists
     */
    public function checkProductExists(Request $request)
    {
        try {
            $exists = Product::checkProductExists($request->all());
            return $this->successResponse(['is_product_exists' => $exists]);
        } catch (\Exception $e) {
            return $this->errorResponse('Error: ' . $e->getMessage(), 'CHECK_ERROR', 500);
        }
    }

    /**
     * Get product categories
     */
    public function getProductCategories()
    {
        try {
            $categories = ProductCategory::select('id', 'name', 'products', 'description')->get();
            return $this->successResponse($categories);
        } catch (\Exception $e) {
            return $this->errorResponse('Error: ' . $e->getMessage(), 'FETCH_ERROR', 500);
        }
    }

    /**
     * Validate measurement points structure
     */
    private function validateMeasurementPoints(array $measurementPoints): array
    {
        $errors = [];

        foreach ($measurementPoints as $index => $point) {
            $pointErrors = [];
            
            // Validate setup
            if (!isset($point['setup'])) {
                $pointErrors[] = 'Setup is required';
                $errors["measurement_points.{$index}"] = $pointErrors;
                continue;
            }

            $setup = $point['setup'];
            
            // Required setup fields
            if (empty($setup['name'])) {
                $pointErrors[] = 'Setup name is required';
            }
            
            if (!isset($setup['sample_amount']) || $setup['sample_amount'] < 1) {
                $pointErrors[] = 'Sample amount must be at least 1';
            }

            // Nature-specific validation
            if (isset($setup['nature'])) {
                if ($setup['nature'] === 'QUANTITATIVE') {
                    // Quantitative must have rule_evaluation_setting
                    if (!isset($point['rule_evaluation_setting']) || empty($point['rule_evaluation_setting'])) {
                        $pointErrors[] = 'Rule evaluation setting is required for QUANTITATIVE nature';
                    } else {
                        $ruleErrors = $this->validateRuleEvaluation($point['rule_evaluation_setting']);
                        if (!empty($ruleErrors)) {
                            $pointErrors = array_merge($pointErrors, $ruleErrors);
                        }
                    }
                    
                    // Qualitative setting must be null for quantitative
                    if (isset($point['evaluation_setting']['qualitative_setting']) && $point['evaluation_setting']['qualitative_setting'] !== null) {
                        $pointErrors[] = 'Qualitative setting must be null for QUANTITATIVE nature';
                    }
                    
                } elseif ($setup['nature'] === 'QUALITATIVE') {
                    // Qualitative must have qualitative_setting
                    if (!isset($point['evaluation_setting']['qualitative_setting']) || empty($point['evaluation_setting']['qualitative_setting'])) {
                        $pointErrors[] = 'Qualitative setting is required for QUALITATIVE nature';
                    } else {
                        if (empty($point['evaluation_setting']['qualitative_setting']['label'])) {
                            $pointErrors[] = 'Qualitative label is required';
                        }
                    }
                    
                    // Rule evaluation must be null for qualitative
                    if (isset($point['rule_evaluation_setting']) && $point['rule_evaluation_setting'] !== null) {
                        $pointErrors[] = 'Rule evaluation setting must be null for QUALITATIVE nature';
                    }
                    
                    // For qualitative, evaluation_type should be SKIP_CHECK
                    if (isset($point['evaluation_type']) && $point['evaluation_type'] !== 'SKIP_CHECK') {
                        $pointErrors[] = 'Evaluation type must be SKIP_CHECK for QUALITATIVE nature';
                    }
                }
            }

            if (!empty($pointErrors)) {
                $errors["measurement_points.{$index}"] = $pointErrors;
            }
        }

        return $errors;
    }

    /**
     * Process measurement grouping and ordering
     */
    private function processMeasurementGrouping(array $measurementPoints, array $measurementGroups): array
    {
        if (empty($measurementGroups)) {
            // No grouping specified, return original order
            return $measurementPoints;
        }

        // Create mapping of measurement items by name_id
        $measurementMap = [];
        foreach ($measurementPoints as $point) {
            $measurementMap[$point['setup']['name_id']] = $point;
        }

        // Process groups and create ordered measurement points
        $orderedMeasurementPoints = [];
        
        // Sort groups by order
        usort($measurementGroups, function($a, $b) {
            return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
        });

        foreach ($measurementGroups as $group) {
            foreach ($group['measurement_items'] as $itemNameId) {
                if (isset($measurementMap[$itemNameId])) {
                    // Add group information to measurement point
                    $measurementPoint = $measurementMap[$itemNameId];
                    $measurementPoint['group_name'] = $group['group_name'];
                    $measurementPoint['group_order'] = $group['order'];
                    
                    $orderedMeasurementPoints[] = $measurementPoint;
                    unset($measurementMap[$itemNameId]); // Remove from map to avoid duplicates
                }
            }
        }

        // Add any remaining measurement points that weren't grouped
        foreach ($measurementMap as $point) {
            $point['group_name'] = 'Ungrouped';
            $point['group_order'] = 999; // Put ungrouped items at the end
            $orderedMeasurementPoints[] = $point;
        }

        return $orderedMeasurementPoints;
    }


    /**
     * Validate rule evaluation setting
     */
    private function validateRuleEvaluation(array $ruleEvaluation): array
    {
        $errors = [];

        if (empty($ruleEvaluation['rule'])) {
            $errors[] = 'Rule is required';
        } elseif (!in_array($ruleEvaluation['rule'], ['MIN', 'MAX', 'BETWEEN'])) {
            $errors[] = 'Rule must be one of: MIN, MAX, BETWEEN';
        }

        if (!isset($ruleEvaluation['value']) || !is_numeric($ruleEvaluation['value'])) {
            $errors[] = 'Rule value must be a number';
        }

        if (empty($ruleEvaluation['unit'])) {
            $errors[] = 'Unit is required';
        }

        // BETWEEN rule specific validation
        if (isset($ruleEvaluation['rule']) && $ruleEvaluation['rule'] === 'BETWEEN') {
            if (!isset($ruleEvaluation['tolerance_minus']) || !is_numeric($ruleEvaluation['tolerance_minus'])) {
                $errors[] = 'Tolerance minus is required and must be a number for BETWEEN rule';
            }
            
            if (!isset($ruleEvaluation['tolerance_plus']) || !is_numeric($ruleEvaluation['tolerance_plus'])) {
                $errors[] = 'Tolerance plus is required and must be a number for BETWEEN rule';
            }
        } else {
            // For MIN/MAX, tolerance should be null
            if (isset($ruleEvaluation['tolerance_minus']) && $ruleEvaluation['tolerance_minus'] !== null) {
                $errors[] = 'Tolerance minus must be null for MIN/MAX rules';
            }
            
            if (isset($ruleEvaluation['tolerance_plus']) && $ruleEvaluation['tolerance_plus'] !== null) {
                $errors[] = 'Tolerance plus must be null for MIN/MAX rules';
            }
        }

        return $errors;
    }
}
