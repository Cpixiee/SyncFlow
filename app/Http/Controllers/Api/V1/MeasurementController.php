<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Measurement;
use App\Models\MeasurementItem;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use NXP\MathExecutor;

class MeasurementController extends Controller
{
    use ApiResponseTrait;

    private MathExecutor $mathExecutor;

    public function __construct()
    {
        $this->mathExecutor = new MathExecutor();
        
        // Register custom functions yang akan digunakan dalam formula
        $this->registerCustomFunctions();
    }

    /**
     * Register custom functions untuk Math Executor
     */
    private function registerCustomFunctions(): void
    {
        // Register AVG function untuk menghitung rata-rata array
        $this->mathExecutor->addFunction('AVG', function($values) {
            if (!is_array($values) || empty($values)) {
                return 0;
            }
            return array_sum($values) / count($values);
        });

        // Register THICKNESS function untuk mengambil data thickness by type
        $this->mathExecutor->addFunction('THICKNESS', function($measurementId, $type) {
            $measurement = Measurement::find($measurementId);
            if (!$measurement) {
                return [];
            }
            
            $thicknessData = $measurement->getThicknessValuesByType();
            return $thicknessData[$type] ?? [];
        });

        // Register AVG_THICKNESS function untuk menghitung rata-rata thickness tertentu
        $this->mathExecutor->addFunction('AVG_THICKNESS', function($measurementId, $type) {
            $measurement = Measurement::find($measurementId);
            if (!$measurement) {
                return 0;
            }
            
            $averages = $measurement->getAveragesByType();
            return $averages[$type] ?? 0;
        });
    }

