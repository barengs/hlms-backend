<?php

namespace App\Http\Controllers\Api\V1\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    /**
     * Display a listing of assignments for the instructor's batches.
     */
    public function index(Request $request): JsonResponse
    {
        $assignments = Assignment::whereHas('batch.course', function ($query) use ($request) {
                $query->where('instructor_id', $request->user()->id);
            })
            ->with(['batch:id,name,course_id', 'lesson:id,title'])
            ->when($request->batch_id, function ($query, $batchId) {
                $query->where('batch_id', $batchId);
            })
            ->when($request->type, function ($query, $type) {
                $query->where('type', $type);
            })
            ->when($request->is_published, function ($query, $isPublished) {
                $query->where('is_published', $isPublished);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 10);

        return response()->json($assignments);
    }

    /**
     * Store a newly created assignment.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'batch_id' => ['required', 'exists:batches,id'],
            'lesson_id' => ['nullable', 'exists:lessons,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'instructions' => ['nullable', 'string'],
            'type' => ['required', 'in:assignment,quiz,project,discussion'],
            'content' => ['nullable', 'array'],
            'due_date' => ['nullable', 'date'],
            'available_from' => ['nullable', 'date'],
            'max_points' => ['required', 'integer', 'min:0'],
            'gradable' => ['boolean'],
            'allow_multiple_submissions' => ['boolean'],
            'is_published' => ['boolean'],
            'is_required' => ['boolean'],
        ]);

        // Verify that the user owns the batch
        $batch = Batch::where('id', $validated['batch_id'])
            ->whereHas('course', function ($query) use ($request) {
                $query->where('instructor_id', $request->user()->id);
            })
            ->firstOrFail();

        // Verify lesson if provided
        if (isset($validated['lesson_id'])) {
            $lesson = Lesson::where('id', $validated['lesson_id'])
                ->whereHas('section.course', function ($query) use ($request) {
                    $query->where('instructor_id', $request->user()->id);
                })
                ->firstOrFail();
        }

        $assignment = Assignment::create($validated);

        return response()->json([
            'message' => 'Assignment created successfully.',
            'data' => $assignment->load(['batch', 'lesson']),
        ], 201);
    }

    /**
     * Display the specified assignment.
     */
    public function show(Request $request, string $assignmentId): JsonResponse
    {
        $assignment = Assignment::whereHas('batch.course', function ($query) use ($request) {
                $query->where('instructor_id', $request->user()->id);
            })
            ->with([
                'batch:id,name,course_id',
                'lesson:id,title,content',
                'submissions:id,assignment_id,user_id,status,submitted_at,points_awarded'
            ])
            ->findOrFail($assignmentId);

        return response()->json([
            'data' => $assignment,
        ]);
    }

    /**
     * Update the specified assignment.
     */
    public function update(Request $request, string $assignmentId): JsonResponse
    {
        $assignment = Assignment::whereHas('batch.course', function ($query) use ($request) {
                $query->where('instructor_id', $request->user()->id);
            })
            ->findOrFail($assignmentId);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'instructions' => ['nullable', 'string'],
            'type' => ['sometimes', 'in:assignment,quiz,project,discussion'],
            'content' => ['nullable', 'array'],
            'due_date' => ['nullable', 'date'],
            'available_from' => ['nullable', 'date'],
            'max_points' => ['sometimes', 'integer', 'min:0'],
            'gradable' => ['boolean'],
            'allow_multiple_submissions' => ['boolean'],
            'is_published' => ['boolean'],
            'is_required' => ['boolean'],
        ]);

        $assignment->update($validated);

        return response()->json([
            'message' => 'Assignment updated successfully.',
            'data' => $assignment->fresh(['batch', 'lesson']),
        ]);
    }

    /**
     * Remove the specified assignment.
     */
    public function destroy(Request $request, string $assignmentId): JsonResponse
    {
        $assignment = Assignment::whereHas('batch.course', function ($query) use ($request) {
                $query->where('instructor_id', $request->user()->id);
            })
            ->findOrFail($assignmentId);

        $assignment->delete();

        return response()->json([
            'message' => 'Assignment deleted successfully.',
        ]);
    }

    /**
     * Grade a student submission.
     */
    public function gradeSubmission(Request $request, string $submissionId): JsonResponse
    {
        $validated = $request->validate([
            'points_awarded' => ['required', 'integer', 'min:0'],
            'instructor_feedback' => ['nullable', 'string'],
        ]);

        $submission = Assignment::whereHas('batch.course', function ($query) use ($request) {
                $query->where('instructor_id', $request->user()->id);
            })
            ->join('submissions', 'assignments.id', '=', 'submissions.assignment_id')
            ->where('submissions.id', $submissionId)
            ->select('submissions.*')
            ->firstOrFail();

        $submission->update([
            'points_awarded' => $validated['points_awarded'],
            'instructor_feedback' => $validated['instructor_feedback'] ?? null,
            'graded_at' => now(),
            'graded_by' => $request->user()->id,
            'status' => 'graded',
        ]);

        return response()->json([
            'message' => 'Submission graded successfully.',
            'data' => $submission->load(['user', 'assignment']),
        ]);
    }
}
