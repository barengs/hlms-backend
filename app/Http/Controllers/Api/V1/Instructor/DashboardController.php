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
     * Get Instructor Dashboard
     * 
     * Retrieve aggregated statistics and activity data for the instructor's dashboard.
     * Includes course stats, revenue summaries, action items, and charts.
     * 
     * @group Instructor
     * @subgroup Dashboard
     * 
     * @response 200 {
     *   "success": true,
     *   "message": "Instructor dashboard data retrieved successfully.",
     *   "data": {
     *     "stats": {
     *       "total_courses": 5,
     *       "total_students": 120,
     *       "monthly_revenue": 8500000,
     *       "total_revenue": 45000000,
     *       "average_rating": 4.8
     *     },
     *     "actions": {
     *       "pending_grading": 5,
     *       "unanswered_questions": 12
     *     },
     *     "top_courses": [
     *       {
     *         "id": 1,
     *         "title": "ReactJS Masterclass",
     *         "total_students": 45,
     *         "revenue": 1500000,
     *         "trend": "+12%"
     *       }
     *     ],
     *     "revenue_summary": {
     *       "total_revenue": 45000000,
     *       "this_month": 8500000,
     *       "available_balance": 3200000
     *     },
     *     "activities": [
     *       {
     *         "type": "enrollment",
     *         "message": "Budi enrolled in Laravel Course",
     *         "created_at": "2024-01-01T12:00:00Z"
     *       }
     *     ],
     *     "revenue_chart": [
     *       {"month": "2023-08", "total": 1200000}
     *     ],
     *     "active_students": [
     *       {
     *         "id": 10,
     *         "name": "Siti",
     *         "course": "ReactJS",
     *         "progress": 85
     *       }
     *     ]
     *   }
     * }
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // 1. Stats Cards
            $totalCourses = Course::where('instructor_id', $user->id)->count();
            
            $totalStudents = \App\Models\Enrollment::whereHas('course', function($q) use ($user) {
                $q->where('instructor_id', $user->id);
            })->distinct()->count('user_id');

            // Calculate Revenue (Assuming OrderItem tracks course sales)
            // We need to look at Orders that are 'paid' and sum the OrderItem price for instructor's courses
            $revenueQuery = \App\Models\OrderItem::whereHas('course', function($q) use ($user) {
                $q->where('instructor_id', $user->id);
            })->whereHas('order', function($q) {
                $q->where('status', 'paid');
            });
            
            $totalRevenue = $revenueQuery->sum('price');
            $monthlyRevenue = (clone $revenueQuery)->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('price');

            // Average Rating
            $averageRating = Course::where('instructor_id', $user->id)->avg('average_rating') ?? 0;

            // 2. Action Items (To Do)
            $pendingGrading = Submission::whereHas('assignment.batch.course', function($q) use ($user) {
                $q->where('instructor_id', $user->id);
            })->where('status', 'submitted')->count();

            $unansweredQuestions = \App\Models\Discussion::whereHas('batch.course', function($q) use ($user) {
                $q->where('instructor_id', $user->id);
            })->whereNull('parent_id') // Threads
              ->whereDoesntHave('replies', function($q) use ($user) {
                  $q->where('user_id', $user->id); // Not replied by instructor
              })->count();

            // 3. Course Performance (Top 5)
            $topCourses = Course::where('instructor_id', $user->id)
                ->orderByDesc('total_enrollments')
                ->take(5)
                ->get()
                ->map(function($course) {
                    return [
                        'id' => $course->id,
                        'title' => $course->title,
                        'thumbnail' => $course->thumbnail,
                        'total_students' => $course->total_enrollments,
                        'revenue' => $course->price * $course->total_enrollments, // Approximation if OrderItem stats not granular
                        'trend' => '+12%', // Placeholder for complex trend logic
                    ];
                });

            // 4. Recent Activity (Mixed Feed)
            $recentEnrollments = \App\Models\Enrollment::whereHas('course', fn($q) => $q->where('instructor_id', $user->id))
                ->with(['user.profile', 'course'])
                ->latest()
                ->take(5)
                ->get()
                ->map(fn($e) => [
                    'type' => 'enrollment',
                    'message' => $e->user->name . ' enrolled in ' . $e->course->title,
                    'created_at' => $e->created_at,
                ]);

            $recentSubmissions = Submission::whereHas('assignment.batch.course', fn($q) => $q->where('instructor_id', $user->id))
                ->with(['user.profile', 'assignment'])
                ->latest()
                ->take(5)
                ->get()
                ->map(fn($s) => [
                    'type' => 'submission',
                    'message' => $s->user->name . ' submitted ' . $s->assignment->title,
                    'created_at' => $s->created_at,
                ]);
            
            $activities = $recentEnrollments->merge($recentSubmissions)->sortByDesc('created_at')->take(5)->values();

            // 5. Revenue Chart (Last 6 Months)
            $revenueChart = \App\Models\OrderItem::whereHas('course', function($q) use ($user) {
                    $q->where('instructor_id', $user->id);
                })
                ->whereHas('order', fn($q) => $q->where('status', 'paid'))
                ->where('created_at', '>=', now()->subMonths(6))
                ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, SUM(price) as total')
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            // 6. Active Students (Leaderboard)
            $activeStudents = \App\Models\Enrollment::whereHas('course', fn($q) => $q->where('instructor_id', $user->id))
                ->with(['user.profile', 'course'])
                ->orderByDesc('progress_percentage')
                ->take(5)
                ->get()
                ->map(fn($e) => [
                    'id' => $e->user->id,
                    'name' => $e->user->name,
                    'avatar' => $e->user->profile->avatar ?? null,
                    'course' => $e->course->title,
                    'progress' => $e->progress_percentage,
                ]);

            $data = [
                'stats' => [
                    'total_courses' => $totalCourses,
                    'total_students' => $totalStudents,
                    'monthly_revenue' => $monthlyRevenue,
                    'total_revenue' => $totalRevenue,
                    'average_rating' => round($averageRating, 1),
                ],
                'actions' => [
                    'pending_grading' => $pendingGrading,
                    'unanswered_questions' => $unansweredQuestions,
                ],
                'top_courses' => $topCourses,
                'revenue_summary' => [
                    'total_revenue' => $totalRevenue,
                    'this_month' => $monthlyRevenue,
                    'available_balance' => $totalRevenue * 0.9, // 90% Instructor share example
                ],
                'activities' => $activities,
                'revenue_chart' => $revenueChart,
                'active_students' => $activeStudents,
            ];

            return $this->successResponse(new DashboardResource($data), 'Instructor dashboard data retrieved successfully.');

        } catch (\Exception $e) {
            Log::error('Instructor Dashboard Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to retrieve dashboard data.', 500);
        }
    }
}
