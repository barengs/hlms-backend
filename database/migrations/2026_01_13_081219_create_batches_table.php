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
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            
            // Batch info
            $table->string('name'); // e.g., "Batch September 2024"
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            
            // Schedule
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->timestamp('enrollment_start_date')->nullable();
            $table->timestamp('enrollment_end_date')->nullable();
            
            // Capacity
            $table->integer('max_students')->nullable(); // null means unlimited
            $table->integer('current_students')->default(0);
            
            // Status
            $table->enum('status', ['draft', 'open', 'in_progress', 'completed', 'cancelled'])->default('draft');
            
            // Settings
            $table->boolean('is_public')->default(true); // Whether visible to all users
            $table->boolean('auto_approve')->default(true); // Auto approve enrollment requests
            
            $table->timestamps();

            $table->index(['course_id', 'status']);
            $table->index('start_date');
            $table->index('enrollment_end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
