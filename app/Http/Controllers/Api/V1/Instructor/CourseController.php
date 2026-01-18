<?php

namespace App\Http\Controllers\Api\V1\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CourseController extends Controller
{
    /**
     * List Courses
     * 
     * Get a list of the instructor's courses with filtering options.
     * 
     * @group Instructor
     * @subgroup Course Management
     * @queryParam status string Filter by status (draft, published, etc.).
     * @queryParam type string Filter by type (self_paced, structured).
     * @queryParam search string Search by title.
     * @response 200 [
     *   {
     *     "id": 1,
     *     "title": "Laravel for Beginners",
     *     "revenue": 1500000,
     *     "admin_feedback": null
     *   }
     * ]
     */
    public function index(Request $request): JsonResponse
    {
        $courses = Course::where('instructor_id', $request->user()->id)
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->type, fn($q, $type) => $q->where('type', $type))
            ->when($request->search, fn($q, $search) => $q->where('title', 'like', "%{$search}%"))
            ->with(['category:id,name,slug'])
            ->withCount(['sections', 'lessons'])
            ->withSum(['orderItems as revenue' => function ($query) {
                $query->whereHas('order', function ($q) {
                    $q->where('status', 'paid');
                });
            }], 'price')
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json($courses);
    }

    /**
     * Create Course
     * 
     * Create a new course draft.
     * 
     * @group Instructor
     * @subgroup Course Management
     * @bodyParam title string required The title of the course.
     * @bodyParam type string required Type of course (self_paced, structured).
     * @bodyParam price number optional Price of the course.
     * @response 201 {"message": "Course created successfully.", "data": object}
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:courses'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'type' => ['required', Rule::in(['self_paced', 'structured'])],
            'level' => ['nullable', Rule::in(['beginner', 'intermediate', 'advanced', 'all_levels'])],
            'language' => ['nullable', 'string', 'max:10'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'requirements' => ['nullable', 'array'],
            'outcomes' => ['nullable', 'array'],
            'target_audience' => ['nullable', 'array'],
        ]);

        // Auto-generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }

        // Ensure unique slug
        $originalSlug = $validated['slug'];
        $counter = 1;
        while (Course::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $originalSlug . '-' . $counter++;
        }

        $validated['instructor_id'] = $request->user()->id;

        $course = Course::create($validated);

        return response()->json([
            'message' => 'Course created successfully.',
            'data' => $course->load('category'),
        ], 201);
    }

    /**
     * Get Course Details
     * 
     * Retrieve details of a specific course including sections and lessons count.
     * 
     * @group Instructor
     * @subgroup Course Management
     * @urlParam course integer required The ID of the course.
     * @response 200 {"data": object}
     */
    public function show(Request $request, Course $course): JsonResponse
    {
        // Ensure instructor owns this course
        if ($course->instructor_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        return response()->json([
            'data' => $course->load([
                'category:id,name,slug',
                'sections' => fn($q) => $q->withCount('lessons'),
                'sections.lessons',
            ]),
        ]);
    }

    /**
     * Update Course
     * 
     * Update details of an existing course.
     * 
     * @group Instructor
     * @subgroup Course Management
     * @urlParam course integer required The ID of the course.
     * @bodyParam title string optional The title of the course.
     * @bodyParam description string optional Course description.
     * @response 200 {"message": "Course updated successfully.", "data": object}
     */
    public function update(Request $request, Course $course): JsonResponse
    {
        // Ensure instructor owns this course
        if ($course->instructor_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('courses')->ignore($course->id)],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'type' => ['sometimes', Rule::in(['self_paced', 'structured'])],
            'level' => ['nullable', Rule::in(['beginner', 'intermediate', 'advanced', 'all_levels'])],
            'language' => ['nullable', 'string', 'max:10'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric', 'min:0'],
            'requirements' => ['nullable', 'array'],
            'outcomes' => ['nullable', 'array'],
            'target_audience' => ['nullable', 'array'],
        ]);

        $course->update($validated);

        return response()->json([
            'message' => 'Course updated successfully.',
            'data' => $course->fresh('category'),
        ]);
    }

    /**
     * Upload Thumbnail
     * 
     * Upload and update the course thumbnail image.
     * 
     * @group Instructor
     * @subgroup Course Management
     * @urlParam course integer required The ID of the course.
     * @bodyParam thumbnail file required The image file (max 2MB).
     * @response 200 {"message": "Thumbnail uploaded successfully.", "data": {"url": "..."}}
     */
    public function uploadThumbnail(Request $request, Course $course): JsonResponse
    {
        // Ensure instructor owns this course
        if ($course->instructor_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $request->validate([
            'thumbnail' => ['required', 'image', 'max:2048'], // 2MB max
        ]);

        // Delete old thumbnail
        if ($course->thumbnail) {
            Storage::disk('public')->delete($course->thumbnail);
        }

        $path = $request->file('thumbnail')->store('courses/thumbnails', 'public');
        $course->update(['thumbnail' => $path]);

        return response()->json([
            'message' => 'Thumbnail uploaded successfully.',
            'data' => [
                'thumbnail' => $path,
                'url' => Storage::disk('public')->url($path),
            ],
        ]);
    }

    /**
     * Submit for Review
     * 
     * Submit a draft course for admin review.
     * 
     * @group Instructor
     * @subgroup Course Management
     * @urlParam course integer required The ID of the course.
     * @response 200 {"message": "Course submitted for review.", "data": object}
     */
    public function submitForReview(Request $request, Course $course): JsonResponse
    {
        // Ensure instructor owns this course
        if ($course->instructor_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        // Validate course has minimum requirements
        if ($course->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft courses can be submitted for review.',
            ], 422);
        }

        // Check minimum content
        $lessonsCount = $course->lessons()->count();
        if ($lessonsCount < 1) {
            return response()->json([
                'message' => 'Course must have at least one lesson before submission.',
            ], 422);
        }

        $course->update(['status' => 'pending_review']);

        return response()->json([
            'message' => 'Course submitted for review.',
            'data' => $course,
        ]);
    }

    /**
     * Delete Course
     * 
     * Delete a draft course.
     * 
     * @group Instructor
     * @subgroup Course Management
     * @urlParam course integer required The ID of the course.
     * @response 200 {"message": "Course deleted successfully."}
     */
    public function destroy(Request $request, Course $course): JsonResponse
    {
        // Ensure instructor owns this course
        if ($course->instructor_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        // Can only delete draft courses
        if ($course->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft courses can be deleted.',
            ], 422);
        }

        // Delete thumbnail
        if ($course->thumbnail) {
            Storage::disk('public')->delete($course->thumbnail);
        }

        $course->delete();

        return response()->json([
            'message' => 'Course deleted successfully.',
        ]);
    }
}
