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
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // Grade info
            $table->decimal('overall_score', 5, 2); // e.g., 85.50
            $table->string('letter_grade', 2); // e.g., A, B+, C
            $table->text('final_comment')->nullable();
            
            // Grade breakdown
            $table->json('grade_breakdown')->nullable(); // e.g., {'assignments': 80, 'quizzes': 90, 'final_project': 85}
            
            // Status
            $table->enum('status', ['in_progress', 'completed', 'withdrew'])->default('in_progress');
            $table->timestamp('completed_at')->nullable();
            
            $table->timestamps();

            $table->unique(['batch_id', 'user_id']);
            $table->index(['batch_id', 'overall_score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
