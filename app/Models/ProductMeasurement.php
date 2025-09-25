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

        foreach ($measurementData['measurement_results'] as $measurementItem) {
            $itemResult = $this->processSingleMeasurementItem($measurementItem);
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
    private function processSingleMeasurementItem(array $measurementItem): array
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

        // Process samples dan variables
        $processedSamples = $this->processSamples($measurementItem, $measurementPoint);
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
    private function processSamples(array $measurementItem, array $measurementPoint): array
    {
        $processedSamples = [];
        $variables = $measurementItem['variable_values'] ?? [];
        $setup = $measurementPoint['setup'];

        foreach ($measurementItem['samples'] as $sample) {
            $processedSample = [
                'sample_index' => $sample['sample_index'],
                'raw_values' => [],
                'processed_values' => [],
                'variables' => $variables
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
                    $variables
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

        // Set variables untuk formula
        foreach ($variables as $variable) {
            $executor->setVar($variable['name_id'], $variable['value']);
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
        
        // Process joint formulas
        $jointResults = [];
        if (isset($measurementItem['joint_setting_formula_values'])) {
            $jointResults = $measurementItem['joint_setting_formula_values'];
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
    }
}
