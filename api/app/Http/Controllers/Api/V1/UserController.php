<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** @property User $model */
class UserController extends BaseController
{
    protected $model;

    protected string $resourceClass = UserResource::class;

    protected string $storeRequestClass = 'App\Http\Requests\Api\V1\StoreUserRequest';

    protected string $updateRequestClass = 'App\Http\Requests\Api\V1\UpdateUserRequest';

    public function __construct()
    {
        $this->model = new User;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with('roles');

        if ($role = request('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $role));
        }

        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->paginate(min($perPage, 100));

        return $this->resourceClass::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        $roles = $data['roles'] ?? [];
        unset($data['roles']);

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
        }

        $item = $this->model->create($data);
        $item->syncRoles($roles);

        return (new $this->resourceClass($item))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $item = $this->model->with('roles')->findOrFail($id);

        return (new $this->resourceClass($item))->response();
    }

    public function update(int $id): JsonResponse
    {
        $request = app($this->updateRequestClass);
        $item = $this->model->with('roles')->findOrFail($id);

        $data = $request->validated();

        $roles = $data['roles'] ?? null;
        unset($data['roles']);

        if (isset($data['password'])) {
            if ($data['password'] === '') {
                unset($data['password']);
            } else {
                $data['password'] = Hash::make($data['password']);
            }
        }

        $item->update($data);

        if ($roles !== null) {
            $item->syncRoles($roles);
        }

        return (new $this->resourceClass($item->fresh('roles')))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $item = $this->model->findOrFail($id);

        if ($item->id === auth('api')->id()) {
            throw ValidationException::withMessages(['user' => ['You cannot delete your own account.']]);
        }

        if ($item->hasRole('admin') && ! User::role('admin')->where('id', '!=', $item->id)->exists()) {
            throw ValidationException::withMessages(['user' => ['You cannot delete the last administrator.']]);
        }

        $item->roles()->detach();
        $item->delete();

        return response()->json(null, 204);
    }

    protected function getFilterableFields(): array
    {
        return ['company_id', 'is_active'];
    }

    protected function getSearchableFields(): array
    {
        return ['name', 'email', 'username', 'phone'];
    }
}
