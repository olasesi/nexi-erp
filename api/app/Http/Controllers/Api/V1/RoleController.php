<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\RoleResource;
use App\Support\PermissionGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class RoleController extends BaseController
{
    protected $model;

    protected string $resourceClass = RoleResource::class;

    protected string $storeRequestClass = 'App\Http\Requests\Api\V1\StoreRoleRequest';

    protected string $updateRequestClass = 'App\Http\Requests\Api\V1\UpdateRoleRequest';

    public function __construct()
    {
        $this->model = new Role;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()
            ->where('guard_name', PermissionGuard::name())
            ->with('permissions')
            ->withCount('users');

        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('label', 'like', "%{$search}%");
            });
        }

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderBy('name')->paginate(min($perPage, 100));

        return $this->resourceClass::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        $permissions = $data['permissions'] ?? [];
        unset($data['permissions']);

        $data['guard_name'] = PermissionGuard::name();

        $role = $this->model->create($data);
        $role->syncPermissions($permissions);

        return (new $this->resourceClass($role))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $role = $this->model->with(['permissions', 'users'])->withCount('users')->findOrFail($id);

        return (new $this->resourceClass($role))->response();
    }

    public function update(int $id): JsonResponse
    {
        $request = app($this->updateRequestClass);
        $role = $this->model->with(['permissions', 'users'])->withCount('users')->findOrFail($id);

        $data = $request->validated();

        $permissions = $data['permissions'] ?? null;
        unset($data['permissions']);

        $role->update($data);

        if ($permissions !== null) {
            $role->syncPermissions($permissions);
        }

        return (new $this->resourceClass($role->fresh(['permissions', 'users'])->loadCount('users')))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $role = $this->model->findOrFail($id);

        if ($role->name === 'admin') {
            throw ValidationException::withMessages(['role' => ['You cannot delete the administrator role.']]);
        }

        if ($role->users()->exists()) {
            throw ValidationException::withMessages(['role' => ['You cannot delete a role that is assigned to users.']]);
        }

        $role->permissions()->detach();
        $role->delete();

        return response()->json(null, 204);
    }
}
