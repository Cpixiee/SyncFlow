<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measurements', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Product A Measurement"
            $table->text('formula')->nullable(); // e.g., "AVG(THICKNESS_A) + AVG(THICKNESS_B) + AVG(THICKNESS_C)) / 3"
            $table->decimal('calculated_result', 10, 4)->nullable();
            $table->json('formula_variables')->nullable(); // Store variables used in formula
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurements');
    }
};





