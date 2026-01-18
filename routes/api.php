<?php

use App\Http\Controllers\Api\V1\Admin\CategoryController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\CourseCatalogController;
use App\Http\Controllers\Api\V1\DiscussionController;
use App\Http\Controllers\Api\V1\Instructor\AssignmentController;
use App\Http\Controllers\Api\V1\Instructor\BatchController;
use App\Http\Controllers\Api\V1\Instructor\CourseController;
use App\Http\Controllers\Api\V1\Instructor\LessonController;
use App\Http\Controllers\Api\V1\Instructor\SectionController;
use App\Http\Controllers\Api\V1\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\V1\Instructor\DashboardController as InstructorDashboardController;
use App\Http\Controllers\Api\V1\PaymentWebhookController;
use App\Http\Controllers\Api\V1\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Api\V1\Student\RecommendationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('v1')->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Authentication Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('auth')->group(function () {
        // Public routes
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);

        // Password reset
        Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink']);
        Route::post('/reset-password', [PasswordResetController::class, 'reset']);

        // Email verification (public - signed URL)
        Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');

        // Protected routes
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/user', [AuthController::class, 'user']);

            // Email verification resend
            Route::post('/email/resend', [EmailVerificationController::class, 'resend'])
                ->middleware('throttle:6,1');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Admin Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
        // Dashboard
        Route::get('dashboard', [AdminDashboardController::class, 'index']);

        // Categories CRUD
        Route::apiResource('categories', CategoryController::class);
        Route::post('categories/reorder', [CategoryController::class, 'reorder']);
    });

    /*
    |--------------------------------------------------------------------------
    | Instructor Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('instructor')->middleware(['auth:sanctum', 'role:instructor|admin'])->group(function () {
        // Dashboard
        Route::get('dashboard', [InstructorDashboardController::class, 'index']);

        // Courses CRUD
        Route::apiResource('courses', CourseController::class);
        Route::post('courses/{course}/thumbnail', [CourseController::class, 'uploadThumbnail']);
        Route::post('courses/{course}/submit-review', [CourseController::class, 'submitForReview']);

        // Batches CRUD
        Route::apiResource('batches', BatchController::class);
        Route::get('batches/{batch}/enrollment-stats', [BatchController::class, 'enrollmentStats']);

        // Sections CRUD (nested under courses)
        Route::post('courses/{course}/sections', [SectionController::class, 'store']);
        Route::put('courses/{course}/sections/{section}', [SectionController::class, 'update']);
        Route::delete('courses/{course}/sections/{section}', [SectionController::class, 'destroy']);
        Route::post('courses/{course}/sections/reorder', [SectionController::class, 'reorder']);

        // Lessons CRUD (nested under sections)
        Route::post('courses/{course}/sections/{section}/lessons', [LessonController::class, 'store']);
        Route::get('courses/{course}/sections/{section}/lessons/{lesson}', [LessonController::class, 'show']);
        Route::put('courses/{course}/sections/{section}/lessons/{lesson}', [LessonController::class, 'update']);
        Route::delete('courses/{course}/sections/{section}/lessons/{lesson}', [LessonController::class, 'destroy']);
        Route::post('courses/{course}/sections/{section}/lessons/reorder', [LessonController::class, 'reorder']);

        // Lesson Attachments
        Route::post('courses/{course}/sections/{section}/lessons/{lesson}/attachments', [LessonController::class, 'uploadAttachment']);
        Route::delete('courses/{course}/sections/{section}/lessons/{lesson}/attachments/{attachment}', [LessonController::class, 'deleteAttachment']);

        // Assignments CRUD
        Route::apiResource('assignments', AssignmentController::class);
        Route::post('assignments/{assignment}/grade-submission/{submission}', [AssignmentController::class, 'gradeSubmission']);
    });

    /*
    |--------------------------------------------------------------------------
    | Student Routes (Structured Learning)
    |--------------------------------------------------------------------------
    */
    Route::prefix('student')->middleware(['auth:sanctum'])->group(function () {
        // Dashboard
        Route::get('dashboard', [StudentDashboardController::class, 'index']);
        
        // AI Recommendations
        Route::get('recommendations', [RecommendationController::class, 'recommend']);

        // Batches
        Route::get('courses/{course}/batches', [App\Http\Controllers\Api\V1\Student\BatchController::class, 'index']);
        Route::get('batches/{batch}', [App\Http\Controllers\Api\V1\Student\BatchController::class, 'show']);
        Route::post('batches/{batch}/enroll', [App\Http\Controllers\Api\V1\Student\BatchController::class, 'enroll']);

        // Assignments
        Route::get('assignments', [App\Http\Controllers\Api\V1\Student\AssignmentController::class, 'index']); // List all my assignments or filter by batch_id
        Route::get('assignments/{assignment}', [App\Http\Controllers\Api\V1\Student\AssignmentController::class, 'show']);
        Route::post('assignments/{assignment}/submit', [App\Http\Controllers\Api\V1\Student\AssignmentController::class, 'submit']);

        // Grades
        Route::get('grades', [App\Http\Controllers\Api\V1\Student\GradeController::class, 'index']);
    });

    /*
    |--------------------------------------------------------------------------
    | Discussion Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('discussions')->group(function () {
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/', [DiscussionController::class, 'index']);
            Route::post('/', [DiscussionController::class, 'store']);
            Route::get('{discussion}', [DiscussionController::class, 'show']);
            Route::put('{discussion}', [DiscussionController::class, 'update']);
            Route::delete('{discussion}', [DiscussionController::class, 'destroy']);
            
            // Batch discussions
            Route::get('batch/{batch}', [DiscussionController::class, 'getBatchDiscussions']);
            
            // Lesson discussions
            Route::get('lesson/{lesson}', [DiscussionController::class, 'getLessonDiscussions']);
            
            // Instructor only routes
            Route::middleware('role:instructor|admin')->group(function () {
                Route::post('{discussion}/toggle-pin', [DiscussionController::class, 'togglePin']);
                Route::post('{discussion}/toggle-lock', [DiscussionController::class, 'toggleLock']);
            });
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Class Feature Routes (Hybrid LMS)
    |--------------------------------------------------------------------------
    */
    Route::prefix('classes')->middleware(['auth:sanctum'])->group(function () {
        Route::get('/', [App\Http\Controllers\Api\V1\Classroom\ClassController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\V1\Classroom\ClassController::class, 'store']);
        Route::get('/{id}', [App\Http\Controllers\Api\V1\Classroom\ClassController::class, 'show']);
        Route::post('/join', [App\Http\Controllers\Api\V1\Classroom\ClassController::class, 'join']);

        // Stream
        Route::get('/{id}/stream', [App\Http\Controllers\Api\V1\Classroom\ClassStreamController::class, 'index']);
        Route::post('/{id}/stream', [App\Http\Controllers\Api\V1\Classroom\ClassStreamController::class, 'store']);

        // Classwork
        Route::get('/{id}/work', [App\Http\Controllers\Api\V1\Classroom\ClassworkController::class, 'index']); // Get all sections/lessons
        Route::post('/{id}/topics', [App\Http\Controllers\Api\V1\Classroom\ClassworkController::class, 'storeTopic']);
        Route::post('/{id}/materials', [App\Http\Controllers\Api\V1\Classroom\ClassworkController::class, 'storeMaterial']);

        // People
        Route::get('/{id}/people', [App\Http\Controllers\Api\V1\Classroom\ClassPeopleController::class, 'index']);
    });

    /*
    |--------------------------------------------------------------------------
    | Public Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('public')->group(function () {
        // Course catalog
        Route::get('courses', [CourseCatalogController::class, 'index']);
        Route::get('courses/{slug}', [CourseCatalogController::class, 'show']);
        Route::get('courses/{course}/related', [CourseCatalogController::class, 'related']);
        Route::get('categories', [CourseCatalogController::class, 'categories']);
    });

    /*
    |--------------------------------------------------------------------------
    | Authenticated Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth:sanctum')->group(function () {
        // Cart
        Route::prefix('cart')->group(function () {
            Route::get('/', [CartController::class, 'index']);
            Route::post('/', [CartController::class, 'addToCart']);
            Route::delete('/items/{itemId}', [CartController::class, 'removeFromCart']);
            Route::put('/items/{itemId}', [CartController::class, 'updateItem']);
            Route::delete('/', [CartController::class, 'clearCart']);
            Route::get('/summary', [CartController::class, 'summary']);
        });

        // Checkout
        Route::prefix('checkout')->group(function () {
            Route::post('/process', [CheckoutController::class, 'processCheckout']);
            Route::get('/orders/{orderNumber}', [CheckoutController::class, 'getOrder']);
            Route::get('/orders', [CheckoutController::class, 'getOrderHistory']);
            Route::get('/receipts/{orderNumber}', [CheckoutController::class, 'getReceipt']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Payment Webhook
    |--------------------------------------------------------------------------
    */
    Route::post('webhooks/payment', [PaymentWebhookController::class, 'handleWebhook']);
});
