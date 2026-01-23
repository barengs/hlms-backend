<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\LearningPath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LearningPathController extends Controller
{
    /**
     * Display a listing of learning paths.
     */
    public function index(Request $request): JsonResponse
    {
        $query = LearningPath::with(['category:id,name,slug', 'courses:id,title']);

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by level
        if ($request->has('level')) {
            $query->where('level', $request->level);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by featured
        if ($request->boolean('featured')) {
            $query->featured();
        }

        $learningPaths = $query->orderBy('sort_order')->paginate($request->per_page ?? 15);

        return response()->json($learningPaths);
    }

    /**
     * Store a newly created learning path.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:learning_paths'],
            'description' => ['nullable', 'string'],
            'thumbnail' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'level' => ['required', 'in:beginner,intermediate,advanced'],
            'estimated_duration' => ['nullable', 'integer', 'min:1'],
            'is_featured' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        // Auto-generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }

        // Ensure unique slug
        $originalSlug = $validated['slug'];
        $counter = 1;
        while (LearningPath::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $originalSlug . '-' . $counter++;
        }

        $learningPath = LearningPath::create($validated);

        return response()->json([
            'message' => 'Learning path created successfully.',
            'data' => $learningPath->load('category'),
        ], 201);
    }

    /**
     * Display the specified learning path.
     */
    public function show(LearningPath $learningPath): JsonResponse
    {
        $learningPath->load([
            'category:id,name,slug',
            'items.course:id,title,slug,level,thumbnail',
            'courses:id,title,slug,level,thumbnail,instructor_id',
            'courses.instructor:id,name',
        ]);

        return response()->json([
            'data' => $learningPath,
        ]);
    }

    /**
     * Update the specified learning path.
     */
    public function update(Request $request, LearningPath $learningPath): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('learning_paths')->ignore($learningPath->id)],
            'description' => ['nullable', 'string'],
            'thumbnail' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'level' => ['sometimes', 'in:beginner,intermediate,advanced'],
            'estimated_duration' => ['nullable', 'integer', 'min:1'],
            'is_featured' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $learningPath->update($validated);

        return response()->json([
            'message' => 'Learning path updated successfully.',
            'data' => $learningPath->fresh(['category', 'courses']),
        ]);
    }

    /**
     * Remove the specified learning path.
     */
    public function destroy(LearningPath $learningPath): JsonResponse
    {
        $learningPath->delete();

        return response()->json([
            'message' => 'Learning path deleted successfully.',
        ]);
    }

    /**
     * Add a course to learning path.
     */
    public function addCourse(Request $request, LearningPath $learningPath): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'step_number' => ['required', 'integer', 'min:1'],
            'step_title' => ['nullable', 'string', 'max:255'],
            'step_description' => ['nullable', 'string'],
            'is_required' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        // Check if course already exists in this learning path
        if ($learningPath->courses()->where('course_id', $validated['course_id'])->exists()) {
            return response()->json([
                'message' => 'Course already exists in this learning path.',
            ], 422);
        }

        $learningPath->courses()->attach($validated['course_id'], [
            'step_number' => $validated['step_number'],
            'step_title' => $validated['step_title'] ?? null,
            'step_description' => $validated['step_description'] ?? null,
            'is_required' => $validated['is_required'] ?? true,
            'sort_order' => $validated['sort_order'] ?? $validated['step_number'],
        ]);

        return response()->json([
            'message' => 'Course added to learning path successfully.',
            'data' => $learningPath->fresh(['courses']),
        ]);
    }

    /**
     * Remove a course from learning path.
     */
    public function removeCourse(LearningPath $learningPath, $courseId): JsonResponse
    {
        $learningPath->courses()->detach($courseId);

        return response()->json([
            'message' => 'Course removed from learning path successfully.',
        ]);
    }

    /**
     * Reorder courses in learning path.
     */
    public function reorder(Request $request, LearningPath $learningPath): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.course_id' => ['required', 'exists:courses,id'],
            'items.*.step_number' => ['required', 'integer', 'min:1'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($validated['items'] as $item) {
            $learningPath->courses()->updateExistingPivot($item['course_id'], [
                'step_number' => $item['step_number'],
                'sort_order' => $item['sort_order'],
            ]);
        }

        return response()->json([
            'message' => 'Learning path courses reordered successfully.',
            'data' => $learningPath->fresh(['courses']),
        ]);
    }
}
