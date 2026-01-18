<?php

namespace App\Http\Controllers\Api\V1\Classroom;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClassController extends Controller
{
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
            $classes = Batch::query()
                ->whereHas('course', function ($query) use ($user) {
                    $query->where('instructor_id', $user->id)
                          ->where('type', 'classroom');
                })
                ->with(['course'])
                ->latest()
                ->paginate(20);
        } else {
            $classes = Batch::query()
                ->whereHas('enrollments', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->with(['course.instructor'])
                ->latest()
                ->paginate(20);
        }

        return response()->json($classes);
    }

    /**
     * Create Class
     * 
     * Create a new Hybrid Class. This automatically acts as both a Course (for content) 
     * and a Batch (for people).
     * 
     * @group Hybrid Learning
     * @subgroup Class Management
     * @bodyParam name string required The name of the class. Example: Mathematics 101
     * @bodyParam description string optional A brief description of the class.
     * @bodyParam category_id integer required The ID of the category this class belongs to. Example: 1
     * @response 201 {"message": "Class created successfully", "class": object}
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id', // Needed for course creation
        ]);

        $user = $request->user();

        // 1. Create the Course (Container for content)
        $course = Course::create([
            'instructor_id' => $user->id,
            'category_id' => $request->category_id,
            'title' => $request->name,
            'slug' => Str::slug($request->name . '-' . Str::random(5)),
            'description' => $request->description,
            'type' => 'classroom', // Distinguish from normal courses
            'status' => 'published',
            'published_at' => now(),
            'price' => 0, // Classes are usually free/internal for now
            'is_featured' => false,
        ]);

        // 2. Create the Batch (Container for people/schedule)
        $batch = Batch::create([
            'course_id' => $course->id,
            'name' => $request->name, // Same as course title initially
            'slug' => Str::slug($request->name . '-' . Str::random(5)),
            'description' => $request->description,
            'class_code' => Batch::generateClassCode(),
            'status' => 'open',
            'start_date' => now(),
            'enrollment_start_date' => now(),
            'enrollment_end_date' => now()->addYears(1), // Long duration by default
            'is_public' => false, // Hidden from public catalog
            'auto_approve' => true,
        ]);

        return response()->json([
            'message' => 'Class created successfully',
            'class' => $batch->load('course'),
        ], 201);
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
        $batch = Batch::with(['course.instructor', 'course.sections.lessons'])
            ->findOrFail($id);

        // TODO: distinct between student view and instructor view?
        
        return response()->json($batch);
    }

    /**
     * Join Class
     * 
     * Join an existing class using its unique 6-character class code.
     * 
     * @group Hybrid Learning
     * @subgroup Student Actions
     * @bodyParam class_code string required The unique code of the class to join. Example: A1B2C3
     * @response 200 {"message": "Successfully joined the class", "class": object}
     * @response 404 {"message": "Invalid class code"}
     * @response 409 {"message": "Already enrolled in this class"}
     */
    public function join(Request $request)
    {
        $request->validate([
            'class_code' => 'required|string|size:6',
        ]);

        $batch = Batch::where('class_code', $request->class_code)->first();

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

        // Create Enrollment
        Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $batch->course_id,
            'batch_id' => $batch->id,
            'enrolled_at' => now(),
            'is_completed' => false,
            'progress_percentage' => 0,
        ]);

        $batch->increment('current_students');

        return response()->json([
            'message' => 'Successfully joined the class',
            'class' => $batch,
        ]);
    }
}
