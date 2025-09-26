<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use NXP\MathExecutor;

class ProductMeasurement extends Model
{
    use HasFactory;
    protected $fillable = [
        'measurement_id',
        'product_id',
        'batch_number',
        'sample_count',
        'status',
        'overall_result',
        'measurement_results',
        'measured_by',
        'measured_at',
        'notes'
    ];

    protected $casts = [
        'overall_result' => 'boolean',
        'measurement_results' => 'array',
        'measured_at' => 'datetime',
    ];

    /**
     * Relationship dengan product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relationship dengan user yang melakukan pengukuran
     */
    public function measuredBy(): BelongsTo
    {
        return $this->belongsTo(LoginUser::class, 'measured_by');
    }

    /**
     * Boot method untuk auto-generate measurement_id
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($measurement) {
            if (empty($measurement->measurement_id)) {
                $measurement->measurement_id = self::generateMeasurementId();
            }
        });
    }

    /**
     * Generate unique measurement ID
     */
    public static function generateMeasurementId(): string
    {
        do {
            $measurementId = 'MSR-' . strtoupper(Str::random(8));
        } while (self::where('measurement_id', $measurementId)->exists());

        return $measurementId;
    }

    /**
     * Process measurement dan evaluate OK/NG status
     */
    public function processMeasurementResults(array $measurementData): array
    {
        $processedResults = [];
        $overallStatus = true; // Assume OK until proven NG
        $currentBatchData = []; // Store current batch data for cross-reference

        // First pass: collect all measurement data for cross-referencing
        foreach ($measurementData['measurement_results'] as $measurementItem) {
            $currentBatchData[$measurementItem['measurement_item_name_id']] = $measurementItem;
        }

        // Second pass: process each measurement item with access to current batch
        foreach ($measurementData['measurement_results'] as $measurementItem) {
            $itemResult = $this->processSingleMeasurementItem($measurementItem, $currentBatchData);
            $processedResults[] = $itemResult;
            
            // If any measurement item is NG, overall status becomes NG
            if (!$itemResult['status']) {
                $overallStatus = false;
            }
        }

        // Update measurement dengan hasil
        $this->update([
            'measurement_results' => $processedResults,
            'overall_result' => $overallStatus,
            'status' => 'COMPLETED',
            'measured_at' => now()
        ]);

        return [
            'overall_status' => $overallStatus,
            'measurement_results' => $processedResults
        ];
    }

    /**
     * Process single measurement item
     */
    private function processSingleMeasurementItem(array $measurementItem, array $currentBatchData = []): array
    {
        $product = $this->product;
        $measurementPoint = $product->getMeasurementPointByNameId($measurementItem['measurement_item_name_id']);
        
        if (!$measurementPoint) {
            throw new \Exception("Measurement point tidak ditemukan: {$measurementItem['measurement_item_name_id']}");
        }

        $result = [
            'measurement_item_name_id' => $measurementItem['measurement_item_name_id'],
            'status' => false,
            'samples' => [],
            'final_value' => null,
            'evaluation_details' => []
        ];

        // Process samples dan variables dengan akses ke current batch data
        $processedSamples = $this->processSamples($measurementItem, $measurementPoint, $currentBatchData);
        $result['samples'] = $processedSamples;

        // Evaluate berdasarkan evaluation type
        $evaluationType = $measurementPoint['evaluation_type'];
        
        switch ($evaluationType) {
            case 'PER_SAMPLE':
                $result = $this->evaluatePerSample($result, $measurementPoint, $processedSamples);
                break;
                
            case 'JOINT':
                $result = $this->evaluateJoint($result, $measurementPoint, $processedSamples, $measurementItem);
                break;
                
            case 'SKIP_CHECK':
                $result['status'] = true; // Always OK for SKIP_CHECK
                break;
        }

        return $result;
    }

