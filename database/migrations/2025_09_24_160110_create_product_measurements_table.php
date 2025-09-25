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
        Schema::create('product_measurements', function (Blueprint $table) {
            $table->id();
            $table->string('measurement_id')->unique(); // Custom measurement ID
            $table->foreignId('product_id')->constrained('products');
            $table->string('batch_number')->nullable(); // Nomor batch produksi
            $table->integer('sample_count'); // Jumlah sample yang diukur
            
            // Status pengukuran
            $table->enum('status', ['PENDING', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'])->default('PENDING');
            $table->boolean('overall_result')->nullable(); // Overall OK/NG result
            
            // Measurement results - JSON untuk flexibility
            $table->json('measurement_results')->nullable(); // Detail hasil pengukuran per measurement item
            
            // Metadata
            $table->foreignId('measured_by')->nullable()->constrained('login_users');
            $table->timestamp('measured_at')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('measurement_id');
            $table->index('product_id');
            $table->index('status');
            $table->index('overall_result');
            $table->index('measured_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_measurements');
    }
};
