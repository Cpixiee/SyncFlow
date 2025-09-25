<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Quarter extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'year',
        'start_month',
        'end_month',
        'start_date',
        'end_date',
        'is_active'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Relationship dengan products
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get current active quarter
     */
    public static function getActiveQuarter()
    {
        return self::where('is_active', true)->first();
    }

    /**
     * Get current quarter berdasarkan tanggal
     */
    public static function getCurrentQuarter(?int $year = null): ?Quarter
    {
        $year = $year ?? date('Y');
        $currentMonth = (int) date('n');
        
        return self::where('year', $year)
            ->where('start_month', '<=', $currentMonth)
            ->where('end_month', '>=', $currentMonth)
            ->first();
    }

    /**
     * Generate quarters untuk tahun tertentu
     */
    public static function generateQuartersForYear(int $year): void
    {
        $quarters = [
            ['name' => 'Q1', 'start_month' => 1, 'end_month' => 3],
            ['name' => 'Q2', 'start_month' => 4, 'end_month' => 6],
            ['name' => 'Q3', 'start_month' => 7, 'end_month' => 9],
            ['name' => 'Q4', 'start_month' => 10, 'end_month' => 12],
        ];

        foreach ($quarters as $quarter) {
            self::updateOrCreate(
                ['year' => $year, 'name' => $quarter['name']],
                [
                    'start_month' => $quarter['start_month'],
                    'end_month' => $quarter['end_month'],
                    'start_date' => Carbon::createFromDate($year, $quarter['start_month'], 1),
                    'end_date' => Carbon::createFromDate($year, $quarter['end_month'], 1)->endOfMonth(),
                ]
            );
        }
    }

    /**
     * Set quarter as active (dan set yang lain inactive)
     */
    public function setAsActive(): void
    {
        // Set semua quarter jadi inactive
        self::query()->update(['is_active' => false]);
        
        // Set quarter ini jadi active
        $this->update(['is_active' => true]);
    }

    /**
     * Get quarter display name
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->name} {$this->year}";
    }
}
