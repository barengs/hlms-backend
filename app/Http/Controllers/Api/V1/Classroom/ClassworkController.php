<?php

namespace App\Http\Controllers\Api\V1\Classroom;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Lesson;
use App\Models\Section;
use Illuminate\Http\Request;

class ClassworkController extends Controller
{
    /**
     * Get Class Materials (Work)
     * 
     * Retrieve the structured curriculum (topics and materials) for a specific class.
     * 
     * @group Hybrid Learning
     * @subgroup Classwork
     * @urlParam id integer required The ID of the class (batch).
     * @response 200 array{data: array<object>}
     */
    public function index($id)
    {
        $batch = Batch::findOrFail($id);
        
        // TODO: Authorization check

        $sections = Section::where('course_id', $batch->course_id)
            ->with(['lessons' => function($query) {
                $query->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        return response()->json($sections);
    }

    /**
     * Create Topic
     * 
     * Create a new topic (section) to organize materials in the class.
     * 
     * @group Hybrid Learning
     * @subgroup Classwork
     * @urlParam id integer required The ID of the class (batch).
     * @bodyParam title string required The title of the topic. Example: Chapter 1: Introduction
     * @response 201 {"message": "Topic created successfully", "topic": object}
     */
    public function storeTopic(Request $request, $id)
    {
        $batch = Batch::findOrFail($id);
        
        // TODO: Authorization (Instructor only)

        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $section = Section::create([
            'course_id' => $batch->course_id,
            'title' => $request->title,
            'sort_order' => Section::where('course_id', $batch->course_id)->max('sort_order') + 1,
        ]);

        return response()->json([
            'message' => 'Topic created successfully',
            'topic' => $section,
        ], 201);
    }

    /**
     * Create Material
     * 
     * Add a new learning material (lesson) to a specific topic in the class.
     * 
     * @group Hybrid Learning
     * @subgroup Classwork
     * @urlParam id integer required The ID of the class (batch).
     * @bodyParam section_id integer required The ID of the topic (section) to add this material to.
     * @bodyParam title string required The title of the material.
     * @bodyParam content string optional Text content or description.
     * @bodyParam video_url string optional URL to a video resource.
     * @response 201 {"message": "Material created successfully", "material": object}
     */
    public function storeMaterial(Request $request, $id)
    {
        $batch = Batch::findOrFail($id);
        
        // TODO: Authorization (Instructor only)

        $request->validate([
            'section_id' => 'required|exists:sections,id', // Must ensure section belongs to this course
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'video_url' => 'nullable|url',
        ]);

        $section = Section::findOrFail($request->section_id);
        
        if ($section->course_id !== $batch->course_id) {
             return response()->json(['message' => 'Invalid section for this class'], 400);
        }

        $lesson = Lesson::create([
            'section_id' => $section->id,
            'title' => $request->title,
            'content' => $request->input('content'),
            'video_url' => $request->video_url,
            'type' => 'text', // Default to text/material handling
            'is_published' => true,
            'is_free' => true,
            'sort_order' => Lesson::where('section_id', $section->id)->max('sort_order') + 1,
        ]);

        return response()->json([
            'message' => 'Material created successfully',
            'material' => $lesson,
        ], 201);
    }
}
