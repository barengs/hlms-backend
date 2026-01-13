<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles and permissions first
        $this->call([
            RolePermissionSeeder::class,
        ]);

        // Create admin user
        $admin = User::factory()->create([
            'name' => 'Admin HLMS',
            'email' => 'admin@hlms.test',
        ]);
        $admin->assignRole('admin');
        $admin->profile()->create();

        // Create test instructor
        $instructor = User::factory()->create([
            'name' => 'Test Instructor',
            'email' => 'instructor@hlms.test',
        ]);
        $instructor->assignRole('instructor');
        $instructor->profile()->create([
            'headline' => 'Senior Software Developer',
            'bio' => 'Experienced software developer with 10+ years in web development.',
        ]);

        // Create test student
        $student = User::factory()->create([
            'name' => 'Test Student',
            'email' => 'student@hlms.test',
        ]);
        $student->assignRole('student');
        $student->profile()->create();
    }
}
