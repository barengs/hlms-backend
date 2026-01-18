<?php

namespace App\Http\Controllers\Api\V1\Classroom;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use Illuminate\Http\Request;

class ClassPeopleController extends Controller
{
    /**
     * List Members
     * 
     * Get a list of all members (Instructors and Students) in a specific class.
     * 
     * @group Hybrid Learning
     * @subgroup Class Management
     * @urlParam id integer required The ID of the class (batch).
     * @response 200 {
     *   "instructors": [object],
     *   "students": [object]
     * }
     */
    public function index($id)
    {
        $batch = Batch::with(['course.instructor.profile', 'enrollments.user.profile'])
            ->findOrFail($id);

        // TODO: Authorization check

        $instructors = [
            $batch->course->instructor
        ];

        $students = $batch->enrollments->map(function ($enrollment) {
            return $enrollment->user;
        });

        return response()->json([
            'instructors' => $instructors,
            'students' => $students,
        ]);
    }
}
