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
                        $query->select('users.id', 'users.name');
                    },
                    'grades' => function ($query) {
                        $query->select('batch_id', 'overall_score');
                    }
                ])
                ->when($request->status, function ($query, $status) {
                    $query->where('status', $status);
                })
                ->latest()
                ->get();

            // Get the response data using ClassroomResource
            $classesData = ClassroomResource::collection($classes);
            
            // Build final response with meta
            $responseData = [
                'items' => $classesData,
                'meta' => [
                    'statistics' => $statistics,
                    'filters' => $filters
                ]
            ];

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
                        $query->select('users.id', 'users.name');
                    },
                    'grades' => function ($query) use ($user) {
                        $query->where('user_id', $user->id)->select('batch_id', 'overall_score');
                    }
                ])
                ->latest()
                ->get();

            // Use ClassroomResource for consistent response format
            return $this->successResponse(
                ClassroomResource::collection($classes),
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
     * @responseField id integer The ID of the batch.
     * @responseField name string The name of the class.
     * @responseField type string The type of batch (classroom).
     * @responseField class_code string The unique code for students to join.
     * @responseField instructor object The instructor details.
     * @responseField instructor.id integer Instructor ID.
     * @responseField instructor.name string Instructor Name.
     * @responseField courses object[] List of courses attached to this class.
     * @responseField courses[].topics object[] List of topics (sections) in the course.
     * @responseField courses[].topics[].id integer Topic ID.
     * @responseField courses[].topics[].title string Topic Title.
     * @responseField courses[].topics[].materials_count integer Count of materials.
     * @responseField courses[].topics[].materials object[] List of materials (lessons) in the topic.
     * @responseField courses[].topics[].materials[].id integer Material ID.
     * @responseField courses[].topics[].materials[].title string Material Title.
     * @responseField courses[].topics[].materials[].type string Material Type.
     * @responseField students object[] List of enrolled students (Instructor view).
     * @responseField students[].id integer Student ID.
     * @responseField students[].name string Student Name.
     * @responseField students[].avatar string Student Avatar URL.
     * @responseField students[].progress integer Progress percentage (0-100).
     * @responseField students[].grade_score number Overall grade score (0-100).
     * @responseField students[].grade_letter string Letter grade (A, B, C...).
     * @responseField assessment_stats object Assessment statistics (Instructor view).
     * @responseField assessment_stats.assignments_count integer Total assignments.
     * @responseField assessment_stats.ungraded_submissions_count integer submissions waiting for grading.
     * @responseField assessment_stats.class_average_score number Average score of the class.
     * @responseField assessment_stats.achieving_students_count integer Students with score >= 80.
     * @responseField assessment_stats.achieving_students_count integer Students with score >= 80.
     * @responseField assessment_stats.needs_attention_count integer Students with score < 50.
     */
    public function show($id)
    {
        $batch = Batch::classroom()
            // Student and Instructor views might differ, but for now we return all data
        
            ->with([
                'courses.instructor', 
                'courses.sections.lessons', 
                'instructor', 
                'enrollments.student', // Load student info
                'enrollments' => function($q) {
                    $q->with('student'); // Ensure user data is loaded
                } 
            ])
            ->findOrFail($id);

        // Load aggregate grades for the batch
        $batch->load(['grades' => function($q) {
            $q->select('batch_id', 'user_id', 'overall_score', 'letter_grade');
        }]);

        return $this->successResponse(
            new ClassroomResource($batch),
            'Class details retrieved successfully'
        );
    }

    /**
     * Update Class
     * 
     * Update class details (Settings tab).
     * 
     * @group Hybrid Learning
     * @subgroup Class Management
     * @bodyParam name string optional
     * @bodyParam description string optional
     * @bodyParam is_open_for_enrollment boolean optional
     * @bodyParam auto_approve boolean optional
     */
    public function update(Request $request, $id)
    {
        $batch = Batch::classroom()->findOrFail($id);
        
        if ($request->user()->id !== $batch->instructor_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'subject' => 'nullable|string|max:255',
            'section' => 'nullable|string|max:50',
            'is_open_for_enrollment' => 'boolean',
            'auto_approve' => 'boolean',
            'status' => 'in:open,closed,archived',
        ]);

        $batch->update($validated);

        return $this->successResponse(
            new ClassroomResource($batch),
            'Class updated successfully'
        );
    }

    /**
     * Archive/Delete Class
     * 
     * Archive or delete a class.
     */
    public function destroy(Request $request, $id)
    {
        $batch = Batch::classroom()->findOrFail($id);
        
        if ($request->user()->id !== $batch->instructor_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if soft delete or force delete? 
        // For now, simple delete
        $batch->delete();

        return $this->successResponse(
            null,
            'Class deleted successfully'
        );
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

        return $this->successResponse(
            new ClassroomResource($batch->load(['courses', 'instructor'])),
            'Successfully joined the class'
        );
    }
}
