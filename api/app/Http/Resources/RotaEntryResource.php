<?php

namespace App\Http\Resources;

use App\Models\RotaEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RotaEntry */
class RotaEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'rota_id' => $this->rota_id,
            'employee_id' => $this->employee_id,
            'shift_date' => $this->shift_date?->toDateString(),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'shift_type' => $this->shift_type,
            'notes' => $this->notes,
            'rota' => $this->whenLoaded('rota', fn () => $this->rota ? new RotaResource($this->rota) : null),
            'employee' => $this->whenLoaded('employee', fn () => $this->employee ? new HrmEmployeeResource($this->employee) : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
