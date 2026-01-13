<?php

namespace App\Http\Controllers\Api\V1\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BatchController extends Controller
{
    /**
     * Display a listing of batches for the instructor's courses.
     */
    public function index(Request $request): JsonResponse
    {
        $batches = Batch::whereHas('course', function ($query) use ($request) {
                $query->where('instructor_id', $request->user()->id);
            })
            ->with(['course:id,title,slug', 'assignments', 'enrollments'])
            ->when($request->course_id, function ($query, $courseId) {
                $query->where('course_id', $courseId);
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 10);

        return response()->json($batches);
    }

    /**
     * Store a newly created batch.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'enrollment_start_date' => ['nullable', 'date'],
            'enrollment_end_date' => ['nullable', 'date', 'after:enrollment_start_date'],
            'max_students' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', 'in:draft,open,in_progress,completed,cancelled'],
            'is_public' => ['boolean'],
            'auto_approve' => ['boolean'],
        ]);

        // Verify that the user owns the course
        $course = Course::where('id', $validated['course_id'])
            ->where('instructor_id', $request->user()->id)
            ->firstOrFail();

        $validated['slug'] = Str::slug($validated['name']) . '-' . time();
        $validated['current_students'] = 0;

        $batch = Batch::create($validated);

        return response()->json([
            'message' => 'Batch created successfully.',
            'data' => $batch->load('course'),
        ], 201);
    }

    /**
     * Display the specified batch.
     */
    public function show(Request $request, string $batchId): JsonResponse
    {
        $batch = Batch::whereHas('course', function ($query) use ($request) {
                $query->where('instructor_id', $request->user()->id);
            })
            ->with([
                'course:id,title,slug',
                'assignments:id,batch_id,title,type,due_date,is_published,is_required',
                'enrollments.user:id,name,email',
                'grades'
            ])
            ->findOrFail($batchId);

        return response()->json([
            'data' => $batch,
        ]);
    }

    /**
     * Update the specified batch.
     */
    public function update(Request $request, string $batchId): JsonResponse
    {
        $batch = Batch::whereHas('course', function ($query) use ($request) {
                $query->where('instructor_id', $request->user()->id);
            })
            ->findOrFail($batchId);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'enrollment_start_date' => ['nullable', 'date'],
            'enrollment_end_date' => ['nullable', 'date', 'after:enrollment_start_date'],
            'max_students' => ['nullable', 'integer', 'min:1'],
            'status' => ['sometimes', 'in:draft,open,in_progress,completed,cancelled'],
            'is_public' => ['boolean'],
            'auto_approve' => ['boolean'],
        ]);

        $batch->update($validated);

        return response()->json([
            'message' => 'Batch updated successfully.',
            'data' => $batch->fresh('course'),
        ]);
    }

    /**
     * Remove the specified batch.
     */
    public function destroy(Request $request, string $batchId): JsonResponse
    {
        $batch = Batch::whereHas('course', function ($query) use ($request) {
                $query->where('instructor_id', $request->user()->id);
            })
            ->findOrFail($batchId);

        // Check if batch has students enrolled
        if ($batch->current_students > 0) {
            return response()->json([
                'message' => 'Cannot delete batch with enrolled students.',
            ], 422);
        }

        $batch->delete();

        return response()->json([
            'message' => 'Batch deleted successfully.',
        ]);
    }

    /**
     * Get batch enrollment statistics.
     */
    public function enrollmentStats(Request $request, string $batchId): JsonResponse
    {
        $batch = Batch::whereHas('course', function ($query) use ($request) {
                $query->where('instructor_id', $request->user()->id);
            })
            ->findOrFail($batchId);

        $stats = [
            'total_enrolled' => $batch->current_students,
            'max_capacity' => $batch->max_students,
            'capacity_percentage' => $batch->max_students ? 
                round(($batch->current_students / $batch->max_students) * 100, 2) : null,
            'assignments_count' => $batch->assignments()->count(),
            'completed_assignments' => $batch->assignments()
                ->whereHas('submissions', function ($query) {
                    $query->where('status', 'graded');
                })
                ->count(),
        ];

        return response()->json([
            'data' => $stats,
        ]);
    }
}
