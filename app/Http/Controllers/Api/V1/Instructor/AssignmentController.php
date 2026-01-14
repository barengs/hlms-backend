<?php

namespace App\Http\Controllers\Api\V1\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Http\Resources\Api\V1\AssignmentResource;
use App\Http\Resources\Api\V1\SubmissionResource;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Log;

class AssignmentController extends Controller
{
    use ApiResponse;

    /**
     * Daftar Tugas
     * 
     * Mengambil daftar tugas yang dibuat oleh instruktur di berbagai batch.
     * 
     * @group Tugas Instruktur
     * @queryParam batch_id string Filter berdasarkan ID Batch.
     * @queryParam type string Filter berdasarkan tipe tugas (assignment, quiz, dll).
     * @queryParam is_published boolean Filter status publikasi.
     * @responseField success boolean Status keberhasilan request.
     * @responseField data object[] Daftar tugas.
     */
    public function index(Request $request): JsonResponse
    {
        try {
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

            return $this->successResponse(
                AssignmentResource::collection($assignments)->response()->getData(true),
                'Assignments retrieved successfully.'
            );

        } catch (\Exception $e) {
            Log::error('Instructor Assignment List Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to retrieve assignments.', 500);
        }
    }

    /**
     * Buat Tugas Baru
     * 
     * Membuat tugas baru dalam batch tertentu.
     * 
     * @group Tugas Instruktur
     * @bodyParam batch_id string required ID Batch.
     * @bodyParam title string required Judul Tugas.
     * @bodyParam description string Deskripsi tugas.
     * @bodyParam max_points integer Poin maksimal.
     * @responseField success boolean Status keberhasilan request.
     * @responseField data object Data tugas yang dibuat.
     */
    public function store(Request $request): JsonResponse
    {
        try {
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

            // Verify batch ownership
            Batch::where('id', $validated['batch_id'])
                ->whereHas('course', function ($query) use ($request) {
                    $query->where('instructor_id', $request->user()->id);
                })
                ->firstOrFail();

            // Verify lesson if provided
            if (isset($validated['lesson_id'])) {
                Lesson::where('id', $validated['lesson_id'])
                    ->whereHas('section.course', function ($query) use ($request) {
                        $query->where('instructor_id', $request->user()->id);
                    })
                    ->firstOrFail();
            }

            $assignment = Assignment::create($validated);

            return $this->successResponse(new AssignmentResource($assignment->load(['batch', 'lesson'])), 'Assignment created successfully.', 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Batch or Lesson not found or access denied.', 404);
        } catch (\Exception $e) {
            Log::error('Assignment Creation Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to create assignment.', 500);
        }
    }

    /**
     * Detail Tugas
     * 
     * Melihat detail tugas tertentu.
     * 
     * @group Tugas Instruktur
     * @responseField success boolean Status keberhasilan request.
     * @responseField data object Data detail tugas.
     */
    public function show(Request $request, string $assignmentId): JsonResponse
    {
        try {
            $assignment = Assignment::whereHas('batch.course', function ($query) use ($request) {
                    $query->where('instructor_id', $request->user()->id);
                })
                ->with([
                    'batch:id,name,course_id',
                    'lesson:id,title,content',
                    'submissions:id,assignment_id,user_id,status,submitted_at,points_awarded'
                ])
                ->findOrFail($assignmentId);

            return $this->successResponse(new AssignmentResource($assignment), 'Assignment details retrieved successfully.');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Assignment not found.', 404);
        } catch (\Exception $e) {
            Log::error('Assignment Detail Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to retrieve assignment.', 500);
        }
    }

    /**
     * Update Tugas
     * 
     * Memperbarui informasi tugas.
     * 
     * @group Tugas Instruktur
     * @responseField success boolean Status keberhasilan request.
     * @responseField data object Data tugas yang diperbarui.
     */
    public function update(Request $request, string $assignmentId): JsonResponse
    {
        try {
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

            return $this->successResponse(new AssignmentResource($assignment->fresh(['batch', 'lesson'])), 'Assignment updated successfully.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Assignment not found.', 404);
        } catch (\Exception $e) {
            Log::error('Assignment Update Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to update assignment.', 500);
        }
    }

    /**
     * Hapus Tugas
     * 
     * Menghapus tugas.
     * 
     * @group Tugas Instruktur
     * @responseField success boolean Status keberhasilan request.
     */
    public function destroy(Request $request, string $assignmentId): JsonResponse
    {
        try {
            $assignment = Assignment::whereHas('batch.course', function ($query) use ($request) {
                    $query->where('instructor_id', $request->user()->id);
                })
                ->findOrFail($assignmentId);

            $assignment->delete();

            return $this->successResponse(null, 'Assignment deleted successfully.');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Assignment not found.', 404);
        } catch (\Exception $e) {
            Log::error('Assignment Deletion Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to delete assignment.', 500);
        }
    }

    /**
     * Beri Nilai Tugas
     * 
     * Memberikan nilai dan umpan balik pada pengumpulan tugas siswa.
     * 
     * @group Tugas Instruktur
     * @bodyParam points_awarded integer required Nilai yang diberikan.
     * @bodyParam instructor_feedback string Umpan balik instruktur.
     * @responseField success boolean Status keberhasilan request.
     * @responseField data object Data pengumpulan yang dinilai.
     */
    public function gradeSubmission(Request $request, string $assignmentId, string $submissionId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'points_awarded' => ['required', 'integer', 'min:0'],
                'instructor_feedback' => ['nullable', 'string'],
            ]);

            // Ensure the assignment belongs to instructor and submission belongs to assignment
            // We use Eloquent to find the *Submission* via its Assignment
            $submission = \App\Models\Submission::where('id', $submissionId)
                ->whereHas('assignment', function ($q) use ($assignmentId, $request) {
                    $q->where('id', $assignmentId)
                      ->whereHas('batch.course', function ($inner) use ($request) {
                        $inner->where('instructor_id', $request->user()->id);
                    });
                })
                ->firstOrFail();

            $submission->update([
                'points_awarded' => $validated['points_awarded'],
                'instructor_feedback' => $validated['instructor_feedback'] ?? null,
                'graded_at' => now(),
                'graded_by' => $request->user()->id,
                'status' => 'graded',
            ]);

            return $this->successResponse(new SubmissionResource($submission->load(['student', 'assignment'])), 'Submission graded successfully.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Submission not found or access denied.', 404);
        } catch (\Exception $e) {
            Log::error('Grading Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to grade submission.', 500);
        }
    }
}
