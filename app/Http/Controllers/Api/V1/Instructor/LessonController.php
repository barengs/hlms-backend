<?php

namespace App\Http\Controllers\Api\V1\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class LessonController extends Controller
{
    /**
     * Store a newly created lesson.
     */
    public function store(Request $request, Course $course, Section $section): JsonResponse
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::in(['video', 'text', 'quiz', 'assignment'])],
            'video_url' => ['nullable', 'string', 'max:500'],
            'video_provider' => ['nullable', 'string', 'max:50'],
            'duration' => ['nullable', 'integer', 'min:0'],
            'content' => ['nullable', 'string'],
            'is_free' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        // Get next sort order
        $maxOrder = $section->lessons()->max('sort_order') ?? -1;
        $validated['sort_order'] = $maxOrder + 1;

        $lesson = $section->lessons()->create($validated);

        // Update course stats
        $this->updateCourseStats($course);

        return response()->json([
            'message' => 'Lesson created successfully.',
            'data' => $lesson,
        ], 201);
    }

    /**
     * Display the specified lesson.
     */
    public function show(Request $request, Course $course, Section $section, Lesson $lesson): JsonResponse
    {
        // Ensure instructor owns this course
        if ($course->instructor_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        // Ensure lesson belongs to section
        if ($lesson->section_id !== $section->id) {
            return response()->json([
                'message' => 'Lesson not found in this section.',
            ], 404);
        }

        return response()->json([
            'data' => $lesson->load('attachments'),
        ]);
    }

    /**
     * Update the specified lesson.
     */
    public function update(Request $request, Course $course, Section $section, Lesson $lesson): JsonResponse
    {
        // Ensure instructor owns this course
        if ($course->instructor_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        // Ensure lesson belongs to section
        if ($lesson->section_id !== $section->id) {
            return response()->json([
                'message' => 'Lesson not found in this section.',
            ], 404);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['sometimes', Rule::in(['video', 'text', 'quiz', 'assignment'])],
            'video_url' => ['nullable', 'string', 'max:500'],
            'video_provider' => ['nullable', 'string', 'max:50'],
            'duration' => ['nullable', 'integer', 'min:0'],
            'content' => ['nullable', 'string'],
            'is_free' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $lesson->update($validated);

        // Update course stats
        $this->updateCourseStats($course);

        return response()->json([
            'message' => 'Lesson updated successfully.',
            'data' => $lesson,
        ]);
    }

    /**
     * Remove the specified lesson.
     */
    public function destroy(Request $request, Course $course, Section $section, Lesson $lesson): JsonResponse
    {
        // Ensure instructor owns this course
        if ($course->instructor_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        // Ensure lesson belongs to section
        if ($lesson->section_id !== $section->id) {
            return response()->json([
                'message' => 'Lesson not found in this section.',
            ], 404);
        }

        // Delete attachments
        foreach ($lesson->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->file_path);
            $attachment->delete();
        }

        $lesson->delete();

        // Update course stats
        $this->updateCourseStats($course);

        return response()->json([
            'message' => 'Lesson deleted successfully.',
        ]);
    }

    /**
     * Reorder lessons in a section.
     */
    public function reorder(Request $request, Course $course, Section $section): JsonResponse
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
            'lessons' => ['required', 'array'],
            'lessons.*.id' => ['required', 'exists:lessons,id'],
            'lessons.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($validated['lessons'] as $item) {
            Lesson::where('id', $item['id'])
                ->where('section_id', $section->id)
                ->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json([
            'message' => 'Lessons reordered successfully.',
        ]);
    }

    /**
     * Upload attachment to a lesson.
     */
    public function uploadAttachment(Request $request, Course $course, Section $section, Lesson $lesson): JsonResponse
    {
        // Ensure instructor owns this course
        if ($course->instructor_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        // Ensure lesson belongs to section
        if ($lesson->section_id !== $section->id) {
            return response()->json([
                'message' => 'Lesson not found in this section.',
            ], 404);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:51200'], // 50MB max
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $request->file('file');
        $path = $file->store('courses/attachments', 'public');

        $attachment = $lesson->attachments()->create([
            'title' => $request->title ?? $file->getClientOriginalName(),
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'sort_order' => $lesson->attachments()->count(),
        ]);

        return response()->json([
            'message' => 'Attachment uploaded successfully.',
            'data' => $attachment,
        ], 201);
    }

    /**
     * Delete attachment from a lesson.
     */
    public function deleteAttachment(Request $request, Course $course, Section $section, Lesson $lesson, int $attachmentId): JsonResponse
    {
        // Ensure instructor owns this course
        if ($course->instructor_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $attachment = $lesson->attachments()->findOrFail($attachmentId);

        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        return response()->json([
            'message' => 'Attachment deleted successfully.',
        ]);
    }

    /**
     * Update course statistics.
     */
    private function updateCourseStats(Course $course): void
    {
        $course->update([
            'total_lessons' => $course->lessons()->count(),
            'total_duration' => $course->lessons()->sum('duration'),
        ]);
    }
}
