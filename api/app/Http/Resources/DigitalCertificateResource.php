<?php

namespace App\Http\Resources;

use App\Models\DigitalCertificate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DigitalCertificate */
class DigitalCertificateResource extends JsonResource
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
            'title' => $this->title,
            'certificate_number' => $this->certificate_number,
            'certificate_type' => $this->certificate_type,
            'issued_date' => $this->issued_date?->toDateString(),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'status' => $this->status,
            'serial' => $this->serial,
            'file_url' => $this->file_url,
            'revoked_reason' => $this->revoked_reason,
            'revoked_at' => $this->revoked_at?->toDateTimeString(),
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
