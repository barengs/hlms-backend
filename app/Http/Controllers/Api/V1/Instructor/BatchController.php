<?php

namespace App\Http\Controllers\Api\V1\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

use App\Http\Resources\Api\V1\BatchResource;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class BatchController extends Controller
{
    use ApiResponse;

    /**
     * Daftar Batch
     * 
     * Mengambil daftar batch (kelas) yang dibuat oleh instruktur yang sedang login.
     * Mendukung filter berdasarkan ID kursus dan status batch.
     *
     * @group Kelas Terstruktur Instruktur
     * @queryParam course_id string Filter berdasarkan ID Kursus.
     * @queryParam status string Filter berdasarkan status (draft, open, in_progress, completed, cancelled).
     * @responseField success boolean Status keberhasilan request.
     * @responseField message string Pesan respon.
     * @responseField data object[] Daftar batch.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $instructorId = $request->user()->id;

            // Base query for instructor's batches
            // Include batches where:
            // 1. Instructor owns the courses in the batch, OR
            // 2. Instructor is assigned to the batch (via batch_instructor pivot)
            $baseQuery = Batch::where(function ($query) use ($instructorId) {
                $query->whereHas('courses', function ($q) use ($instructorId) {
                    $q->where('instructor_id', $instructorId);
                })
                ->orWhereHas('instructors', function ($q) use ($instructorId) {
                    $q->where('instructor_id', $instructorId);
                });
            });

            // Calculate global statistics (before pagination)
            $statistics = [
                'total_batches' => (clone $baseQuery)->count(),
                'active_batches' => (clone $baseQuery)->where('status', 'in_progress')->count(),
                'published_batches' => (clone $baseQuery)->where('status', 'open')->count(),
                'archived_batches' => (clone $baseQuery)->where('status', 'completed')->count(),
                'total_students' => (clone $baseQuery)->withCount('enrollments')->get()->sum('enrollments_count'),
                'average_grade' => round(
                    DB::table('grades')
                        ->join('batches', 'grades.batch_id', '=', 'batches.id')
                        ->join('batch_course', 'batches.id', '=', 'batch_course.batch_id')
                        ->join('courses', 'batch_course.course_id', '=', 'courses.id')
                        ->where('courses.instructor_id', $instructorId)
                        ->avg('grades.grade') ?? 0,
                    1
                ),
            ];

            // Filter counts for tabs
            $filters = [
                'all' => $statistics['total_batches'],
                'active' => $statistics['active_batches'],
                'archived' => $statistics['archived_batches'],
            ];

            // Get paginated batches with all required relationships
            $batches = $baseQuery
                ->with([
                    'courses' => function ($query) {
                        $query->select('courses.id', 'courses.title', 'courses.slug', 'courses.thumbnail');
                    },
                    'courses.sections' => function ($query) {
                        $query->select('sections.id', 'sections.course_id');
                    },
                    'courses.sections.lessons' => function ($query) {
                        $query->select('lessons.id', 'lessons.section_id');
                    },
                    'enrollments' => function ($query) {
                        $query->latest()->limit(5);
                    },
                    'enrollments.student' => function ($query) {
                        $query->select('users.id', 'users.name', 'users.avatar');
                    },
                    'grades' => function ($query) {
                        $query->select('batch_id', 'grade');
                    }
                ])
                ->when($request->course_id, function ($query, $courseId) {
                    $query->whereHas('courses', function ($q) use ($courseId) {
                        $q->where('courses.id', $courseId);
                    });
                })
                ->when($request->status, function ($query, $status) {
                    $query->where('status', $status);
                })
                ->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 10);

            // Get the response data
            $responseData = BatchResource::collection($batches)->response()->getData(true);

            // Add statistics and filters to meta
            $responseData['meta']['statistics'] = $statistics;
            $responseData['meta']['filters'] = $filters;

            return $this->successResponse(
                $responseData,
                'Batches retrieved successfully.'
            );

        } catch (\Exception $e) {
            Log::error('Instructor Batch List Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to retrieve batches.', 500);
        }
    }

    /**
     * Buat Batch Baru
     * 
     * Membuat batch baru untuk kursus tertentu. Instruktur harus pemilik kursus.
     *
     * @group Kelas Terstruktur Instruktur
     * @bodyParam course_id string required ID Kursus.
     * @bodyParam name string required Nama Batch.
     * @bodyParam description string Deskripsi Batch.
     * @bodyParam start_date date Tanggal mulai.
     * @bodyParam end_date date Tanggal selesai.
     * @bodyParam max_students integer Kuota maksimal siswa.
     * @responseField success boolean Status keberhasilan request.
     * @responseField message string Pesan respon.
     * @responseField data object Data batch yang dibuat.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'courses' => ['required', 'array', 'min:1'],
                'courses.*.course_id' => ['required', 'exists:courses,id'],
                'courses.*.order' => ['required', 'integer', 'min:1'],
                'courses.*.is_required' => ['boolean'],
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'start_date' => ['nullable', 'date'],
                'end_date' => ['nullable', 'date', 'after:start_date'],
                'enrollment_start_date' => ['nullable', 'date'],
                'enrollment_end_date' => ['nullable', 'date', 'after:enrollment_start_date'],
                'max_students' => ['nullable', 'integer', 'min:1'],
                'status' => ['required', 'in:draft,open,in_progress,completed,cancelled'],
                'is_public' => ['boolean'],
                'auto_approve' => ['boolean'],
                'thumbnail' => ['nullable', 'image', 'max:2048'],
            ]);

            // Verify ownership of all courses
            $courseIds = collect($validated['courses'])->pluck('course_id')->toArray();
            $ownedCourses = Course::whereIn('id', $courseIds)
                ->where('instructor_id', $request->user()->id)
                ->count();

            if ($ownedCourses !== count($courseIds)) {
                return $this->errorResponse('One or more courses not found or access denied.', 403);
            }

            // Use transaction to ensure atomicity
            DB::beginTransaction();
            try {
                // Create batch
                $batchData = collect($validated)->except('courses')->toArray();
                $batchData['slug'] = Str::slug($validated['name']) . '-' . time();
                $batchData['current_students'] = 0;
                $batchData['type'] = 'structured'; // Mark as structured batch

                // Handle Thumbnail Upload
                if ($request->hasFile('thumbnail')) {
                    $batchData['thumbnail'] = $this->handleThumbnailUpload($request->file('thumbnail'));
                }

                $batch = Batch::create($batchData);

                // Attach courses to batch
                $coursesData = [];
                foreach ($validated['courses'] as $course) {
                    $coursesData[$course['course_id']] = [
                        'order' => $course['order'],
                        'is_required' => $course['is_required'] ?? true,
                    ];
                }
                $batch->courses()->attach($coursesData);

                DB::commit();

                return $this->successResponse(
                    new BatchResource($batch->load('courses')), 
                    'Batch created successfully.', 
                    201
                );

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Batch Creation Transaction Error: ' . $e->getMessage());
                throw $e; // Re-throw to be caught by outer catch
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Course not found or access denied.', 404);
        } catch (\Exception $e) {
            Log::error('Batch Creation Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to create batch.', 500);
        }
    }

    /**
     * Detail Batch
     * 
     * Melihat detail informasi batch tertentu.
     *
     * @group Kelas Terstruktur Instruktur
     * @responseField success boolean Status keberhasilan request.
     * @responseField data object Data detail batch.
     */
    public function show(Request $request, string $batchId): JsonResponse
    {
        try {
            $batch = Batch::whereHas('courses', function ($query) use ($request) {
                    $query->where('instructor_id', $request->user()->id);
                })
                ->with([
                    'courses:id,title,slug',
                    'assignments',
                    'enrollments.user:id,name,email'
                ])
                ->findOrFail($batchId);

            return $this->successResponse(new BatchResource($batch), 'Batch details retrieved successfully.');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Batch not found.', 404);
        } catch (\Exception $e) {
            Log::error('Batch Detail Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to retrieve batch.', 500);
        }
    }

    /**
     * Update Batch
     * 
     * Memperbarui informasi batch yang ada.
     *
     * @group Kelas Terstruktur Instruktur
     * @responseField success boolean Status keberhasilan request.
     * @responseField message string Pesan respon.
     * @responseField data object Data batch yang diperbarui.
     */
    public function update(Request $request, string $batchId): JsonResponse
    {
        try {
            $batch = Batch::whereHas('courses', function ($query) use ($request) {
                    $query->where('instructor_id', $request->user()->id);
                })
                ->findOrFail($batchId);

            $validated = $request->validate([
                'courses' => ['sometimes', 'array', 'min:1'],
                'courses.*.course_id' => ['required_with:courses', 'exists:courses,id'],
                'courses.*.order' => ['required_with:courses', 'integer', 'min:1'],
                'courses.*.is_required' => ['boolean'],
                'name' => ['sometimes', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'start_date' => ['nullable', 'date'],
                'end_date' => ['nullable', 'date', 'after:start_date'],
                'enrollment_start_date' => ['nullable', 'date'],
                'enrollment_end_date' => ['nullable', 'date', 'after:enrollment_start_date'],
                'max_students' => ['nullable', 'integer', 'min:1'],
                'status' => ['sometimes', 'in:draft,open,in_progress,completed,cancelled'],
                'is_public' => ['boolean'],
                'auto_approve' => ['boolean'],
                'thumbnail' => ['nullable', 'image', 'max:2048'],
            ]);

            // Update batch basic info
            $batchData = collect($validated)->except('courses')->toArray();
            
            // Handle Thumbnail Upload
            if ($request->hasFile('thumbnail')) {
                // Delete old thumbnail
                if ($batch->thumbnail) {
                    Storage::disk('public')->delete($batch->thumbnail);
                }
                $batchData['thumbnail'] = $this->handleThumbnailUpload($request->file('thumbnail'));
            }
            
            // Use transaction to ensure atomicity
            DB::beginTransaction();
            try {
                $batch->update($batchData);

                // Update courses if provided
                if (isset($validated['courses'])) {
                    // Verify ownership of all courses
                    $courseIds = collect($validated['courses'])->pluck('course_id')->toArray();
                    $ownedCourses = Course::whereIn('id', $courseIds)
                        ->where('instructor_id', $request->user()->id)
                        ->count();

                    if ($ownedCourses !== count($courseIds)) {
                        throw new \Exception('One or more courses not found or access denied.');
                    }

                    // Sync courses (replaces all existing courses)
                    $coursesData = [];
                    foreach ($validated['courses'] as $course) {
                        $coursesData[$course['course_id']] = [
                            'order' => $course['order'],
                            'is_required' => $course['is_required'] ?? true,
                        ];
                    }
                    $batch->courses()->sync($coursesData);
                }

                DB::commit();

                return $this->successResponse(
                    new BatchResource($batch->fresh('courses')), 
                    'Batch updated successfully.'
                );

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Batch Update Transaction Error: ' . $e->getMessage());
                
                if (str_contains($e->getMessage(), 'courses not found')) {
                    return $this->errorResponse($e->getMessage(), 403);
                }
                
                throw $e; // Re-throw to be caught by outer catch
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Batch not found.', 404);
        } catch (\Exception $e) {
            Log::error('Batch Update Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to update batch.', 500);
        }
    }

    /**
     * Hapus Batch
     * 
     * Menghapus batch. Tidak dapat menghapus batch yang sudah memiliki siswa terdaftar.
     *
     * @group Kelas Terstruktur Instruktur
     * @responseField success boolean Status keberhasilan request.
     * @responseField message string Pesan respon.
     */
    public function destroy(Request $request, string $batchId): JsonResponse
    {
        try {
            $batch = Batch::whereHas('courses', function ($query) use ($request) {
                    $query->where('instructor_id', $request->user()->id);
                })
                ->findOrFail($batchId);

            if ($batch->current_students > 0) {
                return $this->errorResponse('Cannot delete batch with enrolled students.', 422);
            }

            $batch->delete();

            return $this->successResponse(null, 'Batch deleted successfully.');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Batch not found.', 404);
        } catch (\Exception $e) {
            Log::error('Batch Deletion Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to delete batch.', 500);
        }
    }

    /**
     * Upload Thumbnail
     * 
     * Upload and update the batch thumbnail image.
     * 
     * @group Kelas Terstruktur Instruktur
     * @urlParam batch string required The ID of the batch.
     * @bodyParam thumbnail file required The image file (max 2MB).
     * @responseField success boolean Status keberhasilan request.
     * @responseField data object Data thumbnail.
     */
    public function uploadThumbnail(Request $request, string $batchId): JsonResponse
    {
        try {
            $batch = Batch::whereHas('courses', function ($query) use ($request) {
                    $query->where('instructor_id', $request->user()->id);
                })
                ->findOrFail($batchId);

            $request->validate([
                'thumbnail' => ['required', 'image', 'max:2048'], // 2MB max
            ]);

            // Delete old thumbnail
            if ($batch->thumbnail) {
                Storage::disk('public')->delete($batch->thumbnail);
            }

            $path = $this->handleThumbnailUpload($request->file('thumbnail'));
            $batch->update(['thumbnail' => $path]);

            return $this->successResponse([
                'thumbnail' => $path,
                'url' => Storage::disk('public')->url($path),
            ], 'Thumbnail uploaded successfully.');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Batch not found.', 404);
        } catch (\Exception $e) {
            Log::error('Batch Thumbnail Upload Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to upload thumbnail.', 500);
        }
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
        $path = 'batches/thumbnails/' . $filename;

        // Convert to WebP using Intervention Image
        $image = Image::read($file);
        
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
     * Statistik Pendaftaran
     * 
     * Mengambil statistik pendaftaran siswa untuk batch tertentu, termasuk kapasitas dan jumlah tugas yang selesai.
     *
     * @group Kelas Terstruktur Instruktur
     * @responseField success boolean Status keberhasilan request.
     * @responseField data object Data statistik.
     */
    public function enrollmentStats(Request $request, string $batchId): JsonResponse
    {
        try {
            $batch = Batch::whereHas('courses', function ($query) use ($request) {
                    $query->where('instructor_id', $request->user()->id);
                })
                ->findOrFail($batchId);

            $stats = [
                'total_enrolled' => $batch->current_students,
                'max_capacity' => $batch->max_students,
                'capacity_percentage' => $batch->max_students ? 
                    round(($batch->current_students / $batch->max_students) * 100, 2) : 0,
                'assignments_count' => $batch->assignments()->count(),
                'completed_assignments' => $batch->assignments()
                    ->whereHas('submissions', function ($query) {
                        $query->where('status', 'graded');
                    })
                    ->count(),
            ];

            return $this->successResponse($stats, 'Batch statistics retrieved successfully.');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Batch not found.', 404);
        } catch (\Exception $e) {
            Log::error('Batch Stats Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to retrieve statistics.', 500);
        }
    }
}
