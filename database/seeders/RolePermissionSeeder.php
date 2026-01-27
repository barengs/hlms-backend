<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        /*
        |--------------------------------------------------------------------------
        | Define Permissions
        |--------------------------------------------------------------------------
        */

        // User permissions
        $userPermissions = [
            'view own profile',
            'edit own profile',
        ];

        // Course permissions (for instructors)
        $coursePermissions = [
            'create courses',
            'edit own courses',
            'delete own courses',
            'publish courses',
            'view course analytics',
        ];

        // Batch/Class permissions (for instructors)
        $batchPermissions = [
            'create batches',
            'edit own batches',
            'delete own batches',
            'grade submissions',
            'manage assignments',
        ];

        // Student permissions
        $studentPermissions = [
            'enroll courses',
            'view enrolled courses',
            'submit assignments',
            'view own grades',
            'post discussions',
            'write reviews',
        ];

        // Admin permissions - Granular
        $batchManagementPermissions = [
            'view all batches',
            'create batches',
            'edit batches',
            'delete batches',
            'assign instructors',
            'remove instructors',
        ];

        $courseManagementPermissions = [
            'approve courses',
            'reject courses',
            'manage all courses',
            'feature courses',
        ];

        $categoryPermissions = [
            'manage categories',
        ];

        $learningPathPermissions = [
            'manage learning paths',
        ];

        $enrollmentPermissions = [
            'view all enrollments',
            'manage enrollments',
        ];

        $userManagementPermissions = [
            'manage users',
            'verify instructors',
            'manage roles',
        ];

        $financialPermissions = [
            'manage transactions',
            'process payouts',
        ];

        $systemPermissions = [
            'view platform analytics',
            'manage settings',
        ];

        // Create all permissions
        $allPermissions = array_merge(
            $userPermissions,
            $coursePermissions,
            $batchPermissions,
            $studentPermissions,
            $batchManagementPermissions,
            $courseManagementPermissions,
            $categoryPermissions,
            $learningPathPermissions,
            $enrollmentPermissions,
            $userManagementPermissions,
            $financialPermissions,
            $systemPermissions
        );

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Define Roles
        |--------------------------------------------------------------------------
        */

        // Helper function to get permission models
        $getPermissions = fn(array $names) => Permission::whereIn('name', $names)
            ->where('guard_name', 'sanctum')
            ->get();

        // Student role
        $studentRole = Role::firstOrCreate(['name' => 'student', 'guard_name' => 'sanctum']);
        $studentRole->syncPermissions($getPermissions([
            ...$userPermissions,
            ...$studentPermissions,
        ]));

        // Instructor role
        $instructorRole = Role::firstOrCreate(['name' => 'instructor', 'guard_name' => 'sanctum']);
        $instructorRole->syncPermissions($getPermissions([
            ...$userPermissions,
            ...$studentPermissions,
            ...$coursePermissions,
            ...$batchPermissions,
        ]));

        // Curriculum Manager role (Tim Kurikulum)
        $curriculumRole = Role::firstOrCreate(['name' => 'curriculum-manager', 'guard_name' => 'sanctum']);
        $curriculumRole->syncPermissions($getPermissions([
            ...$userPermissions,
            ...$batchManagementPermissions,
            ...$learningPathPermissions,
            'view all enrollments',
        ]));

        // Content Manager role
        $contentRole = Role::firstOrCreate(['name' => 'content-manager', 'guard_name' => 'sanctum']);
        $contentRole->syncPermissions($getPermissions([
            ...$userPermissions,
            ...$courseManagementPermissions,
            ...$categoryPermissions,
            ...$learningPathPermissions,
        ]));

        // Operations Manager role
        $operationsRole = Role::firstOrCreate(['name' => 'operations-manager', 'guard_name' => 'sanctum']);
        $operationsRole->syncPermissions($getPermissions([
            ...$userPermissions,
            'view all batches',
            ...$enrollmentPermissions,
            'view platform analytics',
        ]));

        // Super Admin role (has all permissions)
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'sanctum']);
        $superAdminRole->syncPermissions(Permission::where('guard_name', 'sanctum')->get());

        // Keep legacy admin role for backward compatibility
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'sanctum']);
        $adminRole->syncPermissions(Permission::where('guard_name', 'sanctum')->get());
    }
}
