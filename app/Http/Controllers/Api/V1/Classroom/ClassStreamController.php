<?php

namespace App\Http\Controllers\Api\V1\Classroom;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Discussion;
use Illuminate\Http\Request;

class ClassStreamController extends Controller
{
    /**
     * Get Class Stream
     * 
     * Retrieve the activity stream (discussions, announcements, questions) for a class.
     * 
     * @group Hybrid Learning
     * @subgroup Stream
     * @urlParam id integer required The ID of the class (batch).
     * @response 200 array{data: array<object>}
     */
    public function index(Request $request, $id)
    {
        $batch = Batch::findOrFail($id);
        
        // TODO: Check authorization (is enrolled)

        $discussions = Discussion::query()
            ->where('batch_id', $batch->id)
            ->whereNull('parent_id') // Top level posts
            ->with(['user.profile', 'replies.user.profile']) // Eager load relations
            ->orderBy('is_pinned', 'desc')
            ->latest()
            ->paginate(15);

        return response()->json($discussions);
    }

    /**
     * Post to Stream
     * 
     * Create a new post (discussion, question, or announcement) in the class stream.
     * 
     * @group Hybrid Learning
     * @subgroup Stream
     * @urlParam id integer required The ID of the class (batch).
     * @bodyParam content string required The content of the post.
     * @bodyParam title string optional A title for the post.
     * @bodyParam type string optional The type of post. Allowed values: discussion, question, announcement. Default: discussion.
     * @response 201 {"message": "Posted to stream successfully", "discussion": object}
     */
    public function store(Request $request, $id)
    {
        $batch = Batch::findOrFail($id);

        // TODO: Check authorization

        $request->validate([
            'content' => 'required|string',
            'title' => 'nullable|string',
            'type' => 'in:discussion,question,announcement',
        ]);

        $discussion = Discussion::create([
            'batch_id' => $batch->id,
            'user_id' => $request->user()->id,
            'title' => $request->title,
            'content' => $request->input('content'),
            'type' => $request->type ?? 'discussion',
            'is_approved' => true, // Auto approve for now
        ]);

        return response()->json([
            'message' => 'Posted to stream successfully',
            'discussion' => $discussion->load('user.profile'),
        ], 201);
    }
}
