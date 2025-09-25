<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Measurement extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'formula',
        'calculated_result',
        'formula_variables'
    ];

    protected $casts = [
        'formula_variables' => 'array',
        'calculated_result' => 'decimal:4'
    ];

    public function measurementItems(): HasMany
    {
        return $this->hasMany(MeasurementItem::class);
    }

    /**
     * Get thickness values grouped by type
     */
    public function getThicknessValuesByType(): array
    {
        return $this->measurementItems()
            ->orderBy('thickness_type')
            ->orderBy('sequence')
            ->get()
            ->groupBy('thickness_type')
            ->map(function ($items) {
                return $items->pluck('value')->toArray();
            })
            ->toArray();
    }

    /**
     * Get average values for each thickness type
     */
    public function getAveragesByType(): array
    {
        $thicknessData = $this->getThicknessValuesByType();
        $averages = [];

        foreach ($thicknessData as $type => $values) {
            $averages[$type] = count($values) > 0 ? array_sum($values) / count($values) : 0;
        }

        return $averages;
    }
}





