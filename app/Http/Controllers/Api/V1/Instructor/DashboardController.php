<?php

namespace App\Http\Controllers\Api\V1\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Http\Resources\Api\V1\DashboardResource;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    use ApiResponse;

    /**
     * Dapatkan Statistik
     * 
     * Mengambil statistik valid untuk dashboard instruktur termasuk jumlah kursus, aktivitas batch, dan tugas yang perlu dinilai.
     *
     * @group Dashboard Instruktur
     * @responseField success boolean Status keberhasilan request.
     * @responseField message string Pesan respon.
     * @responseField data object Data statistik dashboard.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // 1. Course Stats
            $courses = Course::where('instructor_id', $user->id);
            $totalCourses = $courses->count();
            
            // 2. Batch Stats
            $activeBatches = Batch::whereHas('course', function($q) use ($user) {
                $q->where('instructor_id', $user->id);
            })->active()->count();

            // 3. Pending Grading
            $pendingGrading = Submission::whereHas('assignment.batch.course', function($q) use ($user) {
                $q->where('instructor_id', $user->id);
            })
            ->submitted()
            ->count();

            // 4. Earnings (Placeholder)
            // Ideally: Sum of payouts or commission-based calc.
            
            $data = [
                'stats' => [
                    'total_courses' => $totalCourses,
                    'active_batches' => $activeBatches,
                    'pending_grading' => $pendingGrading,
                ],
                // Add recent activities later?
            ];

            return $this->successResponse(new DashboardResource($data), 'Instructor dashboard data retrieved successfully.');

        } catch (\Exception $e) {
            Log::error('Instructor Dashboard Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to retrieve dashboard data.', 500);
        }
    }
}
