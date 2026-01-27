<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\User;
use App\Http\Resources\Api\V1\BatchResource;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BatchController extends Controller
{
    use ApiResponse;

    /**
     * List all batches (Admin view)
     * 
     * @group Admin - Batch Management
     */
    public function index(Request $request): JsonResponse
    {
        // Check permission
        if (!$request->user()->can('view all batches')) {
            return $this->errorResponse('Unauthorized. You need "view all batches" permission.', 403);
        }

        try {
            $batches = Batch::with([
                    'instructor',
                    'instructors',
                    'courses:id,title,thumbnail',
                    'enrollments'
                ])
                ->when($request->type, function ($query, $type) {
                    $query->where('type', $type);
                })
                ->when($request->status, function ($query, $status) {
                    $query->where('status', $status);
                })
                ->latest()
                ->paginate($request->per_page ?? 20);

            return $this->successResponse(
                BatchResource::collection($batches)->response()->getData(true),
                'Batches retrieved successfully.'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve batches.', 500);
        }
    }

    /**
     * Create new batch (Admin only)
     * 
     * @group Admin - Batch Management
     */
    public function store(Request $request): JsonResponse
    {
        // Check permission
        if (!$request->user()->can('create batches')) {
            return $this->errorResponse('Unauthorized. You need "create batches" permission.', 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:structured,classroom',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'enrollment_start_date' => 'nullable|date',
            'enrollment_end_date' => 'nullable|date|after:enrollment_start_date',
            'max_students' => 'nullable|integer|min:1',
            'is_public' => 'boolean',
            'instructor_id' => 'nullable|exists:users,id', // Primary instructor (optional)
        ]);

        try {
            DB::beginTransaction();

            $batch = Batch::create([
                'instructor_id' => $request->instructor_id,
                'name' => $request->name,
                'slug' => Str::slug($request->name . '-' . Str::random(5)),
                'description' => $request->description,
                'class_code' => Batch::generateClassCode(),
                'type' => $request->type,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'enrollment_start_date' => $request->enrollment_start_date ?? now(),
                'enrollment_end_date' => $request->enrollment_end_date ?? now()->addMonths(6),
                'max_students' => $request->max_students,
                'current_students' => 0,
                'status' => 'draft',
                'is_public' => $request->is_public ?? false,
                'auto_approve' => $request->auto_approve ?? true,
            ]);

            // If primary instructor provided, also add to instructors pivot
            if ($request->instructor_id) {
                $batch->instructors()->attach($request->instructor_id, [
                    'role' => 'primary'
                ]);
            }

            DB::commit();

            return $this->successResponse(
                new BatchResource($batch->load('instructor', 'instructors')),
                'Batch created successfully.',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to create batch: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Assign instructor to batch
     * 
     * @group Admin - Batch Management
     */
    public function assignInstructor(Request $request, string $batchId): JsonResponse
    {
        // Check permission
        if (!$request->user()->can('assign instructors')) {
            return $this->errorResponse('Unauthorized. You need "assign instructors" permission.', 403);
        }

        $request->validate([
            'instructor_id' => 'required|exists:users,id',
            'role' => 'nullable|string|in:instructor,assistant,primary',
        ]);

        try {
            $batch = Batch::findOrFail($batchId);
            
            // Verify user is instructor
            $instructor = User::findOrFail($request->instructor_id);
            if (!$instructor->isInstructor() && !$instructor->isAdmin()) {
                return $this->errorResponse('User is not an instructor.', 422);
            }

            // Check if already assigned
            if ($batch->instructors()->where('instructor_id', $request->instructor_id)->exists()) {
                return $this->errorResponse('Instructor already assigned to this batch.', 422);
            }

            $batch->instructors()->attach($request->instructor_id, [
                'role' => $request->role ?? 'instructor'
            ]);

            return $this->successResponse(
                new BatchResource($batch->load('instructors')),
                'Instructor assigned successfully.'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to assign instructor: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove instructor from batch
     * 
     * @group Admin - Batch Management
     */
    public function removeInstructor(Request $request, string $batchId, string $instructorId): JsonResponse
    {
        // Check permission
        if (!$request->user()->can('remove instructors')) {
            return $this->errorResponse('Unauthorized. You need "remove instructors" permission.', 403);
        }

        try {
            $batch = Batch::findOrFail($batchId);
            
            if (!$batch->instructors()->where('instructor_id', $instructorId)->exists()) {
                return $this->errorResponse('Instructor not assigned to this batch.', 404);
            }

            $batch->instructors()->detach($instructorId);

            return $this->successResponse(
                new BatchResource($batch->load('instructors')),
                'Instructor removed successfully.'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to remove instructor: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get instructors for a batch
     * 
     * @group Admin - Batch Management
     */
    public function getInstructors(string $batchId): JsonResponse
    {
        try {
            $batch = Batch::with('instructors')->findOrFail($batchId);

            return $this->successResponse([
                'instructors' => $batch->instructors->map(function ($instructor) {
                    return [
                        'id' => $instructor->id,
                        'name' => $instructor->name,
                        'email' => $instructor->email,
                        'avatar' => $instructor->avatar,
                        'role' => $instructor->pivot->role,
                        'assigned_at' => $instructor->pivot->created_at,
                    ];
                })
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve instructors: ' . $e->getMessage(), 500);
        }
    }
}
