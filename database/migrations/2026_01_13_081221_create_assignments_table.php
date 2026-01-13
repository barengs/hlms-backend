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
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            
            // Assignment info
            $table->string('title');
            $table->text('description')->nullable();
            $table->longText('instructions')->nullable();
            
            // Content
            $table->enum('type', ['assignment', 'quiz', 'project', 'discussion'])->default('assignment');
            $table->json('content')->nullable(); // For quiz questions, project requirements, etc
            
            // Schedule
            $table->timestamp('due_date')->nullable();
            $table->timestamp('available_from')->nullable();
            
            // Grading
            $table->integer('max_points')->default(100);
            $table->boolean('gradable')->default(true);
            $table->boolean('allow_multiple_submissions')->default(false);
            
            // Settings
            $table->boolean('is_published')->default(false);
            $table->boolean('is_required')->default(true);
            
            $table->timestamps();

            $table->index(['batch_id', 'due_date']);
            $table->index(['batch_id', 'is_published']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
