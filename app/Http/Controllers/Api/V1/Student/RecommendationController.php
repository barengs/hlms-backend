<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Course;
use EchoLabs\Prism\Prism;
use EchoLabs\Prism\Enums\Provider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Http\Resources\Api\V1\CourseResource;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Log;

class RecommendationController extends Controller
{
    use ApiResponse;

    /**
     * Rekomendasi Kursus
     * 
     * Menggunakan AI untuk merekomendasikan kursus berdasarkan profil siswa dan preferensi kategori.
     * Jika layanan AI tidak tersedia, akan menggunakan strategi fallback (kursus populer).
     *
     * @group Rekomendasi (AI)
     * @queryParam category_id string ID Kategori preferensi (opsional).
     * @responseField success boolean Status keberhasilan request.
     * @responseField data object[] Daftar kursus yang direkomendasikan.
     */
    public function recommend(Request $request): JsonResponse
    {
        try {
            // 1. Context Gathering
            $user = $request->user();
            $preferredCategory = $request->input('category_id') ? Category::find($request->input('category_id')) : null;
            
            // Fetch a pool of courses (e.g., top 20 popular or recent) to choose from
            // Sending ALL courses to LLM might be too big for context window.
            $courses = Course::with('category:id,name')
                ->where('status', 'published') // Assuming 'published' status
                ->limit(20)
                ->get()
                ->map(function ($course) {
                    return [
                        'id' => $course->id,
                        'title' => $course->title,
                        'category' => $course->category->name ?? 'General',
                        'description' => $course->description,
                    ];
                });

            // 2. Prompt Engineering
            $userContext = "User Name: {$user->name}.";
            if ($preferredCategory) {
                $userContext .= " Interested in Category: {$preferredCategory->name}.";
            }
            
            try {
                // Determine provider based on config availability (simple check)
                // For now, default to what Prism is configured with.
                
                $response = Prism::text()
                    ->using(Provider::Ollama, 'llama3') // Default to Ollama/llama3
                    ->withPrompt("
                        You are an expert educational consultant.
                        Here is the user profile: {$userContext}
                        
                        Here is a list of available courses (JSON format):
                        {$courses->toJson()}
                        
                        Recommend 3 courses that best match the user's profile.
                        Return ONLY a JSON array of course IDs. 
                        Example: [1, 5, 12]
                        Do not add any explanation or markdown formatting. Just the JSON array.
                    ")
                    ->generate();

                $recommendedIds = json_decode($response->text);
                
                if (!is_array($recommendedIds)) {
                    // Fallback
                    $recommendedIds = $courses->pluck('id')->take(3)->toArray(); 
                }

                // 3. Fetch full course objects
                $recommendations = Course::whereIn('id', $recommendedIds)
                    ->with('instructor') // Load for Resource
                    ->get();

            } catch (\Exception $e) {
                // Fallback strategy if AI service is down
                Log::warning('AI Service Error: ' . $e->getMessage());
                $recommendations = Course::where('status', 'published')
                    ->with('instructor')
                    ->limit(3)
                    ->get();
            }

            return $this->successResponse(
                CourseResource::collection($recommendations), 
                'Course recommendations generated successfully.'
            );

        } catch (\Exception $e) {
            Log::error('Recommendation System Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to generate recommendations.', 500);
        }
    }
}
