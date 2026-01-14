<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Http\Resources\Api\V1\DashboardResource;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    use ApiResponse;

    /**
     * Statistik & Tugas Mendatang
     * 
     * Mengambil statistik dashboard siswa termasuk pendaftaran aktif, kursus selesai, dan tugas mendatang (3 terdekat).
     *
     * @group Dashboard Siswa
     * @responseField success boolean Status keberhasilan request.
     * @responseField data object Data dashboard.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // 1. Active Enrollments
            $activeEnrollments = Enrollment::where('user_id', $user->id)
                ->active()
                ->count();

            // 2. Completed Courses
            $completedCourses = Enrollment::where('user_id', $user->id)
                ->completed()
                ->count();

            // 3. Upcoming Assignments (Due soon)
            $upcomingAssignments = Assignment::whereHas('batch.enrollments', function($q) use ($user) {
                    $q->where('user_id', $user->id)->active();
                })
                ->where('due_date', '>', now())
                ->orderBy('due_date', 'asc')
                ->take(3)
                ->with('batch.course:id,title')
                ->get();

            $data = [
                'stats' => [
                    'active_enrollments' => $activeEnrollments,
                    'completed_courses' => $completedCourses,
                ],
                'upcoming_assignments' => $upcomingAssignments
            ];

            return $this->successResponse(new DashboardResource($data), 'Student dashboard data retrieved successfully.');

        } catch (\Exception $e) {
            Log::error('Student Dashboard Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to retrieve dashboard data.', 500);
        }
    }
}