    /**
     * Process samples dengan variables dan pre-processing formulas
     */
    private function processSamples(array $measurementItem, array $measurementPoint, array $currentBatchData = []): array
    {
        $processedSamples = [];
        $manualVariables = $measurementItem['variable_values'] ?? [];
        $setup = $measurementPoint['setup'];

        // Process FORMULA variables dari measurement point configuration dengan akses ke current batch
        $configVariables = $measurementPoint['variables'] ?? [];
        $processedVariables = $this->processVariablesWithBatch($configVariables, $currentBatchData);

        // Merge manual variables dengan processed variables
        $allVariables = [];
        foreach ($manualVariables as $variable) {
            $allVariables[$variable['name_id']] = $variable['value'];
        }
        foreach ($processedVariables as $name => $value) {
            $allVariables[$name] = $value;
        }

        foreach ($measurementItem['samples'] as $sample) {
            $processedSample = [
                'sample_index' => $sample['sample_index'],
                'raw_values' => [],
                'processed_values' => [],
                'variables' => $allVariables
            ];

            // Set raw values berdasarkan type
            if ($setup['type'] === 'SINGLE') {
                $processedSample['raw_values']['single_value'] = $sample['single_value'];
            } elseif ($setup['type'] === 'BEFORE_AFTER') {
                $processedSample['raw_values']['before_after_value'] = $sample['before_after_value'];
            }

            // Set qualitative value jika ada
            if ($setup['nature'] === 'QUALITATIVE') {
                $processedSample['raw_values']['qualitative_value'] = $sample['qualitative_value'];
            }

            // Process pre-processing formulas jika ada
            if (isset($measurementPoint['pre_processing_formulas']) && !empty($measurementPoint['pre_processing_formulas'])) {
                $processedSample['processed_values'] = $this->processPreProcessingFormulas(
                    $measurementPoint['pre_processing_formulas'],
                    $processedSample['raw_values'],
                    $allVariables
                );
            }

            $processedSamples[] = $processedSample;
        }

        return $processedSamples;
    }

    /**
     * Process pre-processing formulas
     */
    private function processPreProcessingFormulas(array $formulas, array $rawValues, array $variables): array
    {
        $results = [];
        $executor = new MathExecutor();

        // Register custom AVG function untuk measurement item dependencies
        $this->registerCustomFunctions($executor);

        // Set variables untuk formula (already processed as key-value pairs)
        foreach ($variables as $name => $value) {
            if (is_numeric($value)) {
                $executor->setVar($name, $value);
            }
        }

        // Set raw values
        foreach ($rawValues as $key => $value) {
            if (is_numeric($value)) {
                $executor->setVar($key, $value);
            } elseif (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    if (is_numeric($subValue)) {
                        $executor->setVar($subKey, $subValue);
                    }
                }
            }
        }

