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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            
            // Basic info
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            
            // Media
            $table->string('thumbnail')->nullable();
            $table->string('preview_video')->nullable();
            
            // Course settings
            $table->enum('type', ['self_paced', 'structured'])->default('self_paced');
            $table->enum('level', ['beginner', 'intermediate', 'advanced', 'all_levels'])->default('all_levels');
            $table->string('language')->default('id');
            
            // Pricing
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('discount_price', 12, 2)->nullable();
            
            // Requirements & Outcomes
            $table->json('requirements')->nullable();
            $table->json('outcomes')->nullable(); // What students will learn
            $table->json('target_audience')->nullable();
            
            // Status
            $table->enum('status', ['draft', 'pending_review', 'published', 'rejected', 'archived'])->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();
            
            // Stats (cached for performance)
            $table->integer('total_duration')->default(0); // in seconds
            $table->integer('total_lessons')->default(0);
            $table->integer('total_enrollments')->default(0);
            $table->decimal('average_rating', 3, 2)->default(0);
            $table->integer('total_reviews')->default(0);
            
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['status', 'published_at']);
            $table->index('type');
            $table->index('is_featured');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
