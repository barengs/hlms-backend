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
        Schema::table('batches', function (Blueprint $table) {
            // Add type column to distinguish between structured batches and classroom
            $table->enum('type', ['structured', 'classroom'])
                  ->default('structured')
                  ->after('status')
                  ->comment('Type of batch: structured (traditional) or classroom (Google Classroom style)');
            
            // Add instructor_id for direct ownership of classes
            $table->foreignId('instructor_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('users')
                  ->nullOnDelete()
                  ->comment('Direct instructor ownership for classroom type batches');
            
            // Add index for better query performance
            $table->index(['type', 'instructor_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropIndex(['type', 'instructor_id']);
            $table->dropForeign(['instructor_id']);
            $table->dropColumn(['type', 'instructor_id']);
        });
    }
};
