<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Discussion;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscussionController extends Controller
{
    /**
     * Display a listing of discussions for a batch or lesson.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Discussion::approved()
            ->with(['user:id,name', 'replies'])
            ->when($request->batch_id, function ($query, $batchId) {
                $query->where('batch_id', $batchId);
            })
            ->when($request->lesson_id, function ($query, $lessonId) {
                $query->where('lesson_id', $lessonId);
            })
            ->when($request->type, function ($query, $type) {
                $query->where('type', $type);
            })
            ->when($request->search, function ($query, $search) {
                $query->where('title', 'LIKE', "%{$search}%")
                      ->orWhere('content', 'LIKE', "%{$search}%");
            })
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at');

        $discussions = $query->paginate($request->per_page ?? 15);

        return response()->json($discussions);
    }

    /**
     * Store a newly created discussion.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'batch_id' => ['nullable', 'exists:batches,id'],
            'lesson_id' => ['nullable', 'exists:lessons,id'],
            'title' => ['required_unless:type,announcement', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'type' => ['required', 'in:question,discussion,announcement'],
            'parent_id' => ['nullable', 'exists:discussions,id'],
        ]);

        // If this is a reply, validate the parent discussion exists
        if ($validated['parent_id']) {
            $parentDiscussion = Discussion::findOrFail($validated['parent_id']);
            // Set the same batch_id or lesson_id as the parent
            $validated['batch_id'] = $validated['batch_id'] ?? $parentDiscussion->batch_id;
            $validated['lesson_id'] = $validated['lesson_id'] ?? $parentDiscussion->lesson_id;
        }

        // Verify user has access to the batch or lesson if specified
        if ($validated['batch_id']) {
            $batch = Batch::findOrFail($validated['batch_id']);
            // We'll check if user is enrolled in the batch later
        }

        if ($validated['lesson_id']) {
            $lesson = Lesson::findOrFail($validated['lesson_id']);
            // We'll check if user has access to the lesson later
        }

        $validated['user_id'] = $request->user()->id;
        $validated['is_approved'] = true; // For now, auto-approve

        $discussion = Discussion::create($validated);

        // If this is a reply, increment the parent's reply count
        if ($validated['parent_id']) {
            $parentDiscussion->increment('replies_count');
        }

        return response()->json([
            'message' => 'Discussion created successfully.',
            'data' => $discussion->load(['user:id,name', 'replies']),
        ], 201);
    }

    /**
     * Display the specified discussion.
     */
    public function show(Request $request, string $discussionId): JsonResponse
    {
        $discussion = Discussion::with([
                'user:id,name',
                'replies' => function ($query) {
                    $query->with('user:id,name')
                          ->orderBy('created_at', 'asc');
                }
            ])
            ->findOrFail($discussionId);

        // Increment view count
        $discussion->increment('views_count');

        return response()->json([
            'data' => $discussion,
        ]);
    }

    /**
     * Update the specified discussion.
     */
    public function update(Request $request, string $discussionId): JsonResponse
    {
        $discussion = Discussion::where('user_id', $request->user()->id)
            ->findOrFail($discussionId);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'content' => ['sometimes', 'string'],
        ]);

        $discussion->update($validated);

        return response()->json([
            'message' => 'Discussion updated successfully.',
            'data' => $discussion->load(['user:id,name']),
        ]);
    }

    /**
     * Remove the specified discussion.
     */
    public function destroy(Request $request, string $discussionId): JsonResponse
    {
        $discussion = Discussion::where('user_id', $request->user()->id)
            ->orWhere(function ($query) use ($request) {
                $query->whereHas('batch.courses', function ($q) use ($request) {
                    $q->where('instructor_id', $request->user()->id);
                });
            })
            ->findOrFail($discussionId);

        // If this is a reply, decrement the parent's reply count
        if ($discussion->parent_id) {
            $discussion->parent->decrement('replies_count');
        }

        $discussion->delete();

        return response()->json([
            'message' => 'Discussion deleted successfully.',
        ]);
    }

    /**
     * Pin/unpin a discussion (instructor only).
     */
    public function togglePin(Request $request, string $discussionId): JsonResponse
    {
        $discussion = Discussion::whereHas('batch.courses', function ($query) use ($request) {
                $query->where('instructor_id', $request->user()->id);
            })
            ->findOrFail($discussionId);

        $discussion->update([
            'is_pinned' => !$discussion->is_pinned,
        ]);

        return response()->json([
            'message' => $discussion->is_pinned ? 'Discussion pinned.' : 'Discussion unpinned.',
            'data' => $discussion,
        ]);
    }

    /**
     * Lock/unlock a discussion (instructor only).
     */
    public function toggleLock(Request $request, string $discussionId): JsonResponse
    {
        $discussion = Discussion::whereHas('batch.courses', function ($query) use ($request) {
                $query->where('instructor_id', $request->user()->id);
            })
            ->findOrFail($discussionId);

        $discussion->update([
            'is_locked' => !$discussion->is_locked,
        ]);

        return response()->json([
            'message' => $discussion->is_locked ? 'Discussion locked.' : 'Discussion unlocked.',
            'data' => $discussion,
        ]);
    }

    /**
     * Get discussions for a specific batch.
     */
    public function getBatchDiscussions(Request $request, string $batchId): JsonResponse
    {
        $batch = Batch::findOrFail($batchId);

        $discussions = Discussion::where('batch_id', $batchId)
            ->approved()
            ->with(['user:id,name', 'replies'])
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 15);

        return response()->json($discussions);
    }

    /**
     * Get discussions for a specific lesson.
     */
    public function getLessonDiscussions(Request $request, string $lessonId): JsonResponse
    {
        $lesson = Lesson::findOrFail($lessonId);

        $discussions = Discussion::where('lesson_id', $lessonId)
            ->approved()
            ->with(['user:id,name', 'replies'])
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 15);

        return response()->json($discussions);
    }
}
