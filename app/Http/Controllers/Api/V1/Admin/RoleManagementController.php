<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleManagementController extends Controller
{
    use ApiResponse;

    /**
     * List all roles with their permissions
     * 
     * @group Admin - Role Management
     */
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions')
            ->where('guard_name', 'sanctum')
            ->get();

        return $this->successResponse($roles);
    }

    /**
     * Create a new role
     * 
     * @group Admin - Role Management
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|unique:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'sanctum'
        ]);

        if ($request->permissions) {
            $permissions = Permission::whereIn('name', $request->permissions)
                ->where('guard_name', 'sanctum')
                ->get();
            $role->syncPermissions($permissions);
        }

        return $this->successResponse(
            $role->load('permissions'),
            'Role created successfully.',
            201
        );
    }

    /**
     * Update role permissions
     * 
     * @group Admin - Role Management
     */
    public function update(Request $request, string $roleId): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|unique:roles,name,' . $roleId,
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role = Role::where('guard_name', 'sanctum')->findOrFail($roleId);

        if ($request->has('name')) {
            $role->update(['name' => $request->name]);
        }

        if ($request->has('permissions')) {
            $permissions = Permission::whereIn('name', $request->permissions)
                ->where('guard_name', 'sanctum')
                ->get();
            $role->syncPermissions($permissions);
        }

        return $this->successResponse(
            $role->fresh('permissions'),
            'Role updated successfully.'
        );
    }

    /**
     * Delete a role
     * 
     * @group Admin - Role Management
     */
    public function destroy(string $roleId): JsonResponse
    {
        $role = Role::where('guard_name', 'sanctum')->findOrFail($roleId);

        // Prevent deletion of critical roles
        if (in_array($role->name, ['super-admin', 'admin'])) {
            return $this->errorResponse('Cannot delete system role.', 403);
        }

        $role->delete();

        return $this->successResponse(null, 'Role deleted successfully.');
    }

    /**
     * List all available permissions
     * 
     * @group Admin - Role Management
     */
    public function permissions(): JsonResponse
    {
        $permissions = Permission::where('guard_name', 'sanctum')
            ->orderBy('name')
            ->get();

        return $this->successResponse($permissions);
    }

    /**
     * Assign role to user
     * 
     * @group Admin - Role Management
     */
    public function assignRoleToUser(Request $request, string $userId): JsonResponse
    {
        $request->validate([
            'role' => 'required|exists:roles,name',
        ]);

        $user = \App\Models\User::findOrFail($userId);
        $user->assignRole($request->role);

        return $this->successResponse(
            $user->load('roles'),
            'Role assigned successfully.'
        );
    }

    /**
     * Remove role from user
     * 
     * @group Admin - Role Management
     */
    public function removeRoleFromUser(string $userId, string $roleId): JsonResponse
    {
        $user = \App\Models\User::findOrFail($userId);
        $role = Role::findOrFail($roleId);

        $user->removeRole($role);

        return $this->successResponse(
            $user->load('roles'),
            'Role removed successfully.'
        );
    }
}
