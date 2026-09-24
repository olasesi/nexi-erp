<?php

namespace App\Http\Resources;

use App\Support\PermissionGuard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

/** @mixin Role */
class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => $this->label,
            'guard_name' => $this->guard_name ?? PermissionGuard::name(),
            'permissions' => $this->whenLoaded(
                'permissions',
                fn () => $this->permissions->pluck('name'),
                fn () => $this->getPermissionNames()
            ),
            'users_count' => $this->whenCounted('users'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
