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
        Schema::create('batch_course', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->integer('order')->default(0); // Urutan course dalam batch
            $table->boolean('is_required')->default(true); // Apakah course wajib diambil
            $table->timestamps();
            
            // Ensure unique batch-course combination
            $table->unique(['batch_id', 'course_id']);
            $table->index(['batch_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batch_course');
    }
};
