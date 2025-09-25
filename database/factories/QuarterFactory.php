<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Quarter;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Quarter>
 */
class QuarterFactory extends Factory
{
    protected $model = Quarter::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = $this->faker->numberBetween(2024, 2025);
        $quarter = $this->faker->randomElement(['Q1', 'Q2', 'Q3', 'Q4']);
        
        $quarterData = [
            'Q1' => ['start_month' => 1, 'end_month' => 3],
            'Q2' => ['start_month' => 4, 'end_month' => 6],
            'Q3' => ['start_month' => 7, 'end_month' => 9],
            'Q4' => ['start_month' => 10, 'end_month' => 12],
        ];
        
        $data = $quarterData[$quarter];
        
        return [
            'name' => $quarter,
            'year' => $year,
            'start_month' => $data['start_month'],
            'end_month' => $data['end_month'],
            'start_date' => Carbon::createFromDate($year, $data['start_month'], 1),
            'end_date' => Carbon::createFromDate($year, $data['end_month'], 1)->endOfMonth(),
            'is_active' => false
        ];
    }
}
