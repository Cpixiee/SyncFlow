<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MeasurementInstrument extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'model',
        'serial_number',
        'manufacturer',
        'status',
        'description',
        'specifications',
        'last_calibration',
        'next_calibration'
    ];

    protected $casts = [
        'specifications' => 'array',
        'last_calibration' => 'date',
        'next_calibration' => 'date',
    ];

    /**
     * Scope for active instruments only
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }

    /**
     * Check if instrument needs calibration
     */
    public function needsCalibration(): bool
    {
        if (!$this->next_calibration) {
            return false;
        }
        
        return $this->next_calibration <= now()->addDays(30); // 30 days warning
    }

    /**
     * Get formatted instrument info for dropdown
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name . ' (' . $this->serial_number . ')';
    }
}