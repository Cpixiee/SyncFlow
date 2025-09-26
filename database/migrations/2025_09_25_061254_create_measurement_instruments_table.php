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
        Schema::create('measurement_instruments', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Instrument name
            $table->string('model')->nullable(); // Model/type
            $table->string('serial_number')->unique(); // Serial number
            $table->string('manufacturer')->nullable(); // Manufacturer
            $table->enum('status', ['ACTIVE', 'INACTIVE', 'MAINTENANCE'])->default('ACTIVE');
            $table->text('description')->nullable();
            $table->json('specifications')->nullable(); // Technical specifications
            $table->date('last_calibration')->nullable();
            $table->date('next_calibration')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('name');
            $table->index('status');
            $table->index('serial_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('measurement_instruments');
    }
};