        foreach ($formulas as $formula) {
            try {
                $result = $executor->execute($formula['formula']);
                $results[$formula['name']] = $result;
                
                // Set result untuk formula berikutnya
                $executor->setVar($formula['name'], $result);
            } catch (\Exception $e) {
                throw new \Exception("Error processing formula {$formula['name']}: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Evaluate per sample
     */
    private function evaluatePerSample(array $result, array $measurementPoint, array $processedSamples): array
    {
        $ruleEvaluation = $measurementPoint['rule_evaluation_setting'];
        $evaluationSetting = $measurementPoint['evaluation_setting']['per_sample_setting'];
        
        $allSamplesOK = true;
        
        foreach ($processedSamples as &$sample) {
            // Get value untuk evaluation
            $valueToEvaluate = null;
            
            if ($evaluationSetting['is_raw_data']) {
                // Use raw data
                if (isset($sample['raw_values']['single_value'])) {
                    $valueToEvaluate = $sample['raw_values']['single_value'];
                }
            } else {
                // Use pre-processing formula result
                $formulaName = $evaluationSetting['pre_processing_formula_name'];
                if (isset($sample['processed_values'][$formulaName])) {
                    $valueToEvaluate = $sample['processed_values'][$formulaName];
                }
            }

            // Evaluate dengan rule
            $sampleOK = $this->evaluateWithRule($valueToEvaluate, $ruleEvaluation);
            $sample['status'] = $sampleOK;
            $sample['evaluated_value'] = $valueToEvaluate;
            
            if (!$sampleOK) {
                $allSamplesOK = false;
            }
        }
        
        $result['status'] = $allSamplesOK;
        $result['samples'] = $processedSamples;
        
        return $result;
    }

    /**
     * Evaluate joint (aggregation)
     */
    private function evaluateJoint(array $result, array $measurementPoint, array $processedSamples, array $measurementItem): array
    {
        $jointSetting = $measurementPoint['evaluation_setting']['joint_setting'];
        $ruleEvaluation = $measurementPoint['rule_evaluation_setting'];
        
        // Process joint formulas dengan calculation otomatis
        $jointResults = [];
        if (isset($measurementItem['joint_setting_formula_values'])) {
            $jointResults = $measurementItem['joint_setting_formula_values'];
        } else {
            // Auto-calculate joint formulas jika tidak disediakan
            $jointResults = $this->calculateJointFormulas($jointSetting, $processedSamples, $measurementItem);
        }

        // Find final value
        $finalValue = null;
        foreach ($jointResults as $jointResult) {
            if ($jointResult['is_final_value']) {
                $finalValue = $jointResult['value'] ?? null;
                break;
            }
        }

        if ($finalValue === null) {
            throw new \Exception('Final value tidak ditemukan dalam joint setting');
        }

        // Evaluate dengan rule
        $status = $this->evaluateWithRule($finalValue, $ruleEvaluation);
        
        $result['status'] = $status;
        $result['final_value'] = $finalValue;
        $result['joint_results'] = $jointResults;
        
        return $result;
    }

    /**
     * Calculate joint formulas otomatis jika tidak disediakan
     */
    private function calculateJointFormulas(array $jointSetting, array $processedSamples, array $measurementItem): array
    {
        $jointResults = [];
        $executor = new MathExecutor();
        
        // Register custom functions
        $this->registerCustomFunctions($executor);
        
        // Register AVG function untuk aggregasi samples
        $executor->addFunction('AVG', function($values) {
            if (is_array($values)) {
                return count($values) > 0 ? array_sum($values) / count($values) : 0;
            }
            return $values; // Return as is if not array
        });
        
        // Extract processed values dari samples untuk aggregation
        $aggregatedValues = [];
        foreach ($processedSamples as $sample) {
            foreach ($sample['processed_values'] as $name => $value) {
                if (!isset($aggregatedValues[$name])) {
                    $aggregatedValues[$name] = [];
                }
                $aggregatedValues[$name][] = $value;
            }
        }
        
        // Set aggregated values sebagai variables
        foreach ($aggregatedValues as $name => $values) {
            $executor->setVar($name, $values);
        }
        
        // Process variables dari measurement item
        $variables = $measurementItem['variable_values'] ?? [];
        foreach ($variables as $variable) {
            $executor->setVar($variable['name_id'], $variable['value']);
        }
        
        // Calculate each joint formula
        foreach ($jointSetting['formulas'] as $formula) {
            try {
                $result = $executor->execute($formula['formula']);
                $jointResults[] = [
                    'name' => $formula['name'],
                    'value' => $result,
                    'formula' => $formula['formula'],
                    'is_final_value' => $formula['is_final_value']
                ];
                
                // Set result untuk formula berikutnya
                $executor->setVar($formula['name'], $result);
            } catch (\Exception $e) {
                throw new \Exception("Error processing joint formula {$formula['name']}: " . $e->getMessage());
            }
        }
        
        return $jointResults;
    }

    /**
     * Register custom functions untuk MathExecutor
     */
    private function registerCustomFunctions(MathExecutor $executor): void
    {
        // Register AVG function untuk menghitung rata-rata dari measurement item lain
        $measurement = $this;
        
        $executor->addFunction('AVG', function($measurementItemNameId) use ($measurement) {
            // Get measurement results yang sudah ada
            $measurementResults = $measurement->measurement_results ?? [];
            
            foreach ($measurementResults as $result) {
                if ($result['measurement_item_name_id'] === $measurementItemNameId) {
                    $samples = $result['samples'] ?? [];
                    $values = [];
                    
                    // Extract values dari samples
                    foreach ($samples as $sample) {
                        if (isset($sample['raw_values']['single_value'])) {
                            $values[] = $sample['raw_values']['single_value'];
                        } elseif (isset($sample['single_value'])) {
                            $values[] = $sample['single_value'];
                        }
                    }
                    
                    // Calculate average
                    if (!empty($values)) {
                        return array_sum($values) / count($values);
                    }
                }
            }
            
            // Return 0 if no data found
            return 0;
        });
    }

    /**
     * Process variables untuk formula calculation
     */
    private function processVariables(array $variables, ?array $currentBatchData = null): array
    {
        $processedVariables = [];
        $executor = new MathExecutor();
        
        // Register enhanced custom functions untuk variable processing
        $this->registerVariableProcessingFunctions($executor, $currentBatchData);
        
        foreach ($variables as $variable) {
            if ($variable['type'] === 'FORMULA') {
                try {
                    // Set previously calculated variables
                    foreach ($processedVariables as $name => $value) {
                        $executor->setVar($name, $value);
                    }
                    
                    $result = $executor->execute($variable['formula']);
                    $processedVariables[$variable['name']] = $result;
                } catch (\Exception $e) {
                    throw new \Exception("Error processing variable formula {$variable['name']}: " . $e->getMessage());
                }
            } elseif ($variable['type'] === 'FIXED') {
                $processedVariables[$variable['name']] = $variable['value'];
            } elseif ($variable['type'] === 'MANUAL') {
                // MANUAL variables are provided in variable_values during measurement
                $processedVariables[$variable['name']] = 0; // Default value
            }
        }
        
        return $processedVariables;
    }

    /**
     * Process variables dengan akses ke current batch data
     */
    private function processVariablesWithBatch(array $variables, array $currentBatchData = []): array
    {
        $processedVariables = [];
        
        // Process variables dengan fallback calculation langsung
        foreach ($variables as $variable) {
            if ($variable['type'] === 'FORMULA') {
                // Coba fallback calculation dulu untuk formula yang diketahui
                $fallbackResult = $this->calculateFallbackFormulaValue($variable, $currentBatchData, $processedVariables);
                if ($fallbackResult !== null) {
                    $processedVariables[$variable['name']] = $fallbackResult;
                    continue;
                }
                
                // Jika fallback gagal, coba dengan MathExecutor
                try {
                    $executor = new MathExecutor();
                    $this->registerBatchAwareFunctions($executor, $currentBatchData);
                    
                    // Set previously calculated variables
                    foreach ($processedVariables as $name => $value) {
                        $executor->setVar($name, $value);
                    }
                    
                    $result = $executor->execute($variable['formula']);
                    $processedVariables[$variable['name']] = $result;
                } catch (\Exception $e) {
                    throw new \Exception("Error processing variable formula {$variable['name']}: " . $e->getMessage());
                }
            } elseif ($variable['type'] === 'FIXED') {
                $processedVariables[$variable['name']] = $variable['value'];
            } elseif ($variable['type'] === 'MANUAL') {
                // MANUAL variables are provided in variable_values during measurement
                $processedVariables[$variable['name']] = 0; // Default value
            }
        }
        
        return $processedVariables;
    }

    /**
     * Calculate fallback value untuk formula yang error
     */
    private function calculateFallbackFormulaValue(array $variable, array $currentBatchData, array $processedVariables = []): ?float
    {
        $formula = $variable['formula'];
        
        // Handle case: (AVG(thickness_a) + AVG(thickness_b) + AVG(thickness_c)) / 3
        if (preg_match('/\(AVG\(([^)]+)\)\s*\+\s*AVG\(([^)]+)\)\s*\+\s*AVG\(([^)]+)\)\)\s*\/\s*3/', $formula, $matches)) {
            $item1 = $matches[1];
            $item2 = $matches[2]; 
            $item3 = $matches[3];
            
            $avg1 = $this->getAverageFromBatchData($item1, $currentBatchData);
            $avg2 = $this->getAverageFromBatchData($item2, $currentBatchData);
            $avg3 = $this->getAverageFromBatchData($item3, $currentBatchData);
            
            if ($avg1 !== null && $avg2 !== null && $avg3 !== null) {
                return ($avg1 + $avg2 + $avg3) / 3;
            }
        }
        
        // Handle simple multiplication: variable * number
        if (preg_match('/([a-zA-Z_]+)\s*\*\s*([0-9.]+)/', $formula, $matches)) {
            $varName = $matches[1];
            $multiplier = floatval($matches[2]);
            
            // Look for the variable in processed variables
            if (isset($processedVariables[$varName])) {
                return $processedVariables[$varName] * $multiplier;
            }
        }
        
        return null;
    }

    /**
     * Get average value from batch data
     */
    private function getAverageFromBatchData(string $measurementItemNameId, array $currentBatchData): ?float
    {
        if (isset($currentBatchData[$measurementItemNameId])) {
            $samples = $currentBatchData[$measurementItemNameId]['samples'] ?? [];
            $values = [];
            
            foreach ($samples as $sample) {
                if (isset($sample['single_value'])) {
                    $values[] = $sample['single_value'];
                }
            }
            
            if (!empty($values)) {
                return array_sum($values) / count($values);
            }
        }
        
        return null;
    }

    /**
     * Register functions khusus untuk variable processing
     */
    private function registerVariableProcessingFunctions(MathExecutor $executor, ?array $currentBatchData = null): void
    {
        // Register AVG function yang bisa handle measurement item dependencies
        $measurement = $this;
        
        $executor->addFunction('AVG', function($measurementItemNameId) use ($measurement, $currentBatchData) {
            // Get measurement results yang sudah ada
            $measurementResults = $measurement->measurement_results ?? [];
            
            // Cari di measurement results yang sudah tersimpan
            foreach ($measurementResults as $result) {
                if ($result['measurement_item_name_id'] === $measurementItemNameId) {
                    $samples = $result['samples'] ?? [];
                    $values = [];
                    
                    // Extract values dari samples
                    foreach ($samples as $sample) {
                        if (isset($sample['raw_values']['single_value'])) {
                            $values[] = $sample['raw_values']['single_value'];
                        } elseif (isset($sample['single_value'])) {
                            $values[] = $sample['single_value'];
                        }
                    }
                    
                    // Calculate average
                    if (!empty($values)) {
                        return array_sum($values) / count($values);
                    }
                }
            }
            
            // Jika tidak ditemukan di measurement results, cari di current batch data
            if ($currentBatchData && is_array($currentBatchData)) {
                $samples = $currentBatchData['samples'] ?? [];
                $values = [];
                
                foreach ($samples as $sample) {
                    if (isset($sample['single_value'])) {
                        $values[] = $sample['single_value'];
                    }
                }
                
                if (!empty($values)) {
                    return array_sum($values) / count($values);
                }
            }
            
            // Return default value if no data found (akan diupdate saat processing selesai)
            return 2.0;
        });
    }

    /**
     * Register functions dengan akses ke batch data
     */
    private function registerBatchAwareFunctions(MathExecutor $executor, array $currentBatchData = []): void
    {
        // Register AVG function yang bisa akses current batch data
        $measurement = $this;
        
        $executor->addFunction('AVG', function($measurementItemNameId) use ($measurement, $currentBatchData) {
            // Prioritas 1: Cari di current batch data dulu
            if (isset($currentBatchData[$measurementItemNameId])) {
                $samples = $currentBatchData[$measurementItemNameId]['samples'] ?? [];
                $values = [];
                
                foreach ($samples as $sample) {
                    if (isset($sample['single_value'])) {
                        $values[] = $sample['single_value'];
                    }
                }
                
                if (!empty($values)) {
                    return array_sum($values) / count($values);
                }
            }
            
            // Prioritas 2: Cari di measurement results yang sudah tersimpan
            $measurementResults = $measurement->measurement_results ?? [];
            foreach ($measurementResults as $result) {
                if ($result['measurement_item_name_id'] === $measurementItemNameId) {
                    $samples = $result['samples'] ?? [];
                    $values = [];
                    
                    foreach ($samples as $sample) {
                        if (isset($sample['raw_values']['single_value'])) {
                            $values[] = $sample['raw_values']['single_value'];
                        } elseif (isset($sample['single_value'])) {
                            $values[] = $sample['single_value'];
                        }
                    }
                    
                    if (!empty($values)) {
                        return array_sum($values) / count($values);
                    }
                }
            }
            
            // Return default jika tidak ditemukan
            return 2.0;
        });
    }

    /**
     * Evaluate value dengan rule (MIN, MAX, BETWEEN)
     */
    private function evaluateWithRule($value, array $ruleEvaluation): bool
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
    }}

