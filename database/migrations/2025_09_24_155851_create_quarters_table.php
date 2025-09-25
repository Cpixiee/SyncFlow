<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('quarters', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Q1, Q2, Q3, Q4
            $table->integer('year'); // 2024, 2025, etc
            $table->integer('start_month'); // 1, 4, 7, 10
            $table->integer('end_month'); // 3, 6, 9, 12
            $table->date('start_date'); // 2024-01-01, 2024-04-01, etc
            $table->date('end_date'); // 2024-03-31, 2024-06-30, etc
            $table->boolean('is_active')->default(false); // untuk menandai quarter aktif
            $table->timestamps();
            
            // Index untuk performa query
            $table->index(['year', 'name']);
            $table->index('is_active');
            
            // Unique constraint untuk mencegah duplikasi quarter di tahun yang sama
            $table->unique(['year', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quarters');
    }
};
