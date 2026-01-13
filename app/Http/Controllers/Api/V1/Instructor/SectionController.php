<?php

namespace App\Http\Controllers\Api\V1\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    /**
     * Store a newly created section.
     */
    public function store(Request $request, Course $course): JsonResponse
    {
        // Ensure instructor owns this course
        if ($course->instructor_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        // Get next sort order
        $maxOrder = $course->sections()->max('sort_order') ?? -1;
        $validated['sort_order'] = $maxOrder + 1;

        $section = $course->sections()->create($validated);

        return response()->json([
            'message' => 'Section created successfully.',
            'data' => $section,
        ], 201);
    }

    /**
     * Update the specified section.
     */
    public function update(Request $request, Course $course, Section $section): JsonResponse
    {
        // Ensure instructor owns this course
        if ($course->instructor_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        // Ensure section belongs to course
        if ($section->course_id !== $course->id) {
            return response()->json([
                'message' => 'Section not found in this course.',
            ], 404);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $section->update($validated);

        return response()->json([
            'message' => 'Section updated successfully.',
            'data' => $section,
        ]);
    }

    /**
     * Remove the specified section.
     */
    public function destroy(Request $request, Course $course, Section $section): JsonResponse
    {
        // Ensure instructor owns this course
        if ($course->instructor_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        // Ensure section belongs to course
        if ($section->course_id !== $course->id) {
            return response()->json([
                'message' => 'Section not found in this course.',
            ], 404);
        }

        $section->delete();

        return response()->json([
            'message' => 'Section deleted successfully.',
        ]);
    }

    /**
     * Reorder sections in a course.
     */
    public function reorder(Request $request, Course $course): JsonResponse
    {
        // Ensure instructor owns this course
        if ($course->instructor_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $validated = $request->validate([
            'sections' => ['required', 'array'],
            'sections.*.id' => ['required', 'exists:sections,id'],
            'sections.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($validated['sections'] as $item) {
            Section::where('id', $item['id'])
                ->where('course_id', $course->id)
                ->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json([
            'message' => 'Sections reordered successfully.',
        ]);
    }
}
