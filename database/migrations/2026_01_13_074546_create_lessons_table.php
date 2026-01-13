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
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            
            // Content type
            $table->enum('type', ['video', 'text', 'quiz', 'assignment'])->default('video');
            
            // Video content
            $table->string('video_url')->nullable();
            $table->string('video_provider')->nullable(); // youtube, vimeo, local, etc
            $table->integer('duration')->default(0); // in seconds
            
            // Text content
            $table->longText('content')->nullable();
            
            // Settings
            $table->boolean('is_free')->default(false); // Preview lesson
            $table->boolean('is_published')->default(false);
            $table->integer('sort_order')->default(0);
            
            $table->timestamps();

            $table->index(['section_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
