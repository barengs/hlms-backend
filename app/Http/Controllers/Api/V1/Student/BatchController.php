<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BatchController extends Controller
{
    /**
     * Daftar Batch Kursus
     * 
     * Melihat jadwal batch yang tersedia untuk kursus tertentu.
     *
     * @group Pendaftaran Kelas
     * @urlParam courseId string required ID Kursus.
     * @queryParam include_all boolean Jika true, menampilkan batch privat juga (default: false, hanya publik).
     * @responseField data object[] Daftar batch.
     * @responseField user_enrollment string|null ID batch dimana user terdaftar saat ini.
     */
    public function index(Request $request, string $courseId): JsonResponse
    {
        $user = $request->user();

        // Check if user has enrollment for this course
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $courseId)
            ->active()
            ->first();

        // Get structured batches that include this course
        $batches = Batch::structured()  // Only structured batches, not classroom
            ->whereHas('courses', function ($query) use ($courseId) {
                $query->where('course_id', $courseId);
            })
            ->where('status', '!=', 'draft')
            ->when(!$request->include_all, function($q) {
                $q->where('is_public', true);
            })
            ->with(['courses' => function ($query) use ($courseId) {
                $query->where('course_id', $courseId);
            }])
            ->orderBy('start_date', 'asc')
            ->get();

        return response()->json([
            'data' => $batches,
            'user_enrollment' => $enrollment ? $enrollment->batch_id : null
        ]);
    }

    /**
     * Detail Batch
     * 
     * Melihat detail batch spesifik.
     *
     * @group Pendaftaran Kelas
     * @responseField data object Data batch.
     */
    public function show(string $batchId): JsonResponse
    {
        $batch = Batch::with([
                'courses:id,title,slug,instructor_id',
                'courses.instructor:id,name',
                'instructor:id,name'
            ])
            ->findOrFail($batchId);

        return response()->json([
            'data' => $batch
        ]);
    }

    /**
     * Daftar Masuk Batch
     * 
     * Mendaftar siswa ke dalam batch tertentu. Siswa harus sudah membeli kursus.
     *
     * @group Pendaftaran Kelas
     * @responseField message string Pesan sukses/error.
     * @responseField data object Data batch setelah pendaftaran.
     */
    public function enroll(Request $request, string $batchId): JsonResponse
    {
        $user = $request->user();
        $batch = Batch::structured()->with('courses')->findOrFail($batchId);
        
        // 1. Check if Batch is open for enrollment
        if (!$batch->is_open_for_enrollment) {
            return response()->json([
                'message' => 'This batch is not open for enrollment.'
            ], 422);
        }

        // 2. Get the first course from batch (structured batches should have courses)
        $course = $batch->courses->first();
        
        if (!$course) {
            return response()->json([
                'message' => 'This batch has no courses available.'
            ], 422);
        }

        // 3. Check if user has purchased the course
        $existingEnrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->active()
            ->first();

        if (!$existingEnrollment) {
            return response()->json([
                'message' => 'You must purchase the course before joining a batch.'
            ], 403);
        }

        // 4. Check if already in a batch
        if ($existingEnrollment->batch_id) {
            if ($existingEnrollment->batch_id == $batchId) {
                return response()->json(['message' => 'Already enrolled in this batch.']);
            }
            return response()->json([
                'message' => 'You are already enrolled in another batch for this course. Please contact support to switch.'
            ], 422);
        }

        // 5. Proceed to enroll
        DB::transaction(function () use ($batch, $existingEnrollment) {
            // Lock batch row to prevent race condition on quota
            $lockedBatch = Batch::where('id', $batch->id)->lockForUpdate()->first();
            
            if ($lockedBatch->max_students && $lockedBatch->current_students >= $lockedBatch->max_students) {
                throw new \Exception('Batch is full.');
            }

            $existingEnrollment->update([
                'batch_id' => $lockedBatch->id
            ]);

            $lockedBatch->increment('current_students');
        });

        return response()->json([
            'message' => 'Successfully enrolled in batch.',
            'data' => $batch->load('courses')
        ]);
    }
}
