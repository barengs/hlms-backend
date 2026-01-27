<?php

namespace App\Http\Controllers\Api\V1\Classroom;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User; // Added this line
use App\Http\Resources\Api\V1\ClassroomResource; // Added this line
use App\Traits\ApiResponse; // Added Trait import
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClassController extends Controller
{
    use ApiResponse; // Use the trait

    /**
     * List Classes
     * 
     * Get a list of classes the current user is involved in.
     * If the user is an Instructor, returns classes they teach.
     * If the user is a Student, returns classes they are enrolled in.
     * 
     * @group Hybrid Learning
     * @subgroup Class Management
     * @response array{data: array<object>}
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isInstructor()) {
            // Instructor view - with statistics and enhanced data
            $baseQuery = Batch::classroom()
                ->where('instructor_id', $user->id);

            // Calculate global statistics (before pagination)
            $statistics = [
                'total_batches' => (clone $baseQuery)->count(),
                'active_batches' => (clone $baseQuery)->where('status', 'in_progress')->count(),
                'published_batches' => (clone $baseQuery)->where('status', 'open')->count(),
                'archived_batches' => (clone $baseQuery)->where('status', 'completed')->count(),
                'total_students' => (clone $baseQuery)->withCount('enrollments')->get()->sum('enrollments_count'),
                'average_grade' => round(
                    DB::table('grades')
                        ->join('batches', 'grades.batch_id', '=', 'batches.id')
                        ->where('batches.instructor_id', $user->id)
                        ->where('batches.type', 'classroom')
                        ->avg('grades.overall_score') ?? 0,
                    1
                ),
            ];

            // Filter counts for tabs
            $filters = [
                'all' => $statistics['total_batches'],
                'active' => $statistics['active_batches'],
                'archived' => $statistics['archived_batches'],
            ];

            // Get paginated classes with all required relationships
            $classes = $baseQuery
                ->with([
                    'courses' => function ($query) {
                        $query->select('courses.id', 'courses.title', 'courses.slug', 'courses.thumbnail');
                    },
                    'courses.sections' => function ($query) {
                        $query->select('sections.id', 'sections.course_id');
                    },
                    'courses.sections.lessons' => function ($query) {
                        $query->select('lessons.id', 'lessons.section_id');
                    },
                    'enrollments' => function ($query) {
                        $query->latest()->limit(5);
                    },
                    'enrollments.student' => function ($query) {
                        $query->select('users.id', 'users.name', 'users.avatar');
                    },
                    'grades' => function ($query) {
                        $query->select('batch_id', 'overall_score');
                    }
                ])
                ->when($request->status, function ($query, $status) {
                    $query->where('status', $status);
                })
                ->latest();

            // Get the response data using ClassroomResource
            $responseData = ClassroomResource::collection($classes)->response()->getData(true);

            // Add statistics and filters to meta
            $responseData['meta']['statistics'] = $statistics;
            $responseData['meta']['filters'] = $filters;

            return $this->successResponse(
                $responseData,
                'Classes retrieved successfully'
            );

        } else {
            // Student view - with enhanced data but no statistics
            $classes = Batch::classroom()
                ->whereHas('enrollments', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->with([
                    'courses' => function ($query) {
                        $query->select('courses.id', 'courses.title', 'courses.slug', 'courses.thumbnail');
                    },
                    'courses.sections' => function ($query) {
                        $query->select('sections.id', 'sections.course_id');
                    },
                    'courses.sections.lessons' => function ($query) {
                        $query->select('lessons.id', 'lessons.section_id');
                    },
                    'instructor' => function ($query) {
                        $query->select('users.id', 'users.name', 'users.avatar');
                    },
                    'grades' => function ($query) use ($user) {
                        $query->where('user_id', $user->id)->select('batch_id', 'overall_score');
                    }
                ])
                ->latest()
                ->paginate($request->per_page ?? 20);

            // Use ClassroomResource for consistent response format
            return $this->successResponse(
                ClassroomResource::collection($classes)->response()->getData(true),
                'Classes retrieved successfully'
            );
        }
    }

    /**
     * Create Class
     * 
     * Create a new classroom-style batch (like Google Classroom).
     * Courses can be added later via the addCourse endpoint.
     * 
     * @group Hybrid Learning
     * @subgroup Class Management
     * @bodyParam name string required The name of the class. Example: Mathematics 101
     * @bodyParam description string optional A brief description of the class.
     * @bodyParam subject string optional Subject/room information. Example: Room 301
     * @bodyParam section string optional Section information. Example: Section A
     * @response 201 {"message": "Class created successfully", "data": object}
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'subject' => 'nullable|string|max:255',
            'section' => 'nullable|string|max:50',
        ]);

        $user = $request->user();

        // Create Batch (Class) only - NO auto-create Course
        $batch = Batch::create([
            'instructor_id' => $user->id,
            'name' => $request->name,
            'slug' => Str::slug($request->name . '-' . Str::random(5)),
            'description' => $request->description,
            'class_code' => Batch::generateClassCode(),
            'type' => 'classroom',  // Mark as classroom type
            'status' => 'open',
            'start_date' => now(),
            'enrollment_start_date' => now(),
            'enrollment_end_date' => now()->addYears(1),
            'is_public' => false,
            'auto_approve' => true,
        ]);

        return $this->successResponse(
            new ClassroomResource($batch),
            'Class created successfully',
            201
        );
    }

    /**
     * Get Class Details
     * 
     * Retrieve detailed information about a specific class, including course and instructor details.
     * 
     * @group Hybrid Learning
     * @subgroup Class Management
     * @urlParam id integer required The ID of the class (batch).
     * @response object
     */
    public function show($id)
    {
        $batch = Batch::classroom()
            ->with(['courses.instructor', 'courses.sections.lessons', 'instructor', 'enrollments'])
            ->findOrFail($id);

        // TODO: distinct between student view and instructor view?
        
        return response()->json(['data' => new ClassroomResource($batch)]);
    }

    /**
     * Add Course to Class
     * 
     * Attach an existing course to this class.
     * Instructor must own both the class and the course.
     * 
     * @group Hybrid Learning
     * @subgroup Class Management
     * @bodyParam course_id integer required The ID of the course to add. Example: 5
     * @response 200 {"message": "Course added to class successfully", "data": object}
     */
    public function addCourse(Request $request, $classId)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
        ]);

        $user = $request->user();

        // Get classroom batch
        $batch = Batch::classroom()->findOrFail($classId);

        // Verify instructor owns the class
        if ($batch->instructor_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Verify instructor owns the course
        $course = Course::where('id', '=', $request->course_id)
            ->where('instructor_id', '=', $user->id)
            ->firstOrFail();

        // Check if course already attached
        if ($batch->courses()->where('course_id', $request->course_id)->exists()) {
            return response()->json(['message' => 'Course already added to this class'], 422);
        }

        // Attach course
        $batch->courses()->attach($request->course_id, [
            'order' => $batch->courses()->count() + 1,
            'is_required' => false,  // Optional in classroom
        ]);

        return $this->successResponse(
            new ClassroomResource($batch->load(['courses', 'instructor'])), // Reload to get updated courses
            'Course added to class successfully'
        );
    }

    /**
     * Remove Course from Class
     * 
     * Detach a course from this class.
     * 
     * @group Hybrid Learning
     * @subgroup Class Management
     * @urlParam classId integer required The ID of the class. Example: 1
     * @urlParam courseId integer required The ID of the course to remove. Example: 5
     * @response 200 {"message": "Course removed from class successfully"}
     */
    public function removeCourse(Request $request, $classId, $courseId)
    {
        $user = $request->user();

        // Get classroom batch
        $batch = Batch::classroom()->findOrFail($classId);

        // Verify instructor owns the class
        if ($batch->instructor_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Detach course
        $batch->courses()->detach($courseId);

        return $this->successResponse(
            new ClassroomResource($batch->load(['courses', 'instructor'])),
            'Course removed from class successfully'
        );
    }

    /**
     * Join Class
     * 
     * Join an existing class using its unique 6-character class code.
     * Students can join even if the class has no courses yet.
     * 
     * @group Hybrid Learning
     * @subgroup Student Actions
     * @bodyParam class_code string required The unique code of the class to join. Example: A1B2C3
     * @response 200 {"message": "Successfully joined the class", "data": object}
     * @response 404 {"message": "Invalid class code"}
     * @response 409 {"message": "Already enrolled in this class"}
     */
    public function join(Request $request)
    {
        $request->validate([
            'class_code' => 'required|string|size:6',
        ]);

        $batch = Batch::classroom()
            ->where('class_code', $request->class_code)
            ->first();

        if (!$batch) {
            return response()->json(['message' => 'Invalid class code'], 404);
        }

        if (!$batch->is_open_for_enrollment) {
             return response()->json(['message' => 'Class is not open for enrollment'], 403);
        }

        $user = $request->user();

        // Check if already enrolled
        if ($batch->enrollments()->where('user_id', $user->id)->exists()) {
             return response()->json(['message' => 'Already enrolled in this class'], 409);
        }

        // Create Enrollment (course_id is nullable now)
        Enrollment::create([
            'user_id' => $user->id,
            'course_id' => null,  // No course required for classroom enrollment
            'batch_id' => $batch->id,
            'enrolled_at' => now(),
            'is_completed' => false,
            'progress_percentage' => 0,
        ]);

        $batch->increment('current_students', 1);

        return response()->json([
            'message' => 'Successfully joined the class',
            'data' => new \App\Http\Resources\Api\V1\BatchResource($batch->load('courses', 'instructor')),
        ]);
    }
}
