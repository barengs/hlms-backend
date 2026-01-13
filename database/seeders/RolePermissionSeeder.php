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

        // Admin permissions
        $adminPermissions = [
            'manage users',
            'verify instructors',
            'manage roles',
            'manage all courses',
            'manage categories',
            'feature courses',
            'manage transactions',
            'process payouts',
            'view platform analytics',
            'manage settings',
        ];

        // Create all permissions
        $allPermissions = array_merge(
            $userPermissions,
            $coursePermissions,
            $batchPermissions,
            $studentPermissions,
            $adminPermissions
        );

        foreach ($allPermissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'sanctum']);
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
        $studentRole = Role::create(['name' => 'student', 'guard_name' => 'sanctum']);
        $studentRole->givePermissionTo($getPermissions([
            ...$userPermissions,
            ...$studentPermissions,
        ]));

        // Instructor role
        $instructorRole = Role::create(['name' => 'instructor', 'guard_name' => 'sanctum']);
        $instructorRole->givePermissionTo($getPermissions([
            ...$userPermissions,
            ...$studentPermissions,
            ...$coursePermissions,
            ...$batchPermissions,
        ]));

        // Admin role (has all permissions)
        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'sanctum']);
        $adminRole->givePermissionTo(Permission::where('guard_name', 'sanctum')->get());
    }
}
