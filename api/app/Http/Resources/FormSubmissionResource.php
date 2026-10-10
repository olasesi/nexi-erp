<?php

namespace App\Http\Resources;

use App\Models\FormSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FormSubmission */
class FormSubmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'form_id' => $this->form_id,
            'submitted_by' => $this->submitted_by,
            'status' => $this->status,
            'payload' => $this->payload,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
