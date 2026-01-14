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
            $batches = Batch::whereHas('course', function ($query) use ($request) {
                    $query->where('instructor_id', $request->user()->id);
                })
                ->with(['course:id,title,slug', 'assignments', 'enrollments'])
                ->when($request->course_id, function ($query, $courseId) {
                    $query->where('course_id', $courseId);
                })
                ->when($request->status, function ($query, $status) {
                    $query->where('status', $status);
                })
                ->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 10);

            return $this->successResponse(
                BatchResource::collection($batches)->response()->getData(true),
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
                'course_id' => ['required', 'exists:courses,id'],
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
            ]);

            // Verify owner
            Course::where('id', $validated['course_id'])
                ->where('instructor_id', $request->user()->id)
                ->firstOrFail();

            $validated['slug'] = Str::slug($validated['name']) . '-' . time();
            $validated['current_students'] = 0;

            $batch = Batch::create($validated);

            return $this->successResponse(new BatchResource($batch->load('course')), 'Batch created successfully.', 201);

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
            $batch = Batch::whereHas('course', function ($query) use ($request) {
                    $query->where('instructor_id', $request->user()->id);
                })
                ->with([
                    'course:id,title,slug',
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
            $batch = Batch::whereHas('course', function ($query) use ($request) {
                    $query->where('instructor_id', $request->user()->id);
                })
                ->findOrFail($batchId);

            $validated = $request->validate([
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
            ]);

            $batch->update($validated);

            return $this->successResponse(new BatchResource($batch->fresh('course')), 'Batch updated successfully.');

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
            $batch = Batch::whereHas('course', function ($query) use ($request) {
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
            $batch = Batch::whereHas('course', function ($query) use ($request) {
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
