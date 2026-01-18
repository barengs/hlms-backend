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
     * Create Section
     * 
     * Create a new section (module/chapter) within a specific course.
     * 
     * @group Instructor
     * @subgroup Course Curriculum
     * @urlParam course integer required The ID of the course.
     * @bodyParam title string required The title of the section.
     * @bodyParam description string optional A brief description.
     * @response 201 {"message": "Section created successfully.", "data": object}
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
     * Update Section
     * 
     * Update specific section details.
     * 
     * @group Instructor
     * @subgroup Course Curriculum
     * @urlParam course integer required The ID of the course.
     * @urlParam section integer required The ID of the section.
     * @bodyParam title string optional The title of the section.
     * @bodyParam description string optional A brief description.
     * @response 200 {"message": "Section updated successfully.", "data": object}
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
     * Delete Section
     * 
     * Remove a section and all its lessons.
     * 
     * @group Instructor
     * @subgroup Course Curriculum
     * @urlParam course integer required The ID of the course.
     * @urlParam section integer required The ID of the section.
     * @response 200 {"message": "Section deleted successfully."}
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
     * Reorder Sections
     * 
     * Update the sort order of sections in a course.
     * 
     * @group Instructor
     * @subgroup Course Curriculum
     * @urlParam course integer required The ID of the course.
     * @bodyParam sections array required List of section IDs and their new sort_order.
     * @bodyParam sections.*.id integer required The Section ID.
     * @bodyParam sections.*.sort_order integer required The new order index.
     * @response 200 {"message": "Sections reordered successfully."}
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
