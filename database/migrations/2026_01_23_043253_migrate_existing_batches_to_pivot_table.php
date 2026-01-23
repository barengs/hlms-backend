<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate existing batch-course relationships to pivot table
        $batches = DB::table('batches')->whereNotNull('course_id')->get();
        
        foreach ($batches as $batch) {
            DB::table('batch_course')->insert([
                'batch_id' => $batch->id,
                'course_id' => $batch->course_id,
                'order' => 1, // First course in the batch
                'is_required' => true,
                'created_at' => $batch->created_at ?? now(),
                'updated_at' => $batch->updated_at ?? now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore course_id to batches table from pivot
        $batchCourses = DB::table('batch_course')
            ->where('order', 1)
            ->get();
        
        foreach ($batchCourses as $bc) {
            DB::table('batches')
                ->where('id', $bc->batch_id)
                ->update(['course_id' => $bc->course_id]);
        }
        
        // Clear pivot table
        DB::table('batch_course')->truncate();
    }
};
