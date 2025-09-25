<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProductMeasurement;
use App\Models\Product;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProductMeasurementController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get product measurements list for Monthly Target page
     */
    public function index(Request $request)
    {
        try {
            // Get authenticated user
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not authenticated');
            }

            // Validate query parameters
            $validator = Validator::make($request->all(), [
                'page' => 'nullable|integer|min:1',
                'limit' => 'nullable|integer|min:1|max:100',
                'product_category_id' => 'nullable|integer|exists:product_categories,id',
                'query' => 'nullable|string|max:255',
                'status' => 'nullable|in:TODO,ONGOING,NEED_TO_MEASURE,OK',
                'quarter-timeline.quarter' => 'nullable|integer|min:1|max:4',
                'quarter-timeline.year' => 'nullable|integer|min:2020|max:2030',
                'monthly-timeline.month' => 'nullable|integer|min:1|max:12',
                'monthly-timeline.year' => 'nullable|integer|min:2020|max:2030',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(
                    $validator->errors(),
                    'Request invalid'
                );
            }

            $page = $request->get('page', 1);
            $limit = $request->get('limit', 10);
            $productCategoryId = $request->get('product_category_id');
            $query = $request->get('query');
            $status = $request->get('status');
            $quarterTimeline = $request->get('quarter-timeline');
            $monthlyTimeline = $request->get('monthly-timeline');

            // Build base query
            $productsQuery = Product::with(['productCategory', 'quarter'])
                ->select('products.*');

            // Apply filters
            if ($productCategoryId) {
                $productsQuery->where('product_category_id', $productCategoryId);
            }

            if ($query) {
                $productsQuery->where(function($q) use ($query) {
                    $q->where('product_name', 'like', "%{$query}%")
                      ->orWhere('product_id', 'like', "%{$query}%")
                      ->orWhere('article_code', 'like', "%{$query}%")
                      ->orWhere('ref_spec_number', 'like', "%{$query}%");
                });
            }

            // Apply timeline filters
            if ($quarterTimeline) {
                $productsQuery->whereHas('quarter', function($q) use ($quarterTimeline) {
                    $q->where('name', 'Q' . $quarterTimeline['quarter'])
                      ->where('year', $quarterTimeline['year']);
                });
            }

            if ($monthlyTimeline) {
                $productsQuery->whereHas('quarter', function($q) use ($monthlyTimeline) {
                    $month = $monthlyTimeline['month'];
                    $year = $monthlyTimeline['year'];
                    
                    // Determine quarter based on month
                    $quarter = ceil($month / 3);
                    $q->where('name', 'Q' . $quarter)
                      ->where('year', $year);
                });
            }

            // Get products and process with measurements
            $products = $productsQuery->get();
            $processedData = [];

            foreach ($products as $product) {
                // Get latest measurement for this product
                $latestMeasurement = ProductMeasurement::where('product_id', $product->id)
                    ->latest('created_at')
                    ->first();

                // Determine status and progress
                $productStatus = $this->determineProductStatus($latestMeasurement);
                $progress = $this->calculateProgress($latestMeasurement);
                
                // Apply status filter if provided
                if ($status && $productStatus !== $status) {
                    continue;
                }

                $processedData[] = [
                    'product_measurement_id' => $latestMeasurement ? $latestMeasurement->measurement_id : null,
                    'status' => $productStatus,
                    'batch_number' => $latestMeasurement ? $latestMeasurement->batch_number : null,
                    'progress' => $progress,
                    'due_date' => $latestMeasurement && $latestMeasurement->measured_at 
                        ? $latestMeasurement->measured_at->addDays(30)->format('Y-m-d H:i:s') 
                        : now()->addDays(30)->format('Y-m-d H:i:s'),
                    'product' => [
                        'id' => $product->product_id,
                        'product_category_id' => $product->productCategory->id,
                        'product_category_name' => $product->productCategory->name,
                        'product_name' => $product->product_name,
                        'ref_spec_number' => $product->ref_spec_number,
                        'nom_size_vo' => $product->nom_size_vo,
                        'article_code' => $product->article_code,
                        'no_document' => $product->no_document,
                        'no_doc_reference' => $product->no_doc_reference,
                    ]
                ];
            }

            // Apply pagination to processed data
            $total = count($processedData);
            $offset = ($page - 1) * $limit;
            $paginatedData = array_slice($processedData, $offset, $limit);

            return $this->paginationResponse(
                $paginatedData,
                [
                    'current_page' => $page,
                    'total_page' => ceil($total / $limit),
                    'limit' => $limit,
                    'total_docs' => $total,
                ],
                'Product measurements retrieved successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Could not retrieve product measurements: ' . $e->getMessage(),
                'PRODUCT_MEASUREMENTS_FETCH_ERROR',
                500
            );
        }
    }

    /**
     * Determine product status based on measurement
     */
    private function determineProductStatus($measurement): string
    {
        if (!$measurement) {
            return 'TODO'; // No measurement created yet
        }

        switch ($measurement->status) {
            case 'PENDING':
                return 'ONGOING';
            case 'IN_PROGRESS':
                return 'NEED_TO_MEASURE';
            case 'COMPLETED':
                return $measurement->overall_result ? 'OK' : 'OK'; // Both OK and NG show as OK in list
            default:
                return 'TODO';
        }
    }

    /**
     * Calculate measurement progress
     */
    private function calculateProgress($measurement): ?float
    {
        if (!$measurement || !$measurement->measurement_results) {
            return null;
        }

        if ($measurement->status === 'COMPLETED') {
            return 100.0;
        }

        // Calculate based on completed measurement items
        $measurementResults = $measurement->measurement_results;
        if (empty($measurementResults)) {
            return 0.0;
        }

        $totalItems = count($measurementResults);
        $completedItems = 0;

        foreach ($measurementResults as $result) {
            if (isset($result['status']) && $result['status'] !== null) {
                $completedItems++;
            }
        }

        return $totalItems > 0 ? ($completedItems / $totalItems) * 100.0 : 0.0;
    }

    /**
     * Create new measurement entry for a product
     */
    public function store(Request $request)
    {
        try {
            // Get authenticated user
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return $this->unauthorizedResponse('User not authenticated');
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'product_id' => 'required|string|exists:products,product_id',
                'due_date' => 'required|date|after:now',
                'batch_number' => 'nullable|string|max:255',
                'sample_count' => 'nullable|integer|min:1|max:100',
                'notes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(
                    $validator->errors(),
                    'Request invalid'
                );
            }

            // Find product
            $product = Product::where('product_id', $request->product_id)->first();
            if (!$product) {
                return $this->notFoundResponse('Product tidak ditemukan');
            }

            // Auto-generate batch number if not provided
            $batchNumber = $request->batch_number ?? 'BATCH-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            
            // Get sample count from product measurement points
            $measurementPoints = $product->measurement_points ?? [];
            $sampleCount = $request->sample_count ?? (count($measurementPoints) > 0 ? $measurementPoints[0]['setup']['sample_amount'] ?? 3 : 3);

            // Create measurement entry
            $measurement = ProductMeasurement::create([
                'product_id' => $product->id,
                'batch_number' => $batchNumber,
                'sample_count' => $sampleCount,
                'status' => 'PENDING',
                'measured_by' => $user->id,
                'measured_at' => $request->due_date,
                'notes' => $request->notes,
            ]);

            return $this->successResponse([
                'product_measurement_id' => $measurement->measurement_id,
            ], 'Measurement entry created successfully', 201);

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Could not create measurement entry',
                'MEASUREMENT_CREATE_ERROR',
                500
            );
        }
    }

    /**
     * Check samples for a specific measurement item (for individual measurement item evaluation)
     */
    public function checkSamples(Request $request, string $productMeasurementId)
    {
        try {
            // Find the measurement
            $measurement = ProductMeasurement::where('measurement_id', $productMeasurementId)->first();

            if (!$measurement) {
                return $this->notFoundResponse('Product measurement tidak ditemukan');
            }

            // Get product and measurement point to check source type
            $product = $measurement->product;
            $measurementPoint = $product->getMeasurementPointByNameId($request->measurement_item_name_id);
            
            if (!$measurementPoint) {
                return $this->notFoundResponse("Measurement point tidak ditemukan: {$request->measurement_item_name_id}");
            }

            $sourceType = $measurementPoint['setup']['source'];
            
            // Handle different source types
            switch ($sourceType) {
                case 'MANUAL':
                    // User input manual - validate samples required
                    break;
                case 'INSTRUMENT':
                    // Auto dari IoT/alat ukur - samples bisa kosong karena akan diambil otomatis
                    return $this->successResponse([
                        'status' => null,
                        'message' => 'Measurement akan diambil otomatis dari alat ukur IoT',
                        'source_type' => 'INSTRUMENT',
                        'samples' => []
                    ], 'Waiting for instrument data');
                case 'DERIVED':
                    // Auto dari measurement item lain - samples akan dihitung otomatis
                    $derivedFromId = $measurementPoint['setup']['source_derived_name_id'];
                    return $this->successResponse([
                        'status' => null,
                        'message' => "Measurement akan diambil otomatis dari: {$derivedFromId}",
                        'source_type' => 'DERIVED',
                        'derived_from' => $derivedFromId,
                        'samples' => []
                    ], 'Waiting for derived data');
            }

            // Check if measurement item with formula dependencies needs prerequisite data
            if (isset($measurementPoint['variables']) && !empty($measurementPoint['variables'])) {
                $missingDependencies = $this->checkFormulaDependencies($measurement, $measurementPoint['variables']);
                if (!empty($missingDependencies)) {
                    return $this->errorResponse(
                        "Measurement item ini membutuhkan data dari: " . implode(', ', $missingDependencies) . ". Silakan input data tersebut terlebih dahulu.",
                        'MISSING_DEPENDENCIES',
                        400
                    );
                }
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'measurement_item_name_id' => 'required|string',
                'variable_values' => 'nullable|array',
                'variable_values.*.name_id' => 'required_with:variable_values|string',
                'variable_values.*.value' => 'required_with:variable_values|numeric',
                'samples' => 'required|array|min:1',
                'samples.*.sample_index' => 'required|integer|min:1',
                'samples.*.single_value' => 'nullable|numeric',
                'samples.*.before_after_value' => 'nullable|array',
                'samples.*.before_after_value.before' => 'required_with:samples.*.before_after_value|numeric',
                'samples.*.before_after_value.after' => 'required_with:samples.*.before_after_value|numeric',
                'samples.*.qualitative_value' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(
                    $validator->errors(),
                    'Request invalid'
                );
            }

            // Process single measurement item
            $measurementItemData = [
                'measurement_item_name_id' => $request->measurement_item_name_id,
                'variable_values' => $request->variable_values ?? [],
                'samples' => $request->samples,
            ];

            // Get product and measurement point
            $product = $measurement->product;
            $measurementPoint = $product->getMeasurementPointByNameId($request->measurement_item_name_id);
            
            if (!$measurementPoint) {
                return $this->notFoundResponse("Measurement point tidak ditemukan: {$request->measurement_item_name_id}");
            }

            // Process samples dengan variables dan pre-processing formulas
            $processedSamples = $this->processSampleItem($measurementItemData, $measurementPoint);
            
            // Evaluate berdasarkan evaluation type
            $result = $this->evaluateSampleItem($processedSamples, $measurementPoint, $measurementItemData);

            return $this->successResponse($result, 'Samples processed successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error processing samples: ' . $e->getMessage(),
                'SAMPLES_PROCESS_ERROR',
                500
            );
        }
    }

    /**
     * Process samples for individual measurement item
     */
    private function processSampleItem(array $measurementItem, array $measurementPoint): array
    {
        $processedSamples = [];
        $variables = $measurementItem['variable_values'] ?? [];
        $setup = $measurementPoint['setup'];

        foreach ($measurementItem['samples'] as $sample) {
            $processedSample = [
                'sample_index' => $sample['sample_index'],
                'status' => null,
                'single_value' => $sample['single_value'] ?? null,
                'before_after_value' => $sample['before_after_value'] ?? null,
                'qualitative_value' => $sample['qualitative_value'] ?? null,
                'pre_processing_formula_values' => null,
            ];

            // Process pre-processing formulas jika ada
            if (isset($measurementPoint['pre_processing_formulas']) && !empty($measurementPoint['pre_processing_formulas'])) {
                $rawValues = [];
                if ($setup['type'] === 'SINGLE') {
                    $rawValues['single_value'] = $sample['single_value'];
                } elseif ($setup['type'] === 'BEFORE_AFTER') {
                    $rawValues['before_after_value'] = $sample['before_after_value'];
                }

                $processedFormulas = $this->processPreProcessingFormulasForItem(
                    $measurementPoint['pre_processing_formulas'],
                    $rawValues,
                    $variables
                );
                
                $processedSample['pre_processing_formula_values'] = array_map(function($formula, $result) {
                    return [
                        'name' => $formula['name'],
                        'formula' => $formula['formula'],
                        'value' => $result,
                        'is_show' => $formula['is_show']
                    ];
                }, $measurementPoint['pre_processing_formulas'], $processedFormulas);
            }

            $processedSamples[] = $processedSample;
        }

        return $processedSamples;
    }

    /**
     * Evaluate samples for individual measurement item
     */
    private function evaluateSampleItem(array $processedSamples, array $measurementPoint, array $measurementItem): array
    {
        $evaluationType = $measurementPoint['evaluation_type'];
        $result = [
            'status' => false,
            'variable_values' => $measurementItem['variable_values'] ?? [],
            'samples' => $processedSamples,
            'joint_setting_formula_values' => null,
        ];

        switch ($evaluationType) {
            case 'PER_SAMPLE':
                $result = $this->evaluatePerSampleItem($result, $measurementPoint, $processedSamples);
                break;
                
            case 'JOINT':
                $result = $this->evaluateJointItem($result, $measurementPoint, $processedSamples);
                break;
                
            case 'SKIP_CHECK':
                $result['status'] = true;
                break;
        }

        return $result;
    }

    /**
     * Evaluate per sample for individual item
     */
    private function evaluatePerSampleItem(array $result, array $measurementPoint, array $processedSamples): array
    {
        $ruleEvaluation = $measurementPoint['rule_evaluation_setting'];
        $evaluationSetting = $measurementPoint['evaluation_setting']['per_sample_setting'];
        
        $allSamplesOK = true;
        
        foreach ($result['samples'] as &$sample) {
            $valueToEvaluate = null;
            
            if ($evaluationSetting['is_raw_data']) {
                $valueToEvaluate = $sample['single_value'];
            } else {
                // Use pre-processing formula result
                $formulaName = $evaluationSetting['pre_processing_formula_name'];
                if ($sample['pre_processing_formula_values']) {
                    foreach ($sample['pre_processing_formula_values'] as $formula) {
                        if ($formula['name'] === $formulaName) {
                            $valueToEvaluate = $formula['value'];
                            break;
                        }
                    }
                }
            }

            $sampleOK = $this->evaluateWithRuleItem($valueToEvaluate, $ruleEvaluation);
            $sample['status'] = $sampleOK;
            
            if (!$sampleOK) {
                $allSamplesOK = false;
            }
        }
        
        $result['status'] = $allSamplesOK;
        return $result;
    }

    /**
     * Evaluate joint for individual item
     */
    private function evaluateJointItem(array $result, array $measurementPoint, array $processedSamples): array
    {
        $jointSetting = $measurementPoint['evaluation_setting']['joint_setting'];
        $ruleEvaluation = $measurementPoint['rule_evaluation_setting'];
        
        // Process joint formulas
        $jointResults = [];
        foreach ($jointSetting['formulas'] as $formula) {
            $jointResults[] = [
                'name' => $formula['name'],
                'formula' => $formula['formula'],
                'is_final_value' => $formula['is_final_value'],
                'value' => null // Will be calculated by frontend or provided in submission
            ];
        }

        $result['joint_setting_formula_values'] = $jointResults;
        $result['status'] = true; // For now, assume OK until actual calculation
        
        return $result;
    }

    /**
     * Check if formula dependencies are available
     */
    private function checkFormulaDependencies(ProductMeasurement $measurement, array $variables): array
    {
        $missingDependencies = [];
        $measurementResults = $measurement->measurement_results ?? [];
        
        foreach ($variables as $variable) {
            if ($variable['type'] === 'FORMULA' && isset($variable['formula'])) {
                $formula = $variable['formula'];
                
                // Check if formula references other measurement items like AVG(thickness_a_measurement)
                if (preg_match_all('/AVG\(([^)]+)\)/', $formula, $matches)) {
                    foreach ($matches[1] as $referencedItem) {
                        // Check if referenced measurement item has data
                        $hasData = false;
                        foreach ($measurementResults as $result) {
                            if ($result['measurement_item_name_id'] === $referencedItem) {
                                $hasData = true;
                                break;
                            }
                        }
                        
                        if (!$hasData) {
                            $missingDependencies[] = $referencedItem;
                        }
                    }
                }
            }
        }
        
        return array_unique($missingDependencies);
    }

    /**
     * Process pre-processing formulas for individual item
     */
    private function processPreProcessingFormulasForItem(array $formulas, array $rawValues, array $variables): array
    {
        $results = [];
        // For now, return placeholder values
        // In real implementation, this would use MathExecutor like in the main processing
        foreach ($formulas as $formula) {
            $results[] = 0.0; // Placeholder
        }
        return $results;
    }

    /**
     * Generate evaluation summary for response
     */
    private function generateEvaluationSummary(array $measurementResults): array
    {
        $totalItems = count($measurementResults);
        $passedItems = 0;
        $failedItems = 0;
        $itemDetails = [];

        foreach ($measurementResults as $item) {
            $itemStatus = $item['status'] ?? false;
            $itemName = $item['measurement_item_name_id'];
            
            if ($itemStatus) {
                $passedItems++;
            } else {
                $failedItems++;
            }

            // Count sample results for PER_SAMPLE evaluation
            $sampleResults = [];
            if (isset($item['samples'])) {
                foreach ($item['samples'] as $sample) {
                    $sampleResults[] = [
                        'sample_index' => $sample['sample_index'],
                        'status' => $sample['status'] ?? null,
                        'result' => isset($sample['status']) ? ($sample['status'] ? 'OK' : 'NG') : 'N/A'
                    ];
                }
            }

            $itemDetails[] = [
                'measurement_item' => $itemName,
                'status' => $itemStatus,
                'result' => $itemStatus ? 'OK' : 'NG',
                'evaluation_type' => isset($item['joint_results']) ? 'JOINT' : 'PER_SAMPLE',
                'final_value' => $item['final_value'] ?? null,
                'samples_summary' => $sampleResults
            ];
        }

        return [
            'total_items' => $totalItems,
            'passed_items' => $passedItems,
            'failed_items' => $failedItems,
            'pass_rate' => $totalItems > 0 ? round(($passedItems / $totalItems) * 100, 2) : 0,
            'item_details' => $itemDetails
        ];
    }

    /**
     * Evaluate with rule for individual item
     */
    private function evaluateWithRuleItem($value, array $ruleEvaluation): bool
    {
        if ($value === null || !is_numeric($value)) {
            return false;
        }

        $rule = $ruleEvaluation['rule'];
        $ruleValue = $ruleEvaluation['value'];

        switch ($rule) {
            case 'MIN':
                return $value >= $ruleValue;
            case 'MAX':
                return $value <= $ruleValue;
            case 'BETWEEN':
                $minValue = $ruleValue - $ruleEvaluation['tolerance_minus'];
                $maxValue = $ruleValue + $ruleEvaluation['tolerance_plus'];
                return $value >= $minValue && $value <= $maxValue;
            default:
                return false;
        }
    }

    /**
     * Save measurement progress (partial save)
     */
    public function saveProgress(Request $request, string $productMeasurementId)
    {
        try {
            // Find the measurement
            $measurement = ProductMeasurement::where('measurement_id', $productMeasurementId)->first();

            if (!$measurement) {
                return $this->notFoundResponse('Product measurement tidak ditemukan');
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'measurement_results' => 'required|array|min:1',
                'measurement_results.*.measurement_item_name_id' => 'required|string',
                'measurement_results.*.samples' => 'nullable|array',
                'measurement_results.*.variable_values' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(
                    $validator->errors(),
                    'Request invalid'
                );
            }

            // Save partial results
            $existingResults = $measurement->measurement_results ?? [];
            $newResults = $request->measurement_results;

            // Merge with existing results
            foreach ($newResults as $newResult) {
                $found = false;
                foreach ($existingResults as &$existingResult) {
                    if ($existingResult['measurement_item_name_id'] === $newResult['measurement_item_name_id']) {
                        $existingResult = array_merge($existingResult, $newResult);
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $existingResults[] = $newResult;
                }
            }

            // Update measurement with progress
            $measurement->update([
                'status' => 'IN_PROGRESS',
                'measurement_results' => $existingResults,
            ]);

            // Calculate progress
            $progress = $this->calculateProgress($measurement);

            return $this->successResponse([
                'measurement_id' => $measurement->measurement_id,
                'status' => 'IN_PROGRESS',
                'progress' => $progress,
                'saved_items' => count($newResults),
                'total_items' => count($existingResults),
            ], 'Progress saved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error saving progress: ' . $e->getMessage(),
                'PROGRESS_SAVE_ERROR',
                500
            );
        }
    }

    /**
     * Submit measurement results
     */
    public function submitMeasurement(Request $request, string $productMeasurementId)
    {
        try {
            // Find the measurement
            $measurement = ProductMeasurement::where('measurement_id', $productMeasurementId)->first();

            if (!$measurement) {
                return $this->notFoundResponse('Product measurement tidak ditemukan');
            }

            // Enhanced validation based on your specification
            $validator = Validator::make($request->all(), [
                'measurement_results' => 'required|array|min:1',
                'measurement_results.*.measurement_item_name_id' => 'required|string',
                'measurement_results.*.status' => 'nullable|boolean',
                'measurement_results.*.variable_values' => 'nullable|array',
                'measurement_results.*.variable_values.*.name_id' => 'required_with:measurement_results.*.variable_values|string',
                'measurement_results.*.variable_values.*.value' => 'required_with:measurement_results.*.variable_values|numeric',
                'measurement_results.*.samples' => 'required|array|min:1',
                'measurement_results.*.samples.*.sample_index' => 'required|integer|min:1',
                'measurement_results.*.samples.*.status' => 'nullable|boolean',
                'measurement_results.*.samples.*.single_value' => 'nullable|numeric',
                'measurement_results.*.samples.*.before_after_value' => 'nullable|array',
                'measurement_results.*.samples.*.before_after_value.before' => 'required_with:measurement_results.*.samples.*.before_after_value|numeric',
                'measurement_results.*.samples.*.before_after_value.after' => 'required_with:measurement_results.*.samples.*.before_after_value|numeric',
                'measurement_results.*.samples.*.qualitative_value' => 'nullable|boolean',
                'measurement_results.*.samples.*.pre_processing_formula_values' => 'nullable|array',
                'measurement_results.*.joint_setting_formula_values' => 'nullable|array',
                'measurement_results.*.joint_setting_formula_values.*.name' => 'required_with:measurement_results.*.joint_setting_formula_values|string',
                'measurement_results.*.joint_setting_formula_values.*.value' => 'nullable|numeric',
                'measurement_results.*.joint_setting_formula_values.*.formula' => 'nullable|string',
                'measurement_results.*.joint_setting_formula_values.*.is_final_value' => 'required_with:measurement_results.*.joint_setting_formula_values|boolean',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(
                    $validator->errors(),
                    'Request invalid'
                );
            }

            // Process measurement results
            $result = $measurement->processMeasurementResults($request->all());

            // Update measurement status
            $measurement->update([
                'status' => 'COMPLETED',
                'overall_result' => $result['overall_status'],
                'measurement_results' => $result['measurement_results'],
            ]);

            // Enhanced response with detailed evaluation results
            $evaluationSummary = $this->generateEvaluationSummary($result['measurement_results']);
            
            return $this->successResponse([
                'status' => $result['overall_status'],
                'overall_result' => $result['overall_status'] ? 'OK' : 'NG',
                'evaluation_summary' => $evaluationSummary,
                'samples' => $result['measurement_results'],
            ], 'Measurement results processed successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error processing measurement: ' . $e->getMessage(),
                'MEASUREMENT_PROCESS_ERROR',
                500
            );
        }
    }

    /**
     * Get measurement by ID
     */
    public function show(string $productMeasurementId)
    {
        try {
            $measurement = ProductMeasurement::with(['product', 'measuredBy'])
                ->where('measurement_id', $productMeasurementId)
                ->first();

            if (!$measurement) {
                return $this->notFoundResponse('Product measurement tidak ditemukan');
            }

            return $this->successResponse([
                'measurement_id' => $measurement->measurement_id,
                'product_id' => $measurement->product->product_id,
                'batch_number' => $measurement->batch_number,
                'sample_count' => $measurement->sample_count,
                'status' => $measurement->status,
                'overall_result' => $measurement->overall_result,
                'measurement_results' => $measurement->measurement_results,
                'measured_by' => $measurement->measuredBy ? [
                    'username' => $measurement->measuredBy->username,
                    'employee_id' => $measurement->measuredBy->employee_id,
                ] : null,
                'measured_at' => $measurement->measured_at,
                'notes' => $measurement->notes,
                'created_at' => $measurement->created_at,
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error fetching measurement: ' . $e->getMessage(),
                'MEASUREMENT_FETCH_ERROR',
                500
            );
        }
    }
}