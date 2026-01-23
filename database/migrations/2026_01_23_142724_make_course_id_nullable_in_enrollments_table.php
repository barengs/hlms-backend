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
        Schema::table('enrollments', function (Blueprint $table) {
            // Make course_id nullable to allow enrollment in classes without courses
            // This supports Google Classroom style where students can join a class
            // before any courses are added to it
            $table->foreignId('course_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            // Revert course_id to NOT NULL
            // Note: This might fail if there are existing NULL values
            $table->foreignId('course_id')->nullable(false)->change();
        });
    }
};
