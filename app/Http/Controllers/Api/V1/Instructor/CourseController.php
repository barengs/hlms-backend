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
     * @bodyParam thumbnail file optional Course thumbnail image (max 2MB).
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
            'thumbnail' => ['nullable', 'image', 'max:2048'],
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

        // Handle Thumbnail Upload
        if ($request->hasFile('thumbnail')) {
            $validated['thumbnail'] = $this->handleThumbnailUpload($request->file('thumbnail'));
        }

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
     * @bodyParam slug string unique optional Unique slug.
     * @bodyParam subtitle string optional Subtitle of the course.
     * @bodyParam description string optional Course description.
     * @bodyParam category_id integer optional ID of the category.
     * @bodyParam type enum optional Type of course (self_paced, structured).
     * @bodyParam level enum optional Difficulty level.
     * @bodyParam language string optional Language code.
     * @bodyParam price number optional Price of the course.
     * @bodyParam discount_price number optional Discounted price.
     * @bodyParam requirements array optional List of course requirements.
     * @bodyParam outcomes array optional List of learning outcomes.
     * @bodyParam target_audience array optional List of target audience.
     * @bodyParam thumbnail file optional Course thumbnail image (max 2MB).
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
            'thumbnail' => ['nullable', 'image', 'max:2048'],
        ]);

        // Handle Thumbnail Upload
        if ($request->hasFile('thumbnail')) {
            // Delete old thumbnail
            if ($course->thumbnail) {
                Storage::disk('public')->delete($course->thumbnail);
            }
            $validated['thumbnail'] = $this->handleThumbnailUpload($request->file('thumbnail'));
        }

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

        $path = $this->handleThumbnailUpload($request->file('thumbnail'));
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
     * Handle thumbnail upload, resize, and conversion to WebP.
     * 
     * @param \Illuminate\Http\UploadedFile $file
     * @return string Path to saved file
     */
    private function handleThumbnailUpload($file): string
    {
        $filename = Str::uuid() . '.webp';
        $path = 'courses/thumbnails/' . $filename;

        // Convert to WebP using Intervention Image
        $image = \Intervention\Image\Laravel\Facades\Image::read($file);
        
        // Resize individually to prevent too large images (e.g., 800px width, auto height)
        // aspect ratio is maintained
        $image->scale(width: 800);

        // Encode to webp quality 80
        $encoded = $image->toWebp(quality: 80);

        // Save to storage
        Storage::disk('public')->put($path, (string) $encoded);

        return $path;
    }

    /**
     * Upload Preview Video
     *
     * Upload and transcode the course preview video (HLS).
     *
     * @group Instructor
     * @subgroup Course Management
     * @urlParam course integer required The ID of the course.
     * @bodyParam video file required The video file (max 100MB, mp4/mov/avi).
     * @response 200 {"message": "Video uploaded and processing started.", "data": {"preview_video": "..."}}
     */
    public function uploadPreviewVideo(Request $request, Course $course): JsonResponse
    {
        // Ensure instructor owns this course
        if ($course->instructor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'video' => ['required', 'file', 'mimetypes:video/mp4,video/quicktime,video/x-msvideo', 'max:102400'], // 100MB
        ]);

        // Delete old video if exists
        if ($course->preview_video) {
            // Delete directory
            $oldPath = dirname($course->preview_video);
            Storage::disk('public')->deleteDirectory($oldPath);
        }

        $file = $request->file('video');
        $filename = Str::uuid();
        $basePath = "courses/videos/{$course->id}/{$filename}";

        // Save original temporarily
        $tempPath = $file->storeAs("courses/temp/{$course->id}", $filename . '.' . $file->getClientOriginalExtension());
        
        // Transcode to HLS
        $lowBitrate = (new \FFMpeg\Format\Video\X264)->setKiloBitrate(500);
        $highBitrate = (new \FFMpeg\Format\Video\X264)->setKiloBitrate(1000);

        \ProtoneMedia\LaravelFFMpeg\Support\FFMpeg::fromDisk('local') // Assuming temp storage is local
            ->open($tempPath)
            ->exportForHLS()
            ->toDisk('public')
            ->addFormat($lowBitrate, function($media) {
                $media->resize(640, 360);
            })
            ->addFormat($highBitrate, function($media) {
                $media->resize(1280, 720);
            })
            ->save("{$basePath}/playlist.m3u8");

        // Clean temp file
        Storage::delete($tempPath);

        $playlistPath = "{$basePath}/playlist.m3u8";
        $course->update(['preview_video' => $playlistPath]);

        return response()->json([
            'message' => 'Preview video uploaded and processed successfully.',
            'data' => [
                'preview_video' => $playlistPath,
                'url' => Storage::disk('public')->url($playlistPath),
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
