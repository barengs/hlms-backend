<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Log;

class GradeController extends Controller
{
    use ApiResponse;

    /**
     * Lihat Nilai & Umpan Balik
     * 
     * Melihat nilai dan feedback instruktur untuk semua tugas di batch tertentu.
     *
     * @group Penilaian Siswa
     * @queryParam batch_id string required ID Batch.
     * @responseField success boolean Status keberhasilan request.
     * @responseField data object[] Daftar nilai dan status tugas.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $request->validate([
                'batch_id' => 'required|exists:batches,id'
            ]);

            // Verify enrollment in batch
            $batch = Batch::where('id', $request->batch_id)
                ->whereHas('enrollments', function($q) use ($user) {
                    $q->where('user_id', $user->id)->active();
                })
                ->firstOrFail();

            $grades = Assignment::where('batch_id', $batch->id)
                ->where('is_published', true)
                ->with(['submissions' => function($q) use ($user) {
                    $q->where('user_id', $user->id)
                      ->select('id', 'assignment_id', 'status', 'submitted_at', 'points_awarded', 'instructor_feedback', 'graded_at'); 
                }])
                ->get()
                ->map(function ($assignment) {
                    $submission = $assignment->submissions->first();
                    return [
                        'assignment_id' => $assignment->id,
                        'assignment_title' => $assignment->title,
                        'is_graded' => $submission && $submission->status === 'graded',
                        // Using points_awarded as 'grade' for now based on previous migration check
                        'grade' => $submission ? $submission->points_awarded : null, 
                        'max_points' => $assignment->max_points,
                        'feedback' => $submission ? $submission->instructor_feedback : null,
                        'submitted_at' => $submission ? $submission->submitted_at : null,
                        'status' => $submission ? $submission->status : 'pending',
                    ];
                });

            return $this->successResponse($grades, 'Grades retrieved successfully.');

        } catch (\Illuminate\Validation\ValidationException $e) {
             return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Batch not found or restricted.', 404);
        } catch (\Exception $e) {
            Log::error('Grade List Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to retrieve grades.', 500);
        }
    }
}
