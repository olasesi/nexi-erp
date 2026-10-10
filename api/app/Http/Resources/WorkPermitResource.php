<?php

namespace App\Http\Resources;

use App\Models\WorkPermit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WorkPermit */
class WorkPermitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn () => new HrmEmployeeResource($this->employee)),
            'permit_number' => $this->permit_number,
            'permit_type' => $this->permit_type,
            'issuing_country' => $this->issuing_country,
            'issuing_authority' => $this->issuing_authority,
            'issued_date' => $this->issued_date?->toDateString(),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'status' => $this->status,
            'document_url' => $this->document_url,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
