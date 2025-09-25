<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measurement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('measurement_id')->constrained()->onDelete('cascade');
            $table->string('thickness_type'); // e.g., "THICKNESS_A", "THICKNESS_B", "THICKNESS_C"
            $table->decimal('value', 8, 2); // Individual thickness values like 1.7, 2.6, etc.
            $table->integer('sequence')->default(1); // For ordering multiple values of same type
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurement_items');
    }
};