    /**
     * Store measurement data
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'thickness_data' => 'required|array',
            'thickness_data.*.type' => 'required|string',
            'thickness_data.*.values' => 'required|array',
            'thickness_data.*.values.*' => 'required|numeric',
            'formula' => 'nullable|string'
        ]);

        try {
            $measurement = Measurement::create([
                'name' => $request->name,
                'formula' => $request->formula
            ]);

            // Store thickness data
            foreach ($request->thickness_data as $thicknessGroup) {
                foreach ($thicknessGroup['values'] as $index => $value) {
                    MeasurementItem::create([
                        'measurement_id' => $measurement->id,
                        'thickness_type' => $thicknessGroup['type'],
                        'value' => $value,
                        'sequence' => $index + 1
                    ]);
                }
            }

            return $this->successResponse($measurement->load('measurementItems'), 'Measurement created successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create measurement: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Process formula dan calculate result
     */
    public function processFormula(Request $request, $id): JsonResponse
    {
        $request->validate([
            'formula' => 'required|string'
        ]);

        try {
            $measurement = Measurement::findOrFail($id);
            
            // Update formula jika ada
            $measurement->update(['formula' => $request->formula]);

            // Process formula dengan Math Executor
            $result = $this->calculateFormulaResult($measurement, $request->formula);
            
            // Update calculated result
            $measurement->update([
                'calculated_result' => $result['value'],
                'formula_variables' => $result['variables']
            ]);

            return $this->successResponse([
                'measurement' => $measurement,
                'calculation_details' => $result
            ], 'Formula processed successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to process formula: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Calculate formula result menggunakan Math Executor
     */
    private function calculateFormulaResult(Measurement $measurement, string $formula): array
    {
        // Get thickness data
        $thicknessData = $measurement->getThicknessValuesByType();
        $averages = $measurement->getAveragesByType();
        
        // Prepare variables untuk formula
        $variables = [];
        
        // Set measurement ID untuk custom functions
        $this->mathExecutor->setVar('MEASUREMENT_ID', $measurement->id);
        
        // Set individual thickness values sebagai variables
        foreach ($thicknessData as $type => $values) {
            $this->mathExecutor->setVar($type, $values);
            $variables[$type] = $values;
        }
        
        // Set average values sebagai variables
        foreach ($averages as $type => $avgValue) {
            $avgVarName = 'AVG_' . $type;
            $this->mathExecutor->setVar($avgVarName, $avgValue);
            $variables[$avgVarName] = $avgValue;
        }

        // Parse dan execute formula
        $processedFormula = $this->preprocessFormula($formula, $measurement->id);
        $result = $this->mathExecutor->execute($processedFormula);

        return [
            'value' => round($result, 4),
            'original_formula' => $formula,
            'processed_formula' => $processedFormula,
            'variables' => $variables,
            'steps' => $this->getCalculationSteps($measurement, $formula)
        ];
    }

    /**
     * Preprocess formula untuk mengganti placeholder dengan actual function calls
     */
    private function preprocessFormula(string $formula, int $measurementId): string
    {
        // Replace AVG(THICKNESS_X) dengan AVG_THICKNESS(measurementId, 'THICKNESS_X')
        $formula = preg_replace_callback(
            '/AVG\(([A-Z_]+)\)/',
            function($matches) use ($measurementId) {
                return "AVG_THICKNESS({$measurementId}, '{$matches[1]}')";
            },
            $formula
        );

        return $formula;
    }

    /**
     * Get detailed calculation steps untuk debugging
     */
    private function getCalculationSteps(Measurement $measurement, string $formula): array
    {
        $thicknessData = $measurement->getThicknessValuesByType();
        $averages = $measurement->getAveragesByType();
        $steps = [];

        foreach ($thicknessData as $type => $values) {
            $steps[] = [
                'step' => "Calculate AVG({$type})",
                'values' => $values,
                'calculation' => implode(' + ', $values) . ' / ' . count($values),
                'result' => $averages[$type]
            ];
        }

        return $steps;
    }

    /**
     * Get measurement with calculated result
     */
    public function show($id): JsonResponse
    {
        try {
            $measurement = Measurement::with('measurementItems')->findOrFail($id);
            
            // Include additional data
            $measurement->thickness_by_type = $measurement->getThicknessValuesByType();
            $measurement->averages_by_type = $measurement->getAveragesByType();

            return $this->successResponse($measurement, 'Measurement retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Measurement not found', 404);
        }
    }

    /**
     * Demo calculation dengan multiple measurement types (THICKNESS + ROOM_TEMP)
     */
    public function demoMixedCalculation(): JsonResponse
    {
        // Create demo data dengan berbagai jenis measurement
        $demoData = [
            'THICKNESS_A' => [1.7, 2.6, 3.8, 4.9, 5.2],
            'THICKNESS_B' => [1.4, 2.5, 3.3, 4.1, 5.9],
            'ROOM_TEMP' => [23.5, 24.1, 23.8, 24.3, 23.9],
            'HUMIDITY' => [65.2, 67.1, 66.5, 68.0, 66.8],
            'PRESSURE' => [1013.2, 1014.1, 1013.8, 1014.5, 1013.9]
        ];

        // Calculate averages
        $averages = [];
        foreach ($demoData as $type => $values) {
            $averages['AVG_' . $type] = array_sum($values) / count($values);
            $this->mathExecutor->setVar('AVG_' . $type, $averages['AVG_' . $type]);
        }

        // Demo berbagai formula kombinasi
        $formulas = [
            // Formula 1: Kombinasi thickness dengan temperature
            'thickness_temp_ratio' => 'AVG_THICKNESS_A / AVG_ROOM_TEMP',
            
            // Formula 2: Temperature compensation untuk thickness
            'temp_compensated_thickness' => 'AVG_THICKNESS_A * (1 + (AVG_ROOM_TEMP - 20) * 0.001)',
            
            // Formula 3: Quality index berdasarkan thickness, temp, dan humidity
            'quality_index' => '(AVG_THICKNESS_A * 10) + (AVG_ROOM_TEMP * 2) - (AVG_HUMIDITY * 0.1)',
            
            // Formula 4: Environmental impact factor
            'env_impact' => 'sqrt(pow(AVG_ROOM_TEMP - 23, 2) + pow(AVG_HUMIDITY - 65, 2) + pow(AVG_PRESSURE - 1013, 2))',
            
            // Formula 5: Complex formula dengan conditional
            'adaptive_thickness' => 'if(AVG_ROOM_TEMP > 24, AVG_THICKNESS_A * 1.05, AVG_THICKNESS_A * 0.98)'
        ];

        $results = [];
        foreach ($formulas as $name => $formula) {
            $results[$name] = [
                'formula' => $formula,
                'result' => round($this->mathExecutor->execute($formula), 4)
            ];
        }

        return $this->successResponse([
            'demo_data' => $demoData,
            'averages' => $averages,
            'mixed_formulas' => $results,
            'explanation' => [
                'thickness_temp_ratio' => 'Rasio ketebalan terhadap suhu ruangan',
                'temp_compensated_thickness' => 'Ketebalan yang dikompensasi dengan suhu (thermal expansion)',
                'quality_index' => 'Indeks kualitas berdasarkan multiple parameters',
                'env_impact' => 'Faktor dampak lingkungan menggunakan euclidean distance',
                'adaptive_thickness' => 'Ketebalan adaptif berdasarkan kondisi suhu'
            ]
        ], 'Mixed measurement calculation completed');
    }

    /**
     * Example endpoint untuk demo Math Executor
     */
    public function demoCalculation(): JsonResponse
    {
        // Create demo data seperti contoh Anda
        $demoData = [
            'THICKNESS_A' => [1.7, 2.6, 3.8, 4.9, 5.2],
            'THICKNESS_B' => [1.4, 2.5, 3.3, 4.1, 5.9],
            'THICKNESS_C' => [1.5, 2.6, 3.7, 4.7, 5.3]
        ];

        // Calculate averages
        $averages = [];
        foreach ($demoData as $type => $values) {
            $averages['AVG_' . $type] = array_sum($values) / count($values);
        }

        // Demo formula: (AVG(THICKNESS_A) + AVG(THICKNESS_B) + AVG(THICKNESS_C)) / 3
        $formula = '(AVG_THICKNESS_A + AVG_THICKNESS_B + AVG_THICKNESS_C) / 3';

        // Set variables
        foreach ($averages as $var => $value) {
            $this->mathExecutor->setVar($var, $value);
        }

        // Execute
        $result = $this->mathExecutor->execute($formula);

        return $this->successResponse([
            'demo_data' => $demoData,
            'averages' => $averages,
            'formula' => $formula,
            'result' => round($result, 2),
            'explanation' => [
                'step_1' => 'Calculate AVG for each thickness type',
                'step_2' => 'Apply formula: ' . $formula,
                'step_3' => 'Final result: ' . round($result, 2)
            ]
        ], 'Demo calculation completed');
    }
}

