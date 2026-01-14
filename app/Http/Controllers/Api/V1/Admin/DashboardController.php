<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Http\Resources\Api\V1\DashboardResource;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    use ApiResponse;

    /**
     * Dapatkan Statistik Admin
     * 
     * Mengambil data statistik untuk dashboard admin termasuk total pengguna, instruktur, pendapatan, dan pesanan terbaru.
     *
     * @group Dashboard Admin
     * @responseField success boolean Status keberhasilan request.
     * @responseField message string Pesan respon.
     * @responseField data object Data statistik dashboard.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // 1. User Stats
            $usersCount = User::count();
            $instructorsCount = User::role('instructor')->count();
            $studentsCount = User::role('student')->count();
            
            // 2. Revenue Stats
            $totalRevenue = Order::where('status', 'paid')->sum('total');
            
            // 3. Pending Approvals (Placeholder logic for now)
            $pendingInstructors = User::role('instructor')->whereNull('email_verified_at')->count();

            // 4. Recent Orders
            $recentOrders = Order::with('user:id,name,email')
                ->where('status', 'paid')
                ->latest()
                ->take(5)
                ->get();

            $data = [
                'stats' => [
                    'total_users' => $usersCount,
                    'total_instructors' => $instructorsCount,
                    'total_students' => $studentsCount,
                    'total_revenue' => $totalRevenue,
                    'pending_instructors' => $pendingInstructors,
                ],
                'recent_orders' => $recentOrders
            ];

            return $this->successResponse(new DashboardResource($data), 'Admin dashboard data retrieved successfully.');

        } catch (\Exception $e) {
            Log::error('Admin Dashboard Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to retrieve dashboard data.', 500);
        }
    }
}
