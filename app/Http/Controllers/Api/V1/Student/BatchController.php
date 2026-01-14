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
        // First check if user has access to this course (has purchased it)
        $enrollment = Enrollment::where('user_id', $request->user()->id)
            ->where('course_id', $courseId)
            ->active()
            ->first();

        // Optional: If you want to show batches even before purchase, you can remove the enrollment check.
        // But for "My Classes", they likely need to be enrolled.
        // The requirements say "Pendaftaran Kelas Terstruktur: Melihat jadwal batch yang tersedia dan mendaftar".
        // Let's allow viewing if it's public, but maybe highlight the one they are in.

        $batches = Batch::where('course_id', $courseId)
            ->where('status', '!=', 'draft') // Show open, in_progress, completed, cancelled
            ->when(!$request->include_all, function($q) {
                $q->where('is_public', true);
            })
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
        $batch = Batch::with(['course:id,title,instructor_id', 'course.instructor:id,name'])
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
        $batch = Batch::findOrFail($batchId);
        
        // 1. Check if Batch is open for enrollment
        if (!$batch->is_open_for_enrollment) {
            return response()->json([
                'message' => 'This batch is not open for enrollment.'
            ], 422);
        }

        // 2. Check if user has purchased the course (Validation: Must have an active enrollment record)
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $batch->course_id)
            ->active()
            ->first();

        if (!$enrollment) {
            return response()->json([
                'message' => 'You must purchase the course before joining a batch.'
            ], 403);
        }

        // 3. Check if already in a batch for this course?
        if ($enrollment->batch_id) {
            if ($enrollment->batch_id == $batchId) {
                return response()->json(['message' => 'Already enrolled in this batch.']);
            }
            // Decide business logic: Can they switch? For now, prevent "silent" switch.
            return response()->json([
                'message' => 'You are already enrolled in another batch for this course. Please contact support to switch.'
            ], 422);
        }

        // 4. Proceed to enroll (Update enrollment & batch count)
        DB::transaction(function () use ($batch, $enrollment) {
            // Lock batch row to prevent race condition on quota
            $lockedBatch = Batch::where('id', $batch->id)->lockForUpdate()->first();
            
            if ($lockedBatch->max_students && $lockedBatch->current_students >= $lockedBatch->max_students) {
                throw new \Exception('Batch is full.');
            }

            $enrollment->update([
                'batch_id' => $lockedBatch->id
            ]);

            $lockedBatch->increment('current_students');
        });

        return response()->json([
            'message' => 'Successfully enrolled in batch.',
            'data' => $batch
        ]);
    }
}
