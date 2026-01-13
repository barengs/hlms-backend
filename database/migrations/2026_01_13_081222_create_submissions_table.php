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
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // Submission info
            $table->text('content')->nullable(); // For text submissions
            $table->json('answers')->nullable(); // For quiz answers
            $table->json('files')->nullable(); // For file uploads
            $table->text('notes')->nullable(); // Student notes
            
            // Status
            $table->enum('status', ['submitted', 'draft', 'late', 'graded'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            
            // Grading
            $table->integer('points_awarded')->nullable();
            $table->text('instructor_feedback')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();

            $table->unique(['assignment_id', 'user_id']);
            $table->index(['assignment_id', 'status']);
            $table->index('submitted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
