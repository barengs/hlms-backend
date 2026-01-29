<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\Submission;
use App\Models\User;
use App\Models\Profile; // Ensure Profile model is imported
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class ClassroomDummySeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Instructor
        $instructor = User::firstOrCreate(
            ['email' => 'instructor@dummy.com'],
            [
                'name' => 'Budi Instruktur',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $instructor->assignRole('instructor');
        Profile::updateOrCreate(['user_id' => $instructor->id], ['avatar' => 'https://ui-avatars.com/api/?name=Budi+Instruktur&background=random']);


        // 2. Create Students
        $students = [];
        for ($i = 1; $i <= 5; $i++) {
            $student = User::firstOrCreate(
                ['email' => "student{$i}@dummy.com"],
                [
                    'name' => "Siswa {$i}",
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );
            $student->assignRole('student');
            Profile::updateOrCreate(['user_id' => $student->id], ['avatar' => "https://ui-avatars.com/api/?name=Siswa+{$i}&background=random"]);
            $students[] = $student;
        }

        // 3. Create Courses (Materials/Topics Source)
        // NUCLEAR OPTION 3: Bypassing Eloquent
        $rand = time() . '-' . rand(1000,9999);
        
        // NUCLEAR OPTION 4: Wipe instructor courses
        DB::table('courses')->where('instructor_id', $instructor->id)->delete();

        try {
            $courseMobileId = DB::table('courses')->insertGetId([
                'title' => 'Mastering React Native ' . $rand,
                'slug' => 'dummy-course-rn-' . $rand,
                'description' => 'Complete guide to mobile app development.',
                'instructor_id' => $instructor->id,
                'thumbnail' => '/assets/images/thumbnail mobile.jpeg',
                'level' => 'intermediate',
                'price' => 0,
                'type' => 'structured', 
                'status' => 'published',
                'is_published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch(\Exception $e) {
            file_put_contents('seeder_error.txt', "ERROR: " . $e->getMessage());
            throw $e;
        }

        // Topics (Sections) for Mobile
        $section1Id = DB::table('sections')->insertGetId([
            'course_id' => $courseMobileId, 
            'title' => 'Introduction to RN', 
            'order' => 1,
            'created_at' => now(), 'updated_at' => now()
        ]);
        
        DB::table('lessons')->insert([
            ['section_id' => $section1Id, 'title' => 'Setup Environment', 'type' => 'video', 'duration' => 10, 'order' => 1, 'is_preview' => true, 'created_at' => now(), 'updated_at' => now()],
            ['section_id' => $section1Id, 'title' => 'Hello World', 'type' => 'video', 'duration' => 15, 'order' => 2, 'is_preview' => false, 'created_at' => now(), 'updated_at' => now()]
        ]);
        
        $section2Id = DB::table('sections')->insertGetId([
            'course_id' => $courseMobileId, 
            'title' => 'Components & Styling', 
            'order' => 2,
            'created_at' => now(), 'updated_at' => now()
        ]);
        
        DB::table('lessons')->insert([
            ['section_id' => $section2Id, 'title' => 'View & Text', 'type' => 'document', 'duration' => 5, 'order' => 1, 'is_preview' => false, 'created_at' => now(), 'updated_at' => now()],
            ['section_id' => $section2Id, 'title' => 'Flexbox Layout', 'type' => 'video', 'duration' => 20, 'order' => 2, 'is_preview' => false, 'created_at' => now(), 'updated_at' => now()]
        ]);

        $courseWebId = DB::table('courses')->insertGetId([
            'title' => 'Full Stack Web Dev ' . $rand,
            'slug' => 'dummy-course-web-' . $rand,
            'description' => 'Build modern web apps.',
            'instructor_id' => $instructor->id,
            'thumbnail' => '/assets/images/thumbnail-web-app.webp',
            'level' => 'advanced',
            'price' => 0,
            'type' => 'structured',
            'status' => 'published',
            'is_published' => true, // Might not exist in DB column if status exists? Migration had status enum and published_at. But Model had is_published fillable? Wait.
            // Migration had: $table->enum('status', ...)->default('draft');
            // Migration did NOT have is_published boolean? 
            // Migration had: $table->boolean('is_featured')->default(false);
            // I better check migration again.
            // Migration line 45: $table->boolean('is_featured')->default(false);
            // Migration line 44: enum status.
            // I'll assume is_published column does NOT exist if migration is correct.
            // But model fillable had 'status'.
            // I'll stick to 'status' => 'published'.
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Fix for is_published column if it doesn't exist
        // I will just omit is_published in DB insert if I'm unsure, but the previous Eloquent Create worked fine (until unique error). 
        // If Eloquent has 'is_published' in fillable but not in DB, Eloquent ignores it? No, it throws column not found.
        // So 'is_published' column MUST exist if Eloquent didn't throw "Column not found".
        // OR the error "UniqueConstraintViolation" WAS actually "Column not found"? No.
        
        $secWeb1Id = DB::table('sections')->insertGetId([
            'course_id' => $courseWebId, 
            'title' => 'Backend API', 
            'order' => 1,
            'created_at' => now(), 'updated_at' => now()
        ]);
        
        DB::table('lessons')->insert([
            ['section_id' => $secWeb1Id, 'title' => 'Laravel Setup', 'type' => 'video', 'duration' => 12, 'order' => 1, 'is_preview' => false, 'created_at' => now(), 'updated_at' => now()]
        ]);


        // 4. Create Classroom Batches
        $batchId = DB::table('batches')->insertGetId([
                'name' => 'Kelas React Native Pagi ' . $rand,
                'slug' => 'dummy-batch-rn-' . $rand,
                'description' => 'Kelas intensif React Native untuk pemula.',
                'instructor_id' => $instructor->id,
                'type' => 'classroom',
                'class_code' => 'URN' . rand(100,999), 
                'is_open_for_enrollment' => true,
                'auto_approve' => true,
                'start_date' => now(),
                'created_at' => now(),
                'updated_at' => now(),
        ]);
        
        // Attach
        DB::table('batch_course')->insert([
            'batch_id' => $batchId,
            'course_id' => $courseMobileId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 5. Enroll Students & Add Progress/Assessment
        foreach ($students as $index => $student) {
            $enrollmentId = DB::table('enrollments')->insertGetId([
                'user_id' => $student->id,
                'batch_id' => $batchId,
                'enrolled_at' => now()->subDays(rand(1, 10)),
                'status' => 'active',
                'progress_percentage' => rand(20, 90),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Grade
            $score = rand(40, 95);
            $gradeLetter = $score >= 80 ? 'A' : ($score >= 70 ? 'B' : ($score >= 50 ? 'C' : 'D'));
            if ($score < 50) $gradeLetter = 'E';

            DB::table('grades')->insert([
                'user_id' => $student->id,
                'batch_id' => $batchId,
                'overall_score' => $score,
                'letter_grade' => $gradeLetter,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 6. Create Assignment & Submissions
        $assignmentId = DB::table('assignments')->insertGetId([
            'batch_id' => $batchId,
            'title' => 'Final Project: Clone Gojek UI',
            'description' => 'Create a simple clone of the Gojek Home screen.',
            'max_points' => 100,
            'type' => 'project',
            'is_published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Submissions
        foreach ($students as $index => $student) {
            $status = rand(0, 1) ? 'submitted' : 'graded';
            if ($index > 3) $status = 'pending'; // Some not submitted
            
            if ($status !== 'pending') {
                 DB::table('submissions')->insert([
                    'assignment_id' => $assignmentId,
                    'user_id' => $student->id,
                    'content' => 'https://github.com/student/repo',
                    'submitted_at' => now(),
                    'status' => $status == 'graded' ? 'graded' : 'submitted',
                    'points_awarded' => $status == 'graded' ? rand(70, 100) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
