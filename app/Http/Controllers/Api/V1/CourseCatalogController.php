<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseCatalogController extends Controller
{
    /**
     * Display a listing of courses.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Course::published()
            ->with(['instructor:id,name', 'category:id,name,slug']);

        // Filters
        if ($request->has('category')) {
            $query->where('category_id', $request->category);
        }

        if ($request->has('level')) {
            $query->where('level', $request->level);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('instructor')) {
            $query->where('instructor_id', $request->instructor);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('subtitle', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Sorting
        $sort = $request->get('sort', 'latest');
        switch ($sort) {
            case 'latest':
                $query->latest();
                break;
            case 'oldest':
                $query->oldest();
                break;
            case 'price_low':
                $query->orderBy('price');
                break;
            case 'price_high':
                $query->orderBy('price', 'desc');
                break;
            case 'rating':
                $query->orderByDesc('average_rating');
                break;
            case 'popularity':
                $query->orderByDesc('total_enrollments');
                break;
        }

        // Featured courses
        if ($request->boolean('featured')) {
            $query->featured();
        }

        // Self-paced only
        if ($request->boolean('self_paced')) {
            $query->selfPaced();
        }

        // Structured only
        if ($request->boolean('structured')) {
            $query->structured();
        }

        $perPage = min($request->get('per_page', 12), 50);
        $courses = $query->paginate($perPage);

        return response()->json($courses);
    }

    /**
     * Display a specific course.
     */
    public function show(string $slug): JsonResponse
    {
        $course = Course::published()
            ->with([
                'instructor:id,name,email,created_at',
                'instructor.profile:id,user_id,bio,headline,expertise',
                'category:id,name,slug',
                'sections:id,course_id,title,description,sort_order',
                'sections.lessons:id,section_id,title,type,duration,is_free,sort_order'
            ])
            ->where('slug', $slug)
            ->firstOrFail();

        // Increment view count (using cache for performance)
        $cacheKey = "course_views_{$course->id}";
        $views = cache()->get($cacheKey, 0);
        cache()->put($cacheKey, $views + 1, 3600); // Cache for 1 hour

        return response()->json([
            'data' => $course,
            'meta' => [
                'view_count' => $views + 1,
            ],
        ]);
    }

    /**
     * Get related courses.
     */
    public function related(Course $course): JsonResponse
    {
        $relatedCourses = Course::published()
            ->where('id', '!=', $course->id)
            ->where('category_id', $course->category_id)
            ->orWhere('instructor_id', $course->instructor_id)
            ->with(['instructor:id,name', 'category:id,name,slug'])
            ->limit(6)
            ->get();

        return response()->json([
            'data' => $relatedCourses,
        ]);
    }

    /**
     * Get course categories.
     */
    public function categories(Request $request): JsonResponse
    {
        $query = Category::active()->root();

        if ($request->boolean('with_subcategories')) {
            $query->with('children');
        }

        $categories = $query->orderBy('sort_order')->get();

        return response()->json([
            'data' => $categories,
        ]);
    }
}